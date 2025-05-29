<?php
// sampler-backend/api/upload_audio.php

// 1. CORS HEADERS
require_once __DIR__ . '/../config/cors_headers.php';

// 2. SESSION
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 3. DB CONNECTION
require_once __DIR__ . '/../config/db_connection.php';

// 4. CONTENT-TYPE
header('Content-Type: application/json');


// --- VERIFICAR SI EL USUARIO ESTÁ LOGUEADO ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'No autorizado. Debes iniciar sesión para subir archivos.']);
    exit;
}

$idUsuarioSubida = $_SESSION['user_id'];
$db = connect();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- LÓGICA DE SUBIDA DE ARCHIVOS CON PHP ---
    // Esto es más complejo en PHP puro que con librerías como Multer en Node.js.
    // Necesitas manejar los archivos de $_FILES.

    // Ejemplo básico (necesitarás validación robusta y manejo de errores):
    $targetDirCovers = __DIR__ . "/../uploads/covers/"; // Asegúrate que esta carpeta existe y tiene permisos de escritura
    $targetDirAudio = __DIR__ . "/../uploads/audio/";   // Asegúrate que esta carpeta existe y tiene permisos de escritura

    if (!file_exists($targetDirCovers)) mkdir($targetDirCovers, 0777, true);
    if (!file_exists($targetDirAudio)) mkdir($targetDirAudio, 0777, true);

    $response = ['error' => 'No se subieron archivos o datos incompletos.'];
    http_response_code(400); // Bad request por defecto

    if (
        isset($_FILES['coverArt']) && $_FILES['coverArt']['error'] == UPLOAD_ERR_OK &&
        isset($_FILES['audioFile']) && $_FILES['audioFile']['error'] == UPLOAD_ERR_OK &&
        isset($_POST['title']) && isset($_POST['artist']) && isset($_POST['genre'])
    ) {
        $title = $db->real_escape_string($_POST['title']);
        $artist = $db->real_escape_string($_POST['artist']);
        $featuredArtists = isset($_POST['featuredArtists']) ? $db->real_escape_string($_POST['featuredArtists']) : null;
        $genre = $db->real_escape_string($_POST['genre']);
        $duration = isset($_POST['duration']) ? $db->real_escape_string($_POST['duration']) : null; // El frontend no envía duración actualmente

        // Procesar portada
        $coverName = time() . '_' . basename($_FILES["coverArt"]["name"]);
        $coverTargetPath = $targetDirCovers . $coverName;
        $coverUrl = "/uploads/covers/" . $coverName; // Ruta relativa para acceso web

        // Procesar audio
        $audioName = time() . '_' . basename($_FILES["audioFile"]["name"]);
        $audioTargetPath = $targetDirAudio . $audioName;
        $audioUrl = "/uploads/audio/" . $audioName;

        // Mover archivos subidos
        if (move_uploaded_file($_FILES["coverArt"]["tmp_name"], $coverTargetPath) && move_uploaded_file($_FILES["audioFile"]["tmp_name"], $audioTargetPath)) {
            
            // Insertar en la base de datos
            $stmt = $db->prepare("INSERT INTO archivos_audio (titulo_audio, nombre_original, nombre_servidor, ruta_archivo, tipo_mime, tamano_bytes, id_usuario_subida) VALUES (?, ?, ?, ?, ?, ?, ?)");
            // Necesitas adaptar esto a tu tabla 'songs' y los datos que envías
            // Ejemplo para tabla 'songs' (simplificado, necesitarás más campos como artist, genre):
            // $stmt = $db->prepare("INSERT INTO songs (title, artist, genre, albumArtUrl, audioUrl, duration, userId) VALUES (?, ?, ?, ?, ?, ?, ?)");
            // if (!$stmt) { /* ... error handling ... */ }
            // $stmt->bind_param("ssssssi", $title, $artist, $genre, $coverUrl, $audioUrl, $duration, $idUsuarioSubida);

            // --- EJEMPLO PARA TU TABLA 'archivos_audio' ---
            // Esto es solo un ejemplo, necesitarás pasar los campos correctos desde el frontend
            // y adaptar la tabla o la inserción.
            $originalCoverName = $_FILES["coverArt"]["name"];
            $originalAudioName = $_FILES["audioFile"]["name"];
            $coverMime = $_FILES["coverArt"]["type"];
            $coverSize = $_FILES["coverArt"]["size"];
            // Para simplificar, usaremos el título de la canción como titulo_audio
            // y nombre_original como el del audio.
            
            // ¡¡¡NECESITAS AJUSTAR ESTO A LA ESTRUCTURA DE TU TABLA 'archivos_audio' Y 'songs'!!!
            // Lo siguiente es una suposición basada en 'archivos_audio'
            // pero el frontend envía datos para una 'canción' que probablemente va en la tabla 'songs'.
            // POR AHORA, haré una inserción SIMPLIFICADA a 'songs' como en el ejemplo de Node.js
            $stmt_songs = $db->prepare("INSERT INTO songs (title, artist, featuredArtists, genre, albumArtUrl, audioUrl, duration, userId) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
             if (!$stmt_songs) {
                http_response_code(500);
                echo json_encode(['error' => 'Error preparando la inserción de canción: ' . $db->error]);
                // Considera borrar archivos subidos si la BD falla
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
                    'albumArtUrl' => $coverUrl, // El frontend lo espera así
                    'audioUrl' => $audioUrl,   // El frontend lo espera así
                    'duration' => $duration
                ];
            } else {
                http_response_code(500);
                $response = ['error' => 'Error al guardar la información en la base de datos: ' . $stmt_songs->error];
                unlink($coverTargetPath); // Limpiar archivos si la BD falla
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
        // ... más validaciones ...
        $response = ['error' => 'Datos incompletos o error en la subida.', 'details' => $errors];
    }

    echo json_encode($response);

} else {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido. Solo POST.']);
}

$db->close();
?>