<?php // NADA ANTES DE ESTO

// --- HABILITAR LOGS Y ERRORES DE PHP (AL INICIO ABSOLUTO) ---
ini_set('display_errors', 0); 
ini_set('log_errors', 1);
// Log específico para este script
ini_set('error_log', __DIR__ . '/../php_session_check_debug.log'); 
error_reporting(E_ALL);
error_log("--- verificar_sesion.php: INICIO EJECUCIÓN --- " . date("Y-m-d H:i:s"));
if (isset($_SERVER['HTTP_ORIGIN'])) { error_log("Origin: " . $_SERVER['HTTP_ORIGIN']);}
// --- FIN HABILITAR LOGS ---

// 1. INCLUIR CABECERAS CORS - ¡ESTO DEBE SER LO PRIMERO EJECUTABLE!
require_once __DIR__ . '/../config/cors_headers.php'; 
error_log("verificar_sesion.php: cors_headers.php incluido.");

// 2. INICIAR SESIÓN (SIEMPRE DESPUÉS DE LAS CABECERAS CORS)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
    error_log("verificar_sesion.php: session_start() llamado.");
} else {
    error_log("verificar_sesion.php: Sesión ya estaba iniciada.");
}

// 3. ESTABLECER CONTENT-TYPE PARA LA RESPUESTA JSON
// Solo si las cabeceras no se han enviado ya (por ejemplo, por un exit en cors_headers.php para OPTIONS)
if (!headers_sent()) {
    header('Content-Type: application/json');
    error_log("verificar_sesion.php: Content-Type application/json seteado.");
}


// 4. LÓGICA PARA VERIFICAR LA SESIÓN
if (isset($_SESSION['user_id'])) {
    $current_user_id_from_session = (int)$_SESSION['user_id'];
    error_log("verificar_sesion.php: user_id ENCONTRADO en sesión: " . $current_user_id_from_session);
    
    // --- OBTENER DATOS COMPLETOS DEL USUARIO DE LA BD ---
    require_once __DIR__ . '/../config/db_connection.php'; // Necesario para la consulta
    $db = null;
    $user_data_for_frontend = null;

    try {
        $db = connect();
        if ($db) {
            // Asegúrate de que tu tabla usuarios tenga todas estas columnas
            $stmt_get_user = $db->prepare("SELECT id, usuario, email, nombre, apellido, dob, gender, aboutMe, profilePicUrl FROM usuarios WHERE id = ?");
            if ($stmt_get_user) {
                $stmt_get_user->bind_param("i", $current_user_id_from_session);
                $stmt_get_user->execute();
                $result_user = $stmt_get_user->get_result();
                if ($db_user_data = $result_user->fetch_assoc()) {
                    
                    // Construir la URL completa para profilePicUrl
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
                    $host = $_SERVER['HTTP_HOST'];
                    $backendWebPath = '/backend-Sampler'; // !!! AJUSTA ESTA RUTA SI ES NECESARIO !!!
                    $baseAppUrl = rtrim($protocol . $host . $backendWebPath, '/');
                    
                    $fullProfilePicUrl = null;
                    if (!empty($db_user_data['profilePicUrl'])) {
                        if (preg_match('/^https?:\/\//i', $db_user_data['profilePicUrl'])) {
                            $fullProfilePicUrl = $db_user_data['profilePicUrl']; // Ya es completa
                        } else {
                            $fullProfilePicUrl = $baseAppUrl . '/' . ltrim($db_user_data['profilePicUrl'],'/');
                        }
                    }
                    error_log("verificar_sesion.php: URL completa de foto de perfil para JSON: " . ($fullProfilePicUrl ?? 'NINGUNA'));

                    $user_data_for_frontend = [
                        'id' => (int)$db_user_data['id'],
                        'username' => $db_user_data['usuario'] ?? null,
                        'name' => $db_user_data['nombre'] ?? null, 
                        'email' => $db_user_data['email'] ?? null,
                        'lastName' => $db_user_data['apellido'] ?? null, // Si tienes esta columna
                        'dob' => $db_user_data['dob'] ?? null,           // Si tienes esta columna
                        'gender' => $db_user_data['gender'] ?? null,       // Si tienes esta columna
                        'aboutMe' => $db_user_data['aboutMe'] ?? null,     // Si tienes esta columna
                        'profilePicUrl' => $fullProfilePicUrl           // URL completa
                    ];
                } else {
                    error_log("verificar_sesion.php: Usuario con ID " . $current_user_id_from_session . " no encontrado en la BD.");
                }
                $stmt_get_user->close();
            } else {
                error_log("verificar_sesion.php: Error preparando statement para obtener datos de usuario: " . $db->error);
            }
            if ($db instanceof mysqli) $db->close();
        } else {
             error_log("verificar_sesion.php: No se pudo conectar a la BD para obtener datos de usuario.");
        }
    } catch (Exception $e) {
        error_log("verificar_sesion.php: Excepción al obtener datos de usuario de BD: " . $e->getMessage());
    }
    
    // Si no se pudieron obtener datos de la BD, al menos devuelve lo que hay en sesión
    if (!$user_data_for_frontend) {
        error_log("verificar_sesion.php: No se pudieron obtener datos de la BD, usando datos de sesión limitados.");
        $user_data_for_frontend = [
            'id' => $_SESSION['user_id'],
            'username' => isset($_SESSION['username']) ? $_SESSION['username'] : null,
            'name' => isset($_SESSION['user_nombre']) ? $_SESSION['user_nombre'] : null,
            'email' => isset($_SESSION['user_email']) ? $_SESSION['user_email'] : null,
            'profilePicUrl' => null // No se pudo obtener de la BD
        ];
    }
    error_log("verificar_sesion.php: Devolviendo usuario: " . print_r($user_data_for_frontend, true));

    http_response_code(200); 
    echo json_encode([
        'isLoggedIn' => true,
        'user' => $user_data_for_frontend
    ]);

} else {
    error_log("verificar_sesion.php: Devolviendo isLoggedIn: false (no hay user_id en sesión).");
    http_response_code(200); 
    echo json_encode(['isLoggedIn' => false, 'user' => null]);
}
exit;
?>