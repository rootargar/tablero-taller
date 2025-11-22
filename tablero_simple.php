<?php
// Incluir archivo de conexión (igual que cotizador.php)
include 'conexion.php';

// Calcular fecha de inicio
$fechaInicio = date('Ymd', strtotime('-7 days'));

// Consulta principal
$sql = "
SELECT TOP 100
    o.IdOrdenServicio,
    o.NumeroOrden,
    ISNULL(a.NumArticulo, '-') AS Unidad,
    ISNULL(t.Nombre, 'Sin Tipo') AS TipoServicio,
    ISNULL(c.NombreCalculado, 'Sin Cliente') AS NombreCliente,
    o.FechaHoraOrden,
    CASE o.estadoOrden WHEN 'S' THEN 'Abierta' ELSE 'Otro' END AS EstadoOS
FROM tdsOrdenesServicio o (NOLOCK)
    INNER JOIN tdsTiposervicio t (NOLOCK) ON o.IdtipoServicio = t.IdtipoServicio
    INNER JOIN cpcClientes c (NOLOCK) ON c.IdCliente = o.IdCliente
    LEFT JOIN invArticulos a (NOLOCK) ON o.IdArticulo = a.IdArticulo
WHERE o.estadoOrden = 'S' AND o.FechaHoraOrden >= '$fechaInicio'
ORDER BY o.NumeroOrden DESC
";

$result = sqlsrv_query($conn, $sql);
if ($result === false) {
    die(print_r(sqlsrv_errors(), true));
}

// Recolectar datos
$ordenes = array();
$totalOS = 0;
$porTipo = array();
$porCliente = array();

while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
    if ($row['FechaHoraOrden'] instanceof DateTime) {
        $row['FechaHoraOrden'] = $row['FechaHoraOrden']->format('Y-m-d H:i:s');
    }

    $ordenes[] = $row;
    $totalOS++;

    $tipo = $row['TipoServicio'];
    $porTipo[$tipo] = ($porTipo[$tipo] ?? 0) + 1;

    $cliente = $row['NombreCliente'];
    $porCliente[$cliente] = ($porCliente[$cliente] ?? 0) + 1;
}

sqlsrv_free_stmt($result);

// Ordenar
arsort($porTipo);
arsort($porCliente);
$porTipo = array_slice($porTipo, 0, 10, true);
$porCliente = array_slice($porCliente, 0, 10, true);

sqlsrv_close($conn);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tablero de Taller</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
        }
        .header {
            background: rgba(0, 0, 0, 0.3);
            padding: 2rem;
            text-align: center;
        }
        .header h1 { font-size: 3rem; margin-bottom: 1rem; }
        .container { max-width: 1920px; margin: 0 auto; padding: 2rem; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            color: #333;
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
        }
        .stat-number {
            font-size: 3.5rem;
            font-weight: 900;
            color: #667eea;
        }
        .stat-label {
            font-size: 1.2rem;
            color: #666;
            margin-top: 0.5rem;
        }
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        .chart-box {
            background: rgba(255, 255, 255, 0.95);
            padding: 2rem;
            border-radius: 15px;
        }
        .chart-title {
            color: #333;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-align: center;
        }
        table {
            width: 100%;
            background: white;
            border-radius: 15px;
            overflow: hidden;
        }
        th, td {
            padding: 1rem;
            text-align: left;
            color: #333;
        }
        th {
            background: #667eea;
            color: white;
        }
        tr:nth-child(even) { background: #f9f9f9; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔧 TABLERO DE TALLER</h1>
        <p>Última Semana - <?php echo date('Y-m-d H:i:s'); ?></p>
    </div>

    <div class="container">
        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $totalOS; ?></div>
                <div class="stat-label">Total OS Abiertas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($porTipo); ?></div>
                <div class="stat-label">Tipos de Servicio</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($porCliente); ?></div>
                <div class="stat-label">Clientes</div>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="charts-grid">
            <div class="chart-box">
                <div class="chart-title">OS por Tipo de Servicio</div>
                <canvas id="chartTipo"></canvas>
            </div>
            <div class="chart-box">
                <div class="chart-title">OS por Cliente (Top 10)</div>
                <canvas id="chartCliente"></canvas>
            </div>
        </div>

        <!-- Tabla -->
        <table>
            <thead>
                <tr>
                    <th>Número</th>
                    <th>Unidad</th>
                    <th>Tipo Servicio</th>
                    <th>Cliente</th>
                    <th>Fecha</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($ordenes, 0, 20) as $o): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($o['NumeroOrden']); ?></strong></td>
                    <td><?php echo htmlspecialchars($o['Unidad']); ?></td>
                    <td><?php echo htmlspecialchars($o['TipoServicio']); ?></td>
                    <td><?php echo htmlspecialchars($o['NombreCliente']); ?></td>
                    <td><?php echo htmlspecialchars($o['FechaHoraOrden']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
        // Gráfico Tipo de Servicio
        new Chart(document.getElementById('chartTipo'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_keys($porTipo)); ?>,
                datasets: [{
                    label: 'Cantidad',
                    data: <?php echo json_encode(array_values($porTipo)); ?>,
                    backgroundColor: 'rgba(102, 126, 234, 0.8)'
                }]
            },
            options: { responsive: true, plugins: { legend: { display: false } } }
        });

        // Gráfico Clientes
        new Chart(document.getElementById('chartCliente'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_keys($porCliente)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_values($porCliente)); ?>,
                    backgroundColor: [
                        'rgba(102, 126, 234, 0.8)',
                        'rgba(118, 75, 162, 0.8)',
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 206, 86, 0.8)',
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(153, 102, 255, 0.8)',
                        'rgba(255, 159, 64, 0.8)',
                        'rgba(199, 199, 199, 0.8)',
                        'rgba(83, 102, 255, 0.8)'
                    ]
                }]
            },
            options: { responsive: true }
        });

        // Auto-refresh cada 10 minutos
        setTimeout(() => location.reload(), 600000);
    </script>
</body>
</html>
