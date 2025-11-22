<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start();

try {
    require_once 'conexion.php';

    if ($conn === false) {
        throw new Exception("Conexión falló");
    }

    $fechaInicio = date('Ymd', strtotime('-7 days'));
    $fechaActual = date('Y-m-d');

    // PASO 1: Consulta principal expandida (100 órdenes con más campos)
    $sql = "
    SELECT TOP 100
        o.IdOrdenServicio,
        o.NumeroOrden,
        ISNULL(a.NumArticulo, '-') AS Unidad,
        ISNULL(t.Nombre, 'Sin Tipo') AS TipoServicio,
        ISNULL(c.NombreCalculado, 'Sin Cliente') AS NombreCliente,
        o.FechaHoraOrden,
        CASE o.estadoOrden
            WHEN 'S' THEN 'Abierta'
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

    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt === false) {
        throw new Exception("Error consulta principal: " . json_encode(sqlsrv_errors()));
    }

    $ordenes = array();
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Convertir DateTime a string
        if ($row['FechaHoraOrden'] instanceof DateTime) {
            $row['FechaHoraOrden'] = $row['FechaHoraOrden']->format('Y-m-d H:i:s');
        }
        // Inicializar campos por defecto
        $row['NombreTecnico'] = null;
        $row['SinTecnico'] = 1;
        $row['EstadoOperativo'] = 'Sin Estado';
        $row['DiasEstadia'] = 0;

        $ordenes[] = $row;
    }
    sqlsrv_free_stmt($stmt);

    // PASO 2: Consulta de técnicos (simple)
    $sql2 = "
    SELECT DISTINCT o.IdOrdenServicio, m.Nombre AS NombreTecnico
    FROM tdsTrabajosTecnicos tt (NOLOCK)
        INNER JOIN tdsTecnicos m (NOLOCK) ON tt.IdTecnico = m.IdTecnico
        INNER JOIN tdsTrabajos t (NOLOCK) ON t.IdTrabajo = tt.IdTrabajo
        INNER JOIN tdsOrdenesServicio o (NOLOCK) ON t.IdOrdenServicio = o.IdOrdenServicio
    WHERE o.estadoOrden = 'S' AND o.FechaHoraOrden >= '$fechaInicio'
    ";

    $stmt2 = sqlsrv_query($conn, $sql2);
    $tecnicos = array();
    if ($stmt2 !== false) {
        while ($row = sqlsrv_fetch_array($stmt2, SQLSRV_FETCH_ASSOC)) {
            $id = $row['IdOrdenServicio'];
            if (!isset($tecnicos[$id])) $tecnicos[$id] = array();
            $tecnicos[$id][] = $row['NombreTecnico'];
        }
        sqlsrv_free_stmt($stmt2);
    }

    // PASO 3: Consulta de estados operativos
    $sql3 = "
    SELECT po.IdOrdenServicio, p.Nombre AS EstadoOperativo,
           DATEDIFF(DAY, po.FechaHoraInicio, ISNULL(po.FechaHoraFin, GETDATE())) AS DiasEstadia
    FROM tdsEstadosOperativosOrdenes po (NOLOCK)
        INNER JOIN tdsEstadosOperativos p (NOLOCK) ON p.IdEstadoOperativo = po.IdEstadoOperativo
        INNER JOIN tdsOrdenesServicio o (NOLOCK) ON o.IdOrdenServicio = po.IdOrdenServicio
    WHERE o.estadoOrden = 'S' AND o.FechaHoraOrden >= '$fechaInicio'
    ";

    $stmt3 = sqlsrv_query($conn, $sql3);
    $estados = array();
    if ($stmt3 !== false) {
        while ($row = sqlsrv_fetch_array($stmt3, SQLSRV_FETCH_ASSOC)) {
            $id = $row['IdOrdenServicio'];
            if (!isset($estados[$id])) {
                $estados[$id] = array('estado' => $row['EstadoOperativo'], 'dias' => $row['DiasEstadia']);
            }
        }
        sqlsrv_free_stmt($stmt3);
    }

    // PASO 4: Combinar datos
    foreach ($ordenes as &$orden) {
        $id = $orden['IdOrdenServicio'];

        if (isset($tecnicos[$id])) {
            $orden['NombreTecnico'] = implode(', ', $tecnicos[$id]);
            $orden['SinTecnico'] = 0;
        }

        if (isset($estados[$id])) {
            $orden['EstadoOperativo'] = $estados[$id]['estado'];
            $orden['DiasEstadia'] = $estados[$id]['dias'];
        }
    }

    // PASO 5: Calcular estadísticas
    $totalOS = count($ordenes);
    $sinTecnico = 0;
    $porTipo = array();
    $porCliente = array();
    $porEstado = array();
    $totalDias = 0;
    $contDias = 0;

    foreach ($ordenes as $o) {
        if ($o['SinTecnico'] == 1) $sinTecnico++;

        $tipo = $o['TipoServicio'];
        $porTipo[$tipo] = ($porTipo[$tipo] ?? 0) + 1;

        $cliente = $o['NombreCliente'];
        $porCliente[$cliente] = ($porCliente[$cliente] ?? 0) + 1;

        $estado = $o['EstadoOperativo'];
        $porEstado[$estado] = ($porEstado[$estado] ?? 0) + 1;

        if ($o['DiasEstadia'] > 0) {
            $totalDias += $o['DiasEstadia'];
            $contDias++;
        }
    }

    arsort($porTipo);
    arsort($porCliente);
    arsort($porEstado);

    // PASO 6: Consulta por día
    $sql4 = "
    SELECT CONVERT(VARCHAR(10), o.FechaHoraOrden, 120) AS Fecha, COUNT(*) AS CantidadOS
    FROM tdsOrdenesServicio o (NOLOCK)
    WHERE o.estadoOrden = 'S' AND o.FechaHoraOrden >= '$fechaInicio'
    GROUP BY CONVERT(VARCHAR(10), o.FechaHoraOrden, 120)
    ORDER BY Fecha DESC
    ";

    $stmt4 = sqlsrv_query($conn, $sql4);
    $porDia = array();
    if ($stmt4 !== false) {
        while ($row = sqlsrv_fetch_array($stmt4, SQLSRV_FETCH_ASSOC)) {
            $porDia[] = $row;
        }
        sqlsrv_free_stmt($stmt4);
    }

    sqlsrv_close($conn);

    // Respuesta final
    $response = [
        'success' => true,
        'estadisticas' => [
            'totalOSAbiertas' => $totalOS,
            'osSinTecnico' => $sinTecnico,
            'osConTecnico' => $totalOS - $sinTecnico,
            'promedioDiasEstadia' => $contDias > 0 ? round($totalDias / $contDias, 1) : 0
        ],
        'osPorTipoServicio' => array_slice($porTipo, 0, 10, true),
        'osPorCliente' => array_slice($porCliente, 0, 10, true),
        'osPorEstadoOperativo' => array_slice($porEstado, 0, 10, true),
        'osPorDia' => $porDia,
        'ordenes' => $ordenes,
        'periodo' => ['fechaInicio' => $fechaInicio, 'fechaFin' => $fechaActual],
        'ultima_actualizacion' => date('Y-m-d H:i:s')
    ];

    ob_end_clean();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'line' => $e->getLine()], JSON_UNESCAPED_UNICODE);
}
?>
