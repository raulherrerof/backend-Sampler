<?php
// api/registro.php
require_once __DIR__ . '/../config/database.php'; // Incluye conexión y cabeceras CORS

$db = connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

$data = json_decode(file_get_contents("php://input"));

// Validaciones básicas (deberías expandirlas)
if (
    !isset($data->usuario) || empty(trim($data->usuario)) ||
    !isset($data->contrasena) || empty($data->contrasena) ||
    !isset($data->email) || !filter_var($data->email, FILTER_VALIDATE_EMAIL) ||
    !isset($data->nombre) || empty(trim($data->nombre))
) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos incompletos o inválidos. Asegúrate de que el email sea válido.']);
    exit;
}

$usuario = $db->real_escape_string(trim($data->usuario));
$email = $db->real_escape_string(trim($data->email));
$nombre = $db->real_escape_string(trim($data->nombre));

// Hashear la contraseña
$contrasena_hash = password_hash($data->contrasena, PASSWORD_DEFAULT);
if ($contrasena_hash === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al hashear la contraseña.']);
    exit;
}

// Verificar si el usuario o email ya existen
$stmt_check = $db->prepare("SELECT id FROM usuarios WHERE usuario = ? OR email = ?");
$stmt_check->bind_param("ss", $usuario, $email);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows > 0) {
    http_response_code(409); // Conflict
    echo json_encode(['error' => 'El nombre de usuario o el email ya están registrados.']);
    $stmt_check->close();
    $db->close();
    exit;
}
$stmt_check->close();


// Insertar nuevo usuario
$stmt = $db->prepare("INSERT INTO usuarios (usuario, contrasena, email, nombre) VALUES (?, ?, ?, ?, ?, ?)");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al preparar la consulta de inserción: ' . $db->error]);
    $db->close();
    exit;
}
$stmt->bind_param("sssssi", $usuario, $contrasena_hash, $email, $nombre, $apellido, $edad);

if ($stmt->execute()) {
    http_response_code(201); // Created
    echo json_encode([
        'message' => 'Usuario registrado exitosamente.',
        'id_usuario' => $stmt->insert_id,
        'usuario' => $usuario
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Error al registrar el usuario: ' . $stmt->error]);
}

$stmt->close();
$db->close();
?>