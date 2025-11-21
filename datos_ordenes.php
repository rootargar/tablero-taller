<?php
// Incluir el archivo de conexión
require_once 'conexion.php';

// Configurar la respuesta como JSON
header('Content-Type: application/json; charset=utf-8');

try {
    // Definir fechas para el filtro
    $fechaInicio = '2024-10-01'; // Puedes cambiar esta fecha según necesites
    $fechaFin = '2024-12-31';    // Fecha fin
    
    // Nueva consulta SQL simplificada
    $sql = "
    SELECT 
        IdOrdenServicio, 
        NumeroOrden, 
        CASE estadoOrden
            WHEN 'C' THEN 'Cerrada'
            WHEN 'S' THEN 'Abierta'
            WHEN 'F' THEN 'Facturada'
            WHEN 'P' THEN 'Parcialmente Facturada Cerrada'
            WHEN 'A' THEN 'Parcialmente Facturada Abierta'
            ELSE 'NINGUNO'
        END AS EstadoOS,
        FechaHoraOrden, 
        s.NombreSucursal, 
        c.NombreCalculado 
    FROM tdsOrdenesServicio o (NOLOCK)
    JOIN admSucursales s (NOLOCK) ON s.iDsucursal = o.iDsucursal
    JOIN cpcClientes c (NOLOCK) ON c.IdCliente = o.IdCliente
    WHERE IdOrdenServicio NOT IN (SELECT IdOrdenServicio FROM tdsEstadosOperativosOrdenes (NOLOCK))
        AND estadoOrden NOT IN ('F', 'N')
        AND FechaHoraOrden >= ?
        AND FechaHoraOrden <= ?
    ORDER BY NumeroOrden DESC
    ";

    // Ejecutar la consulta con parámetros
    $stmt = sqlsrv_prepare($conn, $sql, array($fechaInicio, $fechaFin));
    
    if ($stmt === false) {
        throw new Exception("Error preparando la consulta: " . print_r(sqlsrv_errors(), true));
    }
    
    $result = sqlsrv_execute($stmt);
    
    if ($result === false) {
        throw new Exception("Error ejecutando la consulta: " . print_r(sqlsrv_errors(), true));
    }

    // Obtener todos los resultados
    $datos = array();
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Convertir objetos DateTime a string para JSON
        foreach ($row as $key => $value) {
            if ($value instanceof DateTime) {
                $row[$key] = $value->format('Y-m-d H:i:s');
            }
        }
        $datos[] = $row;
    }

    // Liberar recursos
    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);

    // Devolver los datos como JSON
    echo json_encode([
        'success' => true,
        'data' => $datos,
        'total' => count($datos),
        'ultima_actualizacion' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // En caso de error, devolver un mensaje de error
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'ultima_actualizacion' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}
?>
