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
    die(print_r(sqlsrv_errors(), true));
}
?>
