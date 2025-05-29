<?php
// sampler-backend/api/verificar_sesion.php

// 1. CORS HEADERS
require_once __DIR__ . '/../config/cors_headers.php';

// 2. SESSION
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 3. CONTENT-TYPE
header('Content-Type: application/json');

if (isset($_SESSION['user_id'])) {
    http_response_code(200);
    echo json_encode([
        'isLoggedIn' => true,
        'user' => [ // Enviar datos consistentes con el login
            'id' => $_SESSION['user_id'],
            'username' => isset($_SESSION['username']) ? $_SESSION['username'] : null,
            'name' => isset($_SESSION['user_nombre']) ? $_SESSION['user_nombre'] : null,
            'email' => isset($_SESSION['user_email']) ? $_SESSION['user_email'] : null
        ]
    ]);
} else {
    http_response_code(200); // Sigue siendo 200, pero isLoggedIn es false
    echo json_encode(['isLoggedIn' => false, 'user' => null]);
}
?>