<?php
// sampler-backend/api/songs.php

ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php_songs_debug.log'); 
error_reporting(E_ALL);
error_log("--- Ejecutando songs.php ---");

require_once __DIR__ . '/../config/cors_headers.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db_connection.php';
header('Content-Type: application/json');

$db = null;
$songs_list_for_json = [];
$current_user_id_for_likes_and_comments = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

error_log("songs.php - User ID para likes/comments: " . ($current_user_id_for_likes_and_comments ?? 'N/A'));

try {
    $db = connect();
    if (!$db) {
        throw new Exception("DB connect() falló.");
    }
    error_log("songs.php - Conexión a BD establecida.");

    $sql_songs = "
        SELECT 
            a.id, a.title, a.artist, a.featuredArtists, a.genre, 
            a.albumArtUrl, a.audioUrl, a.duration, a.userId, 
            a.fecha_subida AS createdAt,
            (SELECT COUNT(*) FROM song_likes sl_count WHERE sl_count.song_id = a.id) AS likeCount";

    if ($current_user_id_for_likes_and_comments !== null) {
        $sql_songs .= ", (EXISTS(SELECT 1 FROM song_likes sl_user WHERE sl_user.song_id = a.id AND sl_user.user_id = ?)) AS userHasLiked";
    } else {
        $sql_songs .= ", 0 AS userHasLiked";
    }
    
    $sql_songs .= " FROM audios a ORDER BY a.fecha_subida DESC"; 
    
    error_log("songs.php - SQL principal: " . preg_replace('/\s+/', ' ', $sql_songs));

    $stmt_songs = $db->prepare($sql_songs);
    if (!$stmt_songs) {
        throw new Exception("Error preparando consulta de canciones: " . $db->error);
    }

    if ($current_user_id_for_likes_and_comments !== null) {
        $stmt_songs->bind_param("i", $current_user_id_for_likes_and_comments);
    }

    $stmt_songs->execute();
    $result_songs = $stmt_songs->get_result();

    if ($result_songs) {
        error_log("songs.php - Consulta de canciones ejecutada. Filas: " . $result_songs->num_rows);

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $webRootPathForUploads = '/backend-Sampler'; // AJUSTA SI ES NECESARIO
        $baseFileAccessUrl = rtrim($protocol . $host . $webRootPathForUploads, '/');
        // error_log("songs.php - BaseFileAccessUrl: " . $baseFileAccessUrl); // Puedes descomentar para depurar URLs

        while ($song_row = $result_songs->fetch_assoc()) {
            $song_id = (int) ($song_row['id'] ?? 0);
            // error_log("songs.php - Procesando audio ID: " . $song_id);

            $processed_album_art_url = $song_row['albumArtUrl'] ?? null;
            if ($processed_album_art_url && !preg_match('/^https?:\/\//i', $processed_album_art_url)) {
                $processed_album_art_url = $baseFileAccessUrl . '/' . ltrim($song_row['albumArtUrl'], '/');
            }
            $processed_audio_url = $song_row['audioUrl'] ?? null;
            if ($processed_audio_url && !preg_match('/^https?:\/\//i', $processed_audio_url)) {
                $processed_audio_url = $baseFileAccessUrl . '/' . ltrim($song_row['audioUrl'], '/');
            }
            // error_log("songs.php - audioUrl PROCESADA para ID {$song_id}: '{$processed_audio_url}'");


            // --- OBTENER COMENTARIOS PARA ESTA CANCIÓN ---
            $song_comments_array = [];
            // Modificado: Se quita u.profilePicUrl ya que no existe en tu tabla usuarios
            $sql_comments = "
                SELECT c.id, c.comment_text, c.created_at, 
                       u.id AS user_id_comment, u.usuario AS user_name_comment 
                       -- u.profilePicUrl AS user_profile_pic_url_comment -- ELIMINADO
                FROM comments c 
                JOIN usuarios u ON c.user_id = u.id
                WHERE c.song_id = ? 
                ORDER BY c.created_at DESC
            ";
            $stmt_comments = $db->prepare($sql_comments);
            if($stmt_comments){
                $stmt_comments->bind_param("i", $song_id);
                $stmt_comments->execute();
                $result_comments = $stmt_comments->get_result();
                while($comment_row = $result_comments->fetch_assoc()){
                    $song_comments_array[] = [
                        'id' => (int)$comment_row['id'],
                        'text' => $comment_row['comment_text'],
                        'createdAt' => $comment_row['created_at'],
                        'user' => [
                            'id' => (int)$comment_row['user_id_comment'],
                            'name' => $comment_row['user_name_comment'], // Usa 'usuario' de tu tabla usuarios
                            'profilePicUrl' => null // Devolver null o quitar si el frontend lo maneja
                        ]
                    ];
                }
                $stmt_comments->close();
                // error_log("songs.php - ID: {$song_id}, Comentarios obtenidos: " . count($song_comments_array));
            } else {
                error_log("songs.php - Error preparando consulta de comentarios para song_id {$song_id}: " . $db->error);
            }
            // --- FIN OBTENER COMENTARIOS ---
            
            $song_item = [
                'id' => $song_id,
                'title' => $song_row['title'] ?? 'Título Desconocido',
                'artist' => $song_row['artist'] ?? 'Artista Desconocido',
                'featuredArtists' => $song_row['featuredArtists'] ?? null,
                'genre' => $song_row['genre'] ?? null,
                'albumArtUrl' => $processed_album_art_url,
                'albumArt' => $processed_album_art_url, 
                'audioUrl' => $processed_audio_url, 
                'duration' => $song_row['duration'] ?? '0:00', 
                'userId' => isset($song_row['userId']) ? (int)$song_row['userId'] : null,
                'createdAt' => $song_row['createdAt'] ?? null,
                'likeCount' => isset($song_row['likeCount']) ? (int)$song_row['likeCount'] : 0,
                'userHasLiked' => isset($song_row['userHasLiked']) ? (bool)$song_row['userHasLiked'] : false,
                'comments' => $song_comments_array 
            ];
            $songs_list_for_json[] = $song_item;
        }
        $result_songs->free();
        $stmt_songs->close(); 
        
        http_response_code(200);
        // error_log("songs.php - Enviando respuesta JSON final (primer elemento si existe): " . (isset($songs_list_for_json[0]) ? print_r($songs_list_for_json[0], true) : "Array vacío"));
        echo json_encode($songs_list_for_json);

    } else {
        if ($stmt_songs) $stmt_songs->close();
        $db_error = $db->error ?: ($stmt_songs ? $stmt_songs->error : "Error desconocido en la consulta de canciones.");
        throw new Exception("Error al ejecutar/obtener resultado de canciones: " . $db_error);
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error CRÍTICO en songs.php: " . $e->getMessage() . " - Archivo: " . $e->getFile() . " - Línea: " . $e->getLine()); // Quitado Trace para brevedad del log
    echo json_encode(['error' => 'Ocurrió un error al obtener las canciones.', 'details_server_message' => $e->getMessage()]);
} finally {
    if ($db instanceof mysqli) {
        $db->close();
        // error_log("songs.php - Conexión a BD cerrada."); // Puedes descomentar para verificar
    }
}
?>