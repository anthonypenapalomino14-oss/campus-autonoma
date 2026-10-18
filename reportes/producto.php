<?php
require_once __DIR__ . '/../config/auth.php';
exigirRol(['admin', 'vendedor', 'soporte']);
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/layout.php';

$esAdmin = rolActual() === 'admin';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

$p = null;
if ($id && $id > 0) {
    $stmt = $conexion->prepare(
        "SELECT p.*, m.nombre AS marca FROM productos p
         LEFT JOIN marcas m ON m.id_marca = p.id_marca WHERE p.id_producto = ?"
    );
    $stmt->execute([$id]);
    $p = $stmt->fetch();
}

if (!$p) {
    http_response_code(404);
    layout_inicio($conexion, 'Producto no encontrado', $esAdmin ? 'reportes' : 'inventario', [
        'acciones' => '<a class="boton sec" href="../inventario/">← Inventario</a>',
    ]);
    echo '<div class="panel estado-vacio"><strong>No encontramos ese producto</strong></div>';
    layout_fin();
    exit;
}

$sedes = listarSedes($conexion);

$stmt = $conexion->prepare("SELECT id_sede, stock, stock_minimo FROM inventario WHERE id_producto = ?");
$stmt->execute([$id]);
$inv = [];
foreach ($stmt->fetchAll() as $f) {
    $inv[(int)$f['id_sede']] = $f;
}

$ventasSede = [];
$ultimasVentas = [];
$kardex = [];
if ($esAdmin) {
    $stmt = $conexion->prepare(
        "SELECT v.id_sede, SUM(d.cantidad) AS unidades, SUM(d.subtotal) AS ingreso, COUNT(DISTINCT v.id_venta) AS ventas
         FROM detalle_venta d INNER JOIN ventas v ON v.id_venta = d.id_venta AND v.estado = 'completada'
         WHERE d.id_producto = ? GROUP BY v.id_sede"
    );
    $stmt->execute([$id]);
    foreach ($stmt->fetchAll() as $f) {
        $ventasSede[(int)$f['id_sede']] = $f;
    }

    $stmt = $conexion->prepare(
        "SELECT v.id_venta, v.numero_venta, v.fecha_hora, v.cliente_nombre, d.cantidad, d.subtotal,
                s.nombre AS sede, s.color
         FROM detalle_venta d
         INNER JOIN ventas v ON v.id_venta = d.id_venta
         INNER JOIN sedes s ON s.id_sede = v.id_sede
         WHERE d.id_producto = ? ORDER BY v.id_venta DESC LIMIT 10"
    );
    $stmt->execute([$id]);
    $ultimasVentas = $stmt->fetchAll();

    $stmt = $conexion->prepare(
        "SELECT mv.*, s.nombre AS sede, s.color FROM movimientos_inventario mv
         INNER JOIN sedes s ON s.id_sede = mv.id_sede
         WHERE mv.id_producto = ? ORDER BY mv.id_movimiento DESC LIMIT 10"
    );
    $stmt->execute([$id]);
    $kardex = $stmt->fetchAll();
}

$acciones = '<a class="boton sec" href="' . ($esAdmin ? 'index.php' : '../inventario/') . '">← Volver</a>';
if ($esAdmin) {
    $acciones .= '<a class="boton" href="../productos/editar.php?id=' . (int)$id . '">' . icono('edit') . ' Editar producto</a>';
}

layout_inicio($conexion, $p['nombre'], $esAdmin ? 'reportes' : 'inventario', [
    'eyebrow' => 'Ficha de producto' . ($p['marca'] ? ' · ' . $p['marca'] : ''),
    'acciones' => $acciones,
]);
?>

