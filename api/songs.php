<?php
// sampler-backend/api/songs.php

// --- HABILITAR LOGS (SOLO PARA DESARROLLO) ---
ini_set('log_errors', 1);
// Asegúrate de que la carpeta backend-Sampler tenga permisos de escritura para este archivo
ini_set('error_log', __DIR__ . '/../php_debug.log'); 
error_reporting(E_ALL);
error_log("--- Ejecutando songs.php ---");
// --- FIN HABILITAR LOGS ---

// 1. INCLUIR CABECERAS CORS PRIMERO
require_once __DIR__ . '/../config/cors_headers.php'; // Asume que este archivo está configurado correctamente

// 3. INCLUIR CONEXIÓN A BD (que a su vez incluye db_config.php con constantes)
require_once __DIR__ . '/../config/db_connection.php'; 

// 4. ESTABLECER CONTENT-TYPE PARA LA RESPUESTA JSON
header('Content-Type: application/json');

$db = null; 
$songs_list_for_json = [];

try {
    $db = connect(); // No se pasan parámetros, usará las constantes
    // $db ahora es el objeto de conexión con la base de datos seleccionada

    // Asegúrate de que la tabla y las columnas coincidan con tu BD.
    $sql = "SELECT id, title, artist, featuredArtists, genre, albumArtUrl, audioUrl, duration, userId, fecha_subida AS createdAt
            FROM audios  
            ORDER BY fecha_subida DESC"; 
    
    $result = $db->query($sql);

    if ($result) {
        error_log("songs.php - Consulta SQL ejecutada. Filas obtenidas: " . $result->num_rows);

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST']; 
        $webRootPathForUploads = '/backend-Sampler'; // AJUSTA SI TU CARPETA BACKEND NO ES 'backend-Sampler' en la URL
        
        $baseFileAccessUrl = rtrim($protocol . $host . $webRootPathForUploads, '/');
        error_log("songs.php - BaseFileAccessUrl: " . $baseFileAccessUrl);

        while ($row = $result->fetch_assoc()) {
            error_log("songs.php - Procesando fila ID: " . ($row['id'] ?? 'N/A') . ", audioUrl de BD: " . ($row['audioUrl'] ?? 'N/A'));

            $processed_album_art_url = null;
            if (isset($row['albumArtUrl']) && !empty($row['albumArtUrl'])) {
                if (preg_match('/^https?:\/\//i', $row['albumArtUrl'])) {
                    $processed_album_art_url = $row['albumArtUrl']; 
                } else {
                    $path_from_db_art = ltrim($row['albumArtUrl'], '/');
                    $processed_album_art_url = $baseFileAccessUrl . '/' . $path_from_db_art;
                }
                error_log("songs.php - albumArtUrl procesada para ID " . ($row['id'] ?? 'N/A') . ": " . ($processed_album_art_url ?? 'N/A'));
            }

            $processed_audio_url = null;
            if (isset($row['audioUrl']) && !empty($row['audioUrl'])) {
                if (preg_match('/^https?:\/\//i', $row['audioUrl'])) {
                    $processed_audio_url = $row['audioUrl']; 
                } else {
                    $path_from_db_audio = ltrim($row['audioUrl'], '/');
                    $processed_audio_url = $baseFileAccessUrl . '/' . $path_from_db_audio;
                }
                error_log("songs.php - audioUrl procesada para ID " . ($row['id'] ?? 'N/A') . ": " . ($processed_audio_url ?? 'N/A'));
            }
            
            $song_item = [
                'id' => (int) ($row['id'] ?? 0),
                'title' => $row['title'] ?? 'Título Desconocido',
                'artist' => $row['artist'] ?? 'Artista Desconocido',
                'featuredArtists' => $row['featuredArtists'] ?? null,
                'genre' => $row['genre'] ?? null,
                'albumArtUrl' => $processed_album_art_url,
                'albumArt' => $processed_album_art_url, 
                'audioUrl' => $processed_audio_url, 
                'duration' => $row['duration'] ?? '0:00',
                'userId' => isset($row['userId']) ? (int)$row['userId'] : null,
                'createdAt' => $row['createdAt'] ?? null
            ];
            $songs_list_for_json[] = $song_item;
        }
        $result->free();
        
        http_response_code(200);
        error_log("songs.php - Enviando respuesta JSON: " . print_r($songs_list_for_json, true));
        echo json_encode($songs_list_for_json);

    } else {
        error_log("songs.php - Error en la consulta SQL: " . $db->error);
        throw new Exception("Error al ejecutar la consulta de canciones: " . $db->error);
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error CRÍTICO en songs.php: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString());
    echo json_encode(['error' => 'Ocurrió un error al obtener las canciones.', 'details_server' => $e->getMessage()]);
} finally {
    if ($db instanceof mysqli) {
        $db->close();
        error_log("songs.php - Conexión a BD cerrada.");
    }
}
?>