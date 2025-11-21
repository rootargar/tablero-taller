<?php
// Test detallado de consultas
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'conexion.php';

$fechaInicio = date('Y-m-d', strtotime('-7 days'));
$resultados = [];

try {
    // TEST 1: Consulta básica de órdenes
    $resultados['test1'] = ['nombre' => 'Consulta básica de órdenes', 'status' => 'iniciando'];

    $sql1 = "
    SELECT TOP 5
        o.IdOrdenServicio,
        o.NumeroOrden,
        o.FechaHoraOrden
    FROM tdsOrdenesServicio o (NOLOCK)
    WHERE o.estadoOrden = 'S'
        AND o.FechaHoraOrden >= CAST('$fechaInicio' AS DATETIME)
    ORDER BY o.NumeroOrden DESC
    ";

    $stmt1 = sqlsrv_query($conn, $sql1);
    if ($stmt1 === false) {
        $resultados['test1']['status'] = 'error';
        $resultados['test1']['error'] = sqlsrv_errors();
    } else {
        $count1 = 0;
        while ($row = sqlsrv_fetch_array($stmt1, SQLSRV_FETCH_ASSOC)) {
            $count1++;
        }
        $resultados['test1']['status'] = 'ok';
        $resultados['test1']['registros'] = $count1;
        sqlsrv_free_stmt($stmt1);
    }

    // TEST 2: Consulta con JOINs
    $resultados['test2'] = ['nombre' => 'Consulta con JOINs (tipos y clientes)', 'status' => 'iniciando'];

    $sql2 = "
    SELECT TOP 5
        o.IdOrdenServicio,
        o.NumeroOrden,
        t.Nombre AS TipoServicio,
        c.NombreCalculado AS NombreCliente
    FROM tdsOrdenesServicio o (NOLOCK)
        INNER JOIN tdsTiposervicio t (NOLOCK) ON o.IdtipoServicio = t.IdtipoServicio
        INNER JOIN cpcClientes c (NOLOCK) ON c.IdCliente = o.IdCliente
    WHERE o.estadoOrden = 'S'
        AND o.FechaHoraOrden >= CAST('$fechaInicio' AS DATETIME)
    ORDER BY o.NumeroOrden DESC
    ";

    $stmt2 = sqlsrv_query($conn, $sql2);
    if ($stmt2 === false) {
        $resultados['test2']['status'] = 'error';
        $resultados['test2']['error'] = sqlsrv_errors();
    } else {
        $count2 = 0;
        while ($row = sqlsrv_fetch_array($stmt2, SQLSRV_FETCH_ASSOC)) {
            $count2++;
        }
        $resultados['test2']['status'] = 'ok';
        $resultados['test2']['registros'] = $count2;
        sqlsrv_free_stmt($stmt2);
    }

    // TEST 3: Consulta de técnicos
    $resultados['test3'] = ['nombre' => 'Consulta de técnicos asignados', 'status' => 'iniciando'];

    $sql3 = "
    SELECT TOP 5
        o.IdOrdenServicio,
        m.Nombre AS NombreTecnico
    FROM tdsTrabajosTecnicos tt (NOLOCK)
        INNER JOIN tdsTecnicos m (NOLOCK) ON tt.IdTecnico = m.IdTecnico
        INNER JOIN tdsTrabajos t (NOLOCK) ON t.IdTrabajo = tt.IdTrabajo
        INNER JOIN tdsOrdenesServicio o (NOLOCK) ON t.IdOrdenServicio = o.IdOrdenServicio
    WHERE o.estadoOrden = 'S'
        AND o.FechaHoraOrden >= CAST('$fechaInicio' AS DATETIME)
    ";

    $stmt3 = sqlsrv_query($conn, $sql3);
    if ($stmt3 === false) {
        $resultados['test3']['status'] = 'error';
        $resultados['test3']['error'] = sqlsrv_errors();
    } else {
        $count3 = 0;
        while ($row = sqlsrv_fetch_array($stmt3, SQLSRV_FETCH_ASSOC)) {
            $count3++;
        }
        $resultados['test3']['status'] = 'ok';
        $resultados['test3']['registros'] = $count3;
        sqlsrv_free_stmt($stmt3);
    }

    // TEST 4: Consulta de estados operativos
    $resultados['test4'] = ['nombre' => 'Consulta de estados operativos', 'status' => 'iniciando'];

    $sql4 = "
    SELECT TOP 5
        po.IdOrdenServicio,
        p.Nombre AS EstadoOperativo
    FROM tdsEstadosOperativosOrdenes po (NOLOCK)
        INNER JOIN tdsEstadosOperativos p (NOLOCK) ON p.IdEstadoOperativo = po.IdEstadoOperativo
        INNER JOIN tdsOrdenesServicio o (NOLOCK) ON o.IdOrdenServicio = po.IdOrdenServicio
    WHERE o.estadoOrden = 'S'
        AND o.FechaHoraOrden >= CAST('$fechaInicio' AS DATETIME)
    ";

    $stmt4 = sqlsrv_query($conn, $sql4);
    if ($stmt4 === false) {
        $resultados['test4']['status'] = 'error';
        $resultados['test4']['error'] = sqlsrv_errors();
    } else {
        $count4 = 0;
        while ($row = sqlsrv_fetch_array($stmt4, SQLSRV_FETCH_ASSOC)) {
            $count4++;
        }
        $resultados['test4']['status'] = 'ok';
        $resultados['test4']['registros'] = $count4;
        sqlsrv_free_stmt($stmt4);
    }

    // TEST 5: Consulta con LEFT JOIN a invArticulos
    $resultados['test5'] = ['nombre' => 'Consulta con LEFT JOIN a invArticulos', 'status' => 'iniciando'];

    $sql5 = "
    SELECT TOP 5
        o.IdOrdenServicio,
        o.NumeroOrden,
        a.NumArticulo AS Unidad
    FROM tdsOrdenesServicio o (NOLOCK)
        LEFT JOIN invArticulos a (NOLOCK) ON o.IdArticulo = a.IdArticulo
    WHERE o.estadoOrden = 'S'
        AND o.FechaHoraOrden >= CAST('$fechaInicio' AS DATETIME)
    ORDER BY o.NumeroOrden DESC
    ";

    $stmt5 = sqlsrv_query($conn, $sql5);
    if ($stmt5 === false) {
        $resultados['test5']['status'] = 'error';
        $resultados['test5']['error'] = sqlsrv_errors();
    } else {
        $count5 = 0;
        while ($row = sqlsrv_fetch_array($stmt5, SQLSRV_FETCH_ASSOC)) {
            $count5++;
        }
        $resultados['test5']['status'] = 'ok';
        $resultados['test5']['registros'] = $count5;
        sqlsrv_free_stmt($stmt5);
    }

    sqlsrv_close($conn);

    echo json_encode([
        'success' => true,
        'fecha_inicio' => $fechaInicio,
        'tests' => $resultados
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'tests' => $resultados
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>
