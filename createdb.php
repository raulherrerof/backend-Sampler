<?php
include 'db_config.php';

error_reporting(E_ALL); // Para ver todos los errores durante el desarrollo
ini_set('display_errors', 1);


$sql = "CREATE DATABASE IF NOT EXISTS $db";

if (mysqli_query($conn, $sql)) {
    echo "Base de datos creada exitosamente";
} else {
    echo "Error al crear la base de datos: " . mysqli_error($conn);
}

mysqli_select_db($conn, $bd);

$sql = "
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50) NOT NULL,
    contrasena VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    edad INT NOT NULL,
    created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
";

if (mysqli_query($conn, $sql)) {
    echo "Tabla 'usuarios' creada exitosamente.<br>";
} else {
    echo "Error al crear la tabla 'usuarios': " . mysqli_error($conn) . "<br>";
}


$sql = "
CREATE TABLE IF NOT EXISTS archivos_audio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_original VARCHAR(255) NOT NULL,         -- Nombre original del archivo subido por el usuario
    nombre_servidor VARCHAR(255) NOT NULL UNIQUE,  -- Nombre único del archivo en el servidor (para evitar colisiones)
    ruta_archivo VARCHAR(512) NOT NULL,            -- Ruta relativa o absoluta al archivo en el servidor
    tipo_mime VARCHAR(100),                        -- Ej: 'audio/mpeg', 'audio/wav'
    tamano_bytes BIGINT,                           -- Tamaño del archivo en bytes
    titulo_audio VARCHAR(255),                     -- Un título legible para el audio (opcional)
    descripcion_audio TEXT,                        -- Descripción (opcional)
    id_usuario_subida INT,                         -- Quién subió el archivo (si tienes usuarios)
    fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    -- Otros campos que necesites: duración, artista, álbum, etc.
    FOREIGN KEY (id_usuario_subida) REFERENCES usuarios(id) -- Si tienes una tabla de usuarios
)";

if (mysqli_query($conn, $sql)) {
    echo "Tabla 'archivos_audio' creada exitosamente.<br>";
} else {
    echo "Error al crear la tabla 'usuarios': " . mysqli_error($conn) . "<br>";
}

mysqli_close($conn);

?>