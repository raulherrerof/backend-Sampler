<?php

require_once __DIR__ . '/../config/cors_headers.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db_connection.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado. Debes iniciar sesión.']);
    exit;
}
$current_user_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

$data = json_decode(file_get_contents("php://input"));

if (!$data || !isset($data->song_id) || !is_numeric($data->song_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de canción inválido o no proporcionado.']);
    exit;
}
$song_id = (int)$data->song_id;

$db = connect();
$userHasLiked = false;
$newLikeCount = 0;

try {
    // Verificar si el usuario ya le dio like
    $stmt_check = $db->prepare("SELECT id FROM song_likes WHERE user_id = ? AND song_id = ?");
    if (!$stmt_check) throw new Exception("Error al preparar la verificación de like: " . $db->error);
    $stmt_check->bind_param("ii", $current_user_id, $song_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $existing_like = $result_check->fetch_assoc();
    $stmt_check->close();

    if ($existing_like) {
        // Ya existe un like, entonces lo quitamos (unlike)
        $stmt_delete = $db->prepare("DELETE FROM song_likes WHERE id = ?");
        if (!$stmt_delete) throw new Exception("Error al preparar la eliminación de like: " . $db->error);
        $stmt_delete->bind_param("i", $existing_like['id']);
        $stmt_delete->execute();
        $stmt_delete->close();
        $userHasLiked = false; // El usuario ya no le gusta
    } else {
        // No existe un like, entonces lo añadimos (like)
        $stmt_insert = $db->prepare("INSERT INTO song_likes (user_id, song_id) VALUES (?, ?)");
        if (!$stmt_insert) throw new Exception("Error al preparar la inserción de like: " . $db->error);
        $stmt_insert->bind_param("ii", $current_user_id, $song_id);
        $stmt_insert->execute();
        $stmt_insert->close();
        $userHasLiked = true; // Al usuario ahora le gusta
    }

    // Obtener el nuevo conteo total de likes para la canción
    $stmt_count = $db->prepare("SELECT COUNT(*) as like_count FROM song_likes WHERE song_id = ?");
    if (!$stmt_count) throw new Exception("Error al preparar el conteo de likes: " . $db->error);
    $stmt_count->bind_param("i", $song_id);
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();
    $count_data = $result_count->fetch_assoc();
    $newLikeCount = $count_data ? (int)$count_data['like_count'] : 0;
    $stmt_count->close();

    http_response_code(200);
    echo json_encode([
        'message' => $userHasLiked ? 'Like añadido.' : 'Like removido.',
        'songId' => $song_id,
        'userHasLiked' => $userHasLiked,
        'likeCount' => $newLikeCount
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error en toggle_like.php: " . $e->getMessage());
    echo json_encode(['error' => 'Ocurrió un error al procesar el "Me Gusta".', 'details' => $e->getMessage()]);
} finally {
    if ($db instanceof mysqli) {
        $db->close();
    }
}
?>