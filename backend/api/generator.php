<?php
// BACKEND/api/generator.php - Generador automático de APIs

require_once '../config/conexion.php';
require_once '../config/cors.php';
require_once '../config/auth.php';

// Definir estructura de tablas con sus relaciones
$tablesConfig = [
    // Tablas base
    'personas' => [
        'primary_key' => 'dni',
        'required_fields' => ['dni', 'nombres', 'apellido_paterno'],
        'relations' => [],
        'permission' => 'admin', // Solo admin puede gestionar personas directamente
        'auto_timestamps' => ['fecha_registro']
    ],
    
    // Tablas de contacto relacionadas con personas
    'contacto' => [
        'primary_key' => 'id_contacto',
        'required_fields' => ['dni_persona', 'numero_principal'],
        'relations' => [
            'dni_persona' => ['table' => 'personas', 'field' => 'dni', 'display' => 'nombres||apellido_paterno']
        ],
        'permission' => 'trabajador',
        'auto_timestamps' => []
    ],
    
    'direccion' => [
        'primary_key' => 'id_direccion',
        'required_fields' => ['dni_persona', 'direccion_principal'],
        'relations' => [
            'dni_persona' => ['table' => 'personas', 'field' => 'dni', 'display' => 'nombres||apellido_paterno']
        ],
        'permission' => 'trabajador',
        'auto_timestamps' => []
    ],
    
    'correos' => [
        'primary_key' => 'id_correo',
        'required_fields' => ['dni_persona', 'correo_principal'],
        'relations' => [
            'dni_persona' => ['table' => 'personas', 'field' => 'dni', 'display' => 'nombres||apellido_paterno']
        ],
        'permission' => 'trabajador',
        'auto_timestamps' => []
    ],
    
    // Tablas de negocio
    'segmentos_cliente' => [
        'primary_key' => 'id_segmento',
        'required_fields' => ['nombre_segmento'],
        'relations' => [],
        'permission' => 'gerente',
        'auto_timestamps' => []
    ],
    
    'etiquetas_credito' => [
        'primary_key' => 'id_etiqueta',
        'required_fields' => ['nombre_etiqueta'],
        'relations' => [],
        'permission' => 'gerente',
        'auto_timestamps' => []
    ],
    
    'tasas_interes' => [
        'primary_key' => 'id_tasa',
        'required_fields' => ['tasa', 'monto'],
        'relations' => [],
        'permission' => 'gerente',
        'auto_timestamps' => []
    ],
    
    'seguros' => [
        'primary_key' => 'id_seguro',
        'required_fields' => ['nombre_seguro', 'costo'],
        'relations' => [],
        'permission' => 'gerente',
        'auto_timestamps' => []
    ],
    
    'calendarios_pago' => [
        'primary_key' => 'id_calendario_pago',
        'required_fields' => ['nombre_calendario'],
        'relations' => [],
        'permission' => 'gerente',
        'auto_timestamps' => []
    ],
    
    'fechas_calendario' => [
        'primary_key' => 'id_fecha',
        'required_fields' => ['id_calendario_pago', 'fecha'],
        'relations' => [
            'id_calendario_pago' => ['table' => 'calendarios_pago', 'field' => 'id_calendario_pago', 'display' => 'nombre_calendario']
        ],
        'permission' => 'gerente',
        'auto_timestamps' => []
    ],
    
    // Tablas de trabajo
    'cliente_trabajo' => [
        'primary_key' => 'id_trabajo',
        'required_fields' => ['id_cliente'],
        'relations' => [
            'id_cliente' => ['table' => 'clientes', 'field' => 'id_cliente', 'display' => 'nombres||apellido_paterno', 'join' => 'personas']
        ],
        'permission' => 'trabajador',
        'auto_timestamps' => []
    ],
    
    // Tablas de garantías
    'garantias' => [
        'primary_key' => 'id_garantia',
        'required_fields' => ['id_credito', 'tipo_garantia'],
        'relations' => [
            'id_credito' => ['table' => 'creditos', 'field' => 'id_credito', 'display' => 'monto_aprovado']
        ],
        'permission' => 'trabajador',
        'auto_timestamps' => []
    ],
    
    'garantias_detalle' => [
        'primary_key' => 'id_garantia_detalle',
        'required_fields' => ['id_garantia', 'tipo_detalle'],
        'relations' => [
            'id_garantia' => ['table' => 'garantias', 'field' => 'id_garantia', 'display' => 'tipo_garantia']
        ],
        'permission' => 'trabajador',
        'auto_timestamps' => ['fecha_registro']
    ],
    
    // Tablas de seguros
    'seguros_credito' => [
        'primary_key' => 'id_seguro_credito',
        'required_fields' => ['id_credito', 'id_seguro'],
        'relations' => [
            'id_credito' => ['table' => 'creditos', 'field' => 'id_credito', 'display' => 'monto_aprovado'],
            'id_seguro' => ['table' => 'seguros', 'field' => 'id_seguro', 'display' => 'nombre_seguro']
        ],
        'permission' => 'gerente',
        'auto_timestamps' => []
    ],
    
    // Tablas de historial y seguimiento
    'historial_crediticio' => [
        'primary_key' => 'id_historial',
        'required_fields' => ['id_credito', 'tipo_evento'],
        'relations' => [
            'id_credito' => ['table' => 'creditos', 'field' => 'id_credito', 'display' => 'monto_aprovado']
        ],
        'permission' => 'trabajador',
        'auto_timestamps' => []
    ],
    
    'seguimiento_cliente' => [
        'primary_key' => 'id_seguimiento',
        'required_fields' => ['id_cliente', 'tipo_contacto'],
        'relations' => [
            'id_cliente' => ['table' => 'clientes', 'field' => 'id_cliente', 'display' => 'nombres||apellido_paterno', 'join' => 'personas']
        ],
        'permission' => 'trabajador',
        'auto_timestamps' => []
    ],
    
    // Tablas de comentarios y bitácora
    'comentarios' => [
        'primary_key' => 'id_comentario',
        'required_fields' => ['id_credito', 'comentario'],
        'relations' => [
            'id_credito' => ['table' => 'creditos', 'field' => 'id_credito', 'display' => 'monto_aprovado'],
            'id_usuario' => ['table' => 'usuarios', 'field' => 'id_usuario', 'display' => 'usuario']
        ],
        'permission' => 'trabajador',
        'auto_timestamps' => ['fecha_comentario']
    ],
    
    'bitacora_operaciones' => [
        'primary_key' => 'id_bitacora',
        'required_fields' => ['tipo_operacion'],
        'relations' => [
            'id_credito' => ['table' => 'creditos', 'field' => 'id_credito', 'display' => 'monto_aprovado'],
            'id_usuario' => ['table' => 'usuarios', 'field' => 'id_usuario', 'display' => 'usuario']
        ],
        'permission' => 'gerente',
        'auto_timestamps' => ['fecha']
    ],
    
    // Tablas de reprogramaciones
    'reprogramaciones' => [
        'primary_key' => 'id_reprogramacion',
        'required_fields' => ['id_credito', 'motivo'],
        'relations' => [
            'id_credito' => ['table' => 'creditos', 'field' => 'id_credito', 'display' => 'monto_aprovado']
        ],
        'permission' => 'gerente',
        'auto_timestamps' => ['fecha_creacion']
    ],
    
    'reprogramaciones_detalle' => [
        'primary_key' => 'id_reprogramacion_detalle',
        'required_fields' => ['id_reprogramacion', 'numero_cuota'],
        'relations' => [
            'id_reprogramacion' => ['table' => 'reprogramaciones', 'field' => 'id_reprogramacion', 'display' => 'motivo']
        ],
        'permission' => 'gerente',
        'auto_timestamps' => []
    ],
    
    // Tablas de fiadores y avales
    'fiadores' => [
        'primary_key' => 'id_fiador',
        'required_fields' => ['id_credito', 'id_persona'],
        'relations' => [
            'id_credito' => ['table' => 'creditos', 'field' => 'id_credito', 'display' => 'monto_aprovado'],
            'id_persona' => ['table' => 'personas', 'field' => 'dni', 'display' => 'nombres||apellido_paterno']
        ],
        'permission' => 'trabajador',
        'auto_timestamps' => []
    ],
    
    'avales' => [
        'primary_key' => 'id_aval',
        'required_fields' => ['id_credito', 'id_persona'],
        'relations' => [
            'id_credito' => ['table' => 'creditos', 'field' => 'id_credito', 'display' => 'monto_aprovado'],
            'id_persona' => ['table' => 'personas', 'field' => 'dni', 'display' => 'nombres||apellido_paterno']
        ],
        'permission' => 'trabajador',
        'auto_timestamps' => []
    ],
    
    // Tablas de sucursales y organizacionales
    'sucursal' => [
        'primary_key' => 'id_sucursal',
        'required_fields' => ['nombre', 'direccion'],
        'relations' => [
            'id_usuario_encargado' => ['table' => 'usuarios', 'field' => 'id_usuario', 'display' => 'usuario']
        ],
        'permission' => 'admin',
        'auto_timestamps' => []
    ],
    
    // Tablas de plantillas y contratos
    'plantillas_contrato' => [
        'primary_key' => 'id_plantilla',
        'required_fields' => ['nombre_plantilla', 'contenido_html'],
        'relations' => [],
        'permission' => 'gerente',
        'auto_timestamps' => ['fecha_creacion']
    ],
    
    // Tablas de notificaciones
    'notificaciones' => [
        'primary_key' => 'id_notificacion',
        'required_fields' => ['id_cliente', 'tipo', 'mensaje'],
        'relations' => [
            'id_cliente' => ['table' => 'clientes', 'field' => 'id_cliente', 'display' => 'nombres||apellido_paterno', 'join' => 'personas']
        ],
        'permission' => 'trabajador',
        'auto_timestamps' => ['fecha_envio']
    ],
    
    // Tablas de adjuntos
    'adjuntos' => [
        'primary_key' => 'id_adjunto',
        'required_fields' => ['entidad_origen', 'id_entidad', 'nombre_archivo'],
        'relations' => [],
        'permission' => 'trabajador',
        'auto_timestamps' => ['fecha_carga']
    ],
    
    // Tablas de comisiones y movimientos
    'comisiones_credito' => [
        'primary_key' => 'id_comision',
        'required_fields' => ['id_credito', 'tipo_comision', 'monto'],
        'relations' => [
            'id_credito' => ['table' => 'creditos', 'field' => 'id_credito', 'display' => 'monto_aprovado']
        ],
        'permission' => 'gerente',
        'auto_timestamps' => []
    ],
    
    'movimientos_cuenta' => [
        'primary_key' => 'id_movimiento',
        'required_fields' => ['tipo_movimiento', 'monto'],
        'relations' => [
            'id_credito' => ['table' => 'creditos', 'field' => 'id_credito', 'display' => 'monto_aprovado']
        ],
        'permission' => 'trabajador',
        'auto_timestamps' => ['fecha']
    ],
    
    // Tablas de perfiles
    'perfiles_crediticio' => [
        'primary_key' => 'id_perfil',
        'required_fields' => ['nombre_perfil'],
        'relations' => [],
        'permission' => 'gerente',
        'auto_timestamps' => []
    ],
    
    // Tablas de relaciones
    'credito_etiqueta' => [
        'primary_key' => 'id_credito_etiqueta',
        'required_fields' => ['id_credito', 'id_etiqueta'],
        'relations' => [
            'id_credito' => ['table' => 'creditos', 'field' => 'id_credito', 'display' => 'monto_aprovado'],
            'id_etiqueta' => ['table' => 'etiquetas_credito', 'field' => 'id_etiqueta', 'display' => 'nombre_etiqueta']
        ],
        'permission' => 'trabajador',
        'auto_timestamps' => []
    ],
    
    // Tabla de errores (solo lectura para admin)
    'registro_errores' => [
        'primary_key' => 'id_error',
        'required_fields' => ['modulo', 'mensaje_error'],
        'relations' => [],
        'permission' => 'admin',
        'readonly' => true, // Solo lectura
        'auto_timestamps' => ['fecha']
    ]
];

