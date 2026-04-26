<?php
require_once 'db.php';

$hash = password_hash('123456', PASSWORD_DEFAULT);

$conn->query("UPDATE usuarios SET password = '$hash'");
echo "✓ Todas las contraseñas actualizadas a: 123456";
?>