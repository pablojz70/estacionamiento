<?php
session_start();
require_once 'db.php';

function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$tipo_usuario = $_SESSION['tipo'];
$nombre_usuario = $_SESSION['nombre_completo'];

$permisos = getPermisos($tipo_usuario);

if (!$permisos['registrar_salida']) {
    die("No tiene permisos para registrar salidas");
}

$message = '';
$messageType = '';
$vehiculo = null;
$montoAPagar = 0;
$datosPago = null;

$datosTasa = getTasaDolarActual();
$tasa_dolar = $datosTasa['tasa'];

$sqlTarifa = "SELECT * FROM tarifas WHERE activo = 1 ORDER BY tipo_vehiculo, tipo_tarifa";
$resultTarifa = $conn->query($sqlTarifa);
$tarifas = [];
while ($row = $resultTarifa->fetch_assoc()) {
    $tarifas[] = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['buscar'])) {
        $placa = strtoupper(trim($_POST['placa']));
        
        if (!empty($placa)) {
            $sql = "SELECT v.*, vf.nombre_dueño as dueño_fijo, vf.tipo_cuota as cuota_fija FROM vehiculos v LEFT JOIN vehiculos_fijos vf ON v.id_vehiculo_fijo = vf.id WHERE v.placa = ? AND v.status IN ('dentro', 'fijo_salida')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $placa);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $vehiculo = $result->fetch_assoc();
                $es_fijo_activo = ($vehiculo['es_fijo'] == 1 && $vehiculo['status'] == 'fijo_salida');
                
                if ($es_fijo_activo) {
                    $montoAPagar = 0;
                    $datosPago = ['monto_dolar' => 0, 'monto_bs' => 0, 'tasa' => $tasa_dolar];
                } else {
                    $tarifaSeleccionada = isset($_POST['tarifa']) ? $_POST['tarifa'] : null;
                    
                    if (!$tarifaSeleccionada) {
                        $tipo_v = $vehiculo['tipo_vehiculo'];
                        foreach ($tarifas as $tarifa) {
                            if ($tarifa['tipo_vehiculo'] == $tipo_v && $tarifa['tipo_tarifa'] == 'hora') {
                                $tarifaSeleccionada = $tarifa['id'];
                                break;
                            }
                        }
                    }
                    
                    foreach ($tarifas as $tarifa) {
                        if ($tarifa['id'] == $tarifaSeleccionada) {
                            $datosPago = calcularPago($vehiculo['fecha_entrada'], date('Y-m-d H:i:s'), $tarifa, $tasa_dolar);
                            $montoAPagar = $datosPago['monto_bs'];
                            $vehiculo['tarifa_seleccionada'] = $tarifa;
                            break;
                        }
                    }
                }
            } else {
                $message = 'No se encontró un vehículo dentro con esa placa';
                $messageType = 'error';
            }
        }
    }
    
    if (isset($_POST['cobrar'])) {
        $vehiculoId = $_POST['vehiculo_id'];
        $monto_bs = floatval($_POST['monto_bs']);
        $monto_dolar = floatval($_POST['monto_dolar']);
        $es_fijo = isset($_POST['es_fijo']) && $_POST['es_fijo'] == 1;
        
        if ($es_fijo) {
            $sqlUpdate = "UPDATE vehiculos SET fecha_salida = NOW(), monto_pagado = 0, monto_dolar = 0, tarifa_aplicada = 'VEHICULO FIJO', status = 'fijo_salida' WHERE id = ?";
            $stmt = $conn->prepare($sqlUpdate);
            $stmt->bind_param("i", $vehiculoId);
            $stmt->execute();
            
            $message = 'Salida de vehículo fijo registrada - SIN COBRO';
            $messageType = 'success';
        } else {
            $tarifaId = $_POST['tarifa_id'];
            
            foreach ($tarifas as $tarifa) {
                if ($tarifa['id'] == $tarifaId) {
                    $tarifaNombre = $tarifa['nombre'];
                    break;
                }
            }
            
            $sqlUpdate = "UPDATE vehiculos SET fecha_salida = NOW(), monto_pagado = ?, monto_dolar = ?, tarifa_aplicada = ?, status = 'pagado' WHERE id = ?";
            $stmt = $conn->prepare($sqlUpdate);
            $stmt->bind_param("ddsi", $monto_bs, $monto_dolar, $tarifaNombre, $vehiculoId);
            $stmt->execute();
            
            $message = 'Pago registrado exitosamente. Monto: ' . number_format($monto_bs, 2) . ' BS (' . number_format($monto_dolar, 2) . ' USD)';
            $messageType = 'success';
        }
        
        $vehiculo = null;
        $montoAPagar = 0;
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$sqlRecientes = "SELECT v.*, vf.nombre_dueño as dueño_fijo FROM vehiculos v LEFT JOIN vehiculos_fijos vf ON v.id_vehiculo_fijo = vf.id WHERE v.status IN ('dentro', 'fijo_salida') ORDER BY v.fecha_entrada DESC LIMIT 10";
$resultRecientes = $conn->query($sqlRecientes);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salida - Estacionamiento</title>
    <link rel="stylesheet" href="style.css">
    <script>
        function printFactura() {
            document.querySelectorAll('.info-box').forEach(function(div) { div.style.display = 'none'; });
            document.querySelector('.info-box.ticket').style.display = 'block';
            window.print();
            location.reload();
        }
    </script>
</head>
<body>
    <div class="container no-print">
        <div class="header">
            <img src="imagen/logo.png" alt="Logo" class="logo">
            <h1>ESTACIONAMIENTO</h1>
            <p>Registro de Salida y Cobro</p>
        </div>
        
        <div class="user-bar">
            <div class="user-info">
                <span class="user-name"><?php echo $nombre_usuario; ?></span>
                <span class="user-type badge badge-<?php echo $tipo_usuario; ?>"><?php echo ucfirst($tipo_usuario); ?></span>
            </div>
            <div class="user-actions">
                <span class="tasa-display">
                    Tasa dólar: <strong><?php echo $tasa_dolar > 0 ? number_format($tasa_dolar, 2) . ' BS' : 'No configurada'; ?></strong>
                </span>
                <a href="?logout=1" class="btn btn-danger btn-sm">Cerrar Sesión</a>
            </div>
        </div>
        
        <nav class="nav no-print">
            <a href="index.php"><img src="imagen/entrada.png" alt="Entrada"></a>
            <a href="salida.php" class="active"><img src="imagen/salida.png" alt="Salida"></a>
            <a href="cierre.php"><img src="imagen/caja.png" alt="Caja"></a>
            <?php if ($permisos['configurar_tarifas'] || $permisos['gestionar_usuarios']): ?>
            <a href="admin.php"><img src="imagen/administracion.png" alt="Admin"></a>
            <?php endif; ?>
        </nav>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="grid-2">
            <div class="card">
                <h2>Buscar Vehículo</h2>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="placa">Ingrese la Placa</label>
                        <input type="text" id="placa" name="placa" placeholder="Ej: ABC-1234" required autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="tarifa">Seleccionar Tarifa</label>
                        <select id="tarifa" name="tarifa">
                            <optgroup label="Carro">
                                <?php foreach ($tarifas as $tarifa): ?>
                                    <?php if ($tarifa['tipo_vehiculo'] == 'carro'): ?>
                                        <option value="<?php echo $tarifa['id']; ?>">
                                            <?php echo $tarifa['nombre'] . ' - ' . number_format($tarifa['monto'], 2) . ' USD'; ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="Moto">
                                <?php foreach ($tarifas as $tarifa): ?>
                                    <?php if ($tarifa['tipo_vehiculo'] == 'moto'): ?>
                                        <option value="<?php echo $tarifa['id']; ?>">
                                            <?php echo $tarifa['nombre'] . ' - ' . number_format($tarifa['monto'], 2) . ' USD'; ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="Camión">
                                <?php foreach ($tarifas as $tarifa): ?>
                                    <?php if ($tarifa['tipo_vehiculo'] == 'camion'): ?>
                                        <option value="<?php echo $tarifa['id']; ?>">
                                            <?php echo $tarifa['nombre'] . ' - ' . number_format($tarifa['monto'], 2) . ' USD'; ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>
                    <button type="submit" name="buscar" class="btn btn-primary">Buscar</button>
                </form>
                
                <h3 class="mt-20">Vehículos Recientes</h3>
                <?php if ($resultRecientes->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Placa</th>
                                <th>Tipo</th>
                                <th>Entrada</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $resultRecientes->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo $row['placa']; ?></strong></td>
                                    <td><span class="badge badge-<?php echo $row['tipo_vehiculo']; ?>"><?php echo ucfirst($row['tipo_vehiculo']); ?></span></td>
                                    <td><?php echo formatDateTime($row['fecha_entrada']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-center" style="color: #a0aec0;">No hay vehículos dentro</p>
                <?php endif; ?>
            </div>
            
            <?php if ($vehiculo): ?>
            <?php $es_fijo_salida = ($vehiculo['es_fijo'] == 1 && $vehiculo['status'] == 'fijo_salida'); ?>
            <div class="card no-print">
                <div class="flex-between">
                    <h2><?php echo $es_fijo_salida ? 'Salida Vehículo Fijo' : 'Detalles del Cobro'; ?></h2>
                    <button onclick="printFactura()" class="btn btn-primary">Imprimir Comprobante</button>
                </div>
                <div class="info-box ticket">
                    <div class="form-group">
                        <label>Placa del Vehículo</label>
                        <p style="font-size: 1.5rem; color: #00d9ff; font-weight: bold;"><?php echo h($vehiculo['placa']); ?></p>
                    </div>
                    
                    <div class="form-group">
                        <label>Tipo de Vehículo</label>
                        <p><span class="badge badge-<?php echo h($vehiculo['tipo_vehiculo']); ?>"><?php echo ucfirst(h($vehiculo['tipo_vehiculo'])); ?></span></p>
                    </div>
                    
                    <?php if ($es_fijo_salida): ?>
                    <div class="form-group">
                        <label>Tipo</label>
                        <p><span class="badge badge-success">VEHÍCULO FIJO</span></p>
                    </div>
                    <div class="form-group">
                        <label>Dueño</label>
                        <p><?php echo $vehiculo['dueño_fijo']; ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>Hora de Entrada</label>
                        <p><?php echo formatDateTime($vehiculo['fecha_entrada']); ?></p>
                    </div>
                    
                    <div class="form-group">
                        <label>Hora de Salida</label>
                        <p><?php echo formatDateTime(date('Y-m-d H:i:s')); ?></p>
                    </div>
                    
                    <?php if (!$es_fijo_salida): ?>
                    <?php 
                    $entrada = new DateTime($vehiculo['fecha_entrada']);
                    $salida = new DateTime();
                    $diff = $salida->diff($entrada);
                    $minutos = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
                    ?>
                    <div class="form-group">
                        <label>Tiempo Estacionado</label>
                        <p><?php echo $diff->h; ?> horas con <?php echo $diff->i; ?> minutos (<?php echo $minutos; ?> min total)</p>
                    </div>
                    
                    <div class="form-group">
                        <label>Tarifa Aplicada</label>
                        <p><?php echo $vehiculo['tarifa_seleccionada']['nombre']; ?></p>
                    </div>
                    
                    <div class="form-group">
                        <label>Monto en Dólares</label>
                        <p style="font-size: 1.2rem; color: #a0aec0;"><?php echo number_format($datosPago['monto_dolar'], 2); ?> USD</p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>Monto a Pagar</label>
                        <?php if ($es_fijo_salida): ?>
                        <p style="font-size: 2rem; color: #28a745; font-weight: bold;">$0.00 - SIN COBRO</p>
                        <?php else: ?>
                        <p style="font-size: 2rem; color: #48bb78; font-weight: bold;">
                            <?php echo number_format($montoAPagar, 2); ?> BS
                        </p>
                        <small>Tasa aplicada: <?php echo number_format($tasa_dolar, 2); ?> BS/USD</small>
                        <?php endif; ?>
                    </div>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="vehiculo_id" value="<?php echo $vehiculo['id']; ?>">
                        <input type="hidden" name="monto_bs" value="<?php echo $montoAPagar; ?>">
                        <input type="hidden" name="monto_dolar" value="<?php echo $datosPago['monto_dolar']; ?>">
                        <input type="hidden" name="es_fijo" value="<?php echo $es_fijo_salida ? 1 : 0; ?>">
                        <?php if (!$es_fijo_salida): ?>
                        <input type="hidden" name="tarifa_id" value="<?php echo $vehiculo['tarifa_seleccionada']['id']; ?>">
                        <?php endif; ?>
                        <button type="submit" name="cobrar" class="btn btn-success" style="width: 100%;">
                            <?php echo $es_fijo_salida ? 'Confirmar Salida (Sin Cobro)' : 'Confirmar Pago y Salida'; ?>
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>