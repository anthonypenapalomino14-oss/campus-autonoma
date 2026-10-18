<?php
require_once __DIR__ . '/_comun.php';

if (clienteLogueado()) {
    header('Location: mi_cuenta.php');
    exit;
}

$error = '';
$dni = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = trim($_POST['dni'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!csrfTiendaValido($_POST['csrf_token'] ?? '')) {
        $error = 'La sesión expiró. Recarga la página e inténtalo de nuevo.';
    } elseif (!preg_match('/^\d{8}$/', $dni) || $password === '') {
        $error = 'Escribe un DNI de 8 dígitos y tu contraseña.';
    } else {
        try {
            $stmt = $conexion->prepare(
                "SELECT id_usuario, dni, nombres, apellidos, password_hash
                 FROM usuarios WHERE dni = ? AND rol = 'cliente' LIMIT 1"
            );
            $stmt->execute([$dni]);
            $cliente = $stmt->fetch();

            if ($cliente && password_verify($password, $cliente['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['cliente_id'] = (int)$cliente['id_usuario'];
                $_SESSION['cliente_dni'] = $cliente['dni'];
                $_SESSION['cliente_nombre'] = $cliente['nombres'];
                $_SESSION['cliente_apellidos'] = $cliente['apellidos'];
                $_SESSION['csrf_tienda'] = bin2hex(random_bytes(32));

                header('Location: mi_cuenta.php');
                exit;
            }

            $error = 'El DNI o la contraseña no coinciden. Si compraste en tienda y aún no tienes acceso, pide ayuda en caja.';
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = 'No pudimos iniciar tu sesión. Inténtalo otra vez.';
        }
    }
}

tienda_inicio('Iniciar sesión', 'angosto');
?>

<div class="tarjeta-form">
    <h1>Hola de nuevo</h1>
    <p class="bajada" style="margin-bottom:20px">Entra para ver tus compras y boletas.</p>

    <?php if (isset($_GET['registro'])): ?>
        <div class="aviso ok"><span>¡Cuenta creada! Ya puedes iniciar sesión.</span></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="aviso mal" role="alert"><span><?= h($error) ?></span></div>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <input type="hidden" name="csrf_token" value="<?= h(csrfTienda()) ?>">
        <div class="campo">
            <label for="dni">DNI</label>
            <input id="dni" name="dni" inputmode="numeric" maxlength="8" autocomplete="username" value="<?= h($dni) ?>" required autofocus>
        </div>
        <div class="campo">
            <label for="password">Contraseña</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
        </div>
        <button class="boton" type="submit">Entrar</button>
    </form>

    <div class="enlaces">
        <span>¿Primera vez? <a href="registro.php">Crea tu cuenta</a></span>
        <a href="index.php">Volver a la tienda</a>
    </div>
</div>

<?php tienda_fin(); ?>
