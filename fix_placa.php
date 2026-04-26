<?php
$conn = new mysqli('localhost', 'root', '', 'parking_db');
$conn->query("ALTER TABLE vehiculos MODIFY placa VARCHAR(20) NOT NULL");
$conn->query("ALTER TABLE vehiculos DROP INDEX placa");
$conn->query("CREATE INDEX idx_placa ON vehiculos(placa, status)");
echo "Listo - ahora puede entrar el mismo carro varias veces";
$conn->close();