<?php
require_once '../config/conexion.php';
require_once '../config/cors.php';

setCorsHeaders();
$pdo = getPDO();
$path = $_GET['endpoint'] ?? '';

try {
    switch ($path) {
        case "creditos":
            // Agrupar créditos por mes
            $sql = "
                SELECT MONTH(fecha_otorgamiento) AS mes, COUNT(*) AS total
                FROM creditos
                GROUP BY mes
                ORDER BY mes
            ";
            $stmt = $pdo->query($sql);
            echo json_encode($stmt->fetchAll());
            break;

        case "pagos":
            // Agrupar pagos por mes
            $sql = "
                SELECT MONTH(fecha_pago) AS mes, SUM(monto_pagado) AS total
                FROM pagos
                GROUP BY mes
                ORDER BY mes
            ";
            $stmt = $pdo->query($sql);
            echo json_encode($stmt->fetchAll());
            break;

        case "estado":
            // Agrupar créditos por estado
            $sql = "
                SELECT estado_credito AS estado, COUNT(*) AS total
                FROM creditos
                GROUP BY estado_credito
            ";
            $stmt = $pdo->query($sql);
            echo json_encode($stmt->fetchAll());
            break;

        default:
            http_response_code(404);
            echo json_encode(["error" => "Endpoint no encontrado"]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