function getPermissionFunction($permission) {
    switch($permission) {
        case 'admin': return 'requireAdmin';
        case 'gerente': return 'requireGerente';
        case 'trabajador': return 'requireTrabajador';
        default: return 'requireTrabajador';
    }
}

function generateAPI($tableName, $config) {
    $permissionFunc = getPermissionFunction($config['permission']);
    $primaryKey = $config['primary_key'];
    $isReadonly = $config['readonly'] ?? false;
    
    return "<?php
// BACKEND/api/{$tableName}.php - API Auto-generada
require_once '../config/conexion.php';
require_once '../config/cors.php';
require_once '../config/auth.php';

setCorsHeaders();

\$method = \$_SERVER['REQUEST_METHOD'];
\$input = json_decode(file_get_contents('php://input'), true);

switch (\$method) {
    case 'GET':
        handleGet();
        break;" . 
    (!$isReadonly ? "
    case 'POST':
        handlePost();
        break;
    case 'PUT':
        handlePut();
        break;
    case 'DELETE':
        handleDelete();
        break;" : "") . "
    default:
        jsonResponse(false, 'Método no soportado', null, 405);
}

function handleGet() {
    \$user = {$permissionFunc}();
    \$pdo = getPDO();
    
    if (isset(\$_GET['id'])) {
        \$id = \$_GET['id'];
        \$sql = \"SELECT * FROM {$tableName} WHERE {$primaryKey} = :id\";
        \$stmt = \$pdo->prepare(\$sql);
        \$stmt->execute([':id' => \$id]);
        \$item = \$stmt->fetch();
        
        if (\$item) {
            jsonResponse(true, 'Registro encontrado', \$item);
        } else {
            jsonResponse(false, 'Registro no encontrado', null, 404);
        }
    } else {
        \$page = isset(\$_GET['page']) ? (int)\$_GET['page'] : 1;
        \$limit = isset(\$_GET['limit']) ? (int)\$_GET['limit'] : 10;
        \$offset = (\$page - 1) * \$limit;
        
        \$sql = \"SELECT * FROM {$tableName} ORDER BY {$primaryKey} DESC LIMIT :limit OFFSET :offset\";
        \$stmt = \$pdo->prepare(\$sql);
        \$stmt->bindValue(':limit', \$limit, PDO::PARAM_INT);
        \$stmt->bindValue(':offset', \$offset, PDO::PARAM_INT);
        \$stmt->execute();
        \$items = \$stmt->fetchAll();
        
        \$countSql = \"SELECT COUNT(*) as total FROM {$tableName}\";
        \$countStmt = \$pdo->query(\$countSql);
        \$total = \$countStmt->fetch()['total'];
        
        jsonResponse(true, 'Registros obtenidos', [
            'data' => \$items,
            'pagination' => [
                'page' => \$page,
                'limit' => \$limit,
                'total' => \$total,
                'pages' => ceil(\$total / \$limit)
            ]
        ]);
    }
}
" . (!$isReadonly ? generateCRUDMethods($tableName, $config, $permissionFunc) : "") . "
?>";
}

function generateCRUDMethods($tableName, $config, $permissionFunc) {
    $primaryKey = $config['primary_key'];
    $requiredFields = implode("', '", $config['required_fields']);
    
    return "
function handlePost() {
    global \$input;
    \$user = {$permissionFunc}();
    \$pdo = getPDO();
    
    \$required = ['{$requiredFields}'];
    foreach (\$required as \$field) {
        if (!isset(\$input[\$field]) || empty(\$input[\$field])) {
            jsonResponse(false, \"Campo requerido: \$field\", null, 400);
        }
    }
    
    try {
        \$fields = array_keys(\$input);
        \$placeholders = ':' . implode(', :', \$fields);
        \$sql = \"INSERT INTO {$tableName} (\" . implode(', ', \$fields) . \") VALUES (\$placeholders)\";
        \$stmt = \$pdo->prepare(\$sql);
        \$stmt->execute(\$input);
        
        \$id = \$pdo->lastInsertId();
        jsonResponse(true, 'Registro creado exitosamente', ['{$primaryKey}' => \$id], 201);
        
    } catch (PDOException \$e) {
        jsonResponse(false, 'Error al crear registro: ' . \$e->getMessage(), null, 500);
    }
}

function handlePut() {
    global \$input;
    \$user = {$permissionFunc}();
    \$pdo = getPDO();
    
    if (!isset(\$_GET['id'])) {
        jsonResponse(false, 'ID requerido', null, 400);
    }
    
    \$id = \$_GET['id'];
    
    try {
        \$updateFields = [];
        \$params = [':id' => \$id];
        
        foreach (\$input as \$field => \$value) {
            \$updateFields[] = \"\$field = :\$field\";
            \$params[\":\$field\"] = \$value;
        }
        
        if (!empty(\$updateFields)) {
            \$sql = \"UPDATE {$tableName} SET \" . implode(', ', \$updateFields) . \" WHERE {$primaryKey} = :id\";
            \$stmt = \$pdo->prepare(\$sql);
            \$stmt->execute(\$params);
        }
        
        jsonResponse(true, 'Registro actualizado exitosamente');
        
    } catch (PDOException \$e) {
        jsonResponse(false, 'Error al actualizar registro: ' . \$e->getMessage(), null, 500);
    }
}

function handleDelete() {
    \$user = {$permissionFunc}();
    \$pdo = getPDO();
    
    if (!isset(\$_GET['id'])) {
        jsonResponse(false, 'ID requerido', null, 400);
    }
    
    \$id = \$_GET['id'];
    
    try {
        \$sql = \"DELETE FROM {$tableName} WHERE {$primaryKey} = :id\";
        \$stmt = \$pdo->prepare(\$sql);
        \$stmt->execute([':id' => \$id]);
        
        if (\$stmt->rowCount() > 0) {
            jsonResponse(true, 'Registro eliminado exitosamente');
        } else {
            jsonResponse(false, 'Registro no encontrado', null, 404);
        }
        
    } catch (PDOException \$e) {
        jsonResponse(false, 'Error al eliminar registro: ' . \$e->getMessage(), null, 500);
    }
}";
}

// Si se ejecuta directamente, generar todas las APIs
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    $generatedCount = 0;
    
    foreach ($tablesConfig as $tableName => $config) {
        $apiContent = generateAPI($tableName, $config);
        $filename = "../api/{$tableName}.php";
        
        // Solo crear si no existe o si se pasa ?force=1
        if (!file_exists($filename) || isset($_GET['force'])) {
            file_put_contents($filename, $apiContent);
            $generatedCount++;
            echo "[OK] API generada: {$tableName}.php\n";
        } else {
            echo "[SKIP] API ya existe: {$tableName}.php\n";
        }
    }
    
    echo "\n[DONE] Proceso completado. {$generatedCount} APIs generadas.\n";
}
?>