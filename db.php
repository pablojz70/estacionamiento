<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'parking_db');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

$conn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
$conn->query("USE " . DB_NAME);

$conn->set_charset("utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    tipo ENUM('empleado', 'dueno', 'administrador') NOT NULL,
    nombre_completo VARCHAR(100) NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS tarifas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    tipo_vehiculo ENUM('carro', 'moto', 'camion') NOT NULL,
    tipo_tarifa ENUM('hora', 'fraccion', 'dia') NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    duracion_minutos INT DEFAULT 60,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS cuotas_fijas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_vehiculo ENUM('carro', 'moto', 'camion') NOT NULL,
    tipo_cuota ENUM('semanal', 'mensual') NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS vehiculos_fijos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    placa VARCHAR(20) NOT NULL UNIQUE,
    tipo_vehiculo ENUM('carro', 'moto', 'camion') NOT NULL,
    nombre_dueño VARCHAR(100) NOT NULL,
    telefono VARCHAR(30) NOT NULL,
    tipo_cuota ENUM('semanal', 'mensual') NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    estado ENUM('activo', 'vencido', 'cancelado') DEFAULT 'activo',
    id_usuario_registro INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario_registro) REFERENCES usuarios(id) ON DELETE SET NULL
)");

$conn->query("CREATE TABLE IF NOT EXISTS pagos_fijos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_vehiculo_fijo INT NOT NULL,
    id_usuario INT NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    tipo_cuota ENUM('semanal', 'mensual') NOT NULL,
    fecha_pago DATE NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_vehiculo_fijo) REFERENCES vehiculos_fijos(id) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
)");

$conn->query("CREATE TABLE IF NOT EXISTS vehiculos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    placa VARCHAR(20) NOT NULL,
    tipo_vehiculo ENUM('carro', 'moto', 'camion') NOT NULL,
    es_fijo TINYINT(1) DEFAULT 0,
    id_usuario INT NULL,
    id_vehiculo_fijo INT NULL,
    fecha_entrada DATETIME NOT NULL,
    fecha_salida DATETIME NULL,
    monto_pagado DECIMAL(10,2) DEFAULT 0,
    monto_dolar DECIMAL(10,2) DEFAULT 0,
    tarifa_aplicada VARCHAR(50) NULL,
    tasa_dolar DECIMAL(10,2) DEFAULT 0,
    status ENUM('dentro', 'pagado', 'cancelado', 'fijo_salida') DEFAULT 'dentro',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (id_vehiculo_fijo) REFERENCES vehiculos_fijos(id) ON DELETE SET NULL
)");

$conn->query("CREATE TABLE IF NOT EXISTS tasas_dolar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATE NOT NULL UNIQUE,
    tasa DECIMAL(10,2) NOT NULL,
    id_usuario INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE SET NULL
)");

$conn->query("CREATE TABLE IF NOT EXISTS configuraciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(50) NOT NULL UNIQUE,
    valor TEXT NOT NULL
)");

$result = $conn->query("SELECT COUNT(*) as cnt FROM usuarios");
if ($result && $result->fetch_assoc()['cnt'] == 0) {
    $hashAdmin = password_hash('admin123', PASSWORD_DEFAULT);
    $hashDueno = password_hash('dueno123', PASSWORD_DEFAULT);
    $hashEmpleado = password_hash('empleado123', PASSWORD_DEFAULT);
    
    $conn->query("INSERT INTO usuarios (username, password, tipo, nombre_completo) VALUES ('admin', '$hashAdmin', 'administrador', 'Administrador Principal')");
    $conn->query("INSERT INTO usuarios (username, password, tipo, nombre_completo) VALUES ('dueno', '$hashDueno', 'dueno', 'Dueño del Estacionamiento')");
    $conn->query("INSERT INTO usuarios (username, password, tipo, nombre_completo) VALUES ('empleado1', '$hashEmpleado', 'empleado', 'Empleado 1')");
}

