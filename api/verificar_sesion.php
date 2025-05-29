<?php

require_once __DIR__ . '/../config/cors_headers.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (isset($_SESSION['user_id'])) {
    http_response_code(200);
    echo json_encode([
        'isLoggedIn' => true,
        'user' => [ 
            'id' => $_SESSION['user_id'],
            'username' => isset($_SESSION['username']) ? $_SESSION['username'] : null,
            'name' => isset($_SESSION['user_nombre']) ? $_SESSION['user_nombre'] : null,
            'email' => isset($_SESSION['user_email']) ? $_SESSION['user_email'] : null
        ]
    ]);
} else {
    http_response_code(200); 
    echo json_encode(['isLoggedIn' => false, 'user' => null]);
}
?>