<?php
require_once __DIR__ . '/_comun.php';
exigirCliente();

$idCliente = (int)$_SESSION['cliente_id'];

$stmt = $conexion->prepare(
    "SELECT id_usuario, dni, nombres, apellidos FROM usuarios WHERE id_usuario = ? AND rol = 'cliente' LIMIT 1"
);
$stmt->execute([$idCliente]);
$cliente = $stmt->fetch();

if (!$cliente) {
    $_SESSION = [];
    session_destroy();
    header('Location: login.php');
    exit;
}

$stmt = $conexion->prepare(
    "SELECT v.id_venta, v.numero_venta, v.fecha_hora, v.total, v.estado,
            b.codigo_verificacion, s.nombre AS sede, s.color
     FROM ventas v
     INNER JOIN sedes s ON s.id_sede = v.id_sede
     LEFT JOIN boletas b ON b.id_venta = v.id_venta
     WHERE v.id_cliente = ?
     ORDER BY v.fecha_hora DESC LIMIT 20"
);
$stmt->execute([$idCliente]);
$compras = $stmt->fetchAll();

$gastado = 0.0;
foreach ($compras as $c) {
    if ($c['estado'] === 'completada') $gastado += (float)$c['total'];
}

$abiertos = $conexion->prepare("SELECT COUNT(*) FROM tickets_soporte WHERE id_cliente = ? AND estado <> 'resuelto'");
$abiertos->execute([$idCliente]);
$casosAbiertos = (int)$abiertos->fetchColumn();

tienda_inicio('Mis compras');
?>

<section class="portada" style="padding:30px">
    <h1>¡Hola, <?= h($cliente['nombres']) ?>!</h1>
    <p>Aquí están tus compras recientes y sus boletas. DNI <?= h($cliente['dni']) ?>.</p>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a class="boton" href="soporte.php">¿Necesitas ayuda con una compra?</a>
        <?php if ($casosAbiertos): ?><span class="pill bajo" style="align-self:center"><?= $casosAbiertos ?> consulta(s) en curso</span><?php endif; ?>
    </div>
</section>

<section class="rejilla c2">
    <article class="dato"><p>Compras registradas</p><strong><?= count($compras) ?></strong><span>Últimas 20</span></article>
    <article class="dato"><p>Total comprado</p><strong><?= soles($gastado) ?></strong><span>Compras completadas</span></article>
</section>

<section class="panel">
    <h2 style="margin-bottom:12px">Historial</h2>
    <?php if (!$compras): ?>
        <div class="estado-vacio"><strong>Aún no tienes compras</strong>Cuando compres en cualquiera de nuestras tiendas con tu DNI, aparecerán aquí.</div>
    <?php else: ?>
        <div class="tabla-wrap">
            <table>
                <thead><tr><th>Compra</th><th>Tienda</th><th class="num">Total</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($compras as $c): ?>
                    <tr>
                        <td><strong class="mono" style="color:var(--tinta)"><?= h($c['numero_venta']) ?></strong><br><small><?= h(date('d/m/Y H:i', strtotime($c['fecha_hora']))) ?></small></td>
                        <td><span class="chip-sede"><i style="background:<?= h($c['color']) ?>"></i><?= h($c['sede']) ?></span></td>
                        <td class="num"><b><?= soles($c['total']) ?></b></td>
                        <td><span class="pill <?= h($c['estado']) ?>"><?= h(ucfirst($c['estado'])) ?></span></td>
                        <td class="num">
                            <?php if ($c['estado'] === 'completada' && $c['codigo_verificacion']): ?>
                                <a class="boton sec chico" href="compras.php?id=<?= (int)$c['id_venta'] ?>">Ver boleta</a>
                            <?php endif; ?>
                            <a class="boton sec chico" href="soporte.php?venta=<?= (int)$c['id_venta'] ?>">Ayuda</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php tienda_fin(); ?>
