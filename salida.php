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
                    $datosPago = ['monto_dolar' => 0, 'monto_bs' => 0, 'tasa' => $tasa_dolar, 'tarifa_usada' => 'Vehículo Fijo'];
                } else {
                    $tipo_v = $vehiculo['tipo_vehiculo'];
                    $datosPago = calcularPagoAuto($vehiculo['fecha_entrada'], date('Y-m-d H:i:s'), $tipo_v, $tarifas, $tasa_dolar);
                    $montoAPagar = $datosPago['monto_bs'];
                    $vehiculo['tarifa_seleccionada'] = ['nombre' => $datosPago['tarifa_usada']];
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
            $tarifaNombre = $datosPago['tarifa_usada'];
            $sqlUpdate = "UPDATE vehiculos SET fecha_salida = NOW(), monto_pagado = ?, monto_dolar = ?, tarifa_aplicada = ?, status = 'pagado' WHERE id = ?";
            $stmt = $conn->prepare($sqlUpdate);
            $stmt->bind_param("ddsi", $monto_bs, $monto_dolar, $tarifaNombre, $vehiculoId);
            $stmt->execute();
            
            $message = 'Pago registrado exitosamente. Monto: ' . number_format($monto_bs, 2) . ' BS (' . number_format($monto_dolar, 2) . ' USD)';
            $messageType = 'success';
            
            $ultimoPago = [
                'placa' => $_POST['placa_buscada'] ?? '',
                'monto_bs' => $monto_bs,
                'monto_dolar' => $monto_dolar,
                'tarifa' => $tarifaNombre,
                'fecha_salida' => date('Y-m-d H:i:s')
            ];
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
            var contenido = document.querySelector('.info-box.ticket').innerHTML;
            var ventana = window.open('', '_blank');
            ventana.document.write('<html><head><title>Comprobante</title>');
            ventana.document.write('<style>body{font-family:Arial;text-align:center;padding:20px;border:2px solid #000;}</style>');
            ventana.document.write('</head><body>');
            ventana.document.write(contenido);
            ventana.document.write('</body></html>');
            ventana.document.close();
            ventana.print();
            ventana.close();
}
    </script>
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