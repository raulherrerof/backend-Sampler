<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (file_exists(__DIR__ . '/config/db_config.php')) { 
    require_once __DIR__ . '/config/db_config.php';
} else if (file_exists(__DIR__ . '/db_config.php')) { 
    require_once __DIR__ . '/db_config.php';
} else {

    define('DB_HOST', 'localhost');
    define('DB_USER_SETUP', 'root'); 
    define('DB_PASS_SETUP', '');     
    define('DB_NAME_TO_CREATE', 'Sampler_db'); 
    echo "ADVERTENCIA: Usando credenciales y nombre de BD por defecto para setup. Asegúrate de que 'db_config.php' exista y esté configurado para producción.<br>";
}

$conn = new mysqli(defined('DB_HOST') ? DB_HOST : 'localhost',
                   defined('DB_USER_SETUP') ? DB_USER_SETUP : 'root',
                   defined('DB_PASS_SETUP') ? DB_PASS_SETUP : '');

if ($conn->connect_error) {
    die("Error de conexión al servidor MySQL: " . $conn->connect_error . "<br>");
}
echo "Conexión al servidor MySQL exitosa.<br>";


$dbNameToUse = defined('DB_NAME_TO_CREATE') ? DB_NAME_TO_CREATE : (defined('DB_NAME') ? DB_NAME : 'Sampler_db');
$sql_create_db = "CREATE DATABASE IF NOT EXISTS `$dbNameToUse` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql_create_db)) {
    echo "Base de datos '$dbNameToUse' verificada/creada exitosamente.<br>";
} else {
    die("Error al crear la base de datos '$dbNameToUse': " . $conn->error . "<br>");
}


if (!$conn->select_db($dbNameToUse)) {
    die("Error al seleccionar la base de datos '$dbNameToUse': " . $conn->error . "<br>");
}
echo "Base de datos '$dbNameToUse' seleccionada.<br>";


$sql_usuarios = "
CREATE TABLE IF NOT EXISTS `usuarios` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `usuario` VARCHAR(50) NOT NULL UNIQUE,
    `contrasena` VARCHAR(255) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `nombre` VARCHAR(100) NOT NULL,
    `apellido` VARCHAR(100) NOT NULL,
    `edad` INT, -- Permitir NULL si no es siempre obligatorio
    `created_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql_usuarios)) {
    echo "Tabla 'usuarios' verificada/creada exitosamente.<br>";
} else {
    echo "Error al crear la tabla 'usuarios': " . $conn->error . "<br>";
}


$sql_archivos_audio = "
CREATE TABLE IF NOT EXISTS `archivos_audio` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre_original` VARCHAR(255) NOT NULL,
    `nombre_servidor` VARCHAR(255) NOT NULL UNIQUE,
    `ruta_archivo` VARCHAR(512) NOT NULL,
    `tipo_mime` VARCHAR(100),
    `tamano_bytes` BIGINT,
    `titulo_audio` VARCHAR(255),
    `descripcion_audio` TEXT,
    `id_usuario_subida` INT,
    `fecha_subida` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`id_usuario_subida`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL ON UPDATE CASCADE -- Buena práctica añadir acciones ON DELETE/UPDATE
)";
if ($conn->query($sql_archivos_audio)) {
    echo "Tabla 'archivos_audio' verificada/creada exitosamente.<br>";
} else {
   
    echo "Error al crear la tabla 'archivos_audio': " . $conn->error . "<br>";
}

echo "Proceso de configuración de base de datos completado.<br>";
$conn->close();
?>