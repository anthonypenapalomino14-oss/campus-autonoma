<?php
require_once __DIR__ . '/../config/auth.php';
exigirRol(['admin', 'vendedor', 'soporte']);
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/layout.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    header('Location: index.php');
    exit;
}

$stmt = $conexion->prepare(
    "SELECT v.*, u.nombres AS vn, u.apellidos AS va, s.nombre AS sede, s.color,
            b.codigo_verificacion, b.fecha_emision
     FROM ventas v
     INNER JOIN usuarios u ON u.id_usuario = v.id_vendedor
     INNER JOIN sedes s ON s.id_sede = v.id_sede
     LEFT JOIN boletas b ON b.id_venta = v.id_venta
     WHERE v.id_venta = ?"
);
$stmt->execute([$id]);
$v = $stmt->fetch();

// El vendedor solo ve ventas de su tienda
if (!$v || (rolActual() === 'vendedor' && (int)$v['id_sede'] !== sedeActualId($conexion))) {
    http_response_code(404);
    layout_inicio($conexion, 'Venta no encontrada', 'facturacion', [
        'acciones' => '<a class="boton sec" href="index.php">← Volver</a>',
    ]);
    echo '<div class="panel estado-vacio"><strong>No encontramos esa venta</strong>Puede que no exista o sea de otra tienda.</div>';
    layout_fin();
    exit;
}

$stmt = $conexion->prepare(
    "SELECT d.cantidad, d.precio_unitario, d.subtotal, p.id_producto, p.nombre, p.codigo, m.nombre AS marca
     FROM detalle_venta d
     INNER JOIN productos p ON p.id_producto = d.id_producto
     LEFT JOIN marcas m ON m.id_marca = p.id_marca
     WHERE d.id_venta = ? ORDER BY d.id_detalle"
);
$stmt->execute([$id]);
$items = $stmt->fetchAll();

$stmt = $conexion->prepare(
    "SELECT mv.cantidad, mv.stock_resultante, p.nombre
     FROM movimientos_inventario mv
     INNER JOIN productos p ON p.id_producto = mv.id_producto
     WHERE mv.id_venta = ? ORDER BY mv.id_movimiento"
);
$stmt->execute([$id]);
$movs = $stmt->fetchAll();

$acciones = '<a class="boton sec" href="index.php">← Facturación</a>';
if ($v['codigo_verificacion']) {
    $acciones .= '<a class="boton" target="_blank" rel="noopener" href="../ventas/boleta.php?numero=' .
        rawurlencode($v['numero_venta']) . '">' . icono('print') . ' Ver boleta</a>';
}

layout_inicio($conexion, 'Venta ' . $v['numero_venta'], 'facturacion', [
    'eyebrow' => 'Detalle de facturación',
    'acciones' => $acciones,
]);
?>

<section class="rejilla c3">
    <article class="dato destacado"><p>Total</p><strong><?= soles($v['total']) ?></strong><span><span class="pill <?= h($v['estado']) ?>"><?= h(ucfirst($v['estado'])) ?></span></span></article>
    <article class="dato"><p>Tienda</p><strong style="font-size:20px"><?= chipSede(['nombre' => $v['sede'], 'color' => $v['color']]) ?></strong><span>Vendió: <?= h(trim($v['vn'] . ' ' . $v['va'])) ?></span></article>
    <article class="dato"><p>Cliente</p><strong style="font-size:20px"><?= h($v['cliente_nombre'] ?: 'Cliente general') ?></strong><span>DNI <?= h($v['cliente_dni'] ?: '—') ?> · <?= h(date('d/m/Y H:i', strtotime($v['fecha_hora']))) ?></span></article>
</section>

<section class="panel">
    <div class="panel-cabecera"><div><h2>Productos vendidos</h2></div></div>
    <div class="tabla-wrap">
        <table>
            <thead><tr><th>Producto</th><th>Código</th><th class="num">Cant.</th><th class="num">Precio</th><th class="num">Subtotal</th></tr></thead>
            <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td>
                        <div class="celda-producto">
                            <?= avatarProducto($it['marca'], $it['nombre']) ?>
                            <div>
                                <strong><a href="../reportes/producto.php?id=<?= (int)$it['id_producto'] ?>" style="color:inherit;text-decoration:none"><?= h($it['nombre']) ?></a></strong>
                                <?php if ($it['marca']): ?><span class="marca-tag"><?= h($it['marca']) ?></span><?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="mono"><?= h($it['codigo'] ?: '—') ?></td>
                    <td class="num"><?= (int)$it['cantidad'] ?></td>
                    <td class="num"><?= soles($it['precio_unitario']) ?></td>
                    <td class="num"><b><?= soles($it['subtotal']) ?></b></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="rejilla c2">
    <article class="panel">
        <h2 style="margin-bottom:12px">Impacto en el inventario</h2>
        <?php if (!$movs): ?>
            <p class="nota-suave">Esta venta es anterior al control de stock por tienda, no tiene movimientos asociados.</p>
        <?php else: ?>
            <ul class="lista">
                <?php foreach ($movs as $m): ?>
                    <li><div class="crece"><strong><?= h($m['nombre']) ?></strong><small>Quedaron <?= (int)$m['stock_resultante'] ?> en <?= h($v['sede']) ?></small></div>
                        <b style="color:#b42318"><?= (int)$m['cantidad'] ?></b></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </article>
    <article class="panel">
        <h2 style="margin-bottom:12px">Verificación</h2>
        <?php if ($v['codigo_verificacion']): ?>
            <p class="nota-suave">Emitida el <?= h(date('d/m/Y H:i', strtotime($v['fecha_emision']))) ?></p>
            <p class="mono" style="word-break:break-all;margin:10px 0"><?= h($v['codigo_verificacion']) ?></p>
            <a class="boton sec chico" target="_blank" rel="noopener" href="../ventas/verificar_boleta.php?codigo=<?= rawurlencode($v['codigo_verificacion']) ?>">Abrir verificación pública</a>
        <?php else: ?>
            <p class="nota-suave">Esta venta no tiene boleta emitida.</p>
        <?php endif; ?>
    </article>
</section>

<?php layout_fin(); ?>
