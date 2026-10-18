<?php
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/config/auth.php';

if (!empty($_SESSION['usuario_id'])) {
    header('Location: ' . rutaInicio($_SESSION['rol']));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = trim($_POST['dni'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($dni === '' || $password === '') {
        $error = 'Escribe tu DNI y tu contraseña.';
    } else {
        $stmt = $conexion->prepare(
            "SELECT id_usuario, dni, nombres, apellidos, password_hash, rol, id_sede
             FROM usuarios
             WHERE dni = :dni
               AND rol IN ('admin', 'vendedor', 'soporte')
               AND activo = 1
             LIMIT 1"
        );
        $stmt->execute([':dni' => $dni]);
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($password, $usuario['password_hash'])) {
            session_regenerate_id(true);

            $_SESSION['usuario_id'] = (int)$usuario['id_usuario'];
            $_SESSION['dni'] = $usuario['dni'];
            $_SESSION['nombre'] = $usuario['nombres'];
            $_SESSION['apellidos'] = $usuario['apellidos'];
            $_SESSION['rol'] = $usuario['rol'];
            $_SESSION['id_sede'] = (int)($usuario['id_sede'] ?: 1);

            header('Location: ' . rutaInicio($usuario['rol']));
            exit;
        }

        $error = 'El DNI o la contraseña no coinciden. Inténtalo otra vez.';
    }
}

function h($valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar · Market Donna</title>
    <link rel="stylesheet" href="assets/estilos.css">
    <link rel="icon" type="image/png" href="assets/icono.png">
    <style>
        .acceso { min-height: 100vh; display: grid; grid-template-columns: 1.1fr 1fr; }
        .acceso-lado {
            background: radial-gradient(900px 500px at 10% 0%, #1f8f48 0%, transparent 60%), #131311;
            color: #fff; padding: 48px; display: flex; flex-direction: column; justify-content: space-between;
        }
        .acceso-lado .logo { padding: 0; }
        .acceso-lado h2 { font-size: 36px; line-height: 1.1; max-width: 14ch; letter-spacing: -.02em; }
        .acceso-lado p { color: #cfd6cc; max-width: 40ch; margin-top: 14px; }
        .acceso-pie { color: #8b8b84; font-size: 13px; }
        .acceso-form { display: grid; place-items: center; padding: 32px 22px; }
        .acceso-caja { width: 100%; max-width: 380px; }
        .acceso-caja h1 { font-size: 26px; }
        .acceso-caja .bajada { margin-bottom: 24px; }
        .acceso-caja .campo { margin-bottom: 16px; }
        .acceso-caja .boton { width: 100%; padding: 13px; margin-top: 6px; }
        .acceso-enlace { margin-top: 22px; font-size: 14px; color: var(--suave); }
        @media (max-width: 800px) { .acceso { grid-template-columns: 1fr; } .acceso-lado { display: none; } }
    </style>
</head>
<body>
<div class="acceso">
    <section class="acceso-lado">
        <a class="logo" href="login.php">
            <span class="logo-texto">Market <b>Donna</b></span>
        </a>
        <div>
            <h2>Probablemente… las mejores frutas y verduras.</h2>
            <p>Ventas, inventario por sede y atención al cliente conectados, para que todo el equipo vea lo mismo.</p>
        </div>
        <span class="acceso-pie">© Market Donna</span>
    </section>

    <section class="acceso-form">
        <main class="acceso-caja">
            <h1>Bienvenido a Market Donna</h1>
            <p class="bajada">Entra con tu DNI para empezar tu turno.</p>

            <?php if ($error !== ''): ?>
                <div class="aviso mal" role="alert"><span><?= h($error) ?></span></div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="campo">
                    <label for="dni">DNI</label>
                    <input id="dni" name="dni" inputmode="numeric" maxlength="8"
                           autocomplete="username" value="<?= h($_POST['dni'] ?? '') ?>" required autofocus>
                </div>
                <div class="campo">
                    <label for="password">Contraseña</label>
                    <input id="password" name="password" type="password"
                           autocomplete="current-password" required>
                </div>
                <button type="submit" class="boton">Entrar</button>
            </form>

            <p class="acceso-enlace">¿Eres cliente? <a href="tienda/">Ir a la tienda en línea</a></p>
        </main>
    </section>
</div>
</body>
</html>
