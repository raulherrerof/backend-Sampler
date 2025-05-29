<?php
// sampler-backend/config/db_connection.php

require_once __DIR__ . '/db_config.php'; // Carga $host, $user, $password, $db

function connect() {
    global $host, $user, $password, $db;

    $conn = new mysqli($host, $user, $password, $db);

    if ($conn->connect_error) {
        // En un entorno de API real, loguearías este error y devolverías un JSON
        http_response_code(500); // Internal Server Error
        // Para no romper el flujo JSON si se llama antes de header('Content-Type: application/json')
        // es mejor no hacer echo aquí si es posible, o asegurar que el content type ya está seteado.
        // Considera lanzar una excepción que se capture en el script principal.
        error_log("Error de conexión a la base de datos (" . $db . "): " . $conn->connect_error);
        die(json_encode(['error' => 'Error de conexión a la base de datos. Intente más tarde.']));
    }

    if (!$conn->set_charset("utf8mb4")) {
        error_log("Error cargando el conjunto de caracteres utf8mb4: " . $conn->error);
    }
    
    return $conn;
}
?>