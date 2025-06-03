<?php
// sampler-backend/api/songs.php

// 1. INCLUIR CABECERAS CORS PRIMERO Y ANTES DE CUALQUIER OTRA SALIDA
require_once __DIR__ . '/../config/cors_headers.php';

// 2. SESSION (Opcional aquí, ya que listar canciones podría ser público)
// if (session_status() == PHP_SESSION_NONE) {
//     session_start();
// }

// 3. INCLUIR CONEXIÓN A BD
require_once __DIR__ . '/../config/db_connection.php'; // Asume que define la función connect()

// 4. ESTABLECER CONTENT-TYPE PARA LA RESPUESTA JSON
header('Content-Type: application/json');

$db = null;
$songs_list = [];

try {
    $db = connect(); // Obtener la conexión a la base de datos
    // connect() debería manejar el fallo de conexión y salir, o lanzar una excepción.
    // Si connect() puede devolver false/null sin salir, la siguiente verificación es útil.
    if (!$db) {
        throw new Exception("No se pudo establecer la conexión a la base de datos.");
    }

    // Consulta para obtener todas las canciones.
    // Usamos la tabla 'audios' y la columna 'fecha_subida' con un alias.
    $sql = "SELECT id, title, artist, featuredArtists, genre, albumArtUrl, audioUrl, duration, userId, fecha_subida AS createdAt
            FROM audios
            ORDER BY fecha_subida DESC"; // Ordenar por la columna real de la BD
    
    $result = $db->query($sql);

    if ($result) {
        // Determinar la URL base del servidor/proyecto para construir URLs absolutas
        // Esto asume que tus archivos de audio/covers están servidos desde el mismo dominio/path que tu API PHP.
        // Si están en un CDN o un dominio diferente, esta lógica necesitará cambiar.
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        // Asumiendo que tu backend está en una subcarpeta como /backend-Sampler/
        // Si tu backend está en la raíz del dominio, $projectBasePath podría ser "" o "/"
        // Ajusta esto según la estructura de URL de tu proyecto.
        $projectBasePath = '/backend-Sampler'; // Cambia 'backend-Sampler' al nombre de tu carpeta de proyecto en htdocs
        $baseUrl = rtrim($protocol . $host . rtrim($projectBasePath, '/'), '/');


        while ($row = $result->fetch_assoc()) {
            $processed_album_art_url = null;
            if (isset($row['albumArtUrl']) && !empty($row['albumArtUrl'])) {
                if (preg_match('/^https?:\/\//i', $row['albumArtUrl'])) {
                    $processed_album_art_url = $row['albumArtUrl']; // Ya es una URL absoluta
                } else {
                    // Asume que $row['albumArtUrl'] es como 'uploads/covers/imagen.jpg'
                    $processed_album_art_url = $baseUrl . '/' . ltrim($row['albumArtUrl'], '/');
                }
            }

            $processed_audio_url = null;
            if (isset($row['audioUrl']) && !empty($row['audioUrl'])) {
                if (preg_match('/^https?:\/\//i', $row['audioUrl'])) {
                    $processed_audio_url = $row['audioUrl']; // Ya es una URL absoluta
                } else {
                    // Asume que $row['audioUrl'] es como 'uploads/audio/track.mp3'
                    $processed_audio_url = $baseUrl . '/' . ltrim($row['audioUrl'], '/');
                }
            }
            
            $song_item = [
                'id' => (int) $row['id'],
                'title' => $row['title'] ?? 'Título Desconocido',
                'artist' => $row['artist'] ?? 'Artista Desconocido',
                'featuredArtists' => $row['featuredArtists'] ?? null,
                'genre' => $row['genre'] ?? null,
                'albumArtUrl' => $processed_album_art_url,  // URL procesada
                'albumArt' => $processed_album_art_url,     // Para compatibilidad con SongPlayer
                'audioUrl' => $processed_audio_url,       // URL procesada
                'duration' => isset($row['duration']) ? (int)$row['duration'] : 0, // Entero (segundos) o 0
                'userId' => isset($row['userId']) ? (int)$row['userId'] : null,
                'createdAt' => $row['createdAt'] ?? null // Viene del alias 'fecha_subida AS createdAt'
            ];
            $songs_list[] = $song_item;
        }
        $result->free();
        
        http_response_code(200);
        echo json_encode($songs_list); // Devolver directamente el array de canciones

    } else {
        // Error en la consulta SQL
        throw new Exception("Error al ejecutar la consulta de canciones: " . $db->error);
    }

} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    error_log("Error en songs.php: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString()); // Loguear más detalles
    echo json_encode(['error' => 'Ocurrió un error al obtener las canciones.', 'details_server' => $e->getMessage()]);
} finally {
    if ($db instanceof mysqli) {
        $db->close();
    }
}
?>