<?php

require_once __DIR__ . '/../config/cors_headers.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db_connection.php';


header('Content-Type: application/json');


$db = connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); 
    echo json_encode(['error' => 'Método no permitido. Solo se acepta POST.']);
    $db->close();
    exit;
}

$data = json_decode(file_get_contents("php://input"));

if (!$data || !isset($data->email) || empty(trim($data->email)) || !isset($data->password) || empty($data->password)) {
    http_response_code(400); 
    echo json_encode(['error' => 'Email y contraseña son obligatorios.']);
    $db->close();
    exit;
}

$email = $db->real_escape_string(trim($data->email));
$contrasena_enviada = $data->password;

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
        session_regenerate_id(true); 

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
                'name' => $usuario_db['nombre'], 
                'email' => $usuario_db['email']
                
            ]
         
        ]);
    } else {
        http_response_code(401); 
        echo json_encode(['error' => 'Credenciales incorrectas.']);
    }
} else {
    http_response_code(401); 
    echo json_encode(['error' => 'Credenciales incorrectas.']);
}

$stmt->close();
$db->close();
?>