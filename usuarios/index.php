<?php
require_once __DIR__ . '/../config/auth.php';
exigirRol(['admin']);
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/layout.php';

$sedes = listarSedes($conexion);
$yo = (int)$_SESSION['usuario_id'];
$ok = $_SESSION['flash_ok'] ?? null;
unset($_SESSION['flash_ok']);
$error = null;
$form = ['dni' => '', 'nombres' => '', 'apellidos' => '', 'rol' => 'vendedor', 'id_sede' => '1'];

function idsSedeValidos(array $sedes): array
{
    return array_map(fn($s) => (int)$s['id_sede'], $sedes);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if (!csrfValido($_POST['csrf_token'] ?? '')) {
        $error = 'La sesión de seguridad expiró. Recarga la página.';
    } elseif ($accion === 'crear') {
        foreach ($form as $k => $_) {
            $form[$k] = trim((string)($_POST[$k] ?? ''));
        }
        $password = (string)($_POST['password'] ?? '');
        $idSede = (int)$form['id_sede'];

        if (!preg_match('/^\d{8}$/', $form['dni'])) {
            $error = 'El DNI debe tener 8 dígitos.';
        } elseif ($form['nombres'] === '' || $form['apellidos'] === '') {
            $error = 'Escribe nombres y apellidos.';
        } elseif (!in_array($form['rol'], ['admin', 'vendedor', 'soporte'], true)) {
            $error = 'Elige un perfil válido.';
        } elseif (strlen($password) < 8) {
            $error = 'La contraseña debe tener al menos 8 caracteres.';
        } elseif ($form['rol'] === 'vendedor' && !in_array($idSede, idsSedeValidos($sedes), true)) {
            $error = 'Un vendedor necesita una tienda asignada.';
        } else {
            try {
                $conexion->prepare(
                    "INSERT INTO usuarios (dni, nombres, apellidos, password_hash, rol, id_sede)
                     VALUES (?, ?, ?, ?, ?, ?)"
                )->execute([
                    $form['dni'], $form['nombres'], $form['apellidos'],
                    password_hash($password, PASSWORD_DEFAULT), $form['rol'],
                    $form['rol'] === 'vendedor' ? $idSede : null
                ]);
                $_SESSION['flash_ok'] = 'Cuenta creada para ' . $form['nombres'] . '.';
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                $error = $e->getCode() === '23000'
                    ? 'Ese DNI ya existe en el sistema.'
                    : 'No se pudo crear la cuenta.';
                if ($e->getCode() !== '23000') error_log($e->getMessage());
            }
        }
    } else {
        $idObjetivo = (int)($_POST['id_usuario'] ?? 0);
        $stmt = $conexion->prepare("SELECT id_usuario, nombres, rol FROM usuarios WHERE id_usuario = ? AND rol <> 'cliente'");
        $stmt->execute([$idObjetivo]);
        $obj = $stmt->fetch();

        if (!$obj) {
            $error = 'No encontramos esa cuenta.';
        } elseif ($accion === 'estado') {
            if ($idObjetivo === $yo) {
                $error = 'No puedes desactivar tu propia cuenta.';
            } else {
                $nuevo = ($_POST['activo'] ?? '0') === '1' ? 1 : 0;
                $conexion->prepare("UPDATE usuarios SET activo = ? WHERE id_usuario = ?")->execute([$nuevo, $idObjetivo]);
                $_SESSION['flash_ok'] = 'Cuenta de ' . $obj['nombres'] . ($nuevo ? ' activada.' : ' desactivada.');
                header('Location: index.php');
                exit;
            }
        } elseif ($accion === 'clave') {
            $nueva = (string)($_POST['password'] ?? '');
            if (strlen($nueva) < 8) {
                $error = 'La nueva contraseña debe tener al menos 8 caracteres.';
            } else {
                $conexion->prepare("UPDATE usuarios SET password_hash = ? WHERE id_usuario = ?")
                    ->execute([password_hash($nueva, PASSWORD_DEFAULT), $idObjetivo]);
                $_SESSION['flash_ok'] = 'Contraseña de ' . $obj['nombres'] . ' actualizada.';
                header('Location: index.php');
                exit;
            }
        } elseif ($accion === 'sede' && $obj['rol'] === 'vendedor') {
            $idSede = (int)($_POST['id_sede'] ?? 0);
            if (!in_array($idSede, idsSedeValidos($sedes), true)) {
                $error = 'Tienda no válida.';
            } else {
                $conexion->prepare("UPDATE usuarios SET id_sede = ? WHERE id_usuario = ?")->execute([$idSede, $idObjetivo]);
                $_SESSION['flash_ok'] = 'Tienda de ' . $obj['nombres'] . ' actualizada.';
                header('Location: index.php');
                exit;
            }
        }
    }
}

$equipo = $conexion->query(
    "SELECT u.id_usuario, u.dni, u.nombres, u.apellidos, u.rol, u.activo, u.id_sede,
            s.nombre AS sede, s.color
     FROM usuarios u LEFT JOIN sedes s ON s.id_sede = u.id_sede
     WHERE u.rol <> 'cliente' ORDER BY u.activo DESC, FIELD(u.rol,'admin','vendedor','soporte'), u.nombres"
)->fetchAll();

