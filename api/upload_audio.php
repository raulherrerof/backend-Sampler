<?php

// Incluir cabeceras CORS primero
require_once __DIR__ . '/../config/cors_headers.php';

// Iniciar sesión si no está activa
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Incluir conexión a la BD
require_once __DIR__ . '/../config/db_connection.php';

// Establecer el tipo de contenido de la respuesta como JSON
header('Content-Type: application/json');

// 1. Verificar autorización (sesión de usuario)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado. Debes iniciar sesión para subir archivos.']);
    exit;
}
$idUsuarioSubida = (int) $_SESSION['user_id']; // Asegurar que sea un entero

// 2. Verificar el método HTTP (solo POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido. Solo se aceptan peticiones POST.']);
    exit;
}

// Establecer conexión a la BD solo si el método es POST y el usuario está autorizado
$db = connect();

// 3. Definir directorios de subida y crearlos si no existen
$baseUploadDir = __DIR__ . "/../uploads/"; // Directorio base para uploads
$targetDirCovers = $baseUploadDir . "covers/";
$targetDirAudio = $baseUploadDir . "audio/";

$dirPermissions = 0755; // Permisos más seguros
if (!is_dir($targetDirCovers)) {
    if (!mkdir($targetDirCovers, $dirPermissions, true) && !is_dir($targetDirCovers)) {
        http_response_code(500);
        error_log("Error al crear el directorio de portadas: " . $targetDirCovers);
        echo json_encode(['error' => 'Error interno del servidor al crear directorio para portadas.']);
        $db->close();
        exit;
    }
}
if (!is_dir($targetDirAudio)) {
    if (!mkdir($targetDirAudio, $dirPermissions, true) && !is_dir($targetDirAudio)) {
        http_response_code(500);
        error_log("Error al crear el directorio de audio: " . $targetDirAudio);
        echo json_encode(['error' => 'Error interno del servidor al crear directorio para audio.']);
        $db->close();
        exit;
    }
}

// 4. Validar la presencia y el estado de los archivos y datos POST
$errors = [];
$requiredPostFields = ['title', 'artist', 'genre'];

if (!isset($_FILES['coverArt']) || $_FILES['coverArt']['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => "El archivo de portada excede la directiva upload_max_filesize.",
        UPLOAD_ERR_FORM_SIZE  => "El archivo de portada excede MAX_FILE_SIZE.",
        UPLOAD_ERR_PARTIAL    => "El archivo de portada fue solo parcialmente subido.",
        UPLOAD_ERR_NO_FILE    => "Ningún archivo de portada fue subido.",
        UPLOAD_ERR_NO_TMP_DIR => "Falta una carpeta temporal para la portada.",
        UPLOAD_ERR_CANT_WRITE => "No se pudo escribir el archivo de portada en el disco.",
        UPLOAD_ERR_EXTENSION  => "Una extensión de PHP detuvo la subida del archivo de portada.",
    ];
    $errorCode = $_FILES['coverArt']['error'] ?? UPLOAD_ERR_NO_FILE;
    $errors[] = "Archivo de portada: " . ($uploadErrors[$errorCode] ?? 'Error desconocido.');
}
if (!isset($_FILES['audioFile']) || $_FILES['audioFile']['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => "El archivo de audio excede la directiva upload_max_filesize.",
        UPLOAD_ERR_FORM_SIZE  => "El archivo de audio excede MAX_FILE_SIZE.",
        UPLOAD_ERR_PARTIAL    => "El archivo de audio fue solo parcialmente subido.",
        UPLOAD_ERR_NO_FILE    => "Ningún archivo de audio fue subido.",
        UPLOAD_ERR_NO_TMP_DIR => "Falta una carpeta temporal para el audio.",
        UPLOAD_ERR_CANT_WRITE => "No se pudo escribir el archivo de audio en el disco.",
        UPLOAD_ERR_EXTENSION  => "Una extensión de PHP detuvo la subida del archivo de audio.",
    ];
    $errorCode = $_FILES['audioFile']['error'] ?? UPLOAD_ERR_NO_FILE;
    $errors[] = "Archivo de audio: " . ($uploadErrors[$errorCode] ?? 'Error desconocido.');
}
foreach ($requiredPostFields as $field) {
    if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
        $errors[] = "Falta el campo requerido: " . ucfirst($field) . ".";
    }
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos incompletos o error en la subida.', 'details' => $errors]);
    if ($db instanceof mysqli) $db->close(); // Cerrar si la conexión se estableció
    exit;
}

