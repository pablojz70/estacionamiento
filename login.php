<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'db.php';

if (isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $message = 'Por favor ingrese usuario y contraseña';
        $messageType = 'error';
    } else {
        $user = verificarLogin($username, $password);
        
        if ($user) {
            $_SESSION['usuario_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['tipo'] = $user['tipo'];
            $_SESSION['nombre_completo'] = $user['nombre_completo'];
            
            if ($user['tipo'] == 'empleado') {
                header("Location: index.php");
            } elseif ($user['tipo'] == 'administrador') {
                header("Location: admin.php");
            } elseif ($user['tipo'] == 'dueno') {
                header("Location: cierre.php");
            }
            exit;
        } else {
            $message = 'Usuario o contraseña incorrectos';
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Estacionamiento</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-body">
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <img src="imagen/logo.png" alt="Logo" class="logo">
                <h1>ESTACIONAMIENTO</h1>
                <p>Sistema de Gestión</p>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="login-form">
                <div class="form-group">
                    <label for="username">Usuario</label>
                    <input type="text" id="username" name="username" placeholder="Ingrese su usuario" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" placeholder="Ingrese su contraseña" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Iniciar Sesión</button>
            </form>
            
            <div class="login-footer">
                <p>Usuarios por defecto:</p>
                <ul>
                    <li><strong>empleado1</strong> / empleado123</li>
                    <li><strong>admin</strong> / admin123</li>
                    <li><strong>dueno</strong> / dueno123</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>