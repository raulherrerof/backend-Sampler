<?php

require_once __DIR__ . '/../config/cors_headers.php';
require_once __DIR__ . '/../config/db_connection.php';

header('Content-Type: application/json');

$db = connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    $db->close();
    exit;
}

$data = json_decode(file_get_contents("php://input"));


if (
    !$data ||
    !isset($data->username) || empty(trim($data->username)) ||
    !isset($data->password) || empty($data->password) ||
    !isset($data->email) || !filter_var(trim($data->email), FILTER_VALIDATE_EMAIL)

    
) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos incompletos o inválidos. Usuario, email y contraseña son requeridos.']);
    $db->close();
    exit;
}

$usuario = $db->real_escape_string(trim($data->username));
$email = $db->real_escape_string(trim($data->email));


$contrasena_hash = password_hash($data->password, PASSWORD_DEFAULT);
if ($contrasena_hash === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al hashear la contraseña.']);
    $db->close();
    exit;
}

$stmt_check = $db->prepare("SELECT id FROM usuarios WHERE usuario = ? OR email = ?");
if(!$stmt_check) { $db->close(); exit; }
$stmt_check->bind_param("ss", $usuario, $email);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows > 0) {
    http_response_code(409); 
    echo json_encode(['error' => 'El nombre de usuario o el email ya están registrados.']);
    $stmt_check->close();
    $db->close();
    exit;
}
$stmt_check->close();


$stmt = $db->prepare("INSERT INTO usuarios (usuario, contrasena, email) VALUES (?, ?, ?)");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al preparar la consulta de inserción: ' . $db->error]);
    $db->close();
    exit;
}

$stmt->bind_param("sss", $usuario, $contrasena_hash, $email);

if ($stmt->execute()) {
    http_response_code(201); 
    echo json_encode([
        'message' => 'Usuario registrado exitosamente.',
        'userId' => $stmt->insert_id, 
        'username' => $usuario        
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Error al registrar el usuario: ' . $stmt->error]);
}


$stmt->close();
$db->close();
?>