// 5. Validaciones adicionales de archivos (tamaño, tipo MIME)
$coverFile = $_FILES['coverArt'];
$audioFile = $_FILES['audioFile'];

$allowedCoverTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$allowedAudioTypes = ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/aac', 'audio/x-m4a', 'video/mp4'];
$maxFileSize = 50 * 1024 * 1024; // 50 MB

$coverMimeType = function_exists('mime_content_type') ? mime_content_type($coverFile['tmp_name']) : $coverFile['type'];
$audioMimeType = function_exists('mime_content_type') ? mime_content_type($audioFile['tmp_name']) : $audioFile['type'];

if (!in_array($coverMimeType, $allowedCoverTypes)) {
    $errors[] = "Tipo de archivo de portada no permitido ('{$coverMimeType}'). Permitidos: " . implode(', ', $allowedCoverTypes);
}
if ($coverFile['size'] > $maxFileSize) {
    $errors[] = "El archivo de portada excede el tamaño máximo de " . ($maxFileSize / 1024 / 1024) . "MB.";
}
if (!in_array($audioMimeType, $allowedAudioTypes)) {
    $errors[] = "Tipo de archivo de audio no permitido ('{$audioMimeType}'). Permitidos: " . implode(', ', $allowedAudioTypes);
}
if ($audioFile['size'] > $maxFileSize) {
    $errors[] = "El archivo de audio excede el tamaño máximo de " . ($maxFileSize / 1024 / 1024) . "MB.";
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['error' => 'Error de validación de archivos.', 'details' => $errors]);
    if ($db instanceof mysqli) $db->close();
    exit;
}

// 6. Procesar datos POST
$title = $db->real_escape_string(trim($_POST['title']));
$artist = $db->real_escape_string(trim($_POST['artist']));
$featuredArtists = isset($_POST['featuredArtists']) && !empty(trim($_POST['featuredArtists'])) ? $db->real_escape_string(trim($_POST['featuredArtists'])) : null;
$genre = $db->real_escape_string(trim($_POST['genre']));
$duration = isset($_POST['duration']) && is_numeric($_POST['duration']) ? (int) $_POST['duration'] : null;

// 7. Preparar nombres de archivo y rutas
$coverExtension = strtolower(pathinfo($coverFile["name"], PATHINFO_EXTENSION));
$audioExtension = strtolower(pathinfo($audioFile["name"], PATHINFO_EXTENSION));

$coverName = uniqid('cover_', true) . '.' . $coverExtension;
$coverTargetPath = $targetDirCovers . $coverName;
$coverUrl = "uploads/covers/" . $coverName;

$audioName = uniqid('audio_', true) . '.' . $audioExtension;
$audioTargetPath = $targetDirAudio . $audioName;
$audioUrl = "uploads/audio/" . $audioName;

