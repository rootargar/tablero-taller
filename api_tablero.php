<?php
// Incluir el archivo de conexión
require_once 'conexion.php';

// Configurar la respuesta como JSON
header('Content-Type: application/json; charset=utf-8');

try {
    // Calcular fecha de hace 7 días
    $fechaInicio = date('Y-m-d', strtotime('-7 days'));
    $fechaActual = date('Y-m-d');

    // ========== CONSULTA PRINCIPAL - ÓRDENES ABIERTAS DE LA ÚLTIMA SEMANA ==========
    $sqlOrdenes = "
    WITH TecnicosAsignados AS (
        SELECT
            o.IdOrdenServicio,
            tt.IdTecnico,
            m.Nombre AS NombreTecnico,
            ROW_NUMBER() OVER (PARTITION BY o.IdOrdenServicio ORDER BY t.FechaInicio DESC) AS RnTecnico
        FROM
            tdsTrabajosTecnicos tt (NOLOCK)
            INNER JOIN tdsTecnicos m (NOLOCK) ON tt.IdTecnico = m.IdTecnico
            INNER JOIN tdsTrabajos t (NOLOCK) ON t.IdTrabajo = tt.IdTrabajo
            INNER JOIN tdsOrdenesServicio o (NOLOCK) ON t.IdOrdenServicio = o.IdOrdenServicio
    ),
    EstadosOperativos AS (
        SELECT
            po.IdOrdenServicio,
            p.Nombre AS EstadoOperativo,
            po.FechaHoraInicio,
            ISNULL(po.FechaHoraFin, GETDATE()) AS FechaHoraFin,
            DATEDIFF(DAY, po.FechaHoraInicio, ISNULL(po.FechaHoraFin, GETDATE())) AS DiasEstadia,
            ROW_NUMBER() OVER (PARTITION BY po.IdOrdenServicio ORDER BY po.FechaHoraInicio DESC) AS Rn
        FROM
            tdsEstadosOperativosOrdenes po (NOLOCK)
            INNER JOIN tdsEstadosOperativos p (NOLOCK) ON p.IdEstadoOperativo = po.IdEstadoOperativo
    )
    SELECT
        o.IdOrdenServicio,
        o.NumeroOrden,
        a.NumArticulo AS Unidad,
        t.Nombre AS TipoServicio,
        c.NombreCalculado AS NombreCliente,
        o.FechaHoraOrden,
        o.FechaHoraPromEntrega,
        CASE o.estadoOrden
            WHEN 'S' THEN 'Abierta'
            WHEN 'C' THEN 'Cerrada'
            WHEN 'F' THEN 'Facturada'
            ELSE 'Otro'
        END AS EstadoOS,
        ta.IdTecnico,
        ta.NombreTecnico,
        eo.EstadoOperativo,
        eo.DiasEstadia,
        CASE
            WHEN ta.IdTecnico IS NULL THEN 1
            ELSE 0
        END AS SinTecnico
    FROM
        tdsOrdenesServicio o (NOLOCK)
        INNER JOIN tdsTiposervicio t (NOLOCK) ON o.IdtipoServicio = t.IdtipoServicio
        INNER JOIN cpcClientes c (NOLOCK) ON c.IdCliente = o.IdCliente
        LEFT JOIN invArticulos a (NOLOCK) ON o.IdArticulo = a.IdArticulo
        LEFT JOIN TecnicosAsignados ta ON o.IdOrdenServicio = ta.IdOrdenServicio AND ta.RnTecnico = 1
        LEFT JOIN EstadosOperativos eo ON o.IdOrdenServicio = eo.IdOrdenServicio AND eo.Rn = 1
    WHERE
        o.estadoOrden = 'S'
        AND o.FechaHoraOrden >= ?
        AND o.FechaHoraOrden <= GETDATE()
    ORDER BY o.NumeroOrden DESC
    ";

    $stmtOrdenes = sqlsrv_prepare($conn, $sqlOrdenes, array($fechaInicio));

    if ($stmtOrdenes === false) {
        throw new Exception("Error preparando consulta de órdenes: " . print_r(sqlsrv_errors(), true));
    }

    if (!sqlsrv_execute($stmtOrdenes)) {
        throw new Exception("Error ejecutando consulta de órdenes: " . print_r(sqlsrv_errors(), true));
    }

    $ordenes = array();
    while ($row = sqlsrv_fetch_array($stmtOrdenes, SQLSRV_FETCH_ASSOC)) {
        // Convertir objetos DateTime a string
        foreach ($row as $key => $value) {
            if ($value instanceof DateTime) {
                $row[$key] = $value->format('Y-m-d H:i:s');
            }
        }
        $ordenes[] = $row;
    }
    sqlsrv_free_stmt($stmtOrdenes);

    // ========== CALCULAR ESTADÍSTICAS ==========
    $totalOSAbiertas = count($ordenes);
    $osSinTecnico = 0;
    $osPorTipoServicio = array();
    $osPorCliente = array();
    $osPorEstadoOperativo = array();
    $totalDiasEstadia = 0;
    $contadorDias = 0;

    foreach ($ordenes as $orden) {
        // Contar OS sin técnico
        if ($orden['SinTecnico'] == 1) {
            $osSinTecnico++;
        }

        // Agrupar por tipo de servicio
        $tipoServicio = $orden['TipoServicio'] ?? 'Sin Tipo';
        if (!isset($osPorTipoServicio[$tipoServicio])) {
            $osPorTipoServicio[$tipoServicio] = 0;
        }
        $osPorTipoServicio[$tipoServicio]++;

        // Agrupar por cliente
        $cliente = $orden['NombreCliente'] ?? 'Sin Cliente';
        if (!isset($osPorCliente[$cliente])) {
            $osPorCliente[$cliente] = 0;
        }
        $osPorCliente[$cliente]++;

        // Agrupar por estado operativo
        $estadoOp = $orden['EstadoOperativo'] ?? 'Sin Estado';
        if (!isset($osPorEstadoOperativo[$estadoOp])) {
            $osPorEstadoOperativo[$estadoOp] = 0;
        }
        $osPorEstadoOperativo[$estadoOp]++;

        // Calcular promedio de días de estadía
        if (isset($orden['DiasEstadia']) && is_numeric($orden['DiasEstadia'])) {
            $totalDiasEstadia += $orden['DiasEstadia'];
            $contadorDias++;
        }
    }

    // Ordenar arrays por cantidad (descendente) y tomar top 10
    arsort($osPorTipoServicio);
    arsort($osPorCliente);
    arsort($osPorEstadoOperativo);

    $osPorTipoServicio = array_slice($osPorTipoServicio, 0, 10, true);
    $osPorCliente = array_slice($osPorCliente, 0, 10, true);
    $osPorEstadoOperativo = array_slice($osPorEstadoOperativo, 0, 10, true);

    $promedioDiasEstadia = $contadorDias > 0 ? round($totalDiasEstadia / $contadorDias, 1) : 0;

    // ========== CONSULTA ADICIONAL - OS POR DÍA (ÚLTIMOS 7 DÍAS) ==========
    $sqlPorDia = "
    SELECT
        CONVERT(VARCHAR(10), o.FechaHoraOrden, 120) AS Fecha,
        COUNT(*) AS CantidadOS
    FROM tdsOrdenesServicio o (NOLOCK)
    WHERE
        o.estadoOrden = 'S'
        AND o.FechaHoraOrden >= ?
        AND o.FechaHoraOrden <= GETDATE()
    GROUP BY CONVERT(VARCHAR(10), o.FechaHoraOrden, 120)
    ORDER BY Fecha DESC
    ";

    $stmtPorDia = sqlsrv_prepare($conn, $sqlPorDia, array($fechaInicio));
    if ($stmtPorDia === false) {
        throw new Exception("Error preparando consulta por día: " . print_r(sqlsrv_errors(), true));
    }

    if (!sqlsrv_execute($stmtPorDia)) {
        throw new Exception("Error ejecutando consulta por día: " . print_r(sqlsrv_errors(), true));
    }

    $osPorDia = array();
    while ($row = sqlsrv_fetch_array($stmtPorDia, SQLSRV_FETCH_ASSOC)) {
        $osPorDia[] = $row;
    }
    sqlsrv_free_stmt($stmtPorDia);

    // Cerrar conexión
    sqlsrv_close($conn);

    // ========== RESPUESTA JSON ==========
    echo json_encode([
        'success' => true,
        'estadisticas' => [
            'totalOSAbiertas' => $totalOSAbiertas,
            'osSinTecnico' => $osSinTecnico,
            'osConTecnico' => $totalOSAbiertas - $osSinTecnico,
            'promedioDiasEstadia' => $promedioDiasEstadia
        ],
        'osPorTipoServicio' => $osPorTipoServicio,
        'osPorCliente' => $osPorCliente,
        'osPorEstadoOperativo' => $osPorEstadoOperativo,
        'osPorDia' => $osPorDia,
        'ordenes' => $ordenes,
        'periodo' => [
            'fechaInicio' => $fechaInicio,
            'fechaFin' => $fechaActual
        ],
        'ultima_actualizacion' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // En caso de error, devolver mensaje de error
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'ultima_actualizacion' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}
?>
