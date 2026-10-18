<?php
require_once __DIR__ . '/_comun.php';

if (clienteLogueado()) {
    header('Location: mi_cuenta.php');
    exit;
}

$errores = [];
$dni = '';
$nombres = '';
$apellidos = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = trim($_POST['dni'] ?? '');
    $nombres = trim($_POST['nombres'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmacion = $_POST['confirmacion'] ?? '';

    if (!csrfTiendaValido($_POST['csrf_token'] ?? '')) {
        $errores[] = 'La sesión expiró. Recarga la página e inténtalo de nuevo.';
    }
    if (!preg_match('/^\d{8}$/', $dni)) {
        $errores[] = 'El DNI debe tener exactamente 8 dígitos.';
    }
    if ($nombres === '' || $apellidos === '' || mb_strlen($nombres) > 100 || mb_strlen($apellidos) > 100) {
        $errores[] = 'Escribe tus nombres y apellidos.';
    }
    if (strlen($password) < 8) {
        $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
    }
    if ($password !== $confirmacion) {
        $errores[] = 'Las contraseñas no coinciden.';
    }

    if (!$errores) {
        try {
            $stmt = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE dni = ? LIMIT 1");
            $stmt->execute([$dni]);

            if ($stmt->fetch()) {
                $errores[] = 'Este DNI ya está registrado. Si compraste en tienda y aún no tienes acceso, pide ayuda en caja.';
            } else {
                $conexion->prepare(
                    "INSERT INTO usuarios (dni, nombres, apellidos, password_hash, rol, creado_en)
                     VALUES (?, ?, ?, ?, 'cliente', NOW())"
                )->execute([$dni, $nombres, $apellidos, password_hash($password, PASSWORD_DEFAULT)]);

                header('Location: login.php?registro=ok');
                exit;
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $errores[] = 'Ese DNI ya está registrado.';
            } else {
                error_log($e->getMessage());
                $errores[] = 'No pudimos crear tu cuenta. Inténtalo otra vez.';
            }
        }
    }
}

tienda_inicio('Crear cuenta', 'angosto');
?>

<div class="tarjeta-form">
    <h1>Crea tu cuenta</h1>
    <p class="bajada" style="margin-bottom:20px">Con tu DNI verás tus compras de ambas tiendas en un solo lugar.</p>

    <?php foreach ($errores as $e): ?>
        <div class="aviso mal" role="alert"><span><?= h($e) ?></span></div>
    <?php endforeach; ?>

    <form method="POST" action="registro.php">
        <input type="hidden" name="csrf_token" value="<?= h(csrfTienda()) ?>">
        <div class="campo"><label for="dni">DNI</label>
            <input id="dni" name="dni" inputmode="numeric" maxlength="8" value="<?= h($dni) ?>" required autofocus></div>
        <div class="campo"><label for="nombres">Nombres</label>
            <input id="nombres" name="nombres" maxlength="100" value="<?= h($nombres) ?>" required></div>
        <div class="campo"><label for="apellidos">Apellidos</label>
            <input id="apellidos" name="apellidos" maxlength="100" value="<?= h($apellidos) ?>" required></div>
        <div class="campo"><label for="password">Contraseña <small>(mínimo 8 caracteres)</small></label>
            <input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required></div>
        <div class="campo"><label for="confirmacion">Repite la contraseña</label>
            <input id="confirmacion" name="confirmacion" type="password" minlength="8" autocomplete="new-password" required></div>
        <button class="boton" type="submit">Crear cuenta</button>
    </form>

    <div class="enlaces">
        <span>¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a></span>
        <a href="index.php">Volver a la tienda</a>
    </div>
</div>

<?php tienda_fin(); ?>
