<?php
// sampler-backend/api/logout.php

// 1. CORS HEADERS
require_once __DIR__ . '/../config/cors_headers.php';

// 2. SESSION (necesario para destruirla)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 3. CONTENT-TYPE
header('Content-Type: application/json');


// Destruir todas las variables de sesión.
$_SESSION = array();

// Borrar la cookie de sesión.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

http_response_code(200);
echo json_encode(['message' => 'Sesión cerrada exitosamente.']);
?>