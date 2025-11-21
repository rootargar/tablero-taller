<?php
// Test de conexión y consulta básica
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    echo json_encode(['step' => 1, 'message' => 'Iniciando conexión...']) . "\n";

    require_once 'conexion.php';

    echo json_encode(['step' => 2, 'message' => 'Conexión establecida']) . "\n";

    // Test simple
    $sql = "SELECT TOP 5 NumeroOrden, FechaHoraOrden FROM tdsOrdenesServicio (NOLOCK) WHERE estadoOrden = 'S' ORDER BY NumeroOrden DESC";

    echo json_encode(['step' => 3, 'message' => 'Ejecutando consulta...', 'sql' => $sql]) . "\n";

    $stmt = sqlsrv_query($conn, $sql);

    if ($stmt === false) {
        throw new Exception("Error en consulta: " . print_r(sqlsrv_errors(), true));
    }

    $datos = array();
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        foreach ($row as $key => $value) {
            if ($value instanceof DateTime) {
                $row[$key] = $value->format('Y-m-d H:i:s');
            }
        }
        $datos[] = $row;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Consulta exitosa',
        'registros' => count($datos),
        'datos' => $datos
    ], JSON_UNESCAPED_UNICODE);

    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}
?>
