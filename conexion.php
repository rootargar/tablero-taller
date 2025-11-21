<?php
$serverName = "10.48.22.96"; // Nombre del servidor SQL Server
$connectionOptions = array(
    "Database" => "SITIC_KWSIN2", // Nombre de la base de datos
    "Uid" => "consultadatos", // Usuario de la base de datos
    "PWD" => "QUERYDATA" // Contraseña del usuario
);

// Establecer la conexión
$conn = sqlsrv_connect($serverName, $connectionOptions);

if ($conn === false) {
    // Configurar la respuesta como JSON
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);

    // Retornar error en formato JSON
    echo json_encode([
        'success' => false,
        'error' => 'Error de conexión a la base de datos',
        'details' => sqlsrv_errors(),
        'ultima_actualizacion' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);

    exit;
}
?>
