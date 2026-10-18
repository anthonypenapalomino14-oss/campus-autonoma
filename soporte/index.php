<?php
require_once __DIR__ . '/../config/auth.php';
exigirRol(['admin', 'soporte']);
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/layout.php';

$idUsuario = (int)$_SESSION['usuario_id'];
$ok = $_SESSION['flash_ok'] ?? null;
unset($_SESSION['flash_ok']);
$error = null;

// ---- Responder / cambiar estado de un caso ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idTicket = filter_var($_POST['id_ticket'] ?? null, FILTER_VALIDATE_INT);
    $estadoNuevo = $_POST['estado'] ?? '';
    $respuesta = trim((string)($_POST['respuesta'] ?? ''));

    if (!csrfValido($_POST['csrf_token'] ?? '')) {
        $error = 'La sesión de seguridad expiró. Recarga la página.';
    } elseif (!$idTicket || !in_array($estadoNuevo, ['abierto', 'en_proceso', 'resuelto'], true)) {
        $error = 'Datos del caso no válidos.';
    } elseif ($estadoNuevo === 'resuelto' && $respuesta === '') {
        $error = 'Para resolver el caso escribe una respuesta para el cliente.';
    } elseif (mb_strlen($respuesta) > 2000) {
        $error = 'La respuesta es demasiado larga (máximo 2000 caracteres).';
    } else {
        $conexion->prepare(
            "UPDATE tickets_soporte
             SET estado = ?, respuesta = ?, id_atendido_por = ?
             WHERE id_ticket = ?"
        )->execute([$estadoNuevo, $respuesta !== '' ? $respuesta : null, $idUsuario, $idTicket]);

        $_SESSION['flash_ok'] = 'Caso #' . $idTicket . ' actualizado.';
        header('Location: index.php?ver=' . $idTicket);
        exit;
    }
}

$verId = (int)($_GET['ver'] ?? 0);
$filtro = in_array($_GET['estado'] ?? 'pendientes', ['pendientes', 'resueltos', 'todos'], true)
    ? ($_GET['estado'] ?? 'pendientes') : 'pendientes';
$dniBuscado = trim($_GET['dni'] ?? '');

// ---- Lista de casos ----
$sql = "SELECT t.id_ticket, t.asunto, t.estado, t.creado_en, u.nombres, u.apellidos, u.dni
        FROM tickets_soporte t INNER JOIN usuarios u ON u.id_usuario = t.id_cliente WHERE 1=1";
if ($filtro === 'pendientes') $sql .= " AND t.estado <> 'resuelto'";
if ($filtro === 'resueltos') $sql .= " AND t.estado = 'resuelto'";
$sql .= " ORDER BY t.creado_en DESC LIMIT 100";
$tickets = $conexion->query($sql)->fetchAll();

$pendientes = (int)$conexion->query("SELECT COUNT(*) FROM tickets_soporte WHERE estado <> 'resuelto'")->fetchColumn();

// ---- Caso abierto ----
$ticket = null;
if ($verId > 0) {
    $stmt = $conexion->prepare(
        "SELECT t.*, u.nombres, u.apellidos, u.dni, v.numero_venta, v.total,
                a.nombres AS an, a.apellidos AS aa
         FROM tickets_soporte t
         INNER JOIN usuarios u ON u.id_usuario = t.id_cliente
         LEFT JOIN ventas v ON v.id_venta = t.id_venta
         LEFT JOIN usuarios a ON a.id_usuario = t.id_atendido_por
         WHERE t.id_ticket = ?"
    );
    $stmt->execute([$verId]);
    $ticket = $stmt->fetch();
}

// ---- Consulta rápida de cliente ----
$clienteBuscado = null;
$comprasCliente = [];
if (preg_match('/^\d{8}$/', $dniBuscado)) {
    $stmt = $conexion->prepare("SELECT id_usuario, dni, nombres, apellidos, creado_en FROM usuarios WHERE dni = ? AND rol = 'cliente'");
    $stmt->execute([$dniBuscado]);
    $clienteBuscado = $stmt->fetch();
    if ($clienteBuscado) {
        $stmt = $conexion->prepare(
            "SELECT v.id_venta, v.numero_venta, v.fecha_hora, v.total, v.estado, s.nombre AS sede, s.color
             FROM ventas v INNER JOIN sedes s ON s.id_sede = v.id_sede
             WHERE v.id_cliente = ? ORDER BY v.id_venta DESC LIMIT 5"
        );
        $stmt->execute([(int)$clienteBuscado['id_usuario']]);
        $comprasCliente = $stmt->fetchAll();
    }
}

layout_inicio($conexion, 'Atención al cliente', 'soporte', [
    'eyebrow' => 'Soporte',
    'descripcion' => $pendientes
        ? "Tienes $pendientes caso(s) esperando respuesta."
        : 'No hay casos pendientes. ¡Buen trabajo!',
]);
echo avisoFlash($ok, $error);
$etq = ['abierto' => 'Nuevo', 'en_proceso' => 'En proceso', 'resuelto' => 'Resuelto'];
?>

