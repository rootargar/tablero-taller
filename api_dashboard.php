<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start(); // Iniciar buffer de salida

try {
    require_once 'conexion.php';

    if ($conn === false) {
        throw new Exception("Conexión falló");
    }

    $fechaInicio = date('Ymd', strtotime('-7 days'));

    // Consulta MUY simple - solo 3 órdenes
    $sql = "
    SELECT TOP 3
        o.IdOrdenServicio,
        o.NumeroOrden,
        t.Nombre AS TipoServicio,
        c.NombreCalculado AS NombreCliente
    FROM tdsOrdenesServicio o (NOLOCK)
        INNER JOIN tdsTiposervicio t (NOLOCK) ON o.IdtipoServicio = t.IdtipoServicio
        INNER JOIN cpcClientes c (NOLOCK) ON c.IdCliente = o.IdCliente
    WHERE o.estadoOrden = 'S'
        AND o.FechaHoraOrden >= '$fechaInicio'
    ORDER BY o.NumeroOrden DESC
    ";

    $stmt = sqlsrv_query($conn, $sql);

    if ($stmt === false) {
        throw new Exception("Error en consulta: " . json_encode(sqlsrv_errors()));
    }

    $ordenes = array();
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $ordenes[] = $row;
    }
    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);

    // Respuesta simple
    $response = [
        'success' => true,
        'total' => count($ordenes),
        'ordenes' => $ordenes,
        'fecha_inicio' => $fechaInicio
    ];

    ob_end_clean(); // Limpiar cualquier salida anterior
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'line' => $e->getLine()
    ]);
} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error crítico: ' . $e->getMessage()
    ]);
}
?>
