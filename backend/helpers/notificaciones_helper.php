<?php
/**
 * Helper para crear notificaciones en el sistema
 */

/**
 * Crear una notificación
 * @param PDO $pdo Conexión a la base de datos
 * @param string $tipo Tipo de notificación
 * @param string $mensaje Mensaje de la notificación
 * @param int|null $destinatarioUsuario ID del usuario destinatario (null para notificación por rol)
 * @param int|null $destinatarioRol ID del rol destinatario (null para notificación personal)
 * @param int|null $usuarioOrigen ID del usuario que genera la notificación
 * @param int|null $referenciaId ID de referencia (crédito, pago, etc.)
 * @param string|null $referenciaTipo Tipo de referencia (credito, pago, caja, etc.)
 * @return bool
 */
function crearNotificacion($pdo, $tipo, $mensaje, $destinatarioUsuario = null, $destinatarioRol = null, $usuarioOrigen = null, $referenciaId = null, $referenciaTipo = null) {
    try {
        $sql = "
            INSERT INTO notificaciones (
                tipo, mensaje, fecha_envio, leida,
                destinatario_usuario, destinatario_rol, usuario_origen,
                referencia_id, referencia_tipo
            ) VALUES (
                :tipo, :mensaje, NOW(), 0,
                :destinatario_usuario, :destinatario_rol, :usuario_origen,
                :referencia_id, :referencia_tipo
            )
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':tipo' => $tipo,
            ':mensaje' => $mensaje,
            ':destinatario_usuario' => $destinatarioUsuario,
            ':destinatario_rol' => $destinatarioRol,
            ':usuario_origen' => $usuarioOrigen,
            ':referencia_id' => $referenciaId,
            ':referencia_tipo' => $referenciaTipo
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Error al crear notificación: " . $e->getMessage());
        return false;
    }
}

/**
 * Notificar al gerente (rol 2)
 */
function notificarGerente($pdo, $tipo, $mensaje, $usuarioOrigen = null, $referenciaId = null, $referenciaTipo = null) {
    return crearNotificacion($pdo, $tipo, $mensaje, null, 2, $usuarioOrigen, $referenciaId, $referenciaTipo);
}

/**
 * Notificar a un usuario específico
 */
function notificarUsuario($pdo, $idUsuario, $tipo, $mensaje, $usuarioOrigen = null, $referenciaId = null, $referenciaTipo = null) {
    return crearNotificacion($pdo, $tipo, $mensaje, $idUsuario, null, $usuarioOrigen, $referenciaId, $referenciaTipo);
}

/**
 * Notificar a todos los trabajadores (rol 3)
 */
function notificarTrabajadores($pdo, $tipo, $mensaje, $usuarioOrigen = null, $referenciaId = null, $referenciaTipo = null) {
    return crearNotificacion($pdo, $tipo, $mensaje, null, 3, $usuarioOrigen, $referenciaId, $referenciaTipo);
}
