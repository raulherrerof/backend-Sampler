<?php
// sampler-backend/api/songs.php

// --- HABILITAR LOGS (SOLO PARA DESARROLLO) ---
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php_debug.log');
error_reporting(E_ALL);
error_log("--- Ejecutando songs.php ---");
// --- FIN HABILITAR LOGS ---

// 1. INCLUIR CABECERAS CORS PRIMERO
require_once __DIR__ . '/../config/cors_headers.php';

// Iniciar sesión si no está activa para poder verificar el estado de "like" del usuario
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 3. INCLUIR CONEXIÓN A BD
require_once __DIR__ . '/../config/db_connection.php';

// 4. ESTABLECER CONTENT-TYPE PARA LA RESPUESTA JSON
header('Content-Type: application/json');

$db = null;
$songs_list_for_json = [];

// Determinar si hay un usuario logueado para personalizar la respuesta de "likes"
$current_user_id_for_likes = null;
if (isset($_SESSION['user_id'])) {
    $current_user_id_for_likes = (int)$_SESSION['user_id'];
    error_log("songs.php - Usuario logueado detectado. ID: " . $current_user_id_for_likes);
} else {
    error_log("songs.php - No hay usuario logueado (para likes).");
}

try {
    $db = connect();

    // Consulta SQL Modificada para incluir conteo de likes y si el usuario actual le dio like
    $sql = "
        SELECT 
            a.id, a.title, a.artist, a.featuredArtists, a.genre, 
            a.albumArtUrl, a.audioUrl, a.duration, a.userId, 
            a.fecha_subida AS createdAt,
            (SELECT COUNT(*) FROM song_likes sl_count WHERE sl_count.song_id = a.id) AS likeCount";

    // Si hay un usuario logueado, añadimos la subconsulta para 'userHasLiked'
    // Es importante usar un placeholder (?) aquí y luego hacer bind_param si es necesario.
    // Para EXISTS, no se necesita un SELECT de valor, solo la condición.
    if ($current_user_id_for_likes !== null) {
        $sql .= ", (EXISTS(SELECT 1 FROM song_likes sl_user WHERE sl_user.song_id = a.id AND sl_user.user_id = ?)) AS userHasLiked";
    } else {
        // Si no hay usuario logueado, el campo userHasLiked siempre será falso.
        // Usamos CAST para asegurar que sea interpretado como booleano en JSON si es posible.
        $sql .= ", CAST(0 AS UNSIGNED) AS userHasLiked"; // O false, pero CAST(0 AS BOOLEAN) o CAST(0 AS UNSIGNED) es más SQL-estándar
    }
    
    $sql .= " FROM audios a ORDER BY a.fecha_subida DESC";
    
    error_log("songs.php - SQL a ejecutar: " . preg_replace('/\s+/', ' ', $sql)); // Loguear SQL (sin datos sensibles si hay placeholders)

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        error_log("songs.php - Error al preparar la consulta SQL: " . $db->error);
        throw new Exception("Error al preparar la consulta de canciones: " . $db->error);
    }

    // Hacer bind_param si la consulta lo requiere (si hay usuario logueado)
    if ($current_user_id_for_likes !== null) {
        $stmt->bind_param("i", $current_user_id_for_likes);
        error_log("songs.php - Parámetro enlazado para userHasLiked: " . $current_user_id_for_likes);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        error_log("songs.php - Consulta SQL ejecutada. Filas obtenidas: " . $result->num_rows);

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $webRootPathForUploads = '/backend-Sampler'; // AJUSTA SI TU CARPETA BACKEND NO ES 'backend-Sampler' en la URL
        $baseFileAccessUrl = rtrim($protocol . $host . $webRootPathForUploads, '/');
        // error_log("songs.php - BaseFileAccessUrl: " . $baseFileAccessUrl); // Ya lo tienes

        while ($row = $result->fetch_assoc()) {
            // error_log("songs.php - Procesando fila ID: " . ($row['id'] ?? 'N/A') . ", audioUrl de BD: " . ($row['audioUrl'] ?? 'N/A')); // Ya lo tienes

            $processed_album_art_url = null;
            if (isset($row['albumArtUrl']) && !empty($row['albumArtUrl'])) {
                if (preg_match('/^https?:\/\//i', $row['albumArtUrl'])) {
                    $processed_album_art_url = $row['albumArtUrl'];
                } else {
                    $path_from_db_art = ltrim($row['albumArtUrl'], '/');
                    $processed_album_art_url = $baseFileAccessUrl . '/' . $path_from_db_art;
                }
                // error_log("songs.php - albumArtUrl procesada para ID " . ($row['id'] ?? 'N/A') . ": " . ($processed_album_art_url ?? 'N/A'));
            }

            $processed_audio_url = null;
            if (isset($row['audioUrl']) && !empty($row['audioUrl'])) {
                if (preg_match('/^https?:\/\//i', $row['audioUrl'])) {
                    $processed_audio_url = $row['audioUrl'];
                } else {
                    $path_from_db_audio = ltrim($row['audioUrl'], '/');
                    $processed_audio_url = $baseFileAccessUrl . '/' . $path_from_db_audio;
                }
                // error_log("songs.php - audioUrl procesada para ID " . ($row['id'] ?? 'N/A') . ": " . ($processed_audio_url ?? 'N/A'));
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
                // 'duration' => $row['duration'] ?? '0:00', // Cambiado abajo
                'duration' => isset($row['duration']) ? (int)$row['duration'] : 0, // Asegurar entero o 0
                'userId' => isset($row['userId']) ? (int)$row['userId'] : null,
                'createdAt' => $row['createdAt'] ?? null,
                'likeCount' => isset($row['likeCount']) ? (int)$row['likeCount'] : 0, // Nuevo
                'userHasLiked' => isset($row['userHasLiked']) ? (bool)$row['userHasLiked'] : false // Nuevo, convertir a booleano
            ];
            $songs_list_for_json[] = $song_item;
        }
        $result->free();
        $stmt->close(); // Cerrar el statement aquí
        
        http_response_code(200);
        // error_log("songs.php - Enviando respuesta JSON: " . print_r($songs_list_for_json, true)); // Ya lo tienes
        echo json_encode($songs_list_for_json);

    } else {
        // Este bloque podría no alcanzarse si $db->query() lanza una excepción o devuelve false y el error se captura antes.
        // Si $stmt se preparó pero $stmt->execute() falló (y $stmt->get_result() devuelve false).
        if ($stmt) $stmt->close(); // Asegurarse de cerrar el statement si se abrió.
        $db_error = $db->error ?: ($stmt ? $stmt->error : "Error desconocido en la consulta.");
        error_log("songs.php - Error en la consulta SQL (después de execute/get_result): " . $db_error);
        throw new Exception("Error al ejecutar la consulta de canciones: " . $db_error);
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error CRÍTICO en songs.php: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString());
    echo json_encode(['error' => 'Ocurrió un error al obtener las canciones.', 'details_server_message' => $e->getMessage()]);
} finally {
    if ($db instanceof mysqli) {
        $db->close();
        error_log("songs.php - Conexión a BD cerrada.");
    }
}
?>