<?php
// config/database.php

// --- Cabeceras CORS y configuración de sesión ---
// Es importante que session_start() se llame antes de cualquier salida si usas sesiones.
if (session_status() == PHP_SESSION_NONE) {
    // session_set_cookie_params([
    //     'lifetime' => 86400, // Duración de la cookie de sesión (ej. 1 día)
    //     'path' => '/',
    //     'domain' => $_SERVER['HTTP_HOST'], // Ajusta si es necesario
    //     'secure' => isset($_SERVER['HTTPS']), // True si usas HTTPS
    //     'httponly' => true, // Cookie no accesible por JavaScript
    //     'samesite' => 'Lax' // O 'Strict' o 'None' (si es 'None', 'secure' debe ser true)
    // ]);
    session_start();
}

// Permite peticiones desde tu frontend React
// ¡¡¡CAMBIA 'http://localhost:3000' por el origen real de tu frontend en producción!!!
header("Access-Control-Allow-Origin: http://localhost:3001"); // O el puerto donde corre tu React
header("Access-Control-Allow-Credentials: true"); // Necesario para enviar cookies (sesión)
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, X-Csrf-Token"); // Añade X-Csrf-Token si lo usas
// Tipo de contenido que la API responderá
header("Content-Type: application/json; charset=UTF-8");

// Manejar la petición OPTIONS (pre-flight) para CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    // No es necesario enviar un cuerpo de respuesta para OPTIONS
    http_response_code(204); // No Content
    exit();
}

// --- Definiciones de constantes de la BD ---
// Estas deberían venir de tu archivo db_config.php o definirlas aquí directamente
// Si usas db_config.php, asegúrate de que se incluye ANTES de este archivo o aquí.
// Por simplicidad, las defino aquí, pero es mejor práctica tenerlas en un archivo separado
// y que no esté en el repositorio público (ej. usando variables de entorno).

if (file_exists(__DIR__ . '/db_config.php')) { // Asumiendo que db_config.php está en la misma carpeta 'config'
    include_once __DIR__ . '/db_config.php';
} else {
    // Valores por defecto si db_config.php no existe (no recomendado para producción)
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root'); // CAMBIA ESTO
    define('DB_PASS', '');    // CAMBIA ESTO
    define('DB_NAME', 'Sampler_db'); // CAMBIA ESTO
}

// --- Función de Conexión ---
function connect() {
    // Usar las constantes definidas arriba
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        http_response_code(500);
        // En producción, no muestres el error detallado al cliente. Loguéalo.
        error_log("Error de conexión a MySQL: " . $conn->connect_error);
        echo json_encode(['error' => 'Error interno del servidor al conectar con la base de datos.']);
        exit();
    }

    $conn->set_charset("utf8mb4");
    return $conn;
}

// No hagas: $db = connect(); aquí, ya que cada script de API lo llamará cuando lo necesite.
?>