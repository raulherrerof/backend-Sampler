<?php // NADA ANTES DE ESTO (ni espacios, ni BOM)

// --- HABILITAR LOGS Y ERRORES DE PHP (AL INICIO ABSOLUTO) ---
ini_set('display_errors', 0); 
ini_set('log_errors', 1);
// Log específico para este script
ini_set('error_log', __DIR__ . '/../php_update_profile_activity.log'); // Asume que update_profile.php está en api/users/
error_reporting(E_ALL);
error_log("--- update_profile.php: INICIO EJECUCIÓN --- " . date("Y-m-d H:i:s"));
error_log("update_profile.php: REQUEST_METHOD = " . ($_SERVER['REQUEST_METHOD'] ?? 'No definido'));
if (isset($_SERVER['HTTP_ORIGIN'])) {
    error_log("update_profile.php: HTTP_ORIGIN = " . $_SERVER['HTTP_ORIGIN']);
} else {
    error_log("update_profile.php: HTTP_ORIGIN no está seteado.");
}
// --- FIN HABILITAR LOGS ---


// 1. INCLUIR CABECERAS CORS - ¡ESTO DEBE SER LO PRIMERO EJECUTABLE DESPUÉS DE LOS SETEOS DE ERROR!
// !!! VERIFICA ESTA RUTA: si update_profile.php está en api/, cambia '../../' a '../' !!!
require_once __DIR__ . '/../config/cors_headers.php'; 
error_log("update_profile.php: cors_headers.php incluido. Log de CORS interno: " . (isset($GLOBALS['__cors_headers_debug_log_global']) ? implode(" | ", $GLOBALS['__cors_headers_debug_log_global']) : "cors_headers_debug_log no definido o no accesible globalmente"));


// 2. INICIAR SESIÓN (SIEMPRE DESPUÉS DE LAS CABECERAS CORS)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
    error_log("update_profile.php: session_start() llamado.");
} else {
    error_log("update_profile.php: Sesión ya estaba iniciada.");
}


// 3. INCLUIR CONEXIÓN A BD (DESPUÉS DE CORS Y SESIÓN)
// !!! VERIFICA ESTA RUTA: si update_profile.php está en api/, cambia '../../' a '../' !!!
require_once __DIR__ . '/../config/db_connection.php'; 
error_log("update_profile.php: db_connection.php incluido.");


// 4. ESTABLECER CONTENT-TYPE PARA LA RESPUESTA JSON
// Solo si las cabeceras no se han enviado ya (ej. por un exit en cors_headers.php para OPTIONS)
if (!headers_sent()) { 
    header('Content-Type: application/json');
    error_log("update_profile.php: Content-Type application/json seteado (principal).");
}


// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    error_log("update_profile.php: ACCESO NO AUTORIZADO - user_id no en sesión. Terminando script.");
    http_response_code(401); 
    echo json_encode(['success' => false, 'error' => 'No autorizado. Debes iniciar sesión para actualizar tu perfil.']);
    exit;
}
$current_user_id = (int)$_SESSION['user_id'];
error_log("update_profile.php: Usuario autenticado ID: " . $current_user_id);

$db = null; 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("update_profile.php: Método no permitido: " . $_SERVER['REQUEST_METHOD'] . ". Se esperaba POST.");
    http_response_code(405); 
    echo json_encode(['success' => false, 'error' => 'Método no permitido. Se esperaba POST.']);
    exit;
}
error_log("update_profile.php: Método POST confirmado.");
error_log("update_profile.php: Contenido de _POST: " . print_r($_POST, true));
error_log("update_profile.php: Contenido de _FILES: " . print_r($_FILES, true));

