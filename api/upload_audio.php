<?php
// sampler-backend/api/upload_audio.php (VERSIÓN FINAL)

// Esta línea ahora encontrará el archivo porque la carpeta está en el lugar correcto
require_once __DIR__ . '/../getID3-master/getid3/getid3.php';

require_once __DIR__ . '/../config/cors_headers.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db_connection.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        throw new Exception('No autorizado. Debes iniciar sesión.');
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('Método no permitido.');
    }
    $idUsuarioSubida = (int) $_SESSION['user_id'];

    if (!isset($_FILES['coverArt']) || $_FILES['coverArt']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Error con el archivo de portada.");
    }
    if (!isset($_FILES['audioFile']) || $_FILES['audioFile']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Error con el archivo de audio.");
    }
    if (empty($_POST['title']) || empty($_POST['artist']) || empty($_POST['genre'])) {
        throw new Exception("Título, Artista y Género son obligatorios.");
    }
    
    $db = connect();
    if (!$db) {
        throw new Exception("Error de conexión a la base de datos.");
    }

    $baseUploadDir = __DIR__ . "/../uploads/";
    $targetDirCovers = $baseUploadDir . "covers/";
    $targetDirAudio = $baseUploadDir . "audio/";
    if (!is_dir($targetDirCovers)) mkdir($targetDirCovers, 0775, true);
    if (!is_dir($targetDirAudio)) mkdir($targetDirAudio, 0775, true);
    if (!is_writable($targetDirCovers) || !is_writable($targetDirAudio)) {
        throw new Exception("Los directorios de subida no tienen permisos de escritura.");
    }

    $coverFile = $_FILES['coverArt'];
    $audioFile = $_FILES['audioFile'];
    $coverExtension = strtolower(pathinfo($coverFile["name"], PATHINFO_EXTENSION));
    $coverName = uniqid('cover_', true) . '.' . $coverExtension;
    $coverTargetPath = $targetDirCovers . $coverName;
    $coverUrl = "uploads/covers/" . $coverName;
    $audioExtension = strtolower(pathinfo($audioFile["name"], PATHINFO_EXTENSION));
    $audioName = uniqid('audio_', true) . '.' . $audioExtension;
    $audioTargetPath = $targetDirAudio . $audioName;
    $audioUrl = "uploads/audio/" . $audioName;

    if (!move_uploaded_file($coverFile["tmp_name"], $coverTargetPath)) {
        throw new Exception("No se pudo mover la portada.");
    }
    if (!move_uploaded_file($audioFile["tmp_name"], $audioTargetPath)) {
        unlink($coverTargetPath);
        throw new Exception("No se pudo mover el audio.");
    }

    $getID3 = new getID3;
    $fileInfo = $getID3->analyze($audioTargetPath);
    $durationInteger = round($fileInfo['playtime_seconds'] ?? 0);

    $title = $db->real_escape_string(trim($_POST['title']));
    $artist = $db->real_escape_string(trim($_POST['artist']));
    $featuredArtists = !empty(trim($_POST['featuredArtists'])) ? $db->real_escape_string(trim($_POST['featuredArtists'])) : null;
    $genre = $db->real_escape_string(trim($_POST['genre']));

    $stmt = $db->prepare("INSERT INTO audios (title, artist, featuredArtists, genre, albumArtUrl, audioUrl, duration, userId) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . $db->error);
    }
    
    $stmt->bind_param("ssssssii", $title, $artist, $featuredArtists, $genre, $coverUrl, $audioUrl, $durationInteger, $idUsuarioSubida);
    
    if (!$stmt->execute()) {
        throw new Exception("Error al ejecutar la consulta: " . $stmt->error);
    }

    $newSongId = $stmt->insert_id;
    $stmt->close();
    $db->close();

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'song' => [
            'id' => $newSongId,
            'title' => $_POST['title'],
            'artist' => $_POST['artist'],
            'albumArtUrl' => $coverUrl,
            'audioUrl' => $audioUrl,
            'duration' => $durationInteger,
            'comments' => []
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error en upload_audio.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error en el servidor.',
        'details' => $e->getMessage()
    ]);
    exit;
}
?>