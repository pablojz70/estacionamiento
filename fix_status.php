<?php
require_once 'db.php';

// Agregar status 'fijo_salida' si no existe
$conn->query("ALTER TABLE vehiculos MODIFY COLUMN status ENUM('dentro', 'pagado', 'cancelado', 'fijo_salida')");

echo "✓ Columna status actualizada";
?>