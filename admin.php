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

if (!$permisos['configurar_tarifas'] && !$permisos['configurar_cuotas'] && !$permisos['gestionar_usuarios']) {
    die("No tiene permisos para acceder a administracion");
}

$message = '';
$messageType = '';

if (isset($_POST['crear_usuario'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $tipo = $_POST['tipo'];
    $nombre_completo = trim($_POST['nombre_completo']);
    
    if (empty($username) || empty($password) || empty($nombre_completo)) {
        $message = 'Complete todos los campos';
        $messageType = 'error';
    } else {
        if (crearUsuario($username, $password, $tipo, $nombre_completo)) {
            $message = 'Usuario creado';
            $messageType = 'success';
        } else {
            $message = 'Error al crear usuario';
            $messageType = 'error';
        }
    }
}

if (isset($_POST['actualizar_usuario'])) {
    $id = intval($_POST['usuario_id']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $tipo = $_POST['tipo'];
    $nombre_completo = trim($_POST['nombre_completo']);
    $activo = isset($_POST['activo']) ? 1 : 0;
    
    if (actualizarUsuario($id, $username, $password, $tipo, $nombre_completo, $activo)) {
        $message = 'Usuario actualizado';
        $messageType = 'success';
    }
}

if (isset($_POST['eliminar_usuario'])) {
    $id = intval($_POST['usuario_id']);
    if ($id != $usuario_id) {
        if (eliminarUsuario($id)) {
            $message = 'Usuario eliminado';
            $messageType = 'success';
        }
    }
}

if (isset($_POST['agregar_tarifa'])) {
    $nombre = trim($_POST['nombre']);
    $tipo_vehiculo = $_POST['tipo_vehiculo'];
    $tipo_tarifa = $_POST['tipo_tarifa'];
    $monto = floatval($_POST['monto']);
    $duracion = intval($_POST['duracion']);
    
    if (!empty($nombre) && $monto > 0) {
        $stmt = $conn->prepare("INSERT INTO tarifas (nombre, tipo_vehiculo, tipo_tarifa, monto, duracion_minutos) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssdi", $nombre, $tipo_vehiculo, $tipo_tarifa, $monto, $duracion);
        if ($stmt->execute()) {
            $message = 'Tarifa agregada';
            $messageType = 'success';
        }
    }
}

if (isset($_POST['actualizar_tarifa'])) {
    $id = intval($_POST['tarifa_id']);
    $nombre = trim($_POST['nombre']);
    $tipo_vehiculo = $_POST['tipo_vehiculo'];
    $tipo_tarifa = $_POST['tipo_tarifa'];
    $monto = floatval($_POST['monto']);
    $duracion = intval($_POST['duracion']);
    $activo = isset($_POST['activo']) ? 1 : 0;
    
    $stmt = $conn->prepare("UPDATE tarifas SET nombre=?, tipo_vehiculo=?, tipo_tarifa=?, monto=?, duracion_minutos=?, activo=? WHERE id=?");
    $stmt->bind_param("sssdiii", $nombre, $tipo_vehiculo, $tipo_tarifa, $monto, $duracion, $activo, $id);
    if ($stmt->execute()) {
        $message = 'Tarifa actualizada';
        $messageType = 'success';
    }
}

if (isset($_POST['eliminar_tarifa'])) {
    $id = intval($_POST['tarifa_id']);
    $stmt = $conn->prepare("DELETE FROM tarifas WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $message = 'Tarifa eliminada';
    $messageType = 'success';
}

if (isset($_POST['agregar_cuota'])) {
    $tipo_vehiculo = $_POST['tipo_vehiculo'];
    $tipo_cuota = $_POST['tipo_cuota'];
    $monto = floatval($_POST['monto']);
    
    $stmt = $conn->prepare("INSERT INTO cuotas_fijas (tipo_vehiculo, tipo_cuota, monto) VALUES (?, ?, ?)");
    $stmt->bind_param("ssd", $tipo_vehiculo, $tipo_cuota, $monto);
    if ($stmt->execute()) {
        $message = 'Cuota agregada';
        $messageType = 'success';
    }
}

if (isset($_POST['actualizar_cuota'])) {
    $id = intval($_POST['cuota_id']);
    $monto = floatval($_POST['monto']);
    $activo = isset($_POST['activo']) ? 1 : 0;
    $stmt = $conn->prepare("UPDATE cuotas_fijas SET monto = ?, activo = ? WHERE id = ?");
    $stmt->bind_param("dii", $monto, $activo, $id);
    if ($stmt->execute()) {
        $message = 'Cuota actualizada';
        $messageType = 'success';
    }
}

if (isset($_POST['guardar_config'])) {
    $moneda = trim($_POST['moneda']);
    $stmt = $conn->prepare("UPDATE configuraciones SET valor = ? WHERE clave = 'moneda'");
    $stmt->bind_param("s", $moneda);
    if ($stmt->execute()) {
        $message = 'Configuracion guardada';
        $messageType = 'success';
    }
}

if (isset($_POST['set_tasa_manual'])) {
    $tasa = floatval($_POST['tasa_manual']);
    if ($tasa > 0) {
        setTasaDolar($tasa, $usuario_id, true);
        $message = 'Tasa actualizada: ' . number_format($tasa, 2) . ' BS';
        $messageType = 'success';
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$sqlUsuarios = "SELECT * FROM usuarios ORDER BY tipo, nombre_completo";
$resultUsuarios = $conn->query($sqlUsuarios);

$sqlTarifas = "SELECT * FROM tarifas ORDER BY tipo_vehiculo, tipo_tarifa";
$resultTarifas = $conn->query($sqlTarifas);

$sqlCuotas = "SELECT * FROM cuotas_fijas ORDER BY tipo_vehiculo, tipo_cuota";
$resultCuotas = $conn->query($sqlCuotas);

$datosTasa = getTasaDolarActual();
$tasa_actual = $datosTasa['tasa'];
$historialTasas = getHistorialTasas(30);

// Helper function to escape output for XSS prevention
function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administracion</title>
    <link rel="stylesheet" href="style.css">
    <script>
        function showTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(t => t.style.display = 'none');
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById(tabId).style.display = 'block';
            event.target.classList.add('active');
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="imagen/logo.png" alt="Logo" class="logo">
            <h1>ADMINISTRACION</h1>
            <p>Panel de Configuracion</p>
        </div>
        
        <div class="user-bar">
            <div class="user-info">
                <span class="user-name"><?php echo h($nombre_usuario); ?></span>
                <span class="user-type badge badge-<?php echo h($tipo_usuario); ?>"><?php echo ucfirst(h($tipo_usuario)); ?></span>
            </div>
            <div class="user-actions">
                <a href="?logout=1" class="btn btn-danger btn-sm">Cerrar Sesion</a>
            </div>
        </div>
        
        <nav class="nav">
            <a href="cierre.php"><img src="imagen/caja.png" alt="Caja"></a>
            <a href="admin.php" class="active"><img src="imagen/administracion.png" alt="Admin"></a>
        </nav>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo h($messageType); ?>">
                <?php echo h($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="tabs">
            <?php if ($permisos['configurar_tarifas']): ?>
            <button class="tab-btn active" onclick="showTab('tarifas')">Tarifas Normales</button>
            <?php endif; ?>
            <?php if ($permisos['configurar_cuotas']): ?>
            <button class="tab-btn" onclick="showTab('cuotas')">Cuotas Fijas</button>
            <?php endif; ?>
            <?php if ($permisos['gestionar_usuarios']): ?>
            <button class="tab-btn" onclick="showTab('usuarios')">Usuarios</button>
            <?php endif; ?>
            <button class="tab-btn" onclick="showTab('config')">Configuracion</button>
        </div>
        
        <?php if ($permisos['configurar_tarifas']): ?>
        <div id="tarifas" class="tab-content">
            <div class="card">
                <h2>Agregar Tarifa Normal</h2>
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Nombre</label>
                        <input type="text" name="nombre" placeholder="Ej: Hora Carro" required>
                    </div>
                    <div class="form-group">
                        <label>Tipo de Vehiculo</label>
                        <div class="radio-group">
                            <label class="radio-option">
                                <input type="radio" name="tipo_vehiculo" value="carro" checked>
                                <span class="radio-badge carro">Carro</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="tipo_vehiculo" value="moto">
                                <span class="radio-badge moto">Moto</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="tipo_vehiculo" value="camion">
                                <span class="radio-badge camion">Camion</span>
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Tipo Tarifa</label>
                        <select name="tipo_tarifa">
                            <option value="hora">Por Hora</option>
                            <option value="fraccion">Por Fraccion</option>
                            <option value="dia">Por Dia</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Monto (USD)</label>
                        <input type="number" name="monto" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Duracion (min)</label>
                        <input type="number" name="duracion" value="60" min="1" required>
                    </div>
                    <button type="submit" name="agregar_tarifa" class="btn btn-primary">Agregar Tarifa</button>
                </form>
            </div>
            <div class="card">
                <h2>Tarifas Existentes</h2>
                <table>
                    <thead>
                        <tr><th>Nombre</th><th>Tipo</th><th>Monto</th><th>Duracion</th><th>Activo</th><th>Accion</th></tr>
                    </thead>
                    <tbody>
                        <?php while($t=$resultTarifas->fetch_assoc()): ?>
                        <tr>
                            <form method="POST">
                                <td><input type="text" name="nombre" value="<?php echo h($t['nombre']); ?>"></td>
                                <td><?php echo h($t['tipo_vehiculo']).'/'.h($t['tipo_tarifa']); ?></td>
                                <td><input type="number" name="monto" value="<?php echo h($t['monto']); ?>" step="0.01"></td>
                                <td><input type="number" name="duracion" value="<?php echo h($t['duracion_minutos']); ?>"></td>
                                <td><input type="checkbox" name="activo" <?php echo $t['activo']?'checked':''; ?>></td>
                                <td>
                                    <input type="hidden" name="tarifa_id" value="<?php echo h($t['id']); ?>">
                                    <button type="submit" name="actualizar_tarifa" class="btn btn-warning btn-sm">Guardar</button>
                                    <button type="submit" name="eliminar_tarifa" class="btn btn-danger btn-sm" onclick="return confirm('Eliminar?')">X</button>
                                </td>
                            </form>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($permisos['configurar_cuotas']): ?>
        <div id="cuotas" class="tab-content" style="display: none;">
            <div class="card">
                <h2>Agregar Cuota Fija</h2>
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Tipo de Vehiculo</label>
                        <div class="radio-group">
                            <label class="radio-option">
                                <input type="radio" name="tipo_vehiculo" value="carro" checked>
                                <span class="radio-badge carro">Carro</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="tipo_vehiculo" value="moto">
                                <span class="radio-badge moto">Moto</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="tipo_vehiculo" value="camion">
                                <span class="radio-badge camion">Camion</span>
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Tipo de Cuota</label>
                        <select name="tipo_cuota">
                            <option value="semanal">Semanal</option>
                            <option value="mensual">Mensual</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Monto (USD)</label>
                        <input type="number" name="monto" step="0.01" min="0" required>
                    </div>
                    <button type="submit" name="agregar_cuota" class="btn btn-primary">Agregar Cuota</button>
                </form>
            </div>
            <div class="card">
                <h2>Cuotas Fijas</h2>
                <table>
                    <thead>
                        <tr><th>Tipo</th><th>Cuota</th><th>Monto</th><th>Activo</th><th>Accion</th></tr>
                    </thead>
                    <tbody>
                        <?php while($c=$resultCuotas->fetch_assoc()): ?>
                        <tr>
                            <form method="POST">
                                <td><?php echo h(ucfirst($c['tipo_vehiculo'])); ?></td>
                                <td><?php echo h(ucfirst($c['tipo_cuota'])); ?></td>
                                <td><input type="number" name="monto" value="<?php echo h($c['monto']); ?>" step="0.01"></td>
                                <td><input type="checkbox" name="activo" <?php echo $c['activo']?'checked':''; ?>></td>
                                <td>
                                    <input type="hidden" name="cuota_id" value="<?php echo h($c['id']); ?>">
                                    <button type="submit" name="actualizar_cuota" class="btn btn-warning btn-sm">Guardar</button>
                                </td>
                            </form>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($permisos['gestionar_usuarios']): ?>
        <div id="usuarios" class="tab-content" style="display: none;">
            <div class="card">
                <h2>Crear Usuario</h2>
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Usuario</label>
                        <input type="text" name="username" required>
                    </div>
                    <div class="form-group">
                        <label>Contrasena</label>
                        <input type="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label>Nombre</label>
                        <input type="text" name="nombre_completo" required>
                    </div>
                    <div class="form-group">
                        <label>Tipo</label>
                        <select name="tipo">
                            <option value="empleado">Empleado</option>
                            <option value="administrador">Administrador</option>
                            <option value="dueno">Dueno</option>
                        </select>
                    </div>
                    <button type="submit" name="crear_usuario" class="btn btn-primary">Crear</button>
                </form>
            </div>
            <div class="card">
                <h2>Usuarios</h2>
                <table>
                    <thead>
                        <tr><th>Usuario</th><th>Nombre</th><th>Tipo</th><th>Contrasena</th><th>Activo</th><th>Accion</th></tr>
                    </thead>
                    <tbody>
                        <?php while($u=$resultUsuarios->fetch_assoc()): ?>
                        <tr>
                            <form method="POST">
                                <td><input type="text" name="username" value="<?php echo h($u['username']); ?>"></td>
                                <td><input type="text" name="nombre_completo" value="<?php echo h($u['nombre_completo']); ?>"></td>
                                <td>
                                    <select name="tipo">
                                        <option value="empleado" <?php echo $u['tipo']=='empleado'?'selected':''; ?>>Empleado</option>
                                        <option value="administrador" <?php echo $u['tipo']=='administrador'?'selected':''; ?>>Admin</option>
                                        <option value="dueno" <?php echo $u['tipo']=='dueno'?'selected':''; ?>>Dueno</option>
                                    </select>
                                </td>
                                <td><input type="password" name="password" placeholder="Nueva"></td>
                                <td><input type="checkbox" name="activo" <?php echo $u['activo']?'checked':''; ?>></td>
                                <td>
                                    <input type="hidden" name="usuario_id" value="<?php echo h($u['id']); ?>">
                                    <button type="submit" name="actualizar_usuario" class="btn btn-warning btn-sm">Guardar</button>
                                    <button type="submit" name="eliminar_usuario" class="btn btn-danger btn-sm" onclick="return confirm('Eliminar?')">X</button>
                                </td>
                            </form>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <div id="config" class="tab-content" style="display: none;">
            <?php if ($permisos['ver_historial_dolar']): ?>
            <div class="card">
                <h2>Tasa del Dolar</h2>
                <div class="info-box">
                    <div class="number" style="font-size:2rem;">
                        <?php echo $tasa_actual>0 ? number_format($tasa_actual,2).' BS' : 'No disponible'; ?>
                    </div>
                </div>
                <form method="POST" class="form-inline mt-20">
                    <input type="number" name="tasa_manual" step="0.01" placeholder="Tasa manual">
                    <button type="submit" name="set_tasa_manual" class="btn btn-warning">Establecer</button>
                </form>
            </div>
            <div class="card">
                <h2>Historial de Tasas</h2>
                <table>
                    <thead><tr><th>Fecha</th><th>Tasa</th><th>Fuente</th></tr></thead>
                    <tbody>
                        <?php foreach($historialTasas as $t): ?>
                        <tr>
                            <td><?php echo h(date('d/m/Y',strtotime($t['fecha']))); ?></td>
                            <td><?php echo h(number_format($t['tasa'],2)); ?></td>
                            <td><?php echo h($t['fuente']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            <div class="card">
                <h2>Configuracion General</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Moneda</label>
                        <input type="text" name="moneda" value="<?php echo h(getMoneda()); ?>" required>
                    </div>
                    <button type="submit" name="guardar_config" class="btn btn-primary">Guardar</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
