<?php
// api/add_comment.php

// 1. Incluir configuración de CORS y arrancar sesión
require_once __DIR__ . '/../config/cors_headers.php'; // Asumo que este archivo solo pone headers
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Incluir el archivo que define la función connect()
require_once __DIR__ . '/../config/db_connection.php';

// 3. Configuración de errores y headers JSON
error_reporting(E_ALL);
ini_set('display_errors', 1); // Desarrollo: ON, Producción: OFF (loguear errores en su lugar)

header('Content-Type: application/json');
// Bloque de headers CORS para localhost:3000 y manejo de OPTIONS
if (isset($_SERVER['HTTP_ORIGIN']) && ($_SERVER['HTTP_ORIGIN'] == 'http://localhost:3000' || $_SERVER['HTTP_ORIGIN'] == 'http://127.0.0.1:3000')) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS'); // Asegúrate que OPTIONS esté aquí
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
}

// Manejo de la pre-solicitud OPTIONS (importante para CORS con POST y Content-Type: application/json)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204); // No Content (o 200 OK)
    exit();
}

// --- INICIO DEL CÓDIGO PRINCIPAL DEL SCRIPT ---
$conn = null; // Inicializar $conn para el bloque finally
$transaction_active = false; // Bandera para el estado de la transacción

try {
    // ***** LLAMAR A LA FUNCIÓN PARA OBTENER LA CONEXIÓN *****
    $conn = connect(); // Esta función debe lanzar una excepción si falla

    // Verificar autenticación del usuario
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Debes iniciar sesión para comentar.']);
        exit; // Salir si no está autenticado
    }

    // Obtener datos JSON del cuerpo de la solicitud
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);

    // Validar datos de entrada
    if (!$input || !isset($input['song_id']) || !isset($input['comment_text'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Faltan datos: se requiere song_id y comment_text.']);
        exit;
    }

    $song_id = filter_var($input['song_id'], FILTER_VALIDATE_INT);
    $comment_text = trim($input['comment_text']);
    $user_id_session = (int)$_SESSION['user_id']; // Asegurar que user_id sea entero

    if ($song_id === false || $song_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'ID de canción inválido.']);
        exit;
    }
    if (empty($comment_text)) {
        http_response_code(400);
        echo json_encode(['error' => 'El comentario no puede estar vacío.']);
        exit;
    }
    if (mb_strlen($comment_text) > 1000) { // mb_strlen para multi-byte
        http_response_code(400);
        echo json_encode(['error' => 'El comentario es demasiado largo (máx 1000 caracteres).']);
        exit;
    }

    // Iniciar transacción
    if (!$conn->begin_transaction()) {
        throw new Exception("No se pudo iniciar la transacción.");
    }
    $transaction_active = true;
    error_log("[add_comment.php] Transacción iniciada.");

    // 1. Insertar el nuevo comentario
    $stmt_insert = $conn->prepare(
        "INSERT INTO comments (song_id, user_id, comment_text, created_at) 
         VALUES (?, ?, ?, NOW())"
    );
    if (!$stmt_insert) {
        throw new Exception("Error en prepare (insert): " . $conn->error . " (Código: " . $conn->errno . ")");
    }
    $stmt_insert->bind_param("iis", $song_id, $user_id_session, $comment_text);
    if (!$stmt_insert->execute()) {
        throw new Exception("Error en execute (insert): " . $stmt_insert->error . " (Código: " . $stmt_insert->errno . ")");
    }
    $new_comment_id = $conn->insert_id;
    $stmt_insert->close();
    error_log("[add_comment.php] Comentario insertado. Nuevo ID: $new_comment_id");


    // 2. Obtener el comentario recién insertado CON la información del usuario
    //    (Se movió la numeración, antes era el paso 3)
    $stmt_get_comment = $conn->prepare("
        SELECT 
            c.id, 
            c.comment_text AS text, 
            c.created_at, 
            u.id AS user_db_id, 
            u.usuario AS name,  -- Columna 'usuario' de tu tabla 'usuarios'
            NULL AS profilePicUrl -- No tienes columna para foto de perfil en 'usuarios'
        FROM comments c
        JOIN usuarios u ON c.user_id = u.id
        WHERE c.id = ?
    ");
    if (!$stmt_get_comment) {
        throw new Exception("Error en prepare (get comment): " . $conn->error . " (Código: " . $conn->errno . ")");
    }
    $stmt_get_comment->bind_param("i", $new_comment_id);
    if (!$stmt_get_comment->execute()) {
        throw new Exception("Error en execute (get comment): " . $stmt_get_comment->error . " (Código: " . $stmt_get_comment->errno . ")");
    }
    $result = $stmt_get_comment->get_result();
    $comment_data = $result->fetch_assoc();
    $stmt_get_comment->close();

    if (!$comment_data) {
        throw new Exception("No se pudo recuperar el comentario después de guardarlo (ID: $new_comment_id).");
    }
    error_log("[add_comment.php] Comentario recuperado para enviar al frontend.");

    // Confirmar la transacción si todo fue bien
    if (!$conn->commit()) {
        throw new Exception("No se pudo confirmar la transacción (commit).");
    }
    $transaction_active = false; // Marcar como no activa después de commit exitoso
    error_log("[add_comment.php] Transacción confirmada (commit).");


    $formatted_comment = [
        'id' => (int)$comment_data['id'],
        'text' => $comment_data['text'],
        'created_at' => $comment_data['created_at'],
        'user' => [
            'id' => (int)$comment_data['user_db_id'],
            'name' => $comment_data['name'],
            'profilePicUrl' => $comment_data['profilePicUrl'] // Será null como se definió en la SQL
        ]
    ];

    http_response_code(201); // Created
    echo json_encode(['success' => true, 'comment' => $formatted_comment]);

} catch (RuntimeException $e) { // Captura excepciones de la función connect()
    // Este error ocurre si connect() no puede establecer la conexión.
    error_log("[add_comment.php] RuntimeException (probablemente de connect()): " . $e->getMessage());
    http_response_code(503); // Service Unavailable
    echo json_encode(['error' => 'Error de servicio: No se pudo conectar a la base de datos. ' . $e->getMessage()]);
    // $conn podría ser null aquí. $transaction_active sería false.

} catch (Exception $e) { // Captura otras excepciones (preparación, ejecución, commit, etc.)
    error_log("[add_comment.php] Exception: " . $e->getMessage() .
              // Solo intentar acceder a $conn->error si $conn es un objeto mysqli válido
              ($conn instanceof mysqli && $conn->error ? " | SQL Error: " . $conn->error . " (Código: " . $conn->errno . ")" : ""));

    if ($conn instanceof mysqli && $transaction_active) {
        // Solo intentar rollback si $conn es un objeto mysqli Y la bandera de transacción está activa
        $conn->rollback();
        error_log("[add_comment.php] Transacción revertida debido a excepción.");
    }

    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Ocurrió un error en el servidor al procesar su solicitud. Inténtalo de nuevo.']);

} finally {
    if ($conn instanceof mysqli) { // Asegúrate de que $conn sea un objeto mysqli antes de cerrarlo
        $conn->close();
        error_log("[add_comment.php] Conexión a BD cerrada.");
    }
}
?>