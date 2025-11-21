<?php
// Incluir el archivo de conexión
require_once 'conexion.php';

// Habilitar reporte de errores para debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Configurar la respuesta como JSON
header('Content-Type: application/json; charset=utf-8');

try {
    // Verificar que la conexión exista
    if ($conn === false) {
        throw new Exception("Conexión no establecida");
    }

    // Calcular fecha de hace 7 días en formato ISO 8601 (YYYYMMDD) que SQL Server siempre acepta
    $fechaInicio = date('Ymd', strtotime('-7 days'));
    $fechaActual = date('Y-m-d');

    // ========== CONSULTA SIMPLIFICADA - ÓRDENES ABIERTAS DE LA ÚLTIMA SEMANA ==========
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
    FROM
        tdsOrdenesServicio o (NOLOCK)
        INNER JOIN tdsTiposervicio t (NOLOCK) ON o.IdtipoServicio = t.IdtipoServicio
        INNER JOIN cpcClientes c (NOLOCK) ON c.IdCliente = o.IdCliente
        LEFT JOIN invArticulos a (NOLOCK) ON o.IdArticulo = a.IdArticulo
    WHERE
        o.estadoOrden = 'S'
        AND o.FechaHoraOrden >= '$fechaInicio'
    ORDER BY o.NumeroOrden DESC
    ";

    $stmtOrdenes = sqlsrv_query($conn, $sqlOrdenes);

    if ($stmtOrdenes === false) {
        $errors = sqlsrv_errors();
        throw new Exception("Error en consulta de órdenes: " . json_encode($errors, JSON_UNESCAPED_UNICODE));
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

    // ========== OBTENER TÉCNICOS ASIGNADOS ==========
    $sqlTecnicos = "
    SELECT DISTINCT
        o.IdOrdenServicio,
        m.Nombre AS NombreTecnico
    FROM
        tdsTrabajosTecnicos tt (NOLOCK)
        INNER JOIN tdsTecnicos m (NOLOCK) ON tt.IdTecnico = m.IdTecnico
        INNER JOIN tdsTrabajos t (NOLOCK) ON t.IdTrabajo = tt.IdTrabajo
        INNER JOIN tdsOrdenesServicio o (NOLOCK) ON t.IdOrdenServicio = o.IdOrdenServicio
    WHERE
        o.estadoOrden = 'S'
        AND o.FechaHoraOrden >= '$fechaInicio'
    ";

    $stmtTecnicos = sqlsrv_query($conn, $sqlTecnicos);

    $tecnicosPorOrden = array();
    if ($stmtTecnicos !== false) {
        while ($row = sqlsrv_fetch_array($stmtTecnicos, SQLSRV_FETCH_ASSOC)) {
            $idOrden = $row['IdOrdenServicio'];
            if (!isset($tecnicosPorOrden[$idOrden])) {
                $tecnicosPorOrden[$idOrden] = array();
            }
            $tecnicosPorOrden[$idOrden][] = $row['NombreTecnico'];
        }
        sqlsrv_free_stmt($stmtTecnicos);
    }

    // ========== OBTENER ESTADOS OPERATIVOS ==========
    $sqlEstados = "
    SELECT
        po.IdOrdenServicio,
        p.Nombre AS EstadoOperativo,
        DATEDIFF(DAY, po.FechaHoraInicio, ISNULL(po.FechaHoraFin, GETDATE())) AS DiasEstadia
    FROM
        tdsEstadosOperativosOrdenes po (NOLOCK)
        INNER JOIN tdsEstadosOperativos p (NOLOCK) ON p.IdEstadoOperativo = po.IdEstadoOperativo
        INNER JOIN tdsOrdenesServicio o (NOLOCK) ON o.IdOrdenServicio = po.IdOrdenServicio
    WHERE
        o.estadoOrden = 'S'
        AND o.FechaHoraOrden >= '$fechaInicio'
    ";

    $stmtEstados = sqlsrv_query($conn, $sqlEstados);

    $estadosPorOrden = array();
    if ($stmtEstados !== false) {
        while ($row = sqlsrv_fetch_array($stmtEstados, SQLSRV_FETCH_ASSOC)) {
            $idOrden = $row['IdOrdenServicio'];
            if (!isset($estadosPorOrden[$idOrden])) {
                $estadosPorOrden[$idOrden] = array(
                    'estado' => $row['EstadoOperativo'],
                    'dias' => $row['DiasEstadia']
                );
            }
        }
        sqlsrv_free_stmt($stmtEstados);
    }

    // ========== COMBINAR DATOS ==========
    foreach ($ordenes as &$orden) {
        $idOrden = $orden['IdOrdenServicio'];

        // Agregar técnicos
        if (isset($tecnicosPorOrden[$idOrden])) {
            $orden['NombreTecnico'] = implode(', ', $tecnicosPorOrden[$idOrden]);
            $orden['SinTecnico'] = 0;
        } else {
            $orden['NombreTecnico'] = null;
            $orden['SinTecnico'] = 1;
        }

        // Agregar estado operativo
        if (isset($estadosPorOrden[$idOrden])) {
            $orden['EstadoOperativo'] = $estadosPorOrden[$idOrden]['estado'];
            $orden['DiasEstadia'] = $estadosPorOrden[$idOrden]['dias'];
        } else {
            $orden['EstadoOperativo'] = 'Sin Estado';
            $orden['DiasEstadia'] = 0;
        }
    }

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

    // ========== CONSULTA - OS POR DÍA (ÚLTIMOS 7 DÍAS) ==========
    $sqlPorDia = "
    SELECT
        CONVERT(VARCHAR(10), o.FechaHoraOrden, 120) AS Fecha,
        COUNT(*) AS CantidadOS
    FROM tdsOrdenesServicio o (NOLOCK)
    WHERE
        o.estadoOrden = 'S'
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
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    // En caso de error, devolver mensaje de error detallado
    http_response_code(500);

    $errorResponse = [
        'success' => false,
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
        'ultima_actualizacion' => date('Y-m-d H:i:s')
    ];

    // Agregar errores de SQL Server si existen
    $sqlErrors = sqlsrv_errors();
    if ($sqlErrors !== null) {
        $errorResponse['sql_errors'] = $sqlErrors;
    }

    echo json_encode($errorResponse, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>
