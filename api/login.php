<?php
// sampler-backend/api/login.php

// 1. INCLUIR CABECERAS CORS PRIMERO
require_once __DIR__ . '/../config/cors_headers.php';

// 2. INICIAR SESIÓN (DESPUÉS DE CORS)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 3. INCLUIR CONEXIÓN A BD
require_once __DIR__ . '/../config/db_connection.php';

// 4. ESTABLECER CONTENT-TYPE (JUSTO ANTES DEL PRIMER JSON_ENCODE)
header('Content-Type: application/json');


$db = connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Método no permitido. Solo se acepta POST.']);
    $db->close();
    exit;
}

$data = json_decode(file_get_contents("php://input"));

// El frontend LoginPage.jsx debería enviar 'email' y 'password'
if (!$data || !isset($data->email) || empty(trim($data->email)) || !isset($data->password) || empty($data->password)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Email y contraseña son obligatorios.']);
    $db->close();
    exit;
}

$email = $db->real_escape_string(trim($data->email));
$contrasena_enviada = $data->password;

// Buscar usuario por email. Si quieres buscar por 'usuario_o_email', ajusta la consulta
// y asegúrate que el frontend envía ese campo.
$stmt = $db->prepare("SELECT id, usuario, contrasena, nombre, email FROM usuarios WHERE email = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al preparar la consulta: ' . $db->error]);
    $db->close();
    exit;
}
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $usuario_db = $result->fetch_assoc();

    if (password_verify($contrasena_enviada, $usuario_db['contrasena'])) {
        session_regenerate_id(true); // Previene fijación de sesión

        $_SESSION['user_id'] = $usuario_db['id'];
        $_SESSION['username'] = $usuario_db['usuario'];
        $_SESSION['user_nombre'] = $usuario_db['nombre']; 
        $_SESSION['user_email'] = $usuario_db['email'];

        http_response_code(200);
        echo json_encode([
            'message' => 'Inicio de sesión exitoso.',
            'user' => [ 
                'id' => $usuario_db['id'],
                'username' => $usuario_db['usuario'],
                'name' => $usuario_db['nombre'], // 'name' para coincidir con initialUserProfileData en App.jsx
                'email' => $usuario_db['email']
                // No envíes la contraseña hasheada al frontend
            ]
            // Si usaras JWT, aquí iría el token
        ]);
    } else {
        http_response_code(401); // Unauthorized
        echo json_encode(['error' => 'Credenciales incorrectas.']);
    }
} else {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Credenciales incorrectas.']);
}

$stmt->close();
$db->close();
?>