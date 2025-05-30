<?php

require_once __DIR__ . '/db_config.php'; 

function connect() {
    global $host, $user, $password, $db;

    $conn = new mysqli($host, $user, $password, $db);

    if ($conn->connect_error) {
    http_response_code(500);
    error_log("Error de conexión a la base de datos (" . $db . "): " . $conn->connect_error);
    echo json_encode(['error' => 'Error de conexión a la base de datos. Intente más tarde.']);
    exit;
    }

    if (!$conn->set_charset("utf8mb4")) {
        error_log("Error cargando el conjunto de caracteres utf8mb4: " . $conn->error);
    }
    
    return $conn;
}
?>