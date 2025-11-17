<?php
// BACKEND/api/movimientos_cuenta.php - API Auto-generada
require_once '../config/conexion.php';
require_once '../config/cors.php';
require_once '../config/auth.php';

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
    $user = requireTrabajador();
    $pdo = getPDO();
    
    if (isset($_GET['id'])) {
        $id = $_GET['id'];
        $sql = "SELECT * FROM movimientos_cuenta WHERE id_movimiento = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $item = $stmt->fetch();
        
        if ($item) {
            jsonResponse(true, 'Registro encontrado', $item);
        } else {
            jsonResponse(false, 'Registro no encontrado', null, 404);
        }
    } else {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT * FROM movimientos_cuenta ORDER BY id_movimiento DESC LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();
        
        $countSql = "SELECT COUNT(*) as total FROM movimientos_cuenta";
        $countStmt = $pdo->query($countSql);
        $total = $countStmt->fetch()['total'];
        
        jsonResponse(true, 'Registros obtenidos', [
            'data' => $items,
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
    $user = requireTrabajador();
    $pdo = getPDO();
    
    $required = ['tipo_movimiento', 'monto'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            jsonResponse(false, "Campo requerido: $field", null, 400);
        }
    }
    
    try {
        $fields = array_keys($input);
        $placeholders = ':' . implode(', :', $fields);
        $sql = "INSERT INTO movimientos_cuenta (" . implode(', ', $fields) . ") VALUES ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($input);
        
        $id = $pdo->lastInsertId();
        jsonResponse(true, 'Registro creado exitosamente', ['id_movimiento' => $id], 201);
        
    } catch (PDOException $e) {
        jsonResponse(false, 'Error al crear registro: ' . $e->getMessage(), null, 500);
    }
}

function handlePut() {
    global $input;
    $user = requireTrabajador();
    $pdo = getPDO();
    
    if (!isset($_GET['id'])) {
        jsonResponse(false, 'ID requerido', null, 400);
    }
    
    $id = $_GET['id'];
    
    try {
        $updateFields = [];
        $params = [':id' => $id];
        
        foreach ($input as $field => $value) {
            $updateFields[] = "$field = :$field";
            $params[":$field"] = $value;
        }
        
        if (!empty($updateFields)) {
            $sql = "UPDATE movimientos_cuenta SET " . implode(', ', $updateFields) . " WHERE id_movimiento = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
        
        jsonResponse(true, 'Registro actualizado exitosamente');
        
    } catch (PDOException $e) {
        jsonResponse(false, 'Error al actualizar registro: ' . $e->getMessage(), null, 500);
    }
}

function handleDelete() {
    $user = requireTrabajador();
    $pdo = getPDO();
    
    if (!isset($_GET['id'])) {
        jsonResponse(false, 'ID requerido', null, 400);
    }
    
    $id = $_GET['id'];
    
    try {
        $sql = "DELETE FROM movimientos_cuenta WHERE id_movimiento = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        if ($stmt->rowCount() > 0) {
            jsonResponse(true, 'Registro eliminado exitosamente');
        } else {
            jsonResponse(false, 'Registro no encontrado', null, 404);
        }
        
    } catch (PDOException $e) {
        jsonResponse(false, 'Error al eliminar registro: ' . $e->getMessage(), null, 500);
    }
}
?>