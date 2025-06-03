<?php
// sampler-backend/config/db_connection.php

require_once __DIR__ . '/db_config.php'; // Carga las constantes DB_HOST, DB_USER, etc.

function connect() {
    // Las constantes son accesibles globalmente
    error_log("[db_connection.php] Intentando conectar. Host: " . DB_HOST . ", User: " . DB_USER . ", DB_NAME: '" . DB_NAME . "'");

    // 1. Conectar primero al servidor MySQL
    $conn_server = new mysqli(DB_HOST, DB_USER, DB_PASSWORD);

    // Verificar error de conexión AL SERVIDOR
    if ($conn_server->connect_error) {
        error_log("[db_connection.php] FALLO conexión al SERVIDOR MySQL: " . $conn_server->connect_error . " (Código: " . $conn_server->connect_errno . ")");
        throw new RuntimeException('Error de conexión al servidor de base de datos: ' . $conn_server->connect_error);
    }
    error_log("[db_connection.php] Conexión al SERVIDOR MySQL exitosa.");

    // 2. Ahora, seleccionar la base de datos
    if (empty(DB_NAME)) {
        error_log("[db_connection.php] FALLO: La constante DB_NAME para el nombre de la base de datos está vacía.");
        throw new RuntimeException('Nombre de base de datos no configurado.');
        $conn_server->close(); // No debería llegar aquí, pero por si acaso
    }
    
    if (!$conn_server->select_db(DB_NAME)) {
        error_log("[db_connection.php] FALLO al seleccionar la base de datos '" . DB_NAME . "': " . $conn_server->error . " (Código: " . $conn_server->errno . ")");
        // Código 1049: Unknown database
        // Código 1044: Access denied for user to database
        throw new RuntimeException("Error crítico: No se pudo seleccionar la base de datos '" . DB_NAME . "'. Verifica el nombre y los permisos del usuario '" . DB_USER . "'.");
        $conn_server->close(); // No debería llegar aquí
    }
    error_log("[db_connection.php] Base de datos '" . DB_NAME . "' seleccionada exitosamente.");

    // 3. Establecer el charset
    if (!$conn_server->set_charset("utf8mb4")) {
        error_log("[db_connection.php] Advertencia: Error cargando el conjunto de caracteres utf8mb4: " . $conn_server->error);
    }
    
    return $conn_server; // Devolver el objeto de conexión con la BD ya seleccionada
}
?>