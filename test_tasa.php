<?php
require_once 'db.php';

echo "<h1>Debug Tasa</h1>";

$tasa = getTasaDolarActual();
echo "Tasa: ";
print_r($tasa);

echo "<h2>Consultando API...</h2>";
$api = obtenerTasaDolarBCV();
echo "API retorna: " . $api;
?>