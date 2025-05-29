<?php

require_once __DIR__ . '/../config/cors_headers.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401); 
    echo json_encode(['error' => 'No autorizado. Debes iniciar sesión para subir archivos.']);
    exit;
}

$idUsuarioSubida = $_SESSION['user_id'];
$db = connect();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $targetDirCovers = __DIR__ . "/../uploads/covers/"; 
    $targetDirAudio = __DIR__ . "/../uploads/audio/";  

    if (!file_exists($targetDirCovers)) mkdir($targetDirCovers, 0777, true);
    if (!file_exists($targetDirAudio)) mkdir($targetDirAudio, 0777, true);

    $response = ['error' => 'No se subieron archivos o datos incompletos.'];
    http_response_code(400); 

    if (
        isset($_FILES['coverArt']) && $_FILES['coverArt']['error'] == UPLOAD_ERR_OK &&
        isset($_FILES['audioFile']) && $_FILES['audioFile']['error'] == UPLOAD_ERR_OK &&
        isset($_POST['title']) && isset($_POST['artist']) && isset($_POST['genre'])
    ) {
        $title = $db->real_escape_string($_POST['title']);
        $artist = $db->real_escape_string($_POST['artist']);
        $featuredArtists = isset($_POST['featuredArtists']) ? $db->real_escape_string($_POST['featuredArtists']) : null;
        $genre = $db->real_escape_string($_POST['genre']);
        $duration = isset($_POST['duration']) ? $db->real_escape_string($_POST['duration']) : null;

       
        $coverName = time() . '_' . basename($_FILES["coverArt"]["name"]);
        $coverTargetPath = $targetDirCovers . $coverName;
        $coverUrl = "/uploads/covers/" . $coverName; 

        
        $audioName = time() . '_' . basename($_FILES["audioFile"]["name"]);
        $audioTargetPath = $targetDirAudio . $audioName;
        $audioUrl = "/uploads/audio/" . $audioName;

        // Mover archivos subidos
        if (move_uploaded_file($_FILES["coverArt"]["tmp_name"], $coverTargetPath) && move_uploaded_file($_FILES["audioFile"]["tmp_name"], $audioTargetPath)) {
            
            // Insertar en la base de datos
            $stmt = $db->prepare("INSERT INTO archivos_audio (titulo_audio, nombre_original, nombre_servidor, ruta_archivo, tipo_mime, tamano_bytes, id_usuario_subida) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
            $originalCoverName = $_FILES["coverArt"]["name"];
            $originalAudioName = $_FILES["audioFile"]["name"];
            $coverMime = $_FILES["coverArt"]["type"];
            $coverSize = $_FILES["coverArt"]["size"];
          
            $stmt_songs = $db->prepare("INSERT INTO songs (title, artist, featuredArtists, genre, albumArtUrl, audioUrl, duration, userId) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
             if (!$stmt_songs) {
                http_response_code(500);
                echo json_encode(['error' => 'Error preparando la inserción de canción: ' . $db->error]);
                
                unlink($coverTargetPath); unlink($audioTargetPath);
                $db->close(); exit;
            }
            $stmt_songs->bind_param("sssssssi", $title, $artist, $featuredArtists, $genre, $coverUrl, $audioUrl, $duration, $idUsuarioSubida);


            if ($stmt_songs->execute()) {
                $newSongId = $stmt_songs->insert_id;
                http_response_code(201);
                $response = [
                    'message' => 'Archivos subidos y canción registrada con éxito.',
                    'id' => $newSongId,
                    'title' => $title,
                    'artist' => $artist,
                    'albumArtUrl' => $coverUrl, 
                    'audioUrl' => $audioUrl,   
                    'duration' => $duration
                ];
            } else {
                http_response_code(500);
                $response = ['error' => 'Error al guardar la información en la base de datos: ' . $stmt_songs->error];
                unlink($coverTargetPath); // 
                unlink($audioTargetPath);
            }
            $stmt_songs->close();
        } else {
            http_response_code(500);
            $response = ['error' => 'Error al mover los archivos subidos. Verifica los permisos de la carpeta uploads.'];
        }
    } else {
        $errors = [];
        if (!isset($_FILES['coverArt']) || $_FILES['coverArt']['error'] != UPLOAD_ERR_OK) $errors[] = "Error con el archivo de portada: " . ($_FILES['coverArt']['error'] ?? 'No subido');
        if (!isset($_FILES['audioFile']) || $_FILES['audioFile']['error'] != UPLOAD_ERR_OK) $errors[] = "Error con el archivo de audio: " . ($_FILES['audioFile']['error'] ?? 'No subido');
        if (!isset($_POST['title'])) $errors[] = "Falta el título.";
    
        $response = ['error' => 'Datos incompletos o error en la subida.', 'details' => $errors];
    }

    echo json_encode($response);

} else {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido. Solo POST.']);
}

$db->close();
?>