<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$tipo_usuario = $_SESSION['tipo'];
$nombre_usuario = $_SESSION['nombre_completo'];

$permisos = getPermisos($tipo_usuario);

if (!$permisos['ver_cierre_diario'] && !$permisos['ver_reportes_mensuales'] && !$permisos['ver_reportes_generales']) {
    die("No tiene permisos para ver reportes");
}

$message = '';
$messageType = '';

$fechaActual = $conn->query("SELECT CURDATE() as hoy")->fetch_assoc()['hoy'];
$mesActual = date('Y-m', strtotime($fechaActual));

$sqlDiario = "SELECT 
    COUNT(*) as total_vehiculos,
    SUM(CASE WHEN es_fijo = 0 THEN 1 ELSE 0 END) as normales,
    SUM(CASE WHEN es_fijo = 1 THEN 1 ELSE 0 END) as fijos,
    COALESCE(SUM(CASE WHEN es_fijo = 0 THEN monto_pagado ELSE 0 END), 0) as total_recaudado_bs,
    COALESCE(SUM(CASE WHEN es_fijo = 0 THEN monto_dolar ELSE 0 END), 0) as total_recaudado_usd
FROM vehiculos 
WHERE DATE(fecha_salida) = ? AND status IN ('pagado', 'fijo_salida')";

$stmt = $conn->prepare($sqlDiario);
$stmt->bind_param("s", $fechaActual);
$stmt->execute();
$resultDiario = $stmt->get_result();
$reporte = $resultDiario->fetch_assoc();

$sqlPagosFijos = "SELECT * FROM pagos_fijos WHERE fecha_pago = ? ORDER BY id DESC";
$stmt = $conn->prepare($sqlPagosFijos);
$stmt->bind_param("s", $fechaActual);
$stmt->execute();
$resultFijos = $stmt->get_result();
$pagosFijosDia = $resultFijos->fetch_all(MYSQLI_ASSOC);
$total_fijos_dia = 0;
$cantidad_fijos = 0;
foreach ($pagosFijosDia as $pf) {
    $total_fijos_dia += floatval($pf['monto']);
    $cantidad_fijos++;
}

$sqlVehiculos = "SELECT v.*, u.nombre_completo as empleado, vf.nombre_dueño as dueño_fijo FROM vehiculos v LEFT JOIN usuarios u ON v.id_usuario = u.id LEFT JOIN vehiculos_fijos vf ON v.id_vehiculo_fijo = vf.id WHERE DATE(v.fecha_salida) = ? AND v.status IN ('pagado', 'fijo_salida') AND v.es_fijo = 0 ORDER BY v.fecha_salida DESC";
$stmt = $conn->prepare($sqlVehiculos);
$stmt->bind_param("s", $fechaActual);
$stmt->execute();
$resultVehiculos = $stmt->get_result();

$sqlEnParking = "SELECT COUNT(*) as total FROM vehiculos WHERE status IN ('dentro', 'fijo_salida')";
$resultEnParking = $conn->query($sqlEnParking);
$enParking = $resultEnParking->fetch_assoc();

$sqlCierreDiario = "SELECT 
    u.id,
    u.nombre_completo,
    COUNT(v.id) as total_vehiculos,
    SUM(CASE WHEN v.es_fijo = 0 THEN v.monto_pagado ELSE 0 END) as total_normales,
    COUNT(CASE WHEN v.es_fijo = 0 AND v.status = 'pagado' THEN 1 END) as total_normales_count,
    COUNT(CASE WHEN v.es_fijo = 1 THEN 1 END) as total_fijos_count
FROM usuarios u
LEFT JOIN vehiculos v ON u.id = v.id_usuario AND DATE(v.fecha_salida) = ? AND v.status IN ('pagado', 'fijo_salida')
WHERE u.tipo = 'empleado' AND u.activo = 1
GROUP BY u.id, u.nombre_completo";

$sqlFijosByUser = "SELECT id_usuario, COUNT(*) as cantidad, SUM(monto) as total FROM pagos_fijos WHERE fecha_pago = ? GROUP BY id_usuario";
$stmt = $conn->prepare($sqlFijosByUser);
$stmt->bind_param("s", $fechaActual);
$stmt->execute();
$resultFijosByUser = $stmt->get_result();
$fijosByUser = [];
while ($row = $resultFijosByUser->fetch_assoc()) {
    $fijosByUser[$row['id_usuario']] = $row;
}

