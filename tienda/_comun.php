<?php
// Sesión y diseño compartidos del perfil cliente (tienda en línea).
session_name('VENTAPRO_CLIENTE');

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

date_default_timezone_set('America/Lima');
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/inventario.php';

function h($valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function soles($valor): string
{
    return 'S/ ' . number_format((float)$valor, 2);
}

function clienteLogueado(): bool
{
    return !empty($_SESSION['cliente_id']);
}

function exigirCliente(): void
{
    if (!clienteLogueado()) {
        header('Location: login.php');
        exit;
    }
}

function csrfTienda(): string
{
    if (empty($_SESSION['csrf_tienda'])) {
        $_SESSION['csrf_tienda'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_tienda'];
}

function csrfTiendaValido(?string $token): bool
{
    return !empty($_SESSION['csrf_tienda']) && is_string($token)
        && hash_equals($_SESSION['csrf_tienda'], $token);
}

function avatarMarca(?string $marca, string $nombre, int $tam = 44): string
{
    $base = trim((string)$marca) !== '' ? $marca : $nombre;
    $tono = hexdec(substr(md5(mb_strtolower($base)), 0, 4)) % 360;
    $letras = mb_strtoupper(mb_substr(preg_replace('/[^\p{L}\p{N}]/u', '', $base), 0, 2));
    return '<span class="avatar-prod" style="width:' . $tam . 'px;height:' . $tam .
        'px;background:hsl(' . $tono . ',60%,92%);color:hsl(' . $tono . ',45%,28%)">' . h($letras) . '</span>';
}

function tienda_inicio(string $titulo, string $ancho = ''): void
{
    $logueado = clienteLogueado();
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($titulo) ?> · Market Donna</title>
    <link rel="stylesheet" href="../assets/estilos.css">
    <link rel="icon" type="image/png" href="../assets/icono.png">
</head>
<body class="tienda-cuerpo">
<header class="tienda-barra">
    <a class="logo" href="index.php">
        <span class="logo-texto">Market <b>Donna</b></span>
    </a>
    <nav class="tienda-nav">
        <a href="index.php">Productos y precios</a>
        <?php if ($logueado): ?>
            <a href="mi_cuenta.php">Mis compras</a>
            <a href="soporte.php">Ayuda</a>
            <a class="boton sec chico" href="logout.php">Salir</a>
        <?php else: ?>
            <a href="login.php">Iniciar sesión</a>
            <a class="boton chico" href="registro.php">Crear cuenta</a>
        <?php endif; ?>
    </nav>
</header>
<main class="tienda-main <?= h($ancho) ?>">
    <?php
}

function tienda_fin(): void
{
    ?>
</main>
<footer class="tienda-pie">Market Donna · Hecho para que compres con confianza</footer>
</body>
</html>
    <?php
}
