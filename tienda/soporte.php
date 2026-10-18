<?php
require_once __DIR__ . '/_comun.php';
exigirCliente();

$idCliente = (int)$_SESSION['cliente_id'];
$ok = $_SESSION['flash_ok'] ?? null;
unset($_SESSION['flash_ok']);
$error = null;
$asunto = '';
$mensaje = '';
$idVentaSel = (int)($_GET['venta'] ?? $_POST['id_venta'] ?? 0);

// Compras del cliente (para relacionar el caso)
$stmt = $conexion->prepare(
    "SELECT id_venta, numero_venta, fecha_hora, total FROM ventas WHERE id_cliente = ? ORDER BY fecha_hora DESC LIMIT 20"
);
$stmt->execute([$idCliente]);
$misCompras = $stmt->fetchAll();
$idsPropios = array_map(fn($c) => (int)$c['id_venta'], $misCompras);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $asunto = trim($_POST['asunto'] ?? '');
    $mensaje = trim($_POST['mensaje'] ?? '');
    $idVenta = (int)($_POST['id_venta'] ?? 0);

    if (!csrfTiendaValido($_POST['csrf_token'] ?? '')) {
        $error = 'La sesión expiró. Recarga la página e inténtalo de nuevo.';
    } elseif ($asunto === '' || mb_strlen($asunto) > 150) {
        $error = 'Escribe un asunto breve (hasta 150 caracteres).';
    } elseif (mb_strlen($mensaje) < 10 || mb_strlen($mensaje) > 2000) {
        $error = 'Cuéntanos qué pasó con un poco más de detalle (entre 10 y 2000 caracteres).';
    } elseif ($idVenta && !in_array($idVenta, $idsPropios, true)) {
        $error = 'Esa compra no pertenece a tu cuenta.';
    } else {
        $conexion->prepare(
            "INSERT INTO tickets_soporte (id_cliente, id_venta, asunto, mensaje) VALUES (?, ?, ?, ?)"
        )->execute([$idCliente, $idVenta ?: null, $asunto, $mensaje]);

        $_SESSION['flash_ok'] = 'Recibimos tu consulta. Te responderemos por aquí lo antes posible.';
        header('Location: soporte.php');
        exit;
    }
}

$stmt = $conexion->prepare(
    "SELECT t.id_ticket, t.asunto, t.estado, t.respuesta, t.creado_en, v.numero_venta
     FROM tickets_soporte t LEFT JOIN ventas v ON v.id_venta = t.id_venta
     WHERE t.id_cliente = ? ORDER BY t.creado_en DESC LIMIT 20"
);
$stmt->execute([$idCliente]);
$casos = $stmt->fetchAll();
$etq = ['abierto' => 'Recibida', 'en_proceso' => 'En revisión', 'resuelto' => 'Resuelta'];

tienda_inicio('Ayuda');
echo '';
?>

<h1 style="margin-bottom:6px">¿En qué te ayudamos?</h1>
<p class="bajada" style="margin-bottom:22px">Escríbenos sobre una compra, un producto o tu cuenta. Nuestro equipo de soporte te responde aquí mismo.</p>

<?php if ($ok): ?><div class="aviso ok"><span><?= h($ok) ?></span></div><?php endif; ?>
<?php if ($error): ?><div class="aviso mal" role="alert"><span><?= h($error) ?></span></div><?php endif; ?>

<section class="panel">
    <h2 style="margin-bottom:14px">Nueva consulta</h2>
    <form method="POST" action="soporte.php">
        <input type="hidden" name="csrf_token" value="<?= h(csrfTienda()) ?>">
        <div class="form-rejilla">
            <div class="campo ancho">
                <label for="asunto">Asunto</label>
                <input id="asunto" name="asunto" maxlength="150" value="<?= h($asunto) ?>" required>
            </div>
            <div class="campo ancho">
                <label for="id_venta">Compra relacionada <small>(opcional)</small></label>
                <select id="id_venta" name="id_venta">
                    <option value="0">Ninguna en particular</option>
                    <?php foreach ($misCompras as $c): ?>
                        <option value="<?= (int)$c['id_venta'] ?>" <?= $idVentaSel === (int)$c['id_venta'] ? 'selected' : '' ?>>
                            <?= h($c['numero_venta'] . ' · ' . date('d/m/Y', strtotime($c['fecha_hora'])) . ' · ' . soles($c['total'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo ancho">
                <label for="mensaje">Cuéntanos qué pasó</label>
                <textarea id="mensaje" name="mensaje" maxlength="2000" required><?= h($mensaje) ?></textarea>
            </div>
        </div>
        <div style="margin-top:16px"><button class="boton" type="submit">Enviar consulta</button></div>
    </form>
</section>

<section class="panel">
    <h2 style="margin-bottom:12px">Mis consultas</h2>
    <?php if (!$casos): ?>
        <div class="estado-vacio"><strong>Todavía no has escrito</strong>Cuando lo hagas, verás aquí la respuesta de nuestro equipo.</div>
    <?php else: ?>
        <ul class="lista">
            <?php foreach ($casos as $c): ?>
                <li style="align-items:flex-start">
                    <div class="crece">
                        <strong><?= h($c['asunto']) ?></strong>
                        <small><?= h(date('d/m/Y H:i', strtotime($c['creado_en']))) ?><?= $c['numero_venta'] ? ' · ' . h($c['numero_venta']) : '' ?></small>
                        <?php if ($c['respuesta']): ?>
                            <p style="white-space:pre-wrap;background:var(--marca-suave);border-radius:12px;padding:10px 12px;margin-top:8px"><strong>Respuesta:</strong> <?= h($c['respuesta']) ?></p>
                        <?php endif; ?>
                    </div>
                    <span class="pill <?= h($c['estado']) ?>"><?= $etq[$c['estado']] ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php tienda_fin(); ?>
