<?php
// BACKEND/config/cors.php

function setCorsHeaders() {
    // Permitir múltiples orígenes para desarrollo
    $allowed_origins = [
        'http://localhost:3000',
        'http://localhost:3001',
        'http://localhost:3002',
        'http://127.0.0.1:3000',
        'http://127.0.0.1:3002',
        'http://localhost'
    ];
    
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    if (in_array($origin, $allowed_origins)) {
        header("Access-Control-Allow-Origin: $origin");
    } else {
        header("Access-Control-Allow-Origin: http://localhost:3000");
    }
    
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Max-Age: 86400"); // Cache preflight por 24 horas
    header("Content-Type: application/json; charset=utf-8");
    
    // Responder OPTIONS (preflight) y terminar
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

// JWT Configuration
define('JWT_SECRET', 'sistema_creditos_secret_key_2024');
define('JWT_EXPIRATION', 28800); // 8 horas (jornada laboral)
define('JWT_REFRESH_THRESHOLD', 1800); // Renovar si quedan menos de 30 minutos

// Función para codificar en Base64 URL-safe
function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

// Función para decodificar Base64 URL-safe
function base64UrlDecode($data) {
    return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
}

// Generar JWT Token
function generateJWT($payload) {
    $header = base64UrlEncode(json_encode(["alg" => "HS256", "typ" => "JWT"]));
    $payload['iat'] = time();
    $payload['exp'] = time() + JWT_EXPIRATION;
    $payloadEncoded = base64UrlEncode(json_encode($payload));
    $signature = base64UrlEncode(hash_hmac("sha256", "$header.$payloadEncoded", JWT_SECRET, true));
    return "$header.$payloadEncoded.$signature";
}

// Verificar JWT Token
function verifyJWT($token) {
    try {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }
        
        list($header, $payload, $signature) = $parts;
        $expectedSignature = base64UrlEncode(hash_hmac("sha256", "$header.$payload", JWT_SECRET, true));
        
        if (!hash_equals($signature, $expectedSignature)) {
            return false;
        }
        
        $payloadData = json_decode(base64UrlDecode($payload), true);
        
        if ($payloadData['exp'] < time()) {
            return false; // Token expirado
        }
        
        return $payloadData;
    } catch (Exception $e) {
        return false;
    }
}

// Obtener token del header Authorization
function getBearerToken() {
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        $matches = array();
        if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
            return $matches[1];
        }
    }
    return null;
}

// Middleware para verificar autenticación
function requireAuth() {
    $token = getBearerToken();
    if (!$token) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token requerido']);
        exit;
    }
    
    $payload = verifyJWT($token);
    if (!$payload) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token inválido o expirado']);
        exit;
    }
    
    // Renovar token si está próximo a expirar (menos de 30 minutos restantes)
    $timeRemaining = $payload['exp'] - time();
    if ($timeRemaining < JWT_REFRESH_THRESHOLD) {
        $newToken = generateJWT([
            'id' => $payload['id'],
            'usuario' => $payload['usuario'],
            'id_rol' => $payload['id_rol'],
            'nombre_rol' => $payload['nombre_rol']
        ]);
        // Enviar nuevo token en el header de respuesta
        header('X-New-Token: ' . $newToken);
    }
    
    return $payload;
}

// Función para respuestas JSON estandarizadas
function jsonResponse($success, $message = '', $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    $response = ['success' => $success, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit;
}
?>