<?php
require_once 'db.php';

$tarifas = [
    ['carro', 'semana', 'Tarifa Semana Carro', 20, 7*1440],
    ['carro', 'mensual', 'Tarifa Mes Carro', 30, 30*1440],
    ['moto', 'semana', 'Tarifa Semana Moto', 15, 7*1440],
    ['moto', 'mensual', 'Tarifa Mes Moto', 20, 30*1440],
    ['camion', 'semana', 'Tarifa Semana Camion', 40, 7*1440],
    ['camion', 'mensual', 'Tarifa Mes Camion', 60, 30*1440],
];

foreach ($tarifas as $t) {
    $stmt = $conn->prepare("INSERT INTO tarifas (nombre, tipo_vehiculo, tipo_tarifa, monto, duracion_minutos, activo) VALUES (?, ?, ?, ?, ?, 1)");
    $stmt->bind_param("sssdi", $t[2], $t[0], $t[1], $t[3], $t[4]);
    $stmt->execute();
    echo $t[2] . " - OK<br>";
}

echo "Tarifas agregadas correctamente";