$stmt = $conn->prepare($sqlCierreDiario);
$stmt->bind_param("s", $fechaActual);
$stmt->execute();
$resultCierreDiario = $stmt->get_result();

$sqlMensual = "SELECT 
    COUNT(*) as total_vehiculos,
    SUM(CASE WHEN es_fijo = 0 THEN 1 ELSE 0 END) as normales,
    SUM(CASE WHEN es_fijo = 1 THEN 1 ELSE 0 END) as fijos,
    COALESCE(SUM(CASE WHEN es_fijo = 0 THEN monto_pagado ELSE 0 END), 0) as total_recaudado_bs,
    COALESCE(SUM(CASE WHEN es_fijo = 0 THEN monto_dolar ELSE 0 END), 0) as total_recaudado_usd
FROM vehiculos 
WHERE DATE_FORMAT(fecha_salida, '%Y-%m') = ? AND status IN ('pagado', 'fijo_salida')";

$stmt = $conn->prepare($sqlMensual);
$stmt->bind_param("s", $mesActual);
$stmt->execute();
$resultMensual = $stmt->get_result();
$reporteMensual = $resultMensual->fetch_assoc();

$sqlPagosFijosMes = "SELECT COALESCE(SUM(monto), 0) as total FROM pagos_fijos WHERE DATE_FORMAT(fecha_pago, '%Y-%m') = ?";
$stmt = $conn->prepare($sqlPagosFijosMes);
$stmt->bind_param("s", $mesActual);
$stmt->execute();
$resultFijosMes = $stmt->get_result();
$pagosFijosMes = $resultFijosMes->fetch_assoc();

$total_fijos_mes = $pagosFijosMes['total'];

$sqlMensualPorEmpleado = "SELECT 
    u.id,
    u.nombre_completo,
    COUNT(v.id) as total_vehiculos,
    SUM(CASE WHEN v.es_fijo = 0 THEN v.monto_pagado ELSE 0 END) as total_recaudado_bs,
    SUM(CASE WHEN v.es_fijo = 0 THEN v.monto_dolar ELSE 0 END) as total_recaudado_usd,
    COUNT(CASE WHEN v.es_fijo = 0 AND v.status = 'pagado' THEN 1 END) as normales_count,
    COUNT(CASE WHEN v.es_fijo = 1 THEN 1 END) as fijos_count
FROM usuarios u
LEFT JOIN vehiculos v ON u.id = v.id_usuario AND DATE_FORMAT(v.fecha_salida, '%Y-%m') = ? AND v.status IN ('pagado', 'fijo_salida')
WHERE u.tipo = 'empleado' AND u.activo = 1
GROUP BY u.id, u.nombre_completo";

$stmt = $conn->prepare($sqlMensualPorEmpleado);
$stmt->bind_param("s", $mesActual);
$stmt->execute();
$resultMensualEmpleado = $stmt->get_result();

$sqlHistorialDolar = "SELECT * FROM tasas_dolar ORDER BY fecha DESC LIMIT 30";
$resultHistorialDolar = $conn->query($sqlHistorialDolar);

$fijosQuery = "SELECT * FROM vehiculos_fijos ORDER BY fecha_fin ASC";
$resultFijosList = $conn->query($fijosQuery);

$datosTasa = getTasaDolarActual();
$tasa_dolar = $datosTasa['tasa'];

// Helper function to escape output for XSS prevention
function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cierre de Caja - Estacionamiento</title>
    <link rel="stylesheet" href="style.css">
    <script>
        function printReport() {
            document.querySelectorAll('.reporte-box').forEach(function(div) { div.style.display = 'none'; });
            document.querySelector('.reporte-box.ticket').style.display = 'block';
            window.print();
            location.reload();
        }
        function showTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(tab => tab.style.display = 'none');
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById(tabId).style.display = 'block';
            event.target.classList.add('active');
        }
    </script>
