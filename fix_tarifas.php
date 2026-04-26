<?php
$conn = new mysqli('localhost', 'root', '', 'parking_db');
$conn->query("ALTER TABLE tarifas MODIFY tipo_tarifa ENUM('hora', 'fraccion', 'dia', 'semana', 'mensual') NOT NULL");
echo "Actualizado";
$conn->close();