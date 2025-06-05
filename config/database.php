<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header("Access-Control-Allow-Origin: http://localhost:3000"); 
header("Access-Control-Allow-Credentials: true"); 
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, X-Csrf-Token"); 

header("Content-Type: application/json; charset=UTF-8");


if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
  
    http_response_code(204); 
    exit();
}


if (file_exists(__DIR__ . '/db_config.php')) { 
    include_once __DIR__ . '/db_config.php';
} else {
   
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root'); 
    define('DB_PASS', '');    
    define('DB_NAME', 'Sampler_db'); 
}

function connect() {
    
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        http_response_code(500);
        error_log("Error de conexión a MySQL: " . $conn->connect_error);
        echo json_encode(['error' => 'Error interno del servidor al conectar con la base de datos.']);
        exit();
    }

    $conn->set_charset("utf8mb4");
    return $conn;
}

?>