</head>
<body>
    <div class="container no-print">
        <div class="header">
            <img src="imagen/logo.png" alt="Logo" class="logo">
            <h1>ESTACIONAMIENTO</h1>
            <p>Reportes y Cierre de Caja - <?php echo date("d/m/Y", strtotime($fechaActual)); ?></p>
        </div>
        
        <div class="user-bar">
            <div class="user-info">
                <span class="user-name"><?php echo h($nombre_usuario); ?></span>
                <span class="user-type badge badge-<?php echo h($tipo_usuario); ?>"><?php echo ucfirst(h($tipo_usuario)); ?></span>
                <span id="fecha_hora" style="margin-left: 15px; font-weight: bold; color: #00d9ff;"></span>
            </div>
            <div class="user-actions">
                <?php if ($permisos['ver_historial_dolar']): ?>
                <span class="tasa-display">
                    Tasa dolar: <strong><?php echo $tasa_dolar > 0 ? number_format($tasa_dolar, 2) . ' BS' : 'N/D'; ?></strong>
                </span>
                <?php endif; ?>
                <a href="?logout=1" class="btn btn-danger btn-sm">Cerrar Sesion</a>
            </div>
        </div>
        
        <nav class="nav no-print">
            <?php if ($permisos['registrar_entrada']): ?>
            <a href="index.php"><img src="imagen/entrada.png" alt="Entrada"></a>
            <?php endif; ?>
            <?php if ($permisos['registrar_salida']): ?>
            <a href="salida.php"><img src="imagen/salida.png" alt="Salida"></a>
            <?php endif; ?>
            <a href="cierre.php" class="active"><img src="imagen/caja.png" alt="Caja"></a>
            <?php if ($permisos['configurar_tarifas'] || $permisos['gestionar_usuarios']): ?>
            <a href="admin.php"><img src="imagen/administracion.png" alt="Admin"></a>
            <?php endif; ?>
        </nav>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo h($messageType); ?>">
                <?php echo h($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="tabs">
            <button class="tab-btn active" onclick="showTab('diario')">Cierre del Dia</button>
            <button class="tab-btn" onclick="showTab('mensual')">Reporte Mensual</button>
            <?php if ($permisos['ver_reportes_mensuales'] || $permisos['ver_reportes_generales']): ?>
            <button class="tab-btn" onclick="showTab('fijos')">Vehiculos Fijos</button>
            <?php endif; ?>
            <?php if ($permisos['ver_historial_dolar']): ?>
            <button class="tab-btn" onclick="showTab('dolar')">Historial Dolar</button>
            <?php endif; ?>
        </div>
        
        <div id="diario" class="tab-content">
            <div class="card no-print">
                <div class="flex-between">
                    <h2>Resumen del Dia</h2>
                    <button onclick="printReport()" class="btn btn-primary">Imprimir Reporte</button>
                </div>
            </div>
            
            <div class="reporte-box mt-20 ticket">
                    <h3>Reporte de Cierre - <?php echo date("d/m/Y", strtotime($fechaActual)); ?></h3>
                    <div class="grid-2" style="text-align: left;">
                        <div>
                            <p><strong>Vehiculos Normales:</strong> <span class="number"><?php echo h($reporte['normales']); ?></span></p>
                            <p><strong>Vehiculos Fijos:</strong> <span class="number"><?php echo $cantidad_fijos; ?></span></p>
                            <p><strong>Total Atendidos:</strong> <span class="number"><?php echo h($reporte['normales'] + $cantidad_fijos); ?></span></p>
                        </div>
                        <div>
                            <p><strong>Recaudado Normales:</strong> <span class="number" style="color: #48bb78;"><?php echo number_format($reporte['total_recaudado_bs'], 2); ?></span> BS</p>
                            <p><strong>Cuotas Fijas:</strong> <span class="number" style="color: #28a745;"><?php echo number_format($total_fijos_dia, 2); ?></span> BS</p>
                            <p><strong>Total Dia:</strong> <span class="number" style="color: #00d9ff;"><?php echo number_format($reporte['total_recaudado_bs'] + $total_fijos_dia, 2); ?></span> BS</p>
                        </div>
                    </div>
                    <p><strong>Vehiculos en Parking:</strong> <span class="number"><?php echo h($enParking['total']); ?></span></p>
                </div>
            </div>
            
            <div class="card">
                <h2>Cierre por Empleado - Hoy</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Empleado</th>
                            <th>Normales</th>
                            <th>Cuotas Fijas</th>
                            <th>Total</th>
                            <th>Recaudado (BS)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $resultCierreDiario->fetch_assoc()): 
                            $fijosUser = $fijosByUser[$row['id']] ?? ['cantidad' => 0, 'total' => 0];
                        ?>
                            <tr>
                                <td><?php echo h($row['nombre_completo']); ?></td>
                                <td><strong><?php echo h($row['total_normales_count']); ?></strong></td>
                                <td><strong><?php echo h($fijosUser['cantidad']); ?></strong></td>
                                <td><strong><?php echo h($row['total_normales_count'] + $fijosUser['cantidad']); ?></strong></td>
                                <td style="color: #48bb78; font-weight: bold;"><?php echo number_format($row['total_normales'] + $fijosUser['total'], 2); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="card">
                <h2>Detalle de Vehiculos</h2>
                <?php if ($resultVehiculos->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Placa</th>
                                <th>Tipo</th>
                                <th>Entrada</th>
                                <th>Salida</th>
                                <th>Tipo</th>
                                <th>Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $resultVehiculos->fetch_assoc()): 
                                $tipo_reg = $row['es_fijo'] ? 'Fijo' : 'Normal';
                            ?>
                                <tr>
                                    <td><strong><?php echo h($row['placa']); ?></strong></td>
                                    <td><span class="badge badge-<?php echo h($row['tipo_vehiculo']); ?>"><?php echo ucfirst(h($row['tipo_vehiculo'])); ?></span></td>
                                    <td><?php echo formatDateTime($row['fecha_entrada']); ?></td>
                                    <td><?php echo formatDateTime($row['fecha_salida']); ?></td>
                                    <td><span class="badge <?php echo $row['es_fijo'] ? 'badge-success' : 'badge-warning'; ?>"><?php echo h($tipo_reg); ?></span></td>
                                    <td style="color: #48bb78; font-weight: bold;"><?php echo number_format($row['monto_pagado'], 2); ?> BS</td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-center" style="color: #a0aec0; padding: 20px;">No hay vehiculos pagados en el dia de hoy</p>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <h2>Cuotas Fijas Cobradas</h2>
                <?php if (count($pagosFijosDia) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Placa</th>
                                <th>Tipo Vehículo</th>
                                <th>Dueño</th>
                                <th>Tipo Cuota</th>
                                <th>Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $connTemp = $conn;
                            foreach ($pagosFijosDia as $pf): 
                                $vfInfo = $connTemp->query("SELECT vf.placa, vf.tipo_vehiculo, vf.nombre_dueño FROM vehiculos_fijos vf WHERE vf.id = " . intval($pf['id_vehiculo_fijo']))->fetch_assoc();
                            ?>
                                <tr>
                                    <td><strong><?php echo h($vfInfo['placa'] ?? 'N/A'); ?></strong></td>
                                    <td><span class="badge badge-<?php echo h($vfInfo['tipo_vehiculo'] ?? 'carro'); ?>"><?php echo ucfirst(h($vfInfo['tipo_vehiculo'] ?? 'carro')); ?></span></td>
                                    <td><?php echo h($vfInfo['nombre_dueño'] ?? 'N/A'); ?></td>
                                    <td><span class="badge badge-primary"><?php echo ucfirst(h($pf['tipo_cuota'])); ?></span></td>
                                    <td style="color: #48bb78; font-weight: bold;"><?php echo number_format($pf['monto'], 2); ?> BS</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-center" style="color: #a0aec0; padding: 20px;">No hay cuotas fijas cobradas en el dia de hoy</p>
                <?php endif; ?>
            </div>
        </div>
        
        <div id="mensual" class="tab-content" style="display: none;">
            <div class="card">
                <div class="flex-between">
                    <h2>Reporte Mensual - <?php echo date("m/Y"); ?></h2>
                    <button onclick="printReport()" class="btn btn-primary">Imprimir Reporte</button>
                </div>
                
                <div class="reporte-box mt-20 ticket">
                    <h3>Resumen del Mes</h3>
                    <div class="grid-2" style="text-align: left;">
                        <div>
                            <p><strong>Vehiculos Normales:</strong> <span class="number"><?php echo h($reporteMensual['normales']); ?></span></p>
                            <p><strong>Vehiculos Fijos:</strong> <span class="number"><?php echo h($reporteMensual['fijos']); ?></span></p>
                            <p><strong>Total Vehiculos:</strong> <span class="number"><?php echo h($reporteMensual['total_vehiculos']); ?></span></p>
                        </div>
                        <div>
                            <p><strong>Recaudado Normales:</strong> <span class="number" style="color: #48bb78;"><?php echo number_format($reporteMensual['total_recaudado_bs'], 2); ?></span> BS</p>
                            <p><strong>Cuotas Fijas:</strong> <span class="number" style="color: #28a745;"><?php echo number_format($total_fijos_mes, 2); ?></span> BS</p>
                            <p><strong>Total Mes:</strong> <span class="number" style="color: #00d9ff;"><?php echo number_format($reporteMensual['total_recaudado_bs'] + $total_fijos_mes, 2); ?></span> BS</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <h2>Resumen por Empleado - Mes</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Empleado</th>
                            <th>Normales</th>
                            <th>Fijos</th>
                            <th>Total</th>
                            <th>Recaudado (BS)</th>
                            <th>USD</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $resultMensualEmpleado->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo h($row['nombre_completo']); ?></td>
                                <td><strong><?php echo h($row['normales_count']); ?></strong></td>
                                <td><strong><?php echo h($row['fijos_count']); ?></strong></td>
                                <td><strong><?php echo h($row['total_vehiculos']); ?></strong></td>
                                <td style="color: #48bb78; font-weight: bold;"><?php echo number_format($row['total_recaudado_bs'], 2); ?></td>
                                <td style="color: #00d9ff;"><?php echo number_format($row['total_recaudado_usd'], 2); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div id="fijos" class="tab-content" style="display: none;">
            <div class="card">
                <h2>Listado de Vehiculos Fijos</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Placa</th>
                            <th>Tipo</th>
                            <th>Dueno</th>
                            <th>Telefono</th>
                            <th>Cuota</th>
                            <th>Fin</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
<?php 
                        $fijosQuery = "SELECT * FROM vehiculos_fijos ORDER BY fecha_fin ASC";
                        $resultFijosList = $conn->query($fijosQuery);
                        while ($row = $resultFijosList->fetch_assoc()): 
                            $estado_class = ($row['estado'] == 'activo') ? 'badge-success' : 'badge-danger';
                            $vencido = (strtotime($row['fecha_fin']) < strtotime(date('Y-m-d')));
                            if ($vencido && $row['estado'] == 'activo') {
                                $stmt = $conn->prepare("UPDATE vehiculos_fijos SET estado = 'vencido' WHERE id = ?");
                                $stmt->bind_param("i", $row['id']);
                                $stmt->execute();
                                $estado_class = 'badge-danger';
                            }
                        ?>
                            <tr>
                                <td><strong><?php echo h($row['placa']); ?></strong></td>
                                <td><span class="badge badge-<?php echo h($row['tipo_vehiculo']); ?>"><?php echo ucfirst(h($row['tipo_vehiculo'])); ?></span></td>
                                <td><?php echo h($row['nombre_dueño']); ?></td>
                                <td><?php echo h($row['telefono']); ?></td>
                                <td><?php echo h(ucfirst($row['tipo_cuota'])); ?></td>
                                <td><?php echo h(date('d/m/Y', strtotime($row['fecha_fin']))); ?></td>
                                <td><span class="badge <?php echo h($estado_class); ?>"><?php echo h(ucfirst($row['estado'])); ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div id="dolar" class="tab-content" style="display: none;">
            <div class="card">
                <h2>Historial de Tasas del Dolar</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tasa (BS/USD)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $resultHistorialDolar->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo h(date("d/m/Y", strtotime($row['fecha']))); ?></td>
                                <td style="color: #48bb78; font-weight: bold;"><?php echo h(number_format($row['tasa'], 2)); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script>
        function actualizarFechaHora() {
            var ahora = new Date();
            var dia = String(ahora.getDate()).padStart(2, '0');
            var mes = String(ahora.getMonth() + 1).padStart(2, '0');
            var anio = ahora.getFullYear();
            var horas = String(ahora.getHours()).padStart(2, '0');
            var minutos = String(ahora.getMinutes()).padStart(2, '0');
            var segundos = String(ahora.getSeconds()).padStart(2, '0');
            document.getElementById('fecha_hora').textContent = dia + '/' + mes + '/' + anio + ' ' + horas + ':' + minutos + ':' + segundos;
        }
        actualizarFechaHora();
        setInterval(actualizarFechaHora, 1000);
    </script>
</body>
</html>
