<?php
// BACKEND/api/tasas_interes.php - API Auto-generada
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
    $user = requireGerente();
    $pdo = getPDO();
    
    if (isset($_GET['id'])) {
        $id = $_GET['id'];
        $sql = "SELECT * FROM tasas_interes WHERE id_tasa = :id";
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
        
        $sql = "SELECT * FROM tasas_interes ORDER BY id_tasa DESC LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();
        
        $countSql = "SELECT COUNT(*) as total FROM tasas_interes";
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
    $user = requireGerente();
    $pdo = getPDO();
    
    $required = ['tasa', 'monto'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            jsonResponse(false, "Campo requerido: $field", null, 400);
        }
    }
    
    try {
        $fields = array_keys($input);
        $placeholders = ':' . implode(', :', $fields);
        $sql = "INSERT INTO tasas_interes (" . implode(', ', $fields) . ") VALUES ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($input);
        
        $id = $pdo->lastInsertId();
        jsonResponse(true, 'Registro creado exitosamente', ['id_tasa' => $id], 201);
        
    } catch (PDOException $e) {
        jsonResponse(false, 'Error al crear registro: ' . $e->getMessage(), null, 500);
    }
}

function handlePut() {
    global $input;
    $user = requireGerente();
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
            $sql = "UPDATE tasas_interes SET " . implode(', ', $updateFields) . " WHERE id_tasa = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
        
        jsonResponse(true, 'Registro actualizado exitosamente');
        
    } catch (PDOException $e) {
        jsonResponse(false, 'Error al actualizar registro: ' . $e->getMessage(), null, 500);
    }
}

function handleDelete() {
    $user = requireGerente();
    $pdo = getPDO();
    
    if (!isset($_GET['id'])) {
        jsonResponse(false, 'ID requerido', null, 400);
    }
    
    $id = $_GET['id'];
    
    try {
        $sql = "DELETE FROM tasas_interes WHERE id_tasa = :id";
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