$result = $conn->query("SELECT COUNT(*) as cnt FROM tarifas");
if ($result && $result->fetch_assoc()['cnt'] == 0) {
    $conn->query("INSERT INTO tarifas (nombre, tipo_vehiculo, tipo_tarifa, monto, duracion_minutos) VALUES ('Hora Carro', 'carro', 'hora', 5.00, 60)");
    $conn->query("INSERT INTO tarifas (nombre, tipo_vehiculo, tipo_tarifa, monto, duracion_minutos) VALUES ('Hora Moto', 'moto', 'hora', 3.00, 60)");
    $conn->query("INSERT INTO tarifas (nombre, tipo_vehiculo, tipo_tarifa, monto, duracion_minutos) VALUES ('Hora Camión', 'camion', 'hora', 10.00, 60)");
    $conn->query("INSERT INTO tarifas (nombre, tipo_vehiculo, tipo_tarifa, monto, duracion_minutos) VALUES ('Fracción Carro', 'carro', 'fraccion', 2.50, 30)");
    $conn->query("INSERT INTO tarifas (nombre, tipo_vehiculo, tipo_tarifa, monto, duracion_minutos) VALUES ('Fracción Moto', 'moto', 'fraccion', 1.50, 30)");
    $conn->query("INSERT INTO tarifas (nombre, tipo_vehiculo, tipo_tarifa, monto, duracion_minutos) VALUES ('Fracción Camión', 'camion', 'fraccion', 5.00, 30)");
    $conn->query("INSERT INTO tarifas (nombre, tipo_vehiculo, tipo_tarifa, monto, duracion_minutos) VALUES ('Día Carro', 'carro', 'dia', 50.00, 1440)");
    $conn->query("INSERT INTO tarifas (nombre, tipo_vehiculo, tipo_tarifa, monto, duracion_minutos) VALUES ('Día Moto', 'moto', 'dia', 30.00, 1440)");
    $conn->query("INSERT INTO tarifas (nombre, tipo_vehiculo, tipo_tarifa, monto, duracion_minutos) VALUES ('Día Camión', 'camion', 'dia', 100.00, 1440)");
}

$result = $conn->query("SELECT COUNT(*) as cnt FROM cuotas_fijas");
if ($result && $result->fetch_assoc()['cnt'] == 0) {
    $conn->query("INSERT INTO cuotas_fijas (tipo_vehiculo, tipo_cuota, monto) VALUES ('carro', 'semanal', 25.00)");
    $conn->query("INSERT INTO cuotas_fijas (tipo_vehiculo, tipo_cuota, monto) VALUES ('carro', 'mensual', 80.00)");
    $conn->query("INSERT INTO cuotas_fijas (tipo_vehiculo, tipo_cuota, monto) VALUES ('moto', 'semanal', 15.00)");
    $conn->query("INSERT INTO cuotas_fijas (tipo_vehiculo, tipo_cuota, monto) VALUES ('moto', 'mensual', 50.00)");
    $conn->query("INSERT INTO cuotas_fijas (tipo_vehiculo, tipo_cuota, monto) VALUES ('camion', 'semanal', 50.00)");
    $conn->query("INSERT INTO cuotas_fijas (tipo_vehiculo, tipo_cuota, monto) VALUES ('camion', 'mensual', 180.00)");
}

$result = $conn->query("SELECT COUNT(*) as cnt FROM configuraciones");
if ($result && $result->fetch_assoc()['cnt'] == 0) {
    $conn->query("INSERT INTO configuraciones (clave, valor) VALUES ('moneda', 'BS')");
    $conn->query("INSERT INTO configuraciones (clave, valor) VALUES ('nombre_estacionamiento', 'Estacionamiento Central')");
}

function getConfig($clave) {
    global $conn;
    $stmt = $conn->prepare("SELECT valor FROM configuraciones WHERE clave = ?");
    $stmt->bind_param("s", $clave);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row ? $row['valor'] : '';
}

function getMoneda() {
    return getConfig('moneda');
}

function getNombreEstacionamiento() {
    return getConfig('nombre_estacionamiento');
}