// 8. Mover archivos subidos
if (move_uploaded_file($coverFile["tmp_name"], $coverTargetPath) && move_uploaded_file($audioFile["tmp_name"], $audioTargetPath)) {

    // 9. Insertar en la base de datos (tabla 'audios')
    $stmt_songs = $db->prepare("INSERT INTO audios (title, artist, featuredArtists, genre, albumArtUrl, audioUrl, duration, userId) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

    if (!$stmt_songs) {
        http_response_code(500);
        error_log("Error preparando la inserción de canción: " . $db->error);
        echo json_encode(['error' => 'Error interno del servidor (p2).']);
        if(file_exists($coverTargetPath)) unlink($coverTargetPath);
        if(file_exists($audioTargetPath)) unlink($audioTargetPath);
        if ($db instanceof mysqli) $db->close();
        exit;
    }

    // *** INICIO DE LA CORRECCIÓN PARA BIND_PARAM ***
    $types_string = "";
    $bind_params_array = [];

    // 1. title (string, not null)
    $types_string .= "s";
    $bind_params_array[] = $title;

    // 2. artist (string, not null)
    $types_string .= "s";
    $bind_params_array[] = $artist;

    // 3. featuredArtists (string, puede ser null)
    $types_string .= "s";
    $bind_params_array[] = $featuredArtists; // Pasamos null directamente si es null

    // 4. genre (string)
    $types_string .= "s";
    $bind_params_array[] = $genre;

    // 5. albumArtUrl (string)
    $types_string .= "s";
    $bind_params_array[] = $coverUrl;

    // 6. audioUrl (string)
    $types_string .= "s";
    $bind_params_array[] = $audioUrl;

    // 7. duration (integer, puede ser null)
    // Ya hemos convertido $duration a (int) o null, así que podemos usar 'i'
    // MySQLi manejará la conversión de PHP null a SQL NULL para un tipo 'i'.
    $types_string .= "i";
    $bind_params_array[] = $duration; // Pasamos null directamente si es null

    // 8. userId (integer, not null)
    $types_string .= "i";
    $bind_params_array[] = $idUsuarioSubida;

    // Opcional: Depuración para verificar tipos y parámetros
    // error_log("Tipos para bind_param: " . $types_string . " (Longitud: " . strlen($types_string) . ")");
    // error_log("Parámetros para bind_param: " . print_r($bind_params_array, true) . " (Cantidad: " . count($bind_params_array) . ")");

    if (strlen($types_string) !== count($bind_params_array)) {
        // Esta condición es una salvaguarda, no debería activarse si la lógica anterior es correcta.
        http_response_code(500);
        error_log("CRÍTICO: Desajuste entre tipos (" . strlen($types_string) . ") y parámetros (" . count($bind_params_array) . ") para bind_param.");
        echo json_encode(['error' => 'Error interno del servidor (bp-mismatch).']);
        if(file_exists($coverTargetPath)) unlink($coverTargetPath);
        if(file_exists($audioTargetPath)) unlink($audioTargetPath);
        if ($db instanceof mysqli) $db->close();
        exit;
    }
    
    $stmt_songs->bind_param($types_string, ...$bind_params_array);
    // *** FIN DE LA CORRECCIÓN PARA BIND_PARAM ***

    if ($stmt_songs->execute()) {
        $newSongId = $stmt_songs->insert_id;
        http_response_code(201); // Created
        $response = [
            'message' => 'Archivos subidos y canción registrada con éxito.',
            'id' => $newSongId,
            'title' => $_POST['title'],
            'artist' => $_POST['artist'],
            'albumArtUrl' => $coverUrl,
            'audioUrl' => $audioUrl,
            'duration' => isset($_POST['duration']) && is_numeric($_POST['duration']) ? (int)$_POST['duration'] : null
        ];
        echo json_encode($response);
    } else {
        http_response_code(500);
        error_log("Error al guardar la información en la base de datos: " . $stmt_songs->error);
        echo json_encode(['error' => 'Error al guardar la información en la base de datos.']);
        if(file_exists($coverTargetPath)) unlink($coverTargetPath);
        if(file_exists($audioTargetPath)) unlink($audioTargetPath);
    }
    $stmt_songs->close();

} else {
    http_response_code(500);
    $move_errors = [];
    // Comprobamos si el error fue no poder mover el archivo, o si el archivo temporal ni siquiera existe
    if (!isset($_FILES["coverArt"]["tmp_name"]) || !file_exists($_FILES["coverArt"]["tmp_name"]) || !is_uploaded_file($_FILES["coverArt"]["tmp_name"])){
         $move_errors[] = "El archivo de portada temporal no es válido o no se subió correctamente.";
    } elseif (!file_exists($coverTargetPath)) { // Si el temporal era válido pero no se movió
         $move_errors[] = "No se pudo mover el archivo de portada al destino.";
    }

    if (!isset($_FILES["audioFile"]["tmp_name"]) || !file_exists($_FILES["audioFile"]["tmp_name"]) || !is_uploaded_file($_FILES["audioFile"]["tmp_name"])){
         $move_errors[] = "El archivo de audio temporal no es válido o no se subió correctamente.";
    } elseif (!file_exists($audioTargetPath)) {
         $move_errors[] = "No se pudo mover el archivo de audio al destino.";
    }
    
    if(empty($move_errors)) $move_errors[] = "Error desconocido al mover archivos."; // Fallback

    error_log("Error al mover archivos subidos. Detalles: " . print_r($move_errors, true) . ". PHP upload errors: " . print_r($_FILES, true));
    echo json_encode(['error' => 'Error al mover los archivos subidos. Verifica los permisos y la configuración del servidor.', 'details' => $move_errors]);
}

if ($db instanceof mysqli) { // Asegurarse de cerrar solo si $db es un objeto mysqli válido
    $db->close();
}
?>