<?php
// api/verificar_sesion.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php'; // Para las cabeceras CORS

if (isset($_SESSION['user_id'])) {
    http_response_code(200);
    echo json_encode([
        'isLoggedIn' => true,
        'user' => [
            'id' => $_SESSION['user_id'],
            'usuario' => $_SESSION['username'],
            'nombre' => $_SESSION['user_nombre']
            // Añade otros datos que hayas guardado en la sesión
        ]
    ]);
} else {
    http_response_code(200); // O 401 si prefieres, pero 200 con isLoggedIn: false es común
    echo json_encode(['isLoggedIn' => false]);
}
?>