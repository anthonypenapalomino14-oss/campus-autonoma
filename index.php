<?php
require_once __DIR__ . '/config/auth.php';
exigirRol(['admin']);
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/config/layout.php';

$sedes = listarSedes($conexion);

// Totales generales
$totales = $conexion->query(
    "SELECT COALESCE(SUM(total),0) AS ingresos, COUNT(*) AS ventas
     FROM ventas WHERE estado = 'completada'"
)->fetch();

$hoy = $conexion->query(
    "SELECT COALESCE(SUM(total),0) AS ingresos, COUNT(*) AS ventas
     FROM ventas WHERE estado = 'completada' AND DATE(fecha_hora) = CURDATE()"
)->fetch();

$productosActivos = (int)$conexion->query(
    "SELECT COUNT(*) FROM productos WHERE activo = 1"
)->fetchColumn();

$clientes = (int)$conexion->query(
    "SELECT COUNT(*) FROM usuarios WHERE rol = 'cliente'"
)->fetchColumn();

$ticketsAbiertos = (int)$conexion->query(
    "SELECT COUNT(*) FROM tickets_soporte WHERE estado <> 'resuelto'"
)->fetchColumn();

// Comparativo por sede
$porSede = [];
foreach ($sedes as $s) {
    $idS = (int)$s['id_sede'];

    $v = $conexion->prepare(
        "SELECT COALESCE(SUM(total),0) AS ingresos, COUNT(*) AS ventas
         FROM ventas WHERE estado='completada' AND id_sede = ?"
    );
    $v->execute([$idS]);
    $ventas = $v->fetch();

    $i = $conexion->prepare(
        "SELECT COALESCE(SUM(i.stock),0) AS unidades,
                SUM(i.stock <= 0) AS agotados,
                SUM(i.stock > 0 AND i.stock <= i.stock_minimo) AS bajos
         FROM inventario i
         INNER JOIN productos p ON p.id_producto = i.id_producto
         WHERE i.id_sede = ? AND p.activo = 1"
    );
    $i->execute([$idS]);
    $inv = $i->fetch();

    $porSede[] = ['sede' => $s, 'ventas' => $ventas, 'inv' => $inv];
}

// Ventas de los últimos 7 días
$dias = [];
for ($d = 6; $d >= 0; $d--) {
    $dias[date('Y-m-d', strtotime("-$d day"))] = 0.0;
}
$q = $conexion->query(
    "SELECT DATE(fecha_hora) AS dia, SUM(total) AS t
     FROM ventas
     WHERE estado='completada' AND fecha_hora >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY DATE(fecha_hora)"
);
foreach ($q->fetchAll() as $fila) {
    if (isset($dias[$fila['dia']])) {
        $dias[$fila['dia']] = (float)$fila['t'];
    }
}
$maxDia = max(1, max($dias));
$nombresDia = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];

// Productos por reponer (cualquier sede)
$reponer = $conexion->query(
    "SELECT p.nombre, m.nombre AS marca, s.nombre AS sede, s.color,
            i.stock, i.stock_minimo, p.id_producto
     FROM inventario i
     INNER JOIN productos p ON p.id_producto = i.id_producto
     INNER JOIN sedes s ON s.id_sede = i.id_sede
     LEFT JOIN marcas m ON m.id_marca = p.id_marca
     WHERE p.activo = 1 AND s.activo = 1 AND i.stock <= i.stock_minimo
     ORDER BY i.stock ASC, p.nombre
     LIMIT 8"
)->fetchAll();

// Más vendidos
$top = $conexion->query(
    "SELECT p.id_producto, p.nombre, m.nombre AS marca,
            SUM(d.cantidad) AS unidades, SUM(d.subtotal) AS ingreso
     FROM detalle_venta d
     INNER JOIN ventas v ON v.id_venta = d.id_venta AND v.estado = 'completada'
     INNER JOIN productos p ON p.id_producto = d.id_producto
     LEFT JOIN marcas m ON m.id_marca = p.id_marca
     GROUP BY p.id_producto, p.nombre, m.nombre
     ORDER BY unidades DESC LIMIT 5"
)->fetchAll();

// Últimas ventas
$ultimas = $conexion->query(
    "SELECT v.id_venta, v.numero_venta, v.fecha_hora, v.total, v.cliente_nombre,
            s.nombre AS sede, s.color
     FROM ventas v INNER JOIN sedes s ON s.id_sede = v.id_sede
     ORDER BY v.id_venta DESC LIMIT 6"
)->fetchAll();

layout_inicio($conexion, 'Panel general', 'panel', [
    'eyebrow' => 'Resumen del negocio',
    'descripcion' => 'Cómo van hoy tus dos tiendas.',
    'acciones' => '<a class="boton" href="ventas/">' . icono('cart') . ' Nueva venta</a>',
]);
?>

