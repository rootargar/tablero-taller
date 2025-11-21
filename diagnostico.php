<?php
// Diagnóstico simple
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

echo "=== INICIO DEL DIAGNÓSTICO ===\n\n";

// Paso 1: Verificar que PHP funciona
echo "1. PHP funciona: OK\n";

// Paso 2: Verificar archivo de conexión
echo "2. Intentando cargar conexion.php...\n";
if (file_exists('conexion.php')) {
    echo "   - Archivo existe: OK\n";
    require_once 'conexion.php';
    echo "   - Archivo cargado: OK\n";
} else {
    echo "   - ERROR: conexion.php NO existe\n";
    exit;
}

// Paso 3: Verificar conexión
echo "3. Verificando conexión a BD...\n";
if ($conn === false) {
    echo "   - ERROR: Conexión falló\n";
    print_r(sqlsrv_errors());
    exit;
} else {
    echo "   - Conexión: OK\n";
}

// Paso 4: Consulta super simple
echo "4. Probando consulta simple...\n";
$sql = "SELECT TOP 3 NumeroOrden FROM tdsOrdenesServicio (NOLOCK) WHERE estadoOrden = 'S' ORDER BY NumeroOrden DESC";
echo "   SQL: $sql\n";

$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    echo "   - ERROR en consulta:\n";
    print_r(sqlsrv_errors());
    exit;
} else {
    echo "   - Consulta ejecutada: OK\n";
    $count = 0;
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $count++;
        echo "   - Orden #$count: " . $row['NumeroOrden'] . "\n";
    }
    echo "   - Total registros: $count\n";
    sqlsrv_free_stmt($stmt);
}

// Paso 5: Probar fecha
echo "5. Probando con filtro de fecha...\n";
$fechaInicio = date('Y-m-d', strtotime('-7 days'));
echo "   - Fecha inicio: $fechaInicio\n";

$sql2 = "SELECT TOP 3 NumeroOrden, FechaHoraOrden FROM tdsOrdenesServicio (NOLOCK) WHERE estadoOrden = 'S' AND FechaHoraOrden >= CAST('$fechaInicio' AS DATETIME) ORDER BY NumeroOrden DESC";

$stmt2 = sqlsrv_query($conn, $sql2);
if ($stmt2 === false) {
    echo "   - ERROR en consulta con fecha:\n";
    print_r(sqlsrv_errors());
    exit;
} else {
    echo "   - Consulta con fecha: OK\n";
    $count2 = 0;
    while ($row = sqlsrv_fetch_array($stmt2, SQLSRV_FETCH_ASSOC)) {
        $count2++;
        $fecha = $row['FechaHoraOrden'];
        if ($fecha instanceof DateTime) {
            $fecha = $fecha->format('Y-m-d H:i:s');
        }
        echo "   - Orden #$count2: " . $row['NumeroOrden'] . " - Fecha: $fecha\n";
    }
    echo "   - Total registros: $count2\n";
    sqlsrv_free_stmt($stmt2);
}

sqlsrv_close($conn);

echo "\n=== DIAGNÓSTICO COMPLETADO EXITOSAMENTE ===\n";
?>