try {
    $db = connect(); // connect() usa constantes de db_config.php
    if (!$db) {
        error_log("update_profile.php - connect() devolvió null o false.");
        throw new Exception("No se pudo conectar a la base de datos.");
    }
    error_log("update_profile.php - Conexión a BD exitosa.");

    $update_fields_sql_parts = []; 
    $params_for_bind = [];       
    $param_types_string = "";    

    // Username
    if (isset($_POST['username']) && !empty(trim($_POST['username']))) {
        $new_username = $db->real_escape_string(trim($_POST['username']));
        // Verificar unicidad si es necesario y si es diferente al actual
        if (strtolower($new_username) !== strtolower($_SESSION['username'] ?? '')) {
            $stmt_check = $db->prepare("SELECT id FROM usuarios WHERE usuario = ? AND id != ?");
            if(!$stmt_check) throw new Exception("Error preparando check username: " . $db->error);
            $stmt_check->bind_param("si", $new_username, $current_user_id);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows > 0) {
                $stmt_check->close();
                http_response_code(409); 
                echo json_encode(['success' => false, 'error' => 'El nombre de usuario ya está en uso.']);
                if ($db instanceof mysqli) $db->close(); exit;
            }
            $stmt_check->close();
        }
        $update_fields_sql_parts[] = "usuario = ?"; $params_for_bind[] = $new_username; $param_types_string .= "s";
    }
    // Email
    if (isset($_POST['email']) && filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL)) {
        $new_email = $db->real_escape_string(trim($_POST['email']));
        // Verificar unicidad si es necesario y si es diferente al actual
         if (strtolower($new_email) !== strtolower($_SESSION['user_email'] ?? '')) {
            $stmt_check = $db->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
            if(!$stmt_check) throw new Exception("Error preparando check email: " . $db->error);
            $stmt_check->bind_param("si", $new_email, $current_user_id);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows > 0) {
                $stmt_check->close();
                http_response_code(409);
                echo json_encode(['success' => false, 'error' => 'El correo electrónico ya está en uso.']);
                if ($db instanceof mysqli) $db->close(); exit;
            }
            $stmt_check->close();
        }
        $update_fields_sql_parts[] = "email = ?"; $params_for_bind[] = $new_email; $param_types_string .= "s";
    }
    // Campos que asumen que las columnas existen en tu tabla `usuarios`
    if (isset($_POST['name'])) { $update_fields_sql_parts[] = "nombre = ?"; $params_for_bind[] = $db->real_escape_string(trim($_POST['name'])); $param_types_string .= "s"; }
    if (isset($_POST['lastName'])) { $update_fields_sql_parts[] = "apellido = ?"; $params_for_bind[] = $db->real_escape_string(trim($_POST['lastName'])); $param_types_string .= "s"; }
    if (isset($_POST['dob']) && !empty($_POST['dob'])) { $update_fields_sql_parts[] = "dob = ?"; $params_for_bind[] = $db->real_escape_string($_POST['dob']); $param_types_string .= "s"; }
    if (isset($_POST['gender'])) { $update_fields_sql_parts[] = "gender = ?"; $params_for_bind[] = $db->real_escape_string($_POST['gender']); $param_types_string .= "s"; }
    if (isset($_POST['aboutMe'])) { $update_fields_sql_parts[] = "aboutMe = ?"; $params_for_bind[] = $db->real_escape_string(trim($_POST['aboutMe'])); $param_types_string .= "s"; }
    
    if (isset($_POST['password']) && !empty($_POST['password'])) {
        $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $update_fields_sql_parts[] = "contrasena = ?"; $params_for_bind[] = $hashed_password; $param_types_string .= "s";
    }

    $newProfilePicWebPath = null; 
    if (isset($_FILES['profilePic']) && $_FILES['profilePic']['error'] == UPLOAD_ERR_OK) {
        $targetDirProfilePics = __DIR__ . "/../uploads/profile_pics/"; 
        if (!file_exists($targetDirProfilePics)) {
             if(!mkdir($targetDirProfilePics, 0777, true) && !is_dir($targetDirProfilePics)) {
                error_log("update_profile.php - FALLO al crear directorio: " . $targetDirProfilePics . " Error: " . print_r(error_get_last(), true));
             } else { error_log("update_profile.php - Directorio creado: " . $targetDirProfilePics); }
        }
        $fileExtension = strtolower(pathinfo($_FILES["profilePic"]["name"], PATHINFO_EXTENSION));
        $fileName = "user" . $current_user_id . "_" . time() . "." . $fileExtension;
        $targetFilePath = $targetDirProfilePics . $fileName;
        $newProfilePicWebPath = "uploads/profile_pics/" . $fileName; 
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($fileExtension, $allowedTypes)) {
            if (move_uploaded_file($_FILES["profilePic"]["tmp_name"], $targetFilePath)) {
                error_log("update_profile.php - Foto de perfil subida: " . $newProfilePicWebPath);
                $update_fields_sql_parts[] = "profilePicUrl = ?"; 
                $params_for_bind[] = $db->real_escape_string($newProfilePicWebPath);
                $param_types_string .= "s";
            } else { error_log("update_profile.php - Error moviendo profilePic. Error PHP: " . $_FILES['profilePic']['error']); $newProfilePicWebPath = null;}
        } else { error_log("update_profile.php - Tipo de archivo no permitido: " . $fileExtension); $newProfilePicWebPath = null;}
    }

    if (count($update_fields_sql_parts) > 0) {
        $sql_update = "UPDATE usuarios SET " . implode(", ", $update_fields_sql_parts) . " WHERE id = ?";
        $params_for_bind[] = $current_user_id; 
        $param_types_string .= "i";
        
        $stmt_update = $db->prepare($sql_update);
        if (!$stmt_update) throw new Exception("Error preparando SQL update: " . $db->error . " SQL: " . $sql_update);
        
        if (!empty($param_types_string) && count($params_for_bind) === strlen($param_types_string) ) { // Asegurar que el número de params coincida con tipos
             $stmt_update->bind_param($param_types_string, ...$params_for_bind);
        } else if (!empty($param_types_string)) { // Log de error si no coinciden
            error_log("update_profile.php - Discrepancia en bind_param. Tipos: '$param_types_string', #Params: " . count($params_for_bind));
            throw new Exception("Error interno al preparar datos para actualizar perfil.");
        }


        if ($stmt_update->execute()) {
            error_log("update_profile.php - Perfil actualizado. Filas afectadas: " . $stmt_update->affected_rows);
            
            $stmt_get_user = $db->prepare("SELECT id, usuario, email, nombre, apellido, dob, gender, aboutMe, profilePicUrl FROM usuarios WHERE id = ?");
            if (!$stmt_get_user) throw new Exception("Error preparando get usuario actualizado: " . $db->error);
            $stmt_get_user->bind_param("i", $current_user_id);
            $stmt_get_user->execute();
            $db_user_data = $stmt_get_user->get_result()->fetch_assoc();
            $stmt_get_user->close();

            if ($db_user_data) {
                $_SESSION['username'] = $db_user_data['usuario'];
                $_SESSION['user_nombre'] = $db_user_data['nombre'];
                $_SESSION['user_email'] = $db_user_data['email'];
                // $_SESSION['user_profile_pic_url'] = $db_user_data['profilePicUrl']; // Opcional: guardar ruta relativa en sesión
            }

            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
            $host = $_SERVER['HTTP_HOST'];
            $backendWebPath = '/backend-Sampler'; // ¡¡¡AJUSTA ESTA RUTA SI ES DIFERENTE!!!
            $baseAppUrl = rtrim($protocol . $host . $backendWebPath, '/');
            $fullProfilePicUrl = null;
            if ($db_user_data && !empty($db_user_data['profilePicUrl'])) {
                 if (preg_match('/^https?:\/\//i', $db_user_data['profilePicUrl'])) {
                    $fullProfilePicUrl = $db_user_data['profilePicUrl'];
                 } else {
                    $fullProfilePicUrl = $baseAppUrl . '/' . ltrim($db_user_data['profilePicUrl'],'/');
                 }
            }
            
            $frontend_user_data = [
                'id' => $db_user_data['id'] ?? null,
                'username' => $db_user_data['usuario'] ?? null,
                'email' => $db_user_data['email'] ?? null,
                'name' => $db_user_data['nombre'] ?? null,
                'lastName' => $db_user_data['apellido'] ?? null,
                'dob' => $db_user_data['dob'] ?? null,
                'gender' => $db_user_data['gender'] ?? null,
                'aboutMe' => $db_user_data['aboutMe'] ?? null,
                'profilePicUrl' => $fullProfilePicUrl 
            ];

            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Perfil actualizado exitosamente.', 'user' => $frontend_user_data]);
        } else {
            throw new Exception("Error ejecutando actualización del perfil: " . $stmt_update->error);
        }
        $stmt_update->close();
    } else {
        error_log("update_profile.php - No se proporcionaron datos válidos para actualizar.");
        http_response_code(200); // O 400 si consideras que es un error del cliente no enviar nada.
        echo json_encode(['success' => true, 'message' => 'No se proporcionaron datos para actualizar.', 'user' => null]); 
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error CRÍTICO en update_profile.php: UserID {$current_user_id} - " . $e->getMessage() . " - Archivo: " . $e->getFile() . " - Línea: " . $e->getLine());
    echo json_encode(['success' => false, 'error' => 'Ocurrió un error al actualizar el perfil.', 'details_server' => $e->getMessage()]);
} finally {
    if ($db instanceof mysqli) $db->close();
    error_log("--- update_profile.php: FIN EJECUCIÓN ---");
}
?>