<section class="rejilla c4">
    <article class="dato destacado">
        <p>Vendido hoy</p>
        <strong><?= soles($hoy['ingresos']) ?></strong>
        <span><?= (int)$hoy['ventas'] ?> venta(s) en el día</span>
    </article>
    <article class="dato">
        <p>Ingresos acumulados</p>
        <strong><?= soles($totales['ingresos']) ?></strong>
        <span><?= (int)$totales['ventas'] ?> ventas completadas</span>
    </article>
    <article class="dato">
        <p>Productos activos</p>
        <strong><?= $productosActivos ?></strong>
        <span>En el catálogo de ambas tiendas</span>
    </article>
    <article class="dato <?= $ticketsAbiertos ? 'alerta' : '' ?>">
        <p>Casos de soporte pendientes</p>
        <strong><?= $ticketsAbiertos ?></strong>
        <span><a href="soporte/">Ver atención al cliente</a> · <?= $clientes ?> clientes</span>
    </article>
</section>

<section class="rejilla c2">
    <?php foreach ($porSede as $ps): $s = $ps['sede']; ?>
        <article class="panel sede-tarjeta" style="border-top-color: <?= h($s['color']) ?>">
            <h3><?= icono('store') ?> <?= h($s['nombre']) ?></h3>
            <div class="sede-numeros">
                <div><p>Ventas</p><strong><?= (int)$ps['ventas']['ventas'] ?></strong></div>
                <div><p>Ingresos</p><strong><?= soles($ps['ventas']['ingresos']) ?></strong></div>
                <div><p>Unidades en stock</p><strong><?= (int)$ps['inv']['unidades'] ?></strong></div>
                <div>
                    <p>Por reponer</p>
                    <strong><?= (int)$ps['inv']['bajos'] + (int)$ps['inv']['agotados'] ?></strong>
                    <small><?= (int)$ps['inv']['agotados'] ?> agotado(s)</small>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<section class="rejilla dos-tercios">
    <article class="panel">
        <div class="panel-cabecera">
            <div><h2>Ventas de la semana</h2><p>Últimos 7 días, ambas tiendas</p></div>
            <a class="boton sec chico" href="facturacion/">Ver facturación</a>
        </div>
        <div class="grafico">
            <?php foreach ($dias as $fecha => $monto): ?>
                <div class="col">
                    <em><?= $monto > 0 ? number_format($monto, 0) : '' ?></em>
                    <div class="barra-g" style="height: <?= max(2, round($monto / $maxDia * 100)) ?>%"></div>
                    <span><?= $nombresDia[(int)date('w', strtotime($fecha))] ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </article>

    <article class="panel">
        <div class="panel-cabecera"><div><h2>Lo más vendido</h2></div>
            <a class="boton sec chico" href="reportes/">Reporte</a></div>
        <?php if (!$top): ?>
            <div class="estado-vacio"><strong>Aún no hay ventas</strong>Cuando vendas, aquí verás tus favoritos.</div>
        <?php else: ?>
            <ul class="lista">
                <?php foreach ($top as $t): ?>
                    <li>
                        <?= avatarProducto($t['marca'], $t['nombre'], 36) ?>
                        <div class="crece">
                            <strong><a href="reportes/producto.php?id=<?= (int)$t['id_producto'] ?>"
                                       style="color:inherit;text-decoration:none"><?= h($t['nombre']) ?></a></strong>
                            <small><?= h($t['marca'] ?: 'Sin marca') ?></small>
                        </div>
                        <span class="num"><b><?= (int)$t['unidades'] ?></b> uds</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </article>
</section>

<section class="rejilla c2">
    <article class="panel">
        <div class="panel-cabecera">
            <div><h2>Para reponer</h2><p>Stock en o bajo el mínimo</p></div>
            <a class="boton sec chico" href="inventario/">Ir a inventario</a>
        </div>
        <?php if (!$reponer): ?>
            <div class="estado-vacio"><strong>Todo en orden</strong>Ninguna tienda tiene productos por agotarse.</div>
        <?php else: ?>
            <ul class="lista">
                <?php foreach ($reponer as $r): ?>
                    <li>
                        <?= avatarProducto($r['marca'], $r['nombre'], 36) ?>
                        <div class="crece">
                            <strong><?= h($r['nombre']) ?></strong>
                            <small><?= h($r['marca'] ?: 'Sin marca') ?></small>
                        </div>
                        <?= chipSede(['nombre' => $r['sede'], 'color' => $r['color']]) ?>
                        <?= pillStock((int)$r['stock'], (int)$r['stock_minimo']) ?>
                        <b class="num"><?= (int)$r['stock'] ?></b>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </article>

    <article class="panel">
        <div class="panel-cabecera"><div><h2>Últimas ventas</h2></div></div>
        <?php if (!$ultimas): ?>
            <div class="estado-vacio"><strong>Todavía no hay ventas</strong>Registra la primera desde la caja.</div>
        <?php else: ?>
            <ul class="lista">
                <?php foreach ($ultimas as $u): ?>
                    <li>
                        <div class="crece">
                            <strong><a href="facturacion/detalle.php?id=<?= (int)$u['id_venta'] ?>"
                                       style="color:inherit;text-decoration:none"><?= h($u['cliente_nombre'] ?: 'Cliente no identificado') ?></a></strong>
                            <small><?= h(date('d/m H:i', strtotime($u['fecha_hora']))) ?> · <?= h($u['numero_venta']) ?></small>
                        </div>
                        <?= chipSede(['nombre' => $u['sede'], 'color' => $u['color']]) ?>
                        <b class="num"><?= soles($u['total']) ?></b>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </article>
</section>

<?php layout_fin(); ?>
