<?php
// api/add_comment.php

// --- CONFIGURACIÓN DE ERRORES Y LOGS (AL PRINCIPIO) ---
ini_set('display_errors', 0); // No mostrar errores de PHP al cliente
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php_comments_activity.log'); // Log específico para este script
error_reporting(E_ALL);
// Descomenta la siguiente línea para loguear cada ejecución si lo necesitas para depurar
// error_log("--- add_comment.php: INICIO --- " . date("Y-m-d H:i:s") . " - Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'N/A'));

// 1. Incluir configuración de CORS
require_once __DIR__ . '/../config/cors_headers.php'; 

// 2. Iniciar sesión DESPUÉS de CORS
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 3. Incluir el archivo que define la función connect()
require_once __DIR__ . '/../config/db_connection.php';

// 4. Configuración de header JSON (ANTES de cualquier posible echo)
if (!headers_sent()) {
    header('Content-Type: application/json');
}

// --- INICIO DEL CÓDIGO PRINCIPAL DEL SCRIPT ---
$conn = null; 
$transaction_active = false; 

try {
    // Verificar autenticación del usuario
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401); // Unauthorized
        echo json_encode(['success' => false, 'error' => 'Debes iniciar sesión para comentar.']);
        exit;
    }
    $user_id_session = (int)$_SESSION['user_id'];

    // Verificar método de la petición
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405); // Method Not Allowed
        echo json_encode(['success' => false, 'error' => 'Método no permitido.']);
        exit;
    }

    // Obtener y decodificar datos JSON del cuerpo de la solicitud
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE); // TRUE para array asociativo

    // Validar datos de entrada
    if (!$input || !isset($input['song_id']) || !isset($input['comment_text'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'error' => 'Faltan datos: se requiere song_id y comment_text.']);
        exit;
    }

    $song_id = filter_var($input['song_id'], FILTER_VALIDATE_INT);
    $comment_text = trim($input['comment_text']);

    if ($song_id === false || $song_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID de canción inválido.']);
        exit;
    }
    if (empty($comment_text)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'El comentario no puede estar vacío.']);
        exit;
    }
    if (mb_strlen($comment_text) > 1000) { // mb_strlen para multi-byte
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'El comentario es demasiado largo (máx 1000 caracteres).']);
        exit;
    }

    $conn = connect(); // Obtener conexión a la BD
    if (!$conn) {
        throw new Exception("Fallo al obtener la conexión a la base de datos desde connect().");
    }

    if (!$conn->begin_transaction()) {
        throw new Exception("No se pudo iniciar la transacción: " . $conn->error);
    }
    $transaction_active = true;

    // 1. Insertar el nuevo comentario
    // Asume que tu tabla de comentarios se llama 'comments' y la de canciones 'audios' (para la FK)
    $stmt_insert = $conn->prepare(
        "INSERT INTO comments (song_id, user_id, comment_text, created_at) 
         VALUES (?, ?, ?, NOW())"
    );
    if (!$stmt_insert) {
        throw new Exception("Error en prepare (insert comentario): " . $conn->error);
    }
    $stmt_insert->bind_param("iis", $song_id, $user_id_session, $comment_text);
    if (!$stmt_insert->execute()) {
        throw new Exception("Error en execute (insert comentario): " . $stmt_insert->error);
    }
    $new_comment_id = $conn->insert_id;
    $stmt_insert->close();

    // 2. Obtener el comentario recién insertado CON la información del usuario
    $stmt_get_comment = $conn->prepare("
        SELECT 
            c.id, 
            c.comment_text AS text, 
            c.created_at, 
            u.id AS user_db_id, 
            u.usuario AS user_name,      -- Columna 'usuario' de tu tabla 'usuarios' para el nombre
            u.profilePicUrl AS user_db_profile_pic_url -- Columna de foto de perfil en 'usuarios'
        FROM comments c
        JOIN usuarios u ON c.user_id = u.id
        WHERE c.id = ?
    ");
    if (!$stmt_get_comment) {
        throw new Exception("Error en prepare (get comentario insertado): " . $conn->error);
    }
    $stmt_get_comment->bind_param("i", $new_comment_id);
    if (!$stmt_get_comment->execute()) {
        throw new Exception("Error en execute (get comentario insertado): " . $stmt_get_comment->error);
    }
    $result = $stmt_get_comment->get_result();
    $comment_data_from_db = $result->fetch_assoc();
    $stmt_get_comment->close();

    if (!$comment_data_from_db) {
        throw new Exception("No se pudo recuperar el comentario después de guardarlo (ID: $new_comment_id).");
    }

    if (!$conn->commit()) {
        throw new Exception("No se pudo confirmar la transacción (commit): " . $conn->error);
    }
    $transaction_active = false;

    // Construir URL completa para profilePicUrl
    $fullProfilePicUrl = null;
    if (!empty($comment_data_from_db['user_db_profile_pic_url'])) {
        if (preg_match('/^https?:\/\//i', $comment_data_from_db['user_db_profile_pic_url'])) {
            $fullProfilePicUrl = $comment_data_from_db['user_db_profile_pic_url'];
        } else {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
            $host = $_SERVER['HTTP_HOST'];
            // !!! AJUSTA ESTA RUTA SI LA RAÍZ DE TU PROYECTO BACKEND EN LA URL ES DIFERENTE !!!
            $backendWebPath = '/backend-Sampler'; 
            $baseAppUrl = rtrim($protocol . $host . $backendWebPath, '/');
            $fullProfilePicUrl = $baseAppUrl . '/' . ltrim($comment_data_from_db['user_db_profile_pic_url'], '/');
        }
    }

    $formatted_comment_for_frontend = [
        'id' => (int)$comment_data_from_db['id'],
        'text' => $comment_data_from_db['text'],
        'createdAt' => $comment_data_from_db['created_at'], // El frontend espera 'createdAt'
        'user' => [
            'id' => (int)$comment_data_from_db['user_db_id'],
            'name' => $comment_data_from_db['user_name'],     // El frontend espera 'name'
            'profilePicUrl' => $fullProfilePicUrl          // URL Completa
        ]
    ];

    http_response_code(201); // Created
    echo json_encode(['success' => true, 'message' => 'Comentario añadido exitosamente.', 'comment' => $formatted_comment_for_frontend]);

} catch (Exception $e) { 
    error_log("[add_comment.php] Exception: " . $e->getMessage() .
              ($conn instanceof mysqli && $conn->error ? " | SQL Error: " . $conn->error . " (Código: " . $conn->errno . ")" : ""));

    if ($conn instanceof mysqli && $transaction_active) {
        $conn->rollback();
        error_log("[add_comment.php] Transacción revertida debido a excepción.");
    }
    // Evitar enviar detalles de error de BD sensibles al cliente en producción
    $client_error_message = 'Ocurrió un error en el servidor al procesar su comentario. Inténtalo de nuevo.';
    // if (ENTORNO_DESARROLLO) { $client_error_message = $e->getMessage(); } // Opcional para desarrollo
    http_response_code(500); 
    echo json_encode(['success' => false, 'error' => $client_error_message]);

} finally {
    if (isset($stmt_insert) && $stmt_insert instanceof mysqli_stmt) $stmt_insert->close();
    if (isset($stmt_get_comment) && $stmt_get_comment instanceof mysqli_stmt) $stmt_get_comment->close();
    if ($conn instanceof mysqli) { 
        $conn->close();
    }
    // error_log("--- add_comment.php: FIN ---"); // Descomenta para depurar fin de script
}
?>