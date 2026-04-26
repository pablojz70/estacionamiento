<?php
require_once 'db.php';

echo "<h1>Fix de Base de Datos</h1>";

echo "<p>El sistema recreará automáticamente la estructura de la base de datos.</p>";
echo "<p>Si necesita reiniciar, agregue '?reset=1' a la URL.</p>";

if (isset($_GET['reset'])) {
    $conn->query("DROP TABLE IF EXISTS tarifas");
    $conn->query("DROP TABLE IF EXISTS vehiculos");
    $conn->query("DROP TABLE IF EXISTS usuarios");
    $conn->query("DROP TABLE IF EXISTS tasas_dolar");
    $conn->query("DROP TABLE IF EXISTS configuraciones");
    
    echo "<p style='color:red'>Base de datos reseteada. Recargue la página.</p>";
}

echo "<p><a href='login.php'>Ir al Login</a></p>";
?>