function getTasaDolarActual() {
    global $conn;
    $fecha = date('Y-m-d');
    $stmt = $conn->prepare("SELECT tasa, fuente FROM tasas_dolar WHERE fecha = ?");
    $stmt->bind_param("s", $fecha);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row && $row['tasa'] > 0) {
        return [
            'tasa' => $row['tasa'],
            'fuente' => $row['fuente'] ?? 'manual'
        ];
    }
    
    $tasaAutomatica = obtenerTasaDolarBCV();
    
    if ($tasaAutomatica > 0) {
        $stmt = $conn->prepare("INSERT INTO tasas_dolar (fecha, tasa, fuente) VALUES (?, ?, 'auto') ON DUPLICATE KEY UPDATE tasa = ?, fuente = 'auto'");
        $stmt->bind_param("sdd", $fecha, $tasaAutomatica, $tasaAutomatica);
        $stmt->execute();
        
        return [
            'tasa' => $tasaAutomatica,
            'fuente' => 'auto'
        ];
    }
    
    return ['tasa' => 0, 'fuente' => 'ninguna'];
}

function obtenerTasaDolarBCV() {
    $urls = [
        'https://ve.dolarapi.com/v1/dolares/oficial',
        'https://pydolarvenezuela-api.onrender.com/api/v1/dolar/bcv'
    ];
    
    foreach ($urls as $url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);
        
        if ($response) {
            $data = json_decode($response, true);
            
            $precio = null;
            
            if (isset($data['promedio']) && $data['promedio']) {
                $precio = floatval($data['promedio']);
            } elseif (isset($data['results']['dolares']['oficial']['precio'])) {
                $precio = floatval($data['results']['dolares']['oficial']['precio']);
            } elseif (isset($data['price'])) {
                $precio = floatval($data['price']);
            }
            
            if ($precio && $precio > 0 && $precio < 1000) {
                return round($precio, 2);
            }
        }
    }
    
    return 0;
}

function consultarDolarBackup() {
    $tasa = 0;
    
    $urls_backup = [
        'https://www.bcv.org.ve/sicca2/api/v1/dollar',
        'https://exchangetest.netlify.app/.netlify/functions/dolar'
    ];
    
    foreach ($urls_backup as $url) {
        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 3
                ]
            ]);
            
            $response = @file_get_contents($url, false, $context);
            
            if ($response) {
                $data = json_decode($response, true);
                
                if (isset($data['dollar']['price'])) {
                    $tasa = floatval($data['dollar']['price']);
                } elseif (isset($data['precio'])) {
                    $tasa = floatval($data['precio']);
                }
                
                if ($tasa > 0 && $tasa < 1000) {
                    return round($tasa, 2);
                }
            }
        } catch (Exception $e) {
            continue;
        }
    }
    
    return 0;
}

function setTasaDolar($tasa, $id_usuario, $manual = true) {
    global $conn;
    $fecha = date('Y-m-d');
    $fuente = $manual ? 'manual_admin' : 'auto';
    $stmt = $conn->prepare("INSERT INTO tasas_dolar (fecha, tasa, id_usuario, fuente) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE tasa = ?, id_usuario = ?, fuente = ?");
    $stmt->bind_param("sdiisd", $fecha, $tasa, $id_usuario, $fuente, $tasa, $id_usuario, $fuente);
    return $stmt->execute();
}

function getHistorialTasas($dias = 30) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM tasas_dolar ORDER BY fecha DESC LIMIT ?");
    $stmt->bind_param("i", $dias);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $tasas = [];
    while ($row = $result->fetch_assoc()) {
        $tasas[] = $row;
    }
    return $tasas;
}

function getCuotaFija($tipo_vehiculo, $tipo_cuota) {
    global $conn;
    $stmt = $conn->prepare("SELECT monto FROM cuotas_fijas WHERE tipo_vehiculo = ? AND tipo_cuota = ? AND activo = 1");
    $stmt->bind_param("ss", $tipo_vehiculo, $tipo_cuota);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row ? $row['monto'] : 0;
}

