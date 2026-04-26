<?php
require_once 'db.php';

echo "<h1>Agregar Tarifas</h1>";

// Verificar existentes
$res = $conn->query("SELECT * FROM tarifas");
echo "<h2>Tarifas actuales:</h2>";
while ($row = $res->fetch_assoc()) {
    echo "<p>{$row['nombre']} - {$row['tipo_vehiculo']} - {$row['tipo_tarifa']} - \${$row['monto']}</p>";
}

// Agregar tarifas de Moto si no existen
$tarifas_moto = [
    ['Hora Moto', 'moto', 'hora', 3.00, 60],
    ['Fracción Moto', 'moto', 'fraccion', 1.50, 30],
    ['Día Moto', 'moto', 'dia', 30.00, 1440]
];

// Agregar tarifas de Camión si no existen
$tarifas_camion = [
    ['Hora Camión', 'camion', 'hora', 8.00, 60],
    ['Fracción Camión', 'camion', 'fraccion', 4.00, 30],
    ['Día Camión', 'camion', 'dia', 80.00, 1440]
];

foreach (array_merge($tarifas_moto, $tarifas_camion) as $tarifa) {
    $existe = $conn->query("SELECT id FROM tarifas WHERE nombre = '{$tarifa[0]}'");
    if ($existe->num_rows == 0) {
        $stmt = $conn->prepare("INSERT INTO tarifas (nombre, tipo_vehiculo, tipo_tarifa, monto, duracion_minutos) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssdi", $tarifa[0], $tarifa[1], $tarifa[2], $tarifa[3], $tarifa[4]);
        $stmt->execute();
        echo "<p style='green'>✓ Agregado: {$tarifa[0]}</p>";
    }
}

echo "<h2 style='green'>¡Tarifas agregadas!</h2>";
?>