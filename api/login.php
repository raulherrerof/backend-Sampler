<?php
// api/login.php

// Iniciar la sesión PHP ANTES de cualquier salida (incluyendo las cabeceras CORS en database.php)
// Si database.php ya tiene session_start(), puedes omitirlo aquí, pero asegúrate de que se llame.
// Por seguridad, es mejor que session_start() esté al inicio de los scripts que la usan.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php'; // Incluye conexión y cabeceras CORS

$db = connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->usuario_o_email) || empty(trim($data->usuario_o_email)) || !isset($data->contrasena) || empty($data->contrasena)) {
    http_response_code(400);
    echo json_encode(['error' => 'Usuario/Email y contraseña son obligatorios.']);
    exit;
}

$usuario_o_email = $db->real_escape_string(trim($data->usuario_o_email));
$contrasena_enviada = $data->contrasena;

// Buscar usuario por nombre de usuario o email
$stmt = $db->prepare("SELECT id, usuario, contrasena, nombre, email FROM usuarios WHERE usuario = ? OR email = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al preparar la consulta: ' . $db->error]);
    exit;
}
$stmt->bind_param("ss", $usuario_o_email, $usuario_o_email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $usuario_db = $result->fetch_assoc();

    // Verificar la contraseña hasheada
    if (password_verify($contrasena_enviada, $usuario_db['contrasena'])) {
        // Contraseña correcta: Iniciar sesión
        $_SESSION['user_id'] = $usuario_db['id'];
        $_SESSION['username'] = $usuario_db['usuario'];
        $_SESSION['user_nombre'] = $usuario_db['nombre'];
        // Puedes añadir más datos del usuario a la sesión si lo necesitas

        http_response_code(200);
        echo json_encode([
            'message' => 'Inicio de sesión exitoso.',
            'user' => [
                'id' => $usuario_db['id'],
                'usuario' => $usuario_db['usuario'],
                'nombre' => $usuario_db['nombre'],
                'email' => $usuario_db['email']
            ]
        ]);
    } else {
        // Contraseña incorrecta
        http_response_code(401); // Unauthorized
        echo json_encode(['error' => 'Credenciales incorrectas.']);
    }
} else {
    // Usuario no encontrado
    http_response_code(401); // Unauthorized (o 404, pero 401 es común para login fallido)
    echo json_encode(['error' => 'Credenciales incorrectas.']);
}

$stmt->close();
$db->close();
?>