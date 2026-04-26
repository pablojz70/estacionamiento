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

if (!$permisos['registrar_entrada']) {
    die("No tiene permisos para registrar entradas");
}

$message = '';
$messageType = '';

$datosTasa = getTasaDolarActual();
$tasa_dolar = $datosTasa['tasa'];
$fuente_tasa = $datosTasa['fuente'] ?? 'ninguna';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_tasa_manual'])) {
    $tasa = floatval($_POST['tasa_manual']);
    if ($tasa > 0) {
        setTasaDolar($tasa, $usuario_id, true);
        $tasa_dolar = $tasa;
        $fuente_tasa = 'manual';
        $message = 'Tasa del dólar configurada: ' . number_format($tasa, 2) . ' BS';
        $messageType = 'success';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['entrada'])) {
    $placa = strtoupper(trim($_POST['placa']));
    $tipo_vehiculo = $_POST['tipo_vehiculo'];
    
    if (empty($placa)) {
        $message = 'Por favor ingrese la placa del vehículo';
        $messageType = 'error';
    } else {
        $vehiculo_fijo = esVehiculoFijo($placa);
        $es_fijo_activo = ($vehiculo_fijo !== null);
        
        $checkSql = "SELECT id, status FROM vehiculos WHERE placa = ? AND status IN ('dentro', 'fijo_salida')";
        $stmt = $conn->prepare($checkSql);
        $stmt->bind_param("s", $placa);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $message = 'El vehículo ya está dentro del estacionamiento';
            $messageType = 'error';
        } else {
            $es_fijo = $es_fijo_activo ? 1 : 0;
            $id_fijo = $vehiculo_fijo ? $vehiculo_fijo['id'] : 0;
            $status = $es_fijo_activo ? 'fijo_salida' : 'dentro';
            
            $insertSql = "INSERT INTO vehiculos (placa, tipo_vehiculo, es_fijo, id_usuario, id_vehiculo_fijo, fecha_entrada, status, tasa_dolar) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)";
            $stmt = $conn->prepare($insertSql);
            $stmt->bind_param("ssiiisd", $placa, $tipo_vehiculo, $es_fijo, $usuario_id, $id_fijo, $status, $tasa_dolar);
            
            if ($stmt->execute()) {
                $vehiculoId = $conn->insert_id;
                if ($es_fijo_activo) {
                    $message = 'Vehículo FIJO registrado - SIN COBRO';
                    $messageType = 'success';
                    $es_fijo_ticket = true;
                } else {
                    $message = 'Vehículo registrado exitosamente';
                    $messageType = 'success';
                    $es_fijo_ticket = false;
                }
                $placaTicket = $placa;
                $fechaTicket = formatDateTime($fechaEntrada);
                $codigoTicket = str_pad($vehiculoId, 6, '0', STR_PAD_LEFT);
                $tipoVehiculoLabel = ucfirst($tipo_vehiculo);
            } else {
                $message = 'Error al registrar el vehículo';
                $messageType = 'error';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_fijo'])) {
    $placa = strtoupper(trim($_POST['placa_fijo']));
    $tipo_vehiculo = $_POST['tipo_vehiculo_fijo'];
    $nombre_dueño = trim($_POST['nombre_dueño']);
    $telefono = trim($_POST['telefono']);
    $tipo_cuota = $_POST['tipo_cuota'];
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'];
    
    if (empty($placa) || empty($nombre_dueño)) {
        $message = 'Complete todos los campos requeridos';
        $messageType = 'error';
    } else {
        $sql = "INSERT INTO vehiculos_fijos (placa, tipo_vehiculo, nombre_dueño, telefono, tipo_cuota, fecha_inicio, fecha_fin, estado, id_usuario_registro) VALUES (?, ?, ?, ?, ?, ?, ?, 'activo', ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssi", $placa, $tipo_vehiculo, $nombre_dueño, $telefono, $tipo_cuota, $fecha_inicio, $fecha_fin, $usuario_id);
        
        if ($stmt->execute()) {
            $message = 'Vehículo fijo creado exitosamente';
            $messageType = 'success';
        } else {
            $message = 'Error al crear vehículo fijo. La placa puede estar en uso.';
            $messageType = 'error';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_fijo'])) {
    $id = intval($_POST['edit_id']);
    $placa = strtoupper(trim($_POST['edit_placa']));
    $tipo_vehiculo = $_POST['edit_tipo'];
    $nombre_dueño = trim($_POST['edit_dueño']);
    $telefono = trim($_POST['edit_telefono']);
    $tipo_cuota = $_POST['edit_cuota'];
    
    $sql = "UPDATE vehiculos_fijos SET placa = ?, tipo_vehiculo = ?, nombre_dueño = ?, telefono = ?, tipo_cuota = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssi", $placa, $tipo_vehiculo, $nombre_dueño, $telefono, $tipo_cuota, $id);
    
    if ($stmt->execute()) {
        $message = 'Vehículo fijo modificado exitosamente';
        $messageType = 'success';
    } else {
        $message = 'Error al modificar vehículo fijo';
        $messageType = 'error';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['renovar_fijo'])) {
    $id = intval($_POST['id_fijo']);
    $fecha_fin = $_POST['nueva_fecha_fin'];
    
    $sql = "UPDATE vehiculos_fijos SET fecha_inicio = CURDATE(), fecha_fin = ?, estado = 'activo', updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $fecha_fin, $id);
    
    if ($stmt->execute()) {
        $message = 'Vehículo fijo renovado exitosamente';
        $messageType = 'success';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pagar_fijo'])) {
    $id = intval($_POST['id_fijo_pago']);
    $monto_bs = floatval($_POST['monto_bs']);
    
    $sqlVf = "SELECT vf.*, 
    (SELECT MAX(p.fecha_fin) FROM pagos_fijos p WHERE p.id_vehiculo_fijo = vf.id) as ultimo_pago_fin 
    FROM vehiculos_fijos vf WHERE vf.id = ?";
    $stmt = $conn->prepare($sqlVf);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $vf = $result->fetch_assoc();
    
    if ($vf) {
        $tipo_cuota = $vf['tipo_cuota'];
        $dias = ($tipo_cuota == 'mensual') ? 30 : 7;
        $nueva_fecha_fin = date('Y-m-d', strtotime("+{$dias} days"));
        
        $sqlUpdate = "UPDATE vehiculos_fijos SET fecha_inicio = CURDATE(), fecha_fin = ?, estado = 'activo', updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sqlUpdate);
        $stmt->bind_param("si", $nueva_fecha_fin, $id);
        $stmt->execute();
        
        $sqlPago = "INSERT INTO pagos_fijos (id_vehiculo_fijo, id_usuario, monto, tipo_cuota, fecha_pago, fecha_inicio, fecha_fin) VALUES (?, ?, ?, ?, CURDATE(), CURDATE(), ?)";
        $stmt = $conn->prepare($sqlPago);
        $stmt->bind_param("iidss", $id, $usuario_id, $monto_bs, $tipo_cuota, $nueva_fecha_fin);
        $stmt->execute();
        
        $message = 'Pago registrado exitosamente. Cuota renovada hasta ' . date('d/m/Y', strtotime($nueva_fecha_fin));
        $messageType = 'success';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_fijo'])) {
    $id = intval($_POST['id_fijo']);
    
    $sql = "DELETE FROM vehiculos_fijos WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $message = 'Vehículo fijo eliminado';
        $messageType = 'success';
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$sqlVehiculos = "SELECT v.*, u.nombre_completo as empleado, vf.nombre_dueño as dueño_fijo FROM vehiculos v LEFT JOIN usuarios u ON v.id_usuario = u.id LEFT JOIN vehiculos_fijos vf ON v.id_vehiculo_fijo = vf.id WHERE v.status IN ('dentro', 'fijo_salida') ORDER BY v.fecha_entrada DESC";
$resultVehiculos = $conn->query($sqlVehiculos);

$sqlFijos = "SELECT vf.*, 
    (SELECT MAX(p.fecha_fin) FROM pagos_fijos p WHERE p.id_vehiculo_fijo = vf.id) as ultimo_pago_fin,
    (SELECT MAX(p.id) FROM pagos_fijos p WHERE p.id_vehiculo_fijo = vf.id) as ultimo_pago_id
    FROM vehiculos_fijos vf ORDER BY vf.fecha_fin DESC";
$resultFijos = $conn->query($sqlFijos);

$mysqlFechaHoy = $conn->query("SELECT CURDATE() as hoy")->fetch_assoc()['hoy'];
$mysqlFechaFin = date('Y-m-d', strtotime($mysqlFechaHoy . ' +7 days'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrada - Estacionamiento</title>
    <link rel="stylesheet" href="style.css">
    <script>
        function printTicket() {
            var tickets = document.querySelectorAll('.ticket');
            tickets.forEach(function(t) { t.style.display = 'none'; });
            var ticketCard = document.querySelector('.card.no-print');
            if (ticketCard) {
                var ticket = ticketCard.querySelector('.ticket');
                if (ticket) {
                    ticket.style.display = 'block';
                }
            }
            window.print();
        }
function printTicketFor(placa, tipo, fecha, esFijo, id) {
            var codigo = String(id).padStart(6, '0');
            var qrData = encodeURIComponent(placa + '|' + codigo + '|' + fecha);
            var printDiv = document.getElementById('ticketPrint');
            if (!printDiv) {
                printDiv = document.createElement('div');
                printDiv.id = 'ticketPrint';
                printDiv.className = 'ticket';
                printDiv.innerHTML = '<h3>ESTACIONAMIENTO</h3>' +
                    '<p>Entrada: <span id="tf_fecha"></span></p>' +
                    '<p>Tipo: <span id="tf_tipo"></span></p>' +
                    '<p id="tf_fijo" style="color: #28a745; font-weight: bold; display: none;">VEHÍCULO FIJO - SIN COBRO</p>' +
                    '<div class="placa" id="tf_placa"></div>' +
                    '<p><small>Código: <span id="tf_codigo"></span></small></p>' +
                    '<img id="tf_qr" src="" style="width:100px;height:100px;margin:10px auto;display:block;">' +
                    '<p><small>Guarde este ticket para el pago</small></p>';
                document.body.appendChild(printDiv);
            }
            setTimeout(function() {
                var elPlaca = document.getElementById('tf_placa');
                var elTipo = document.getElementById('tf_tipo');
                var elFecha = document.getElementById('tf_fecha');
                var elCodigo = document.getElementById('tf_codigo');
                var elFijo = document.getElementById('tf_fijo');
                var elQr = document.getElementById('tf_qr');
                if (elPlaca && elTipo && elFecha && elCodigo) {
                    elPlaca.textContent = placa;
                    elTipo.textContent = tipo.charAt(0).toUpperCase() + tipo.slice(1);
                    elFecha.textContent = formatDateTime(fecha);
                    elCodigo.textContent = codigo;
                    if (elQr) {
                        elQr.src = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' + qrData;
                    }
                    if (elFijo) {
                        elFijo.style.display = (esFijo == 1) ? 'block' : 'none';
                    }
                    printDiv.style.display = 'block';
                    window.print();
                }
            }, 100);
        }
        function formatDateTime(dateStr) {
            var date = new Date(dateStr);
            var day = String(date.getDate()).padStart(2, '0');
            var month = String(date.getMonth() + 1).padStart(2, '0');
            var year = date.getFullYear();
            var hours = String(date.getHours()).padStart(2, '0');
            var minutes = String(date.getMinutes()).padStart(2, '0');
            return day + '/' + month + '/' + year + ' ' + hours + ':' + minutes;
        }
        function showTab(tabId, evt) {
            document.querySelectorAll('.tab-content').forEach(function(tab) { tab.style.display = 'none'; });
            document.querySelectorAll('.tab-btn').forEach(function(btn) { btn.classList.remove('active'); });
            document.getElementById(tabId).style.display = 'block';
            if (evt && evt.target) {
                evt.target.classList.add('active');
            }
        }
    </script>
</head>
<body>
    <div class="container no-print">
        <div class="header">
            <img src="imagen/logo.png" alt="Logo" class="logo">
            <h1>ESTACIONAMIENTO</h1>
            <p>Registro de Entrada</p>
        </div>
        
        <div class="user-bar">
            <div class="user-info">
                <span class="user-name"><?php echo $nombre_usuario; ?></span>
                <span class="user-type badge badge-<?php echo $tipo_usuario; ?>"><?php echo ucfirst($tipo_usuario); ?></span>
                <span id="fecha_hora" style="margin-left: 15px; font-weight: bold; color: #00d9ff;"></span>
            </div>
            <div class="user-actions">
                <?php if ($tasa_dolar > 0): ?>
                <span class="tasa-display" title="Fuente: <?php echo $fuente_tasa; ?>">
                    Tasa dólar: <strong><?php echo number_format($tasa_dolar, 2); ?> BS</strong>
                    <small style="color: #666;">(<?php echo $fuente_tasa; ?>)</small>
                </span>
                <?php else: ?>
                <span class="tasa-display" style="color: red;">
                    Tasa dólar: <strong>No disponible</strong>
                </span>
                <?php endif; ?>
                <a href="?logout=1" class="btn btn-danger btn-sm">Cerrar Sesión</a>
            </div>
        </div>
        
        <nav class="nav no-print">
            <a href="index.php" class="active"><img src="imagen/entrada.png" alt="Entrada"></a>
            <a href="salida.php"><img src="imagen/salida.png" alt="Salida"></a>
            <a href="cierre.php"><img src="imagen/caja.png" alt="Caja"></a>
            <?php if ($permisos['configurar_tarifas'] || $permisos['gestionar_usuarios']): ?>
            <a href="admin.php"><img src="imagen/administracion.png" alt="Admin"></a>
            <?php endif; ?>
        </nav>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> no-print">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($permisos['introducir_dolar'] && $tasa_dolar == 0): ?>
        <div class="card alert-box">
            <h3>⚠️ Tasa del Dólar No Disponible</h3>
            <p style="margin-bottom: 15px;">La consulta automática falló. Ingrese la tasa manualmente:</p>
            <form method="POST" action="" class="form-inline">
                <div class="form-group">
                    <label>Tasa dollar (BS):</label>
                    <input type="number" name="tasa_manual" step="0.01" min="0" placeholder="Ej: 36.50" required>
                </div>
                <button type="submit" name="set_tasa_manual" class="btn btn-warning">Establecer Tasa</button>
            </form>
        </div>
        <?php endif; ?>
        
        <?php if ($permisos['gestionar_fijos']): ?>
        <div class="tabs">
            <button class="tab-btn active" onclick="showTab('entrada', event)">Entrada Normal</button>
            <button class="tab-btn" onclick="showTab('fijos', event)">Gestión Vehículos Fijos</button>
        </div>
        <?php endif; ?>
        
        <div id="entrada" class="tab-content">
            <?php if (isset($codigoTicket)): ?>
            <div class="card no-print">
                <h2>Ticket de Entrada</h2>
                <div class="ticket">
                    <h3>ESTACIONAMIENTO</h3>
                    <p>Entrada: <?php echo $fechaTicket; ?></p>
                    <p>Tipo: <?php echo $tipoVehiculoLabel; ?></p>
                    <?php if (isset($es_fijo_ticket) && $es_fijo_ticket): ?>
                    <p style="color: #28a745; font-weight: bold;">VEHÍCULO FIJO - SIN COBRO</p>
                    <?php endif; ?>
                    <div class="placa"><?php echo $placaTicket; ?></div>
                    <p><small>Código: <?php echo $codigoTicket; ?></small></p>
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode($placaTicket . '|' . $codigoTicket . '|' . $fechaTicket); ?>" alt="QR" style="width:100px;height:100px;margin:10px auto;display:block;">
                    <p><small>Guarde este ticket para el pago</small></p>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <h2>Registrar Entrada</h2>
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Tipo de Vehículo</label>
                        <div class="radio-group">
                            <label class="radio-option">
                                <input type="radio" name="tipo_vehiculo" value="carro" checked>
                                <span class="radio-badge carro">🚗 Carro</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="tipo_vehiculo" value="moto">
                                <span class="radio-badge moto">🏍️ Moto</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="tipo_vehiculo" value="camion">
                                <span class="radio-badge camion">🚛 Camión</span>
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="placa">Placa del Vehículo</label>
                        <input type="text" id="placa" name="placa" placeholder="Ej: ABC-1234" required autocomplete="off" autofocus style="text-transform: uppercase;">
                        <small>La fecha y hora se registran automáticamente</small>
                    </div>
                    <button type="submit" name="entrada" class="btn btn-primary">Registrar Entrada</button>
                </form>
            </div>
            
            <div class="card">
                <h2>Vehículos dentro del Estacionamiento</h2>
                <?php if ($resultVehiculos->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Placa</th>
                                <th>Tipo</th>
                                <th>Hora de Entrada</th>
                                <th>Empleado</th>
                                <th>Tipo</th>
                                <th>Tiempo</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $resultVehiculos->fetch_assoc()): 
                                $entrada = new DateTime($row['fecha_entrada']);
                                $ahora = new DateTime();
                                $diff = $ahora->diff($entrada);
                                $tiempo = $diff->h . 'h ' . $diff->i . 'm';
                                $tipo_reg = $row['es_fijo'] ? 'Fijo' : 'Normal';
                            ?>
                                <tr>
                                    <td><strong><?php echo h($row['placa']); ?></strong></td>
                                    <td><span class="badge badge-<?php echo h($row['tipo_vehiculo']); ?>"><?php echo ucfirst(h($row['tipo_vehiculo'])); ?></span></td>
                                    <td><?php echo formatDateTime($row['fecha_entrada']); ?></td>
                                    <td><?php echo h($row['empleado']); ?></td>
                                    <td><span class="badge <?php echo $row['es_fijo'] ? 'badge-success' : 'badge-warning'; ?>"><?php echo $tipo_reg; ?></span></td>
                                    <td><span class="badge badge-warning"><?php echo $tiempo; ?></span></td>
                                    <td><button type="button" onclick="printTicketFor('<?php echo h($row['placa']); ?>', '<?php echo h($row['tipo_vehiculo']); ?>', '<?php echo $row['fecha_entrada']; ?>', <?php echo intval($row['es_fijo']); ?>, <?php echo intval($row['id']); ?>)" class="btn btn-primary btn-sm">Imprimir</button></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-center" style="color: #a0aec0; padding: 20px;">No hay vehículos dentro del estacionamiento</p>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($permisos['gestionar_fijos']): ?>
        <div id="fijos" class="tab-content" style="display: none;">
            <div class="card">
                <h2>Crear Vehículo Fijo</h2>
                <form method="POST" action="" class="grid-2">
                    <div class="form-group">
                        <label for="placa_fijo">Placa</label>
                        <input type="text" id="placa_fijo" name="placa_fijo" placeholder="ABCD123 o ABC-1234" maxlength="10" required style="text-transform: uppercase;">
                    </div>
                    <div class="form-group">
                        <label>Tipo de Vehículo</label>
                        <div class="radio-group">
                            <label class="radio-option">
                                <input type="radio" name="tipo_vehiculo_fijo" value="carro" checked>
                                <span class="radio-badge carro">🚗 Carro</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="tipo_vehiculo_fijo" value="moto">
                                <span class="radio-badge moto">🏍️ Moto</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="tipo_vehiculo_fijo" value="camion">
                                <span class="radio-badge camion">🚛 Camión</span>
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="nombre_dueño">Nombre del Dueño</label>
                        <input type="text" id="nombre_dueño" name="nombre_dueño" required>
                    </div>
                    <div class="form-group">
                        <label for="telefono">Teléfono</label>
                        <input type="text" id="telefono" name="telefono" required>
                    </div>
                    <div class="form-group">
                        <label for="tipo_cuota">Tipo de Cuota</label>
                        <select id="tipo_cuota" name="tipo_cuota" required onchange="actualizarFechaFin()">
                            <option value="semanal">Semanal</option>
                            <option value="mensual">Mensual</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="fecha_inicio">Fecha Inicio</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?php echo $mysqlFechaHoy; ?>" required onchange="actualizarFechaFin()">
                    </div>
                    <div class="form-group">
                        <label for="fecha_fin">Fecha Fin</label>
                        <input type="date" id="fecha_fin" name="fecha_fin" value="<?php echo $mysqlFechaFin; ?>" required>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="crear_fijo" class="btn btn-primary">Crear Vehículo Fijo</button>
                    </div>
                </form>
                <script>
                function actualizarFechaFin() {
                    var tipoCuota = document.getElementById('tipo_cuota').value;
                    var fechaInicio = document.getElementById('fecha_inicio').value;
                    var fecha = new Date(fechaInicio);
                    if (tipoCuota === 'semanal') {
                        fecha.setDate(fecha.getDate() + 7);
                    } else {
                        fecha.setDate(fecha.getDate() + 30);
                    }
                    var anio = fecha.getFullYear();
                    var mes = String(fecha.getMonth() + 1).padStart(2, '0');
                    var dia = String(fecha.getDate()).padStart(2, '0');
                    document.getElementById('fecha_fin').value = anio + '-' + mes + '-' + dia;
                }
                </script>
            </div>
            
            <div class="card">
                <h2>Vehículos Fijos Registrados</h2>
                <?php if ($resultFijos->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Placa</th>
                                <th>Tipo</th>
                                <th>Dueño</th>
                                <th>Teléfono</th>
                                <th>Cuota</th>
                                <th>Inicio</th>
                                <th>Fin</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $resultFijos->fetch_assoc()): 
                                $estado_class = ($row['estado'] == 'activo') ? 'badge-success' : 'badge-danger';
                            ?>
                                <tr>
                                    <form method="POST" action="">
                                        <td><strong><?php echo h($row['placa']); ?></strong></td>
                                        <td><span class="badge badge-<?php echo h($row['tipo_vehiculo']); ?>"><?php echo ucfirst(h($row['tipo_vehiculo'])); ?></span></td>
                                        <td><?php echo h($row['nombre_dueño']); ?></td>
                                        <td><?php echo h($row['telefono']); ?></td>
                                        <td><?php echo ucfirst(h($row['tipo_cuota'])); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($row['fecha_inicio'])); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($row['fecha_fin'])); ?></td>
                                        <td><span class="badge <?php echo $estado_class; ?>"><?php echo ucfirst(h($row['estado'])); ?></span></td>
                                        <td>
                                            <input type="hidden" name="id_fijo" value="<?php echo intval($row['id']); ?>">
                                            <?php 
                                            $ultimo_pago_fin = isset($row['ultimo_pago_fin']) ? strtotime($row['ultimo_pago_fin']) : 0;
                                            $hoy = strtotime(date('Y-m-d'));
                                            $tiene_pago_vigente = $ultimo_pago_fin >= $hoy;
                                            ?>
                                            <button type="button" class="btn btn-warning btn-sm" onclick="showEditModal(<?php echo intval($row['id']); ?>, '<?php echo h($row['placa']); ?>', '<?php echo h($row['tipo_vehiculo']); ?>', '<?php echo h($row['nombre_dueño']); ?>', '<?php echo h($row['telefono']); ?>', '<?php echo h($row['tipo_cuota']); ?>')">Modificar</button>
                                            <?php if ($tiene_pago_vigente): ?>
                                            <span class="badge badge-success" style="padding: 5px 10px; font-size: 0.85em;">PAGADO HASTA <?php echo date('d/m/Y', $ultimo_pago_fin); ?></span>
                                            <?php else: ?>
                                            <button type="button" class="btn btn-primary btn-sm" onclick="showPagoModal(<?php echo intval($row['id']); ?>, '<?php echo h($row['tipo_vehiculo']); ?>', '<?php echo h($row['tipo_cuota']); ?>', '<?php echo h($row['placa']); ?>', '<?php echo h($row['nombre_dueño']); ?>', '<?php echo h($row['telefono']); ?>')">Cobrar</button>
                                            <?php endif; ?>
                                            <button type="submit" name="eliminar_fijo" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar vehículo fijo?')">Eliminar</button>
                                        </td>
                                    </form>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-center" style="color: #a0aec0;">No hay vehículos fijos registrados</p>
                <?php endif; ?>
            </div>
            
            <div id="pagoModal" class="modal" style="display: none;">
                <div class="modal-content" style="max-width: 450px;">
                    <h3>Detalles del Cobro</h3>
                    <div class="info-box ticket">
                        <div class="form-group">
                            <label>Placa</label>
                            <p style="font-size: 1.5rem; color: #00d9ff; font-weight: bold;" id="modal_placa"></p>
                        </div>
                        <div class="form-group">
                            <label>Tipo de Vehículo</label>
                            <p><span class="badge" id="modal_tipo_badge"></span></p>
                        </div>
                        <div class="form-group">
                            <label>Dueño</label>
                            <p id="modal_dueño"></p>
                        </div>
                        <div class="form-group">
                            <label>Teléfono</label>
                            <p id="modal_telefono"></p>
                        </div>
                        <div class="form-group">
                            <label>Tipo de Cuota</label>
                            <p id="modal_cuota_label"></p>
                        </div>
                        <hr style="margin: 15px 0;">
                        <div class="form-group">
                            <label>Monto en Dólares</label>
                            <p style="font-size: 1.2rem; color: #a0aec0;" id="modal_monto_dolar"></p>
                        </div>
                        <div class="form-group">
                            <label>Monto a Pagar (BS)</label>
                            <p style="font-size: 1.5rem; color: #48bb78; font-weight: bold;" id="modal_monto_bs"></p>
                            <small>Tasa: <?php echo number_format($tasa_dolar, 2); ?> BS/USD</small>
                        </div>
                        <div style="margin-top: 15px;">
                            <img id="modal_qr" src="" style="width:100px;height:100px;display:block;margin:0 auto;">
                        </div>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="id_fijo_pago" id="modal_id_fijo">
                        <input type="hidden" name="tipo_cuota_pago" id="modal_tipo_cuota">
                        <input type="hidden" name="monto_bs" id="modal_monto_hidden">
                        <button type="submit" name="pagar_fijo" class="btn btn-success" style="width: 100%; margin-bottom: 10px;">Confirmar Pago y Cobrar</button>
                        <button type="button" class="btn btn-primary" onclick="imprimirRecibo()" style="width: 100%;">Imprimir Comprobante</button>
                        <button type="button" class="btn btn-danger" onclick="cerrarModal()" style="width: 100%; margin-top: 10px;">Cancelar</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div id="editModal" class="modal" style="display: none;">
            <div class="modal-content" style="max-width: 450px;">
                <h3>Modificar Vehículo Fijo</h3>
                <form method="POST" action="">
                    <input type="hidden" name="editar_fijo" value="1">
                    <input type="hidden" name="edit_id" id="edit_id">
                    <div class="form-group">
                        <label>Placa</label>
                        <input type="text" id="edit_placa" name="edit_placa" required style="text-transform: uppercase;">
                    </div>
                    <div class="form-group">
                        <label>Tipo de Vehículo</label>
                        <select id="edit_tipo" name="edit_tipo" required>
                            <option value="carro">Carro</option>
                            <option value="moto">Moto</option>
                            <option value="camion">Camión</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nombre del Dueño</label>
                        <input type="text" id="edit_dueño" name="edit_dueño" required>
                    </div>
                    <div class="form-group">
                        <label>Teléfono</label>
                        <input type="text" id="edit_telefono" name="edit_telefono" required>
                    </div>
                    <div class="form-group">
                        <label>Tipo de Cuota</label>
                        <select id="edit_cuota" name="edit_cuota" required>
                            <option value="semanal">Semanal</option>
                            <option value="mensual">Mensual</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-warning" style="width: 100%; margin-bottom: 10px;">Guardar Cambios</button>
                    <button type="button" class="btn btn-danger" onclick="cerrarEditModal()" style="width: 100%;">Cancelar</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function showPagoModal(id, tipo, cuota, placa, dueño, telefono) {
            var montoDolar = 0;
            if (tipo == 'carro') montoDolar = (cuota == 'mensual') ? 30 : 20;
            else if (tipo == 'moto') montoDolar = (cuota == 'mensual') ? 20 : 15;
            else montoDolar = (cuota == 'mensual') ? 60 : 40;
            
            var tasa = <?php echo $tasa_dolar ?: 480; ?>;
            var montoBS = montoDolar * tasa;
            
            document.getElementById('modal_id_fijo').value = id;
            document.getElementById('modal_tipo_cuota').value = cuota;
            document.getElementById('modal_monto_hidden').value = montoBS;
            
            document.getElementById('modal_placa').textContent = placa || '';
            document.getElementById('modal_tipo_badge').textContent = tipo.toUpperCase();
            document.getElementById('modal_tipo_badge').className = 'badge badge-' + tipo;
            document.getElementById('modal_dueño').textContent = dueño || '';
            document.getElementById('modal_telefono').textContent = telefono || '';
            document.getElementById('modal_cuota_label').textContent = cuota.toUpperCase();
            document.getElementById('modal_monto_dolar').textContent = montoDolar.toFixed(2) + ' USD';
            document.getElementById('modal_monto_bs').textContent = montoBS.toFixed(2) + ' BS';
            
            var qrData = encodeURIComponent(placa + '|' + cuota.toUpperCase() + '|' + montoDolar + 'USD');
            document.getElementById('modal_qr').src = 'https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=' + qrData;
            
            document.getElementById('pagoModal').style.display = 'block';
        }
        
        function imprimirRecibo() {
            var ventana = window.open('', '_blank');
            ventana.document.write('<html><head><title>Recibo</title>');
            ventana.document.write('<style>body{font-family:Arial;padding:20px;text-align:center;} .info-box{border:2px solid #000;padding:15px;}</style>');
            ventana.document.write('</head><body>');
            ventana.document.write('<h2>ESTACIONAMIENTO</h2>');
            ventana.document.write(document.querySelector('#pagoModal .info-box').innerHTML);
            ventana.document.write('<p style="margin-top:10px;color:green;">PAGADO</p>');
            ventana.document.write('</body></html>');
            ventana.document.close();
            ventana.print();
            ventana.close();
        }
        
        function cerrarModal() {
            document.getElementById('pagoModal').style.display = 'none';
        }
        
        function showEditModal(id, placa, tipo, dueño, telefono, cuota) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_placa').value = placa;
            document.getElementById('edit_dueño').value = dueño;
            document.getElementById('edit_telefono').value = telefono;
            document.getElementById('edit_tipo').value = tipo;
            document.getElementById('edit_cuota').value = cuota;
            document.getElementById('editModal').style.display = 'block';
        }
        
        function cerrarEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
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