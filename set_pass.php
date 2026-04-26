<?php
require_once 'db.php';

$hash = password_hash('123456', PASSWORD_DEFAULT);
$conn->query("UPDATE usuarios SET password = '$hash' WHERE username = 'admin'");

echo "✓ Contraseña de admin actualizada a: 123456";
?>