<section class="panel">
    <form class="buscador" method="GET" style="margin-bottom:0">
        <input type="search" name="dni" inputmode="numeric" maxlength="8" placeholder="Consulta rápida: DNI del cliente (8 dígitos)" value="<?= h($dniBuscado) ?>">
        <button class="boton" type="submit"><?= icono('search', 16) ?> Buscar cliente</button>
    </form>

    <?php if ($dniBuscado !== ''): ?>
        <div style="margin-top:18px">
        <?php if (!preg_match('/^\d{8}$/', $dniBuscado)): ?>
            <p class="nota-suave">El DNI debe tener 8 dígitos.</p>
        <?php elseif (!$clienteBuscado): ?>
            <p class="nota-suave">No hay un cliente registrado con el DNI <?= h($dniBuscado) ?>.</p>
        <?php else: ?>
            <h3><?= h(trim($clienteBuscado['nombres'] . ' ' . $clienteBuscado['apellidos'])) ?>
                <small>· DNI <?= h($clienteBuscado['dni']) ?> · cliente desde <?= h(date('d/m/Y', strtotime($clienteBuscado['creado_en']))) ?></small></h3>
            <?php if (!$comprasCliente): ?><p class="nota-suave" style="margin-top:8px">Aún no tiene compras.</p><?php else: ?>
                <ul class="lista" style="margin-top:8px">
                    <?php foreach ($comprasCliente as $c): ?>
                        <li>
                            <div class="crece"><strong><a href="../facturacion/detalle.php?id=<?= (int)$c['id_venta'] ?>" style="color:inherit;text-decoration:none"><?= h($c['numero_venta']) ?></a></strong>
                                <small><?= h(date('d/m/Y H:i', strtotime($c['fecha_hora']))) ?></small></div>
                            <?= chipSede(['nombre' => $c['sede'], 'color' => $c['color']]) ?>
                            <span class="pill <?= h($c['estado']) ?>"><?= h(ucfirst($c['estado'])) ?></span>
                            <b class="num"><?= soles($c['total']) ?></b>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<div class="rejilla" style="grid-template-columns:minmax(0,1fr) minmax(0,1.3fr);align-items:start">
    <section class="panel">
        <nav class="pestanas">
            <?php foreach (['pendientes' => 'Pendientes', 'resueltos' => 'Resueltos', 'todos' => 'Todos'] as $k => $t): ?>
                <a class="<?= $filtro === $k ? 'activa' : '' ?>" href="?estado=<?= $k ?>"><?= $t ?></a>
            <?php endforeach; ?>
        </nav>

        <?php if (!$tickets): ?>
            <div class="estado-vacio"><strong>Sin casos por aquí</strong>Cuando un cliente escriba desde la tienda, aparecerá en esta lista.</div>
        <?php else: ?>
            <ul class="lista">
                <?php foreach ($tickets as $t): ?>
                    <li>
                        <div class="crece">
                            <strong><a href="?estado=<?= h($filtro) ?>&ver=<?= (int)$t['id_ticket'] ?>" style="color:inherit;text-decoration:none">#<?= (int)$t['id_ticket'] ?> · <?= h($t['asunto']) ?></a></strong>
                            <small><?= h(trim($t['nombres'] . ' ' . $t['apellidos'])) ?> · <?= h(date('d/m H:i', strtotime($t['creado_en']))) ?></small>
                        </div>
                        <span class="pill <?= h($t['estado']) ?>"><?= $etq[$t['estado']] ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="panel">
        <?php if (!$ticket): ?>
            <div class="estado-vacio"><strong>Elige un caso</strong>Verás aquí el mensaje del cliente y podrás responderle.</div>
        <?php else: ?>
            <div class="panel-cabecera">
                <div>
                    <h2>#<?= (int)$ticket['id_ticket'] ?> · <?= h($ticket['asunto']) ?></h2>
                    <p><?= h(trim($ticket['nombres'] . ' ' . $ticket['apellidos'])) ?> · DNI <?= h($ticket['dni']) ?> ·
                        <a href="?dni=<?= h($ticket['dni']) ?>">ver cliente</a></p>
                </div>
                <span class="pill <?= h($ticket['estado']) ?>"><?= $etq[$ticket['estado']] ?></span>
            </div>

            <p style="white-space:pre-wrap;background:#faf8f3;border:1px solid var(--linea);border-radius:12px;padding:14px"><?= h($ticket['mensaje']) ?></p>

            <?php if ($ticket['numero_venta']): ?>
                <p class="nota-suave" style="margin-top:10px">Compra relacionada:
                    <a href="../facturacion/detalle.php?id=<?= (int)$ticket['id_venta'] ?>"><?= h($ticket['numero_venta']) ?></a>
                    (<?= soles($ticket['total']) ?>)</p>
            <?php endif; ?>

            <form method="POST" style="margin-top:18px">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="id_ticket" value="<?= (int)$ticket['id_ticket'] ?>">
                <div class="form-rejilla" style="grid-template-columns:1fr">
                    <div class="campo">
                        <label for="respuesta">Respuesta para el cliente</label>
                        <textarea id="respuesta" name="respuesta" maxlength="2000"><?= h($ticket['respuesta']) ?></textarea>
                        <?php if ($ticket['an']): ?><small>Última atención: <?= h(trim($ticket['an'] . ' ' . $ticket['aa'])) ?></small><?php endif; ?>
                    </div>
                    <div class="campo">
                        <label for="estado">Estado del caso</label>
                        <select id="estado" name="estado">
                            <?php foreach ($etq as $k => $t): ?>
                                <option value="<?= $k ?>" <?= $ticket['estado'] === $k ? 'selected' : '' ?>><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="margin-top:16px"><button class="boton" type="submit">Guardar</button></div>
            </form>
        <?php endif; ?>
    </section>
</div>

<?php layout_fin(); ?>
