<?php
require_once 'db.php';

// Agregar tarifas fijas de ejemplo
$tarifas_fijas = [
    ['Fijo Carro Semanal', 'carro', 'semanal', 25.00],
    ['Fijo Carro Mensual', 'carro', 'mensual', 80.00],
    ['Fijo Moto Semanal', 'moto', 'semanal', 15.00],
    ['Fijo Moto Mensual', 'moto', 'mensual', 50.00],
    ['Fijo Camión Semanal', 'camion', 'semanal', 50.00],
    ['Fijo Camión Mensual', 'camion', 'mensual', 180.00]
];

foreach ($tarifas_fijas as $tarifa) {
    $conn->query("INSERT INTO cuotas_fijas (tipo_vehiculo, tipo_cuota, monto) VALUES ('{$tarifa[1]}', '{$tarifa[2]}', {$tarifa[3]})");
    echo "<p>✓ {$tarifa[0]}: \${$tarifa[3]}</p>";
}

echo "<h2 style='green'>¡Tarifas fijas agregadas!</h2>";
?>