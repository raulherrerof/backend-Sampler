<?php
// api/upload_audio.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';

// --- VERIFICAR SI EL USUARIO ESTÁ LOGUEADO ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'No autorizado. Debes iniciar sesión para subir archivos.']);
    exit;
}

// Obtener el ID del usuario de la sesión
$idUsuarioSubida = $_SESSION['user_id'];

$db = connect();
// ... (resto de tu lógica de subida de archivos, como la tenías antes) ...

// Al insertar en la BD, ahora puedes incluir $idUsuarioSubida:
// $stmt = $db->prepare("INSERT INTO archivos_audio (..., id_usuario_subida) VALUES (..., ?)");
// $stmt->bind_param("...i", ..., $idUsuarioSubida);

// ...
?>