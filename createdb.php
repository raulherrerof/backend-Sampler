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
    `dob` DATE NULL DEFAULT NULL,       -- Fecha de nacimiento
    `gender` VARCHAR(50) NULL DEFAULT NULL, -- Sexo
    `aboutMe` TEXT NULL DEFAULT NULL,   -- Sobre ti
    `profilePicUrl` VARCHAR(512) NULL DEFAULT NULL, -- URL a la foto de perfil
    `created_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql_usuarios)) {
    echo "Tabla 'usuarios' verificada/creada exitosamente.<br>";
} else {
    echo "Error al crear la tabla 'usuarios': " . $conn->error . "<br>";
}

$sql_archivos_audio = "
CREATE TABLE IF NOT EXISTS audios (
    id INT AUTO_INCREMENT PRIMARY KEY,                         -- Clave primaria autoincremental
    title VARCHAR(255) NOT NULL,                               -- Título de la canción/audio
    artist VARCHAR(255) NOT NULL,                              -- Artista principal
    featuredArtists TEXT,                                      -- Artistas invitados (puede ser una lista, por eso TEXT)
    genre VARCHAR(100),                                        -- Género musical
    albumArtUrl VARCHAR(1024),                                 -- URL de la carátula del álbum (puede ser larga)
    audioUrl VARCHAR(1024) NOT NULL,                           -- URL del archivo de audio (esencial)
    duration INT,                                              -- Duración en segundos (o milisegundos, define tu unidad)
    userId INT,                                                -- ID del usuario que subió/posee el audio
    fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP,          -- Fecha de subida/creación del registro
    -- Opcional: Clave foránea para userId si tienes una tabla de usuarios
    FOREIGN KEY (userId) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE
)";
if ($conn->query($sql_archivos_audio)) {
    echo "Tabla 'archivos_audio' verificada/creada exitosamente.<br>";
} else {
   
    echo "Error al crear la tabla 'archivos_audio': " . $conn->error . "<br>";
}

$sql_song_likes = "
CREATE TABLE IF NOT EXISTS song_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    song_id INT NOT NULL,
    liked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE, -- Si se borra el usuario, se borran sus likes
    FOREIGN KEY (song_id) REFERENCES audios(id) ON DELETE CASCADE,  -- Si se borra la canción, se borran sus likes
    UNIQUE KEY unique_user_song_like (user_id, song_id) -- Un usuario solo puede likear una canción una vez
)";

if ($conn->query($sql_song_likes)) {
    echo "Tabla 'song_likes' verificada/creada exitosamente.<br>";
} else {
   
    echo "Error al crear la tabla 'song_likes': " . $conn->error . "<br>";
}

$sql_comments = "
CREATE TABLE IF NOT EXISTS `comments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `song_id` INT NOT NULL,                 -- Referencia al ID de la tabla 'audios'
    `user_id` INT NOT NULL,                 -- Referencia al ID de la tabla 'usuarios'
    `comment_text` TEXT NOT NULL,           -- El contenido del comentario
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Fecha de creación
    FOREIGN KEY (`song_id`) REFERENCES `audios`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
)";

if ($conn->query($sql_comments)) {
    echo "Tabla 'comments' verificada/creada exitosamente.<br>";
} else {
   
    echo "Error al crear la tabla 'comments': " . $conn->error . "<br>";
}

echo "Proceso de configuración de base de datos completado.<br>";
$conn->close();
?>