<?php
require_once __DIR__ . '/../config/auth.php';
exigirRol(['admin', 'soporte']);
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/layout.php';

$esAdmin = rolActual() === 'admin';
$ok = null;
$error = null;
$form = ['dni' => '', 'nombres' => '', 'apellidos' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$esAdmin) {
        http_response_code(403);
        exit('Sin permiso.');
    }

    foreach ($form as $k => $_) {
        $form[$k] = trim((string)($_POST[$k] ?? ''));
    }
    $password = (string)($_POST['password'] ?? '');

    if (!csrfValido($_POST['csrf_token'] ?? '')) {
        $error = 'La sesión de seguridad expiró. Recarga la página.';
    } elseif ($form['dni'] === '' || $form['nombres'] === '' || $form['apellidos'] === '' || $password === '') {
        $error = 'Completa todos los campos.';
    } elseif (!preg_match('/^\d{8}$/', $form['dni'])) {
        $error = 'El DNI debe tener 8 dígitos.';
    } elseif (strlen($password) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres.';
    } else {
        try {
            $conexion->prepare(
                "INSERT INTO usuarios (dni, nombres, apellidos, password_hash, rol)
                 VALUES (?, ?, ?, ?, 'cliente')"
            )->execute([$form['dni'], $form['nombres'], $form['apellidos'], password_hash($password, PASSWORD_DEFAULT)]);
            $ok = 'Cliente registrado correctamente.';
            $form = ['dni' => '', 'nombres' => '', 'apellidos' => ''];
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $error = 'Ese DNI ya está registrado en el sistema.';
            } else {
                error_log($e->getMessage());
                $error = 'No se pudo registrar el cliente.';
            }
        }
    }
}

$buscar = trim($_GET['buscar'] ?? '');
$sql = "SELECT u.id_usuario, u.dni, u.nombres, u.apellidos, u.creado_en,
               COUNT(v.id_venta) AS compras, COALESCE(SUM(v.total), 0) AS gastado, MAX(v.fecha_hora) AS ultima
        FROM usuarios u
        LEFT JOIN ventas v ON v.id_cliente = u.id_usuario AND v.estado = 'completada'
        WHERE u.rol = 'cliente'";
$params = [];
if ($buscar !== '') {
    $sql .= " AND (u.dni LIKE ? OR u.nombres LIKE ? OR u.apellidos LIKE ?)";
    array_push($params, "%$buscar%", "%$buscar%", "%$buscar%");
}
$sql .= " GROUP BY u.id_usuario, u.dni, u.nombres, u.apellidos, u.creado_en ORDER BY u.id_usuario DESC LIMIT 300";
$stmt = $conexion->prepare($sql);
$stmt->execute($params);
$clientes = $stmt->fetchAll();

layout_inicio($conexion, 'Clientes', 'clientes', [
    'eyebrow' => 'Personas',
    'descripcion' => 'Quién compra, cuánto y cuándo fue la última vez.',
]);
echo avisoFlash($ok, $error);
?>

<?php if ($esAdmin): ?>
<details class="panel" <?= $error ? 'open' : '' ?>>
    <summary style="cursor:pointer;font-weight:700;font-size:17px;list-style:none;display:flex;align-items:center;gap:8px"><?= icono('plus') ?> Registrar un cliente</summary>
    <form method="POST" style="margin-top:18px">
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
        <div class="form-rejilla">
            <div class="campo"><label for="dni">DNI</label><input id="dni" name="dni" maxlength="8" inputmode="numeric" value="<?= h($form['dni']) ?>" required></div>
            <div class="campo"><label for="password">Contraseña inicial</label><input id="password" name="password" type="password" minlength="8" required></div>
            <div class="campo"><label for="nombres">Nombres</label><input id="nombres" name="nombres" maxlength="100" value="<?= h($form['nombres']) ?>" required></div>
            <div class="campo"><label for="apellidos">Apellidos</label><input id="apellidos" name="apellidos" maxlength="100" value="<?= h($form['apellidos']) ?>" required></div>
        </div>
        <div style="margin-top:16px"><button class="boton" type="submit">Guardar cliente</button></div>
    </form>
</details>
<?php endif; ?>

<section class="panel">
    <form class="buscador" method="GET">
        <input type="search" name="buscar" placeholder="Buscar por DNI, nombre o apellido…" value="<?= h($buscar) ?>">
        <button class="boton" type="submit">Buscar</button>
    </form>
    <div class="tabla-wrap">
        <table>
            <thead><tr><th>Cliente</th><th>DNI</th><th class="num">Compras</th><th class="num">Total gastado</th><th>Última compra</th><?php if (!$esAdmin): ?><th></th><?php endif; ?></tr></thead>
            <tbody>
            <?php if (!$clientes): ?><tr><td colspan="6" class="vacio">No encontramos clientes.</td></tr><?php endif; ?>
            <?php foreach ($clientes as $c): ?>
                <tr>
                    <td><strong><?= h(trim($c['nombres'] . ' ' . $c['apellidos'])) ?></strong><br><small>Desde <?= h(date('d/m/Y', strtotime($c['creado_en']))) ?></small></td>
                    <td class="mono"><?= h($c['dni']) ?></td>
                    <td class="num"><?= (int)$c['compras'] ?></td>
                    <td class="num"><b><?= soles($c['gastado']) ?></b></td>
                    <td><?= $c['ultima'] ? h(date('d/m/Y H:i', strtotime($c['ultima']))) : '—' ?></td>
                    <?php if (!$esAdmin): ?><td class="num"><a class="boton sec chico" href="../soporte/?dni=<?= h($c['dni']) ?>">Ver</a></td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php layout_fin(); ?>