layout_inicio($conexion, 'Equipo', 'usuarios', [
    'eyebrow' => 'Administración',
    'descripcion' => 'Quién puede entrar al sistema, con qué perfil y en qué tienda trabaja.',
]);
echo avisoFlash($ok, $error);
$token = csrfToken();
?>

<details class="panel" <?= $error ? 'open' : '' ?>>
    <summary style="cursor:pointer;font-weight:700;font-size:17px;list-style:none;display:flex;align-items:center;gap:8px"><?= icono('plus') ?> Crear una cuenta del equipo</summary>
    <form method="POST" style="margin-top:18px">
        <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
        <input type="hidden" name="accion" value="crear">
        <div class="form-rejilla">
            <div class="campo"><label for="dni">DNI</label><input id="dni" name="dni" maxlength="8" inputmode="numeric" value="<?= h($form['dni']) ?>" required></div>
            <div class="campo"><label for="password">Contraseña inicial</label><input id="password" name="password" type="password" minlength="8" required></div>
            <div class="campo"><label for="nombres">Nombres</label><input id="nombres" name="nombres" maxlength="100" value="<?= h($form['nombres']) ?>" required></div>
            <div class="campo"><label for="apellidos">Apellidos</label><input id="apellidos" name="apellidos" maxlength="100" value="<?= h($form['apellidos']) ?>" required></div>
            <div class="campo"><label for="rol">Perfil</label>
                <select id="rol" name="rol">
                    <option value="vendedor" <?= $form['rol'] === 'vendedor' ? 'selected' : '' ?>>Ventas (caja de una tienda)</option>
                    <option value="soporte" <?= $form['rol'] === 'soporte' ? 'selected' : '' ?>>Soporte (atención al cliente)</option>
                    <option value="admin" <?= $form['rol'] === 'admin' ? 'selected' : '' ?>>Administración (todo)</option>
                </select></div>
            <div class="campo"><label for="id_sede">Tienda <small>(solo para ventas)</small></label>
                <select id="id_sede" name="id_sede">
                    <?php foreach ($sedes as $s): ?><option value="<?= (int)$s['id_sede'] ?>" <?= (int)$form['id_sede'] === (int)$s['id_sede'] ? 'selected' : '' ?>><?= h($s['nombre']) ?></option><?php endforeach; ?>
                </select></div>
        </div>
        <div style="margin-top:16px"><button class="boton" type="submit">Crear cuenta</button></div>
    </form>
</details>

<section class="panel">
    <div class="tabla-wrap">
        <table>
            <thead><tr><th>Persona</th><th>DNI</th><th>Perfil</th><th>Tienda</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($equipo as $u):
                $perfil = ['admin' => ['Administración', 'abierto'], 'vendedor' => ['Ventas', 'ok'], 'soporte' => ['Soporte', 'bajo']][$u['rol']]; ?>
                <tr>
                    <td><strong><?= h(trim($u['nombres'] . ' ' . $u['apellidos'])) ?></strong><?= (int)$u['id_usuario'] === $yo ? ' <small>(tú)</small>' : '' ?></td>
                    <td class="mono"><?= h($u['dni']) ?></td>
                    <td><span class="pill <?= $perfil[1] ?>"><?= $perfil[0] ?></span></td>
                    <td>
                        <?php if ($u['rol'] === 'vendedor'): ?>
                            <form method="POST" style="display:flex;gap:6px">
                                <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                                <input type="hidden" name="accion" value="sede">
                                <input type="hidden" name="id_usuario" value="<?= (int)$u['id_usuario'] ?>">
                                <select name="id_sede" style="width:auto;padding:6px 10px" onchange="this.form.submit()" aria-label="Tienda de <?= h($u['nombres']) ?>">
                                    <?php foreach ($sedes as $s): ?><option value="<?= (int)$s['id_sede'] ?>" <?= (int)$u['id_sede'] === (int)$s['id_sede'] ? 'selected' : '' ?>><?= h($s['nombre']) ?></option><?php endforeach; ?>
                                </select>
                            </form>
                        <?php else: ?><small>Ambas</small><?php endif; ?>
                    </td>
                    <td><span class="pill <?= $u['activo'] ? 'ok' : 'inactivo' ?>"><?= $u['activo'] ? 'Activa' : 'Desactivada' ?></span></td>
                    <td class="num">
                        <form method="POST" style="display:inline-flex;gap:6px;align-items:center">
                            <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
                            <input type="hidden" name="id_usuario" value="<?= (int)$u['id_usuario'] ?>">
                            <input type="password" name="password" placeholder="Nueva clave" minlength="8" style="width:130px;padding:6px 10px" aria-label="Nueva contraseña">
                            <button class="boton sec chico" name="accion" value="clave" type="submit">Cambiar</button>
                            <?php if ((int)$u['id_usuario'] !== $yo): ?>
                                <input type="hidden" name="activo" value="<?= $u['activo'] ? '0' : '1' ?>">
                                <button class="boton <?= $u['activo'] ? 'peligro' : 'sec' ?> chico" name="accion" value="estado" type="submit"><?= $u['activo'] ? 'Desactivar' : 'Activar' ?></button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php layout_fin(); ?>