<section class="panel" style="display:flex;gap:20px;align-items:center;flex-wrap:wrap">
    <?= avatarProducto($p['marca'], $p['nombre'], 64) ?>
    <div style="flex:1;min-width:220px">
        <?php if ($p['marca']): ?><span class="marca-tag"><?= h($p['marca']) ?></span><?php endif; ?>
        <span class="pill <?= $p['activo'] ? 'ok' : 'inactivo' ?>"><?= $p['activo'] ? 'Activo' : 'Inactivo' ?></span>
        <p class="mono" style="margin-top:6px">Código: <?= h($p['codigo'] ?: 'sin código') ?></p>
        <?php if ($p['descripcion']): ?><p class="nota-suave" style="margin-top:4px"><?= h($p['descripcion']) ?></p><?php endif; ?>
    </div>
    <div style="text-align:right"><small>Precio de venta</small><div style="font-size:30px;font-weight:800"><?= soles($p['precio']) ?></div></div>
</section>

<section class="rejilla c2">
    <?php foreach ($sedes as $s): $idS = (int)$s['id_sede'];
        $f = $inv[$idS] ?? ['stock' => 0, 'stock_minimo' => 5];
        $vs = $ventasSede[$idS] ?? null; ?>
        <article class="panel sede-tarjeta" style="border-top-color:<?= h($s['color']) ?>">
            <h3><?= icono('store') ?> <?= h($s['nombre']) ?> <?= pillStock((int)$f['stock'], (int)$f['stock_minimo']) ?></h3>
            <div class="sede-numeros">
                <div><p>Stock actual</p><strong><?= (int)$f['stock'] ?></strong><small>Aviso en <?= (int)$f['stock_minimo'] ?></small></div>
                <?php if ($esAdmin): ?>
                    <div><p>Vendidas (histórico)</p><strong><?= (int)($vs['unidades'] ?? 0) ?></strong><small><?= soles($vs['ingreso'] ?? 0) ?></small></div>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<?php if ($esAdmin): ?>
<section class="rejilla c2">
    <article class="panel">
        <h2 style="margin-bottom:10px">Últimas ventas</h2>
        <?php if (!$ultimasVentas): ?><p class="nota-suave">Este producto aún no se ha vendido.</p><?php else: ?>
        <ul class="lista">
            <?php foreach ($ultimasVentas as $u): ?>
                <li>
                    <div class="crece"><strong><a href="../facturacion/detalle.php?id=<?= (int)$u['id_venta'] ?>" style="color:inherit;text-decoration:none"><?= h($u['cliente_nombre'] ?: 'Cliente general') ?></a></strong>
                        <small><?= h(date('d/m H:i', strtotime($u['fecha_hora']))) ?> · <?= (int)$u['cantidad'] ?> ud(s)</small></div>
                    <?= chipSede(['nombre' => $u['sede'], 'color' => $u['color']]) ?>
                    <b class="num"><?= soles($u['subtotal']) ?></b>
                </li>
            <?php endforeach; ?>
        </ul><?php endif; ?>
    </article>
    <article class="panel">
        <h2 style="margin-bottom:10px">Movimientos de stock</h2>
        <?php if (!$kardex): ?><p class="nota-suave">Sin movimientos registrados.</p><?php else: ?>
        <ul class="lista">
            <?php foreach ($kardex as $k): ?>
                <li>
                    <div class="crece"><strong><?= h($k['motivo'] ?: ucfirst(str_replace('_', ' ', $k['tipo']))) ?></strong>
                        <small><?= h(date('d/m H:i', strtotime($k['creado_en']))) ?> · quedó en <?= (int)$k['stock_resultante'] ?></small></div>
                    <?= chipSede(['nombre' => $k['sede'], 'color' => $k['color']]) ?>
                    <b style="color:<?= $k['cantidad'] >= 0 ? '#166534' : '#b42318' ?>"><?= $k['cantidad'] > 0 ? '+' : '' ?><?= (int)$k['cantidad'] ?></b>
                </li>
            <?php endforeach; ?>
        </ul><?php endif; ?>
    </article>
</section>
<?php endif; ?>

<?php layout_fin(); ?>
