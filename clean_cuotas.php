<?php
require_once 'db.php';

echo "<h1>Limpiar Cuotas Duplicadas</h1>";

// Ver actuales
echo "<h2>Antes:</h2>";
$res = $conn->query("SELECT * FROM cuotas_fijas ORDER BY tipo_vehiculo, tipo_cuota");
while ($row = $res->fetch_assoc()) {
    echo "<p>{$row['tipo_vehiculo']} - {$row['tipo_cuota']} - \${$row['monto']} (id: {$row['id']})</p>";
}

// Eliminar duplicados, keeping el mayor ID
$conn->query("DELETE FROM cuotas_fijas WHERE id NOT IN (
    SELECT MAX(id) FROM cuotas_fijas GROUP BY tipo_vehiculo, tipo_cuota
)");

echo "<h2>Después:</h2>";
$res = $conn->query("SELECT * FROM cuotas_fijas ORDER BY tipo_vehiculo, tipo_cuota");
while ($row = $res->fetch_assoc()) {
    echo "<p>{$row['tipo_vehiculo']} - {$row['tipo_cuota']} - \${$row['monto']}</p>";
}

echo "<h2 style='green'>✓ Duplicados eliminados</h2>";
?>