<?php
require_once 'db.php';

echo "<h1>Limpiar Tarifas Duplicadas</h1>";

// Ver actuales
echo "<h2>Antes:</h2>";
$res = $conn->query("SELECT * FROM tarifas ORDER BY tipo_vehiculo, tipo_tarifa");
while ($row = $res->fetch_assoc()) {
    echo "<p>{$row['nombre']} (id: {$row['id']})</p>";
}

// Eliminar duplicados
$conn->query("DELETE FROM tarifas WHERE id NOT IN (
    SELECT MAX(id) FROM tarifas GROUP BY nombre
)");

echo "<h2>Después:</h2>";
$res = $conn->query("SELECT * FROM tarifas ORDER BY tipo_vehiculo, tipo_tarifa");
while ($row = $res->fetch_assoc()) {
    echo "<p>{$row['nombre']}</p>";
}

echo "<h2 style='green'>✓ Listo</h2>";
?>