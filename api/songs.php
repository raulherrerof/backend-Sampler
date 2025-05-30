<?php

require_once __DIR__ . '/../config/cors_headers.php'; // Asegurarse de incluir los encabezados CORS

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido. Solo se acepta GET.']);
    exit;
}

header('Content-Type: application/json');

// Datos de canciones de ejemplo
$exampleSongs = [
    [
        'id' => 1,
        'title' => 'Canción de Ejemplo 1',
        'artist' => 'Artista Demo A',
        'albumArt' => 'url/a/imagen1.jpg', // Reemplazar con URLs reales o manejar en el frontend
        'duration' => '3:45'
    ],
    [
        'id' => 2,
        'title' => 'Otra Canción de Prueba',
        'artist' => 'Banda Demo B',
        'albumArt' => 'url/a/imagen2.png', // Reemplazar con URLs reales o manejar en el frontend
        'duration' => '4:10'
    ],
    [
        'id' => 3,
        'title' => 'Demo Track C',
        'artist' => 'Solista Demo C',
        'albumArt' => 'url/a/imagen3.jpeg', // Reemplazar con URLs reales o manejar en el frontend
        'duration' => '2:59'
    ]
];

// Devolver los datos de ejemplo como JSON
http_response_code(200); // OK
echo json_encode($exampleSongs);

// Eliminar el código de conexión a la base de datos y la consulta por ahora
/*
$db = new mysqli('localhost', 'root', '', 'sampler');
if ($db->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de conexión a la base de datos: ' . $db->connect_error]);
    exit;
}

$query = "SELECT * FROM canciones";
$result = $db->query($query);

if ($result === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al ejecutar la consulta: ' . $db->error]);
    exit;
}
*/

?>