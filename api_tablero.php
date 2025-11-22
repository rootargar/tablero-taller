<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start(); // Buffer de salida para evitar contenido extra

try {
    require_once 'conexion.php';

    if ($conn === false) {
        throw new Exception("Conexión a BD falló");
    }

    // Fecha de inicio (últimos 7 días) en formato YYYYMMDD
    $fechaInicio = date('Ymd', strtotime('-7 days'));
    $fechaActual = date('Y-m-d');

    // ========== CONSULTA 1: ÓRDENES BÁSICAS ==========
    $sqlOrdenes = "
    SELECT TOP 100
        o.IdOrdenServicio,
        o.NumeroOrden,
        ISNULL(a.NumArticulo, '-') AS Unidad,
        ISNULL(t.Nombre, 'Sin Tipo') AS TipoServicio,
        ISNULL(c.NombreCalculado, 'Sin Cliente') AS NombreCliente,
        o.FechaHoraOrden,
        o.FechaHoraPromEntrega,
        CASE o.estadoOrden
            WHEN 'S' THEN 'Abierta'
            WHEN 'C' THEN 'Cerrada'
            WHEN 'F' THEN 'Facturada'
            ELSE 'Otro'
        END AS EstadoOS
    FROM tdsOrdenesServicio o (NOLOCK)
        INNER JOIN tdsTiposervicio t (NOLOCK) ON o.IdtipoServicio = t.IdtipoServicio
        INNER JOIN cpcClientes c (NOLOCK) ON c.IdCliente = o.IdCliente
        LEFT JOIN invArticulos a (NOLOCK) ON o.IdArticulo = a.IdArticulo
    WHERE o.estadoOrden = 'S'
        AND o.FechaHoraOrden >= '$fechaInicio'
    ORDER BY o.NumeroOrden DESC
    ";

    $stmtOrdenes = sqlsrv_query($conn, $sqlOrdenes);
    if ($stmtOrdenes === false) {
        throw new Exception("Error en consulta órdenes: " . json_encode(sqlsrv_errors()));
    }

    $ordenes = array();
    while ($row = sqlsrv_fetch_array($stmtOrdenes, SQLSRV_FETCH_ASSOC)) {
        foreach ($row as $key => $value) {
            if ($value instanceof DateTime) {
                $row[$key] = $value->format('Y-m-d H:i:s');
            }
        }
        $ordenes[] = $row;
    }
    sqlsrv_free_stmt($stmtOrdenes);

    // ========== CONSULTA 2: TÉCNICOS ==========
    $sqlTecnicos = "
    SELECT DISTINCT
        o.IdOrdenServicio,
        m.Nombre AS NombreTecnico
    FROM tdsTrabajosTecnicos tt (NOLOCK)
        INNER JOIN tdsTecnicos m (NOLOCK) ON tt.IdTecnico = m.IdTecnico
        INNER JOIN tdsTrabajos t (NOLOCK) ON t.IdTrabajo = tt.IdTrabajo
        INNER JOIN tdsOrdenesServicio o (NOLOCK) ON t.IdOrdenServicio = o.IdOrdenServicio
    WHERE o.estadoOrden = 'S'
        AND o.FechaHoraOrden >= '$fechaInicio'
    ";

    $stmtTecnicos = sqlsrv_query($conn, $sqlTecnicos);
    $tecnicosPorOrden = array();

    if ($stmtTecnicos !== false) {
        while ($row = sqlsrv_fetch_array($stmtTecnicos, SQLSRV_FETCH_ASSOC)) {
            $id = $row['IdOrdenServicio'];
            if (!isset($tecnicosPorOrden[$id])) {
                $tecnicosPorOrden[$id] = array();
            }
            $tecnicosPorOrden[$id][] = $row['NombreTecnico'];
        }
        sqlsrv_free_stmt($stmtTecnicos);
    }

    // ========== CONSULTA 3: ESTADOS OPERATIVOS ==========
    $sqlEstados = "
    SELECT
        po.IdOrdenServicio,
        p.Nombre AS EstadoOperativo,
        DATEDIFF(DAY, po.FechaHoraInicio, ISNULL(po.FechaHoraFin, GETDATE())) AS DiasEstadia
    FROM tdsEstadosOperativosOrdenes po (NOLOCK)
        INNER JOIN tdsEstadosOperativos p (NOLOCK) ON p.IdEstadoOperativo = po.IdEstadoOperativo
        INNER JOIN tdsOrdenesServicio o (NOLOCK) ON o.IdOrdenServicio = po.IdOrdenServicio
    WHERE o.estadoOrden = 'S'
        AND o.FechaHoraOrden >= '$fechaInicio'
    ";

    $stmtEstados = sqlsrv_query($conn, $sqlEstados);
    $estadosPorOrden = array();

    if ($stmtEstados !== false) {
        while ($row = sqlsrv_fetch_array($stmtEstados, SQLSRV_FETCH_ASSOC)) {
            $id = $row['IdOrdenServicio'];
            if (!isset($estadosPorOrden[$id])) {
                $estadosPorOrden[$id] = array(
                    'estado' => $row['EstadoOperativo'],
                    'dias' => $row['DiasEstadia']
                );
            }
        }
        sqlsrv_free_stmt($stmtEstados);
    }

    // ========== COMBINAR DATOS ==========
    foreach ($ordenes as &$orden) {
        $id = $orden['IdOrdenServicio'];

        // Técnicos
        if (isset($tecnicosPorOrden[$id])) {
            $orden['NombreTecnico'] = implode(', ', $tecnicosPorOrden[$id]);
            $orden['SinTecnico'] = 0;
        } else {
            $orden['NombreTecnico'] = null;
            $orden['SinTecnico'] = 1;
        }

        // Estados
        if (isset($estadosPorOrden[$id])) {
            $orden['EstadoOperativo'] = $estadosPorOrden[$id]['estado'];
            $orden['DiasEstadia'] = $estadosPorOrden[$id]['dias'];
        } else {
            $orden['EstadoOperativo'] = 'Sin Estado';
            $orden['DiasEstadia'] = 0;
        }
    }

    // ========== ESTADÍSTICAS ==========
    $totalOSAbiertas = count($ordenes);
    $osSinTecnico = 0;
    $osPorTipoServicio = array();
    $osPorCliente = array();
    $osPorEstadoOperativo = array();
    $totalDiasEstadia = 0;
    $contadorDias = 0;

    foreach ($ordenes as $orden) {
        if ($orden['SinTecnico'] == 1) $osSinTecnico++;

        $tipo = $orden['TipoServicio'] ?? 'Sin Tipo';
        $osPorTipoServicio[$tipo] = ($osPorTipoServicio[$tipo] ?? 0) + 1;

        $cliente = $orden['NombreCliente'] ?? 'Sin Cliente';
        $osPorCliente[$cliente] = ($osPorCliente[$cliente] ?? 0) + 1;

        $estado = $orden['EstadoOperativo'] ?? 'Sin Estado';
        $osPorEstadoOperativo[$estado] = ($osPorEstadoOperativo[$estado] ?? 0) + 1;

        if (isset($orden['DiasEstadia']) && is_numeric($orden['DiasEstadia'])) {
            $totalDiasEstadia += $orden['DiasEstadia'];
            $contadorDias++;
        }
    }

    arsort($osPorTipoServicio);
    arsort($osPorCliente);
    arsort($osPorEstadoOperativo);

    $osPorTipoServicio = array_slice($osPorTipoServicio, 0, 10, true);
    $osPorCliente = array_slice($osPorCliente, 0, 10, true);
    $osPorEstadoOperativo = array_slice($osPorEstadoOperativo, 0, 10, true);

    $promedioDiasEstadia = $contadorDias > 0 ? round($totalDiasEstadia / $contadorDias, 1) : 0;

    // ========== CONSULTA 4: OS POR DÍA ==========
    $sqlPorDia = "
    SELECT
        CONVERT(VARCHAR(10), o.FechaHoraOrden, 120) AS Fecha,
        COUNT(*) AS CantidadOS
    FROM tdsOrdenesServicio o (NOLOCK)
    WHERE o.estadoOrden = 'S'
        AND o.FechaHoraOrden >= '$fechaInicio'
    GROUP BY CONVERT(VARCHAR(10), o.FechaHoraOrden, 120)
    ORDER BY Fecha DESC
    ";

    $stmtPorDia = sqlsrv_query($conn, $sqlPorDia);
    $osPorDia = array();

    if ($stmtPorDia !== false) {
        while ($row = sqlsrv_fetch_array($stmtPorDia, SQLSRV_FETCH_ASSOC)) {
            $osPorDia[] = $row;
        }
        sqlsrv_free_stmt($stmtPorDia);
    }

    sqlsrv_close($conn);

    // ========== RESPUESTA FINAL ==========
    $response = [
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
    ];

    ob_end_clean();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}
?>