function esVehiculoFijo($placa) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM vehiculos_fijos WHERE placa = ? AND estado = 'activo' AND fecha_fin >= CURDATE()");
    $stmt->bind_param("s", $placa);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function calcularPago($fecha_entrada, $fecha_salida, $tarifa, $tasa_dolar = null) {
    $entrada = new DateTime($fecha_entrada);
    $salida = new DateTime($fecha_salida);
    $diff = $salida->diff($entrada);
    $minutos = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
    
    $monto = 0;
    
    if ($tarifa['tipo_tarifa'] == 'dia' && $minutos >= 1440) {
        $dias = ceil($minutos / 1440);
        $monto = $dias * $tarifa['monto'];
    } elseif ($tarifa['tipo_tarifa'] == 'fraccion') {
        $fracciones = ceil($minutos / $tarifa['duracion_minutos']);
        $monto = $fracciones * $tarifa['monto'];
    } else {
        $horas = ceil($minutos / 60);
        $monto = $horas * $tarifa['monto'];
    }
    
    $monto_bs = $monto;
    if ($tasa_dolar && $tasa_dolar > 0) {
        $monto_bs = $monto * $tasa_dolar;
    }
    
    return [
        'monto_dolar' => round($monto, 2),
        'monto_bs' => round($monto_bs, 2),
        'tasa' => $tasa_dolar
    ];
}

function formatDateTime($datetime) {
    return date("d/m/Y H:i", strtotime($datetime));
}

function verificarLogin($username, $password) {
    global $conn;
    $stmt = $conn->prepare("SELECT id, username, password, tipo, nombre_completo FROM usuarios WHERE username = ? AND activo = 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            return $user;
        }
    }
    return false;
}

function crearUsuario($username, $password, $tipo, $nombre_completo) {
    global $conn;
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO usuarios (username, password, tipo, nombre_completo) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $username, $hash, $tipo, $nombre_completo);
    return $stmt->execute();
}

function actualizarUsuario($id, $username, $password, $tipo, $nombre_completo, $activo) {
    global $conn;
    if (!empty($password)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE usuarios SET username = ?, password = ?, tipo = ?, nombre_completo = ?, activo = ? WHERE id = ?");
        $stmt->bind_param("ssssii", $username, $hash, $tipo, $nombre_completo, $activo, $id);
    } else {
        $stmt = $conn->prepare("UPDATE usuarios SET username = ?, tipo = ?, nombre_completo = ?, activo = ? WHERE id = ?");
        $stmt->bind_param("sssii", $username, $tipo, $nombre_completo, $activo, $id);
    }
    return $stmt->execute();
}

function eliminarUsuario($id) {
    global $conn;
    $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}

function getPermisos($tipo_usuario) {
    $permisos = [
        'empleado' => [
            'registrar_entrada' => true,
            'registrar_salida' => true,
            'introducir_dolar' => true,
            'imprimir_tickets' => true,
            'ver_cierre_diario' => true,
            'gestionar_fijos' => true,
            'configurar_tarifas' => false,
            'configurar_cuotas' => false,
            'gestionar_usuarios' => false,
            'ver_reportes_mensuales' => false,
            'ver_reportes_generales' => false,
            'ver_historial_dolar' => false,
            'mantenimiento' => false
        ],
        'administrador' => [
            'registrar_entrada' => false,
            'registrar_salida' => false,
            'introducir_dolar' => true,
            'imprimir_tickets' => false,
            'ver_cierre_diario' => false,
            'gestionar_fijos' => false,
            'configurar_tarifas' => true,
            'configurar_cuotas' => true,
            'gestionar_usuarios' => true,
            'ver_reportes_mensuales' => true,
            'ver_reportes_generales' => false,
            'ver_historial_dolar' => true,
            'mantenimiento' => true
        ],
        'dueno' => [
            'registrar_entrada' => false,
            'registrar_salida' => false,
            'introducir_dolar' => false,
            'imprimir_tickets' => false,
            'ver_cierre_diario' => false,
            'gestionar_fijos' => false,
            'configurar_tarifas' => false,
            'configurar_cuotas' => false,
            'gestionar_usuarios' => false,
            'ver_reportes_mensuales' => true,
            'ver_reportes_generales' => true,
            'ver_historial_dolar' => true,
            'mantenimiento' => false
        ]
    ];
    return $permisos[$tipo_usuario] ?? [];
}

function tienePermiso($tipo_usuario, $permiso) {
    $permisos = getPermisos($tipo_usuario);
    return $permisos[$permiso] ?? false;
}
?>