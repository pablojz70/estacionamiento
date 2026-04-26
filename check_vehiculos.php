<?php
require_once 'db.php';

echo "<h1>Verificar estructura vehiculos</h1>";

$res = $conn->query("DESCRIBE vehiculos");
while ($row = $res->fetch_assoc()) {
    echo "<p>{$row['Field']} - {$row['Type']}</p>";
}
?>