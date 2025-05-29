<?php
// sampler-backend/config/cors_headers.php

if (isset($_SERVER['HTTP_ORIGIN'])) {
    // Reemplaza http://localhost:3001 con el origen de tu frontend React
    // Para producción, debes ser más específico o tener una lista de orígenes permitidos.
    header("Access-Control-Allow-Origin: http://localhost:3001"); 
    header('Access-Control-Allow-Credentials: true'); // Necesario para sesiones/cookies con CORS
    header('Access-Control-Max-Age: 86400');    // Cachear respuesta preflight por 1 día
}

// Manejar la petición preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
        // Métodos que tu API permitirá
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    }
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
        // Cabeceras que tu frontend puede enviar
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    }
    http_response_code(204); // No Content (o 200 OK)
    exit(0); 
}

// No pongas header('Content-Type: application/json'); aquí globalmente,
// ya que no todos los endpoints podrían devolver JSON o podrían necesitar establecerlo más tarde.
?>