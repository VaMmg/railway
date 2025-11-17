<?php
// BACKEND/config/auth.php - Sistema de Roles y Autenticación

require_once 'conexion.php';
require_once 'cors.php';

// Definir roles del sistema
define('ROLE_ADMIN', 1);
define('ROLE_GERENTE', 2);
define('ROLE_TRABAJADOR', 3);

class AuthSystem {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getPDO();
    }
    
    // Obtener información completa del usuario
    public function getUserInfo($userId) {
        $sql = "
            SELECT u.*, p.nombres, p.apellido_paterno, p.apellido_materno, 
                   p.dni, r.nombre_rol, r.descripcion as rol_descripcion
            FROM usuarios u
            INNER JOIN personas p ON u.dni_persona = p.dni
            LEFT JOIN rol r ON u.id_rol = r.id_rol
            WHERE u.id_usuario = :id AND u.estado = 'Activo'
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Verificar permisos por rol
    public function hasRole($userPayload, $requiredRoles) {
        if (!is_array($requiredRoles)) {
            $requiredRoles = [$requiredRoles];
        }
        
        $userInfo = $this->getUserInfo($userPayload['id']);
        if (!$userInfo) return false;
        
        // Verificar por nombre de rol en lugar de ID
        $roleName = strtolower($userInfo['nombre_rol'] ?? '');
        
        foreach ($requiredRoles as $role) {
            if (is_numeric($role)) {
                // Compatibilidad con IDs antiguos
                if ($userInfo['id_rol'] == $role) return true;
            } else {
                // Verificar por nombre de rol
                if (strpos($roleName, strtolower($role)) !== false) return true;
            }
        }
        
        return false;
    }
    
    // Middleware para verificar roles específicos
    public function requireRole($requiredRoles) {
        $userPayload = requireAuth();
        
        if (!$this->hasRole($userPayload, $requiredRoles)) {
            jsonResponse(false, 'No tienes permisos para realizar esta acción', null, 403);
        }
        
        return $this->getUserInfo($userPayload['id']);
    }
    
    // Verificar si el gerente tiene caja abierta
    public function isMainCashOpen() {
        $sql = "
            SELECT c.estado_caja, c.hora_apertura
            FROM cajas_usuario c
            INNER JOIN usuarios u ON c.id_usuario = u.id_usuario
            WHERE u.id_rol = :gerente_role 
            AND c.estado_caja = 'Abierta'
            AND DATE(c.fecha_creacion_caja) = CURRENT_DATE
            ORDER BY c.hora_apertura DESC
            LIMIT 1
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':gerente_role' => ROLE_GERENTE]);
        return $stmt->fetch() !== false;
    }
    
    // Verificar si un trabajador puede abrir caja
    public function canOpenWorkerCash($userId) {
        // Primero verificar si la caja principal está abierta
        if (!$this->isMainCashOpen()) {
            return ['can_open' => false, 'reason' => 'La caja principal debe estar abierta primero'];
        }
        
        // Verificar si el trabajador ya tiene una caja abierta hoy
        $sql = "
            SELECT COUNT(*) as count
            FROM cajas_usuario
            WHERE id_usuario = :user_id 
            AND estado_caja = 'Abierta'
            AND DATE(fecha_creacion_caja) = CURRENT_DATE
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            return ['can_open' => false, 'reason' => 'Ya tienes una caja abierta hoy'];
        }
        
        return ['can_open' => true, 'reason' => ''];
    }
    
    // Crear usuario (solo admin)
    public function createUser($userData) {
        $sql = "
            INSERT INTO usuarios (id_rol, dni_persona, usuario, pwd, estado, fecha_creacion)
            VALUES (:id_rol, :dni_persona, :usuario, :pwd, 'Activo', NOW())
        ";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id_rol' => $userData['id_rol'],
            ':dni_persona' => $userData['dni_persona'],
            ':usuario' => $userData['usuario'],
            ':pwd' => password_hash($userData['password'], PASSWORD_DEFAULT)
        ]);
    }
    
    // Registrar log de acceso - MEJORADO
    public function logAccess($userId, $successful = true) {
        try {
            $sql = "
                INSERT INTO log_accesos (id_usuario, ip_origen, navegador, fecha_acceso, exitoso)
                VALUES (:id_usuario, :ip, :navegador, NOW(), :exitoso)
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':id_usuario' => $userId,
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ':navegador' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 100),
                ':exitoso' => $successful
            ]);
        } catch (PDOException $e) {
            // No fallar el login por problemas de logging
            error_log('Error logging access: ' . $e->getMessage());
        }
    }
    
    // Obtener logs de acceso para auditoría
    public function getAccessLogs($limit = 100, $userId = null) {
        $where = $userId ? 'WHERE la.id_usuario = :user_id' : '';
        $sql = "
            SELECT la.*, u.usuario, p.nombres, p.apellido_paterno
            FROM log_accesos la
            LEFT JOIN usuarios u ON la.id_usuario = u.id_usuario
            LEFT JOIN personas p ON u.dni_persona = p.dni
            $where
            ORDER BY la.fecha_acceso DESC
            LIMIT :limit
        ";
        $stmt = $this->pdo->prepare($sql);
        if ($userId) {
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}

// Funciones helper globales
function requireAdmin() {
    $auth = new AuthSystem();
    return $auth->requireRole('administrador');
}

function requireGerente() {
    $auth = new AuthSystem();
    return $auth->requireRole(['administrador', 'gerente']);
}

function requireTrabajador() {
    $auth = new AuthSystem();
    return $auth->requireRole(['administrador', 'gerente', 'trabajador']);
}

function getCurrentUser() {
    $userPayload = requireAuth();
    $auth = new AuthSystem();
    return $auth->getUserInfo($userPayload['id']);
}

function requireAuthProfile() {
    // Cualquier usuario autenticado puede acceder a su perfil
    return getCurrentUser();
}

function getRoleName($roleId) {
    switch($roleId) {
        case ROLE_ADMIN: return 'Administrador';
        case ROLE_GERENTE: return 'Gerente';
        case ROLE_TRABAJADOR: return 'Trabajador';
        default: return 'Desconocido';
    }
}
?>