<?php
// sampler-backend/api/songs.php

// 1. INCLUIR CABECERAS CORS PRIMERO Y ANTES DE CUALQUIER OTRA SALIDA
require_once __DIR__ . '/../config/cors_headers.php'; // Asume que este archivo está configurado correctamente

// 2. SESSION (Opcional aquí, ya que listar canciones podría ser público)
// if (session_status() == PHP_SESSION_NONE) {
//     session_start();
// }

// 3. INCLUIR CONEXIÓN A BD
require_once __DIR__ . '/../config/db_connection.php'; // Asume que define la función connect()

// 4. ESTABLECER CONTENT-TYPE PARA LA RESPUESTA JSON
header('Content-Type: application/json');

$db = null; // Inicializar para el bloque finally
$songs_list = [];

try {
    $db = connect(); // Obtener la conexión a la base de datos
    if (!$db) {
        // connect() ya hace die() o debería lanzar una excepción si no puede conectar,
        // pero como medida de seguridad adicional:
        throw new Exception("No se pudo establecer la conexión a la base de datos.");
    }

    // Consulta para obtener todas las canciones.
    // ¡¡¡ASEGÚRATE DE QUE 'songs' SEA EL NOMBRE CORRECTO DE TU TABLA!!!
    // ¡¡¡Y QUE LAS COLUMNAS 'id', 'title', 'artist', 'albumArtUrl', 'audioUrl', 'duration', 'createdAt' EXISTAN!!!
    // Tu App.jsx espera estos campos. El upload_audio.php también debería insertar con estos nombres.
    $sql = "SELECT id, title, artist, featuredArtists, genre, albumArtUrl, audioUrl, duration, userId, createdAt 
            FROM songs 
            ORDER BY createdAt DESC";
    
    $result = $db->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Lógica para construir URLs completas si es necesario
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
            $host = $_SERVER['HTTP_HOST'];
            
            // Asume que las URLs en la BD son relativas a la raíz de tu servidor web
            // donde se sirven los archivos, por ejemplo: /uploads/covers/imagen.jpg
            // Si ya son URLs completas en la BD, esta lógica no es necesaria.

            if (isset($row['albumArtUrl']) && $row['albumArtUrl'] && !preg_match('/^https?:\/\//i', $row['albumArtUrl'])) {
                $row['albumArtUrl'] = $protocol . $host . (strpos($row['albumArtUrl'], '/') === 0 ? '' : '/') . ltrim($row['albumArtUrl'], '/');
            }
            if (isset($row['audioUrl']) && $row['audioUrl'] && !preg_match('/^https?:\/\//i', $row['audioUrl'])) {
                $row['audioUrl'] = $protocol . $host . (strpos($row['audioUrl'], '/') === 0 ? '' : '/') . ltrim($row['audioUrl'], '/');
            }
            
            // Asegurar que los campos esperados por el frontend estén presentes, incluso si son null
            $song_item = [
                'id' => $row['id'],
                'title' => $row['title'] ?? 'Título Desconocido',
                'artist' => $row['artist'] ?? 'Artista Desconocido',
                'featuredArtists' => $row['featuredArtists'] ?? null,
                'genre' => $row['genre'] ?? null,
                'albumArtUrl' => $row['albumArtUrl'] ?? null, // Frontend espera esto
                'albumArt' => $row['albumArtUrl'] ?? null,    // Para compatibilidad con el SongPlayer que me pasaste
                'audioUrl' => $row['audioUrl'] ?? null,
                'duration' => $row['duration'] ?? '0:00',
                'userId' => $row['userId'] ?? null,
                'createdAt' => $row['createdAt'] ?? null
            ];
            $songs_list[] = $song_item;
        }
        $result->free();
        
        http_response_code(200);
        // Tu App.jsx tiene: setSongs(fetchedSongs.songs || fetchedSongs);
        // Devolver un array directamente es más simple y compatible con '|| fetchedSongs'.
        echo json_encode($songs_list); 

    } else {
        // Error en la consulta SQL
        throw new Exception("Error al ejecutar la consulta de canciones: " . $db->error);
    }

} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    error_log("Error en songs.php: " . $e->getMessage()); // Loguear el error en el servidor
    echo json_encode(['error' => 'Ocurrió un error al obtener las canciones.', 'details_server' => $e->getMessage()]);
} finally {
    if ($db instanceof mysqli) { // Verificar si $db es un objeto mysqli antes de llamar a close
        $db->close();
    }
}
?>