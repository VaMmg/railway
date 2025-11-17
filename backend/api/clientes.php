<?php
// BACKEND/api/clientes.php
require_once '../config/conexion.php';
require_once '../config/cors.php';
require_once '../config/auth.php';
require_once '../helpers/notificaciones_helper.php';

setCorsHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        handleGet();
        break;
    case 'POST':
        handlePost();
        break;
    case 'PUT':
        handlePut();
        break;
    case 'DELETE':
        handleDelete();
        break;
    default:
        jsonResponse(false, 'Método no soportado', null, 405);
}

function handleGet() {
    $user = requireAuth();
    $pdo = getPDO();
    
    if (isset($_GET['id'])) {
        // Obtener cliente específico
        $id = $_GET['id'];
        $sql = "
            SELECT c.*, p.nombres, p.apellido_paterno, p.apellido_materno, p.dni,
                   p.sexo, p.fecha_nacimiento, p.nacionalidad, p.estado_civil,
                   co.correo1 AS correo_principal, con.numero1 AS numero_principal, d.direccion1 AS direccion_principal
            FROM clientes c
            INNER JOIN personas p ON c.dni_persona = p.dni
            LEFT JOIN correos co ON p.dni = co.dni_persona
            LEFT JOIN contacto con ON p.dni = con.dni_persona
            LEFT JOIN direccion d ON p.dni = d.dni_persona
            WHERE c.id_cliente = :id
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $cliente = $stmt->fetch();
        
        if ($cliente) {
            jsonResponse(true, 'Cliente encontrado', $cliente);
        } else {
            jsonResponse(false, 'Cliente no encontrado', null, 404);
        }
    } else {
        // Obtener todos los clientes con paginación
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;
        
        $sql = "
            SELECT c.id_cliente, c.ingreso_mensual, c.gasto_mensual, c.estado, c.fecha_registro, c.id_usuario,
                   p.nombres, p.apellido_paterno, p.apellido_materno, p.dni,
                   co.correo1 AS correo_principal, con.numero1 AS numero_principal
            FROM clientes c
            INNER JOIN personas p ON c.dni_persona = p.dni
            LEFT JOIN correos co ON p.dni = co.dni_persona
            LEFT JOIN contacto con ON p.dni = con.dni_persona
            ORDER BY c.fecha_registro DESC
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $clientes = $stmt->fetchAll();
        
        // Contar total
        $countSql = "SELECT COUNT(*) as total FROM clientes";
        $countStmt = $pdo->query($countSql);
        $total = $countStmt->fetch()['total'];
        
        jsonResponse(true, 'Clientes obtenidos', [
            'clientes' => $clientes,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }
}

function handlePost() {
    global $input;
    $user = requireAuth();
    $pdo = getPDO();
    
    // Validar datos requeridos
    $required = ['dni', 'nombres', 'apellido_paterno', 'ingreso_mensual'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            jsonResponse(false, "Campo requerido: $field", null, 400);
        }
    }
    
    try {
        $pdo->beginTransaction();
        
        // Verificar si la persona ya existe
        $checkPersonSql = "SELECT dni FROM personas WHERE dni = :dni";
        $checkPersonStmt = $pdo->prepare($checkPersonSql);
        $checkPersonStmt->execute([':dni' => $input['dni']]);
        $personaExiste = $checkPersonStmt->fetch();
        
        // Solo insertar persona si NO existe
        if (!$personaExiste) {
            $personSql = "
                INSERT INTO personas (dni, nombres, apellido_paterno, apellido_materno, sexo, 
                                    fecha_nacimiento, nacionalidad, estado_civil, fecha_registro)
                VALUES (:dni, :nombres, :apellido_paterno, :apellido_materno, :sexo,
                        :fecha_nacimiento, :nacionalidad, :estado_civil, NOW())
            ";
            $personStmt = $pdo->prepare($personSql);
            $personStmt->execute([
                ':dni' => $input['dni'],
                ':nombres' => $input['nombres'],
                ':apellido_paterno' => $input['apellido_paterno'],
                ':apellido_materno' => $input['apellido_materno'] ?? '',
                ':sexo' => $input['sexo'] ?? 'M',
                ':fecha_nacimiento' => $input['fecha_nacimiento'] ?? null,
                ':nacionalidad' => $input['nacionalidad'] ?? 'Peruana',
                ':estado_civil' => $input['estado_civil'] ?? 'Soltero'
            ]);
        }
        
        // Verificar si el DNI ya está registrado como cliente
        $checkClientSql = "SELECT id_cliente FROM clientes WHERE dni_persona = :dni";
        $checkClientStmt = $pdo->prepare($checkClientSql);
        $checkClientStmt->execute([':dni' => $input['dni']]);
        $clienteExiste = $checkClientStmt->fetch();
        
        if ($clienteExiste) {
            $pdo->rollBack();
            jsonResponse(false, 'Este DNI ya está registrado como cliente', null, 400);
            return;
        }
        
        // Insertar cliente
        $clientSql = "
            INSERT INTO clientes (dni_persona, ingreso_mensual, gasto_mensual, tipo_contrato,
                                antiguedad_laboral, estado, fecha_registro, id_usuario)
            VALUES (:dni_persona, :ingreso_mensual, :gasto_mensual, :tipo_contrato,
                    :antiguedad_laboral, 'Activo', NOW(), :id_usuario)
        ";
        $clientStmt = $pdo->prepare($clientSql);
        $clientStmt->execute([
            ':dni_persona' => $input['dni'],
            ':ingreso_mensual' => $input['ingreso_mensual'],
            ':gasto_mensual' => $input['gasto_mensual'] ?? 0,
            ':tipo_contrato' => $input['tipo_contrato'] ?? 'Indefinido',
            ':antiguedad_laboral' => $input['antiguedad_laboral'] ?? 0,
            ':id_usuario' => $user['id']
        ]);
        
        $clienteId = $pdo->lastInsertId();
        
        // Insertar o actualizar correo si existe
        if (!empty($input['correo'])) {
            $checkEmailSql = "SELECT dni_persona FROM correos WHERE dni_persona = :dni";
            $checkEmailStmt = $pdo->prepare($checkEmailSql);
            $checkEmailStmt->execute([':dni' => $input['dni']]);
            $emailExiste = $checkEmailStmt->fetch();
            
            if (!$emailExiste) {
                $emailSql = "
                    INSERT INTO correos (dni_persona, cuantas_correos, correo1)
                    VALUES (:dni_persona, 1, :correo)
                ";
                $emailStmt = $pdo->prepare($emailSql);
                $emailStmt->execute([
                    ':dni_persona' => $input['dni'],
                    ':correo' => $input['correo']
                ]);
            }
        }
        
        // Insertar o actualizar contacto si existe
        if (!empty($input['telefono'])) {
            $checkContactSql = "SELECT dni_persona FROM contacto WHERE dni_persona = :dni";
            $checkContactStmt = $pdo->prepare($checkContactSql);
            $checkContactStmt->execute([':dni' => $input['dni']]);
            $contactExiste = $checkContactStmt->fetch();
            
            if (!$contactExiste) {
                $contactSql = "
                    INSERT INTO contacto (dni_persona, cantidad_numeros, numero1)
                    VALUES (:dni_persona, 1, :telefono)
                ";
                $contactStmt = $pdo->prepare($contactSql);
                $contactStmt->execute([
                    ':dni_persona' => $input['dni'],
                    ':telefono' => $input['telefono']
                ]);
            }
        }
        
        // Insertar o actualizar dirección si existe
        if (!empty($input['direccion'])) {
            $checkDirSql = "SELECT dni_persona FROM direccion WHERE dni_persona = :dni";
            $checkDirStmt = $pdo->prepare($checkDirSql);
            $checkDirStmt->execute([':dni' => $input['dni']]);
            $direccionExiste = $checkDirStmt->fetch();
            
            if (!$direccionExiste) {
                $addressSql = "
                    INSERT INTO direccion (dni_persona, cuantas_direcciones, direccion1)
                    VALUES (:dni_persona, 1, :direccion)
                ";
                $addressStmt = $pdo->prepare($addressSql);
                $addressStmt->execute([
                    ':dni_persona' => $input['dni'],
                    ':direccion' => $input['direccion']
                ]);
            }
        }
        
        // Notificar al gerente sobre el nuevo cliente
        $user = requireAuth();
        $personaSql = "SELECT CONCAT(nombres, ' ', apellido_paterno, ' ', apellido_materno) as nombre_completo FROM personas WHERE dni = :dni";
        $personaStmt = $pdo->prepare($personaSql);
        $personaStmt->execute([':dni' => $input['dni']]);
        $nombreCompleto = $personaStmt->fetchColumn();
        
        notificarGerente(
            $pdo,
            'cliente_nuevo',
            "Nuevo cliente registrado: {$nombreCompleto} (DNI: {$input['dni']})",
            $user['id'] ?? $user['id_usuario'],
            $clienteId,
            'cliente'
        );
        
        $pdo->commit();
        
        jsonResponse(true, 'Cliente creado exitosamente', ['id_cliente' => $clienteId], 201);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Error al crear cliente: ' . $e->getMessage(), null, 500);
    }
}

function handlePut() {
    global $input;
    $user = requireAuth();
    $pdo = getPDO();
    
    if (!isset($_GET['id'])) {
        jsonResponse(false, 'ID de cliente requerido', null, 400);
    }
    
    $id = $_GET['id'];
    
    try {
        $pdo->beginTransaction();
        
        // Actualizar cliente
        $updateFields = [];
        $params = [':id' => $id];
        
        if (isset($input['ingreso_mensual'])) {
            $updateFields[] = "ingreso_mensual = :ingreso_mensual";
            $params[':ingreso_mensual'] = $input['ingreso_mensual'];
        }
        if (isset($input['gasto_mensual'])) {
            $updateFields[] = "gasto_mensual = :gasto_mensual";
            $params[':gasto_mensual'] = $input['gasto_mensual'];
        }
        if (isset($input['estado'])) {
            $updateFields[] = "estado = :estado";
            $params[':estado'] = $input['estado'];
        }
        
        if (!empty($updateFields)) {
            $sql = "UPDATE clientes SET " . implode(', ', $updateFields) . " WHERE id_cliente = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
        
        $pdo->commit();
        
        jsonResponse(true, 'Cliente actualizado exitosamente');
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Error al actualizar cliente: ' . $e->getMessage(), null, 500);
    }
}

function handleDelete() {
    $user = requireAuth();
    $pdo = getPDO();
    
    if (!isset($_GET['id'])) {
        jsonResponse(false, 'ID de cliente requerido', null, 400);
    }
    
    $id = $_GET['id'];
    
    try {
        // Verificar si el cliente tiene créditos activos
        $checkSql = "SELECT COUNT(*) as count FROM creditos WHERE id_cliente = :id AND estado_credito = 'Activo'";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([':id' => $id]);
        $activeCredits = $checkStmt->fetch()['count'];
        
        if ($activeCredits > 0) {
            jsonResponse(false, 'No se puede eliminar: cliente tiene créditos activos', null, 400);
        }
        
        // Soft delete - cambiar estado
        $sql = "UPDATE clientes SET estado = 'Inactivo' WHERE id_cliente = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        jsonResponse(true, 'Cliente desactivado exitosamente');
        
    } catch (PDOException $e) {
        jsonResponse(false, 'Error al eliminar cliente: ' . $e->getMessage(), null, 500);
    }
}
?>