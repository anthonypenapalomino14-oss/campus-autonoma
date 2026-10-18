<?php
require_once __DIR__ . '/../config/auth.php';
exigirRol(['admin']);
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/layout.php';

$sedes = listarSedes($conexion);
$marcas = $conexion->query("SELECT id_marca, nombre FROM marcas ORDER BY nombre")->fetchAll();

$buscar = trim($_GET['buscar'] ?? '');
$idMarca = (int)($_GET['marca'] ?? 0);
$filtroSede = (int)($_GET['sede'] ?? 0);
$desde = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['desde'] ?? '') ? $_GET['desde'] : date('Y-m-01');
$hasta = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['hasta'] ?? '') ? $_GET['hasta'] : date('Y-m-d');
$orden = in_array($_GET['orden'] ?? '', ['unidades', 'ingreso', 'nombre', 'stock'], true) ? $_GET['orden'] : 'unidades';

// Ventas por producto y por tienda en el rango
$sqlV = "SELECT d.id_producto, v.id_sede, SUM(d.cantidad) AS unidades, SUM(d.subtotal) AS ingreso
         FROM detalle_venta d
         INNER JOIN ventas v ON v.id_venta = d.id_venta AND v.estado = 'completada'
         WHERE v.fecha_hora >= ? AND v.fecha_hora < DATE_ADD(?, INTERVAL 1 DAY)";
$pV = [$desde . ' 00:00:00', $hasta];
if ($filtroSede) {
    $sqlV .= " AND v.id_sede = ?";
    $pV[] = $filtroSede;
}
$sqlV .= " GROUP BY d.id_producto, v.id_sede";
$stmt = $conexion->prepare($sqlV);
$stmt->execute($pV);

$ventasMapa = [];
foreach ($stmt->fetchAll() as $f) {
    $ventasMapa[(int)$f['id_producto']][(int)$f['id_sede']] = $f;
}

$stockMapa = [];
foreach ($conexion->query("SELECT id_producto, id_sede, stock FROM inventario")->fetchAll() as $f) {
    $stockMapa[(int)$f['id_producto']][(int)$f['id_sede']] = (int)$f['stock'];
}

$sql = "SELECT p.id_producto, p.codigo, p.nombre, p.precio, p.activo, m.nombre AS marca
        FROM productos p LEFT JOIN marcas m ON m.id_marca = p.id_marca WHERE 1=1";
$params = [];
if ($buscar !== '') {
    $sql .= " AND (p.nombre LIKE ? OR p.codigo LIKE ? OR m.nombre LIKE ?)";
    array_push($params, "%$buscar%", "%$buscar%", "%$buscar%");
}
if ($idMarca) {
    $sql .= " AND p.id_marca = ?";
    $params[] = $idMarca;
}
$stmt = $conexion->prepare($sql);
$stmt->execute($params);

$filas = [];
$totUnid = 0;
$totIng = 0.0;
foreach ($stmt->fetchAll() as $p) {
    $idp = (int)$p['id_producto'];
    $uPorSede = [];
    $u = 0;
    $ing = 0.0;
    foreach ($sedes as $s) {
        $idS = (int)$s['id_sede'];
        $f = $ventasMapa[$idp][$idS] ?? null;
        $uPorSede[$idS] = $f ? (int)$f['unidades'] : 0;
        $u += $uPorSede[$idS];
        $ing += $f ? (float)$f['ingreso'] : 0;
    }
    $stockTotal = 0;
    foreach ($sedes as $s) {
        $stockTotal += $stockMapa[$idp][(int)$s['id_sede']] ?? 0;
    }
    $p['unidades'] = $u;
    $p['ingreso'] = $ing;
    $p['por_sede'] = $uPorSede;
    $p['stock_total'] = $stockTotal;
    $totUnid += $u;
    $totIng += $ing;
    $filas[] = $p;
}

usort($filas, function ($a, $b) use ($orden) {
    switch ($orden) {
        case 'ingreso': return $b['ingreso'] <=> $a['ingreso'];
        case 'nombre':  return strcasecmp($a['nombre'], $b['nombre']);
        case 'stock':   return $a['stock_total'] <=> $b['stock_total'];
        default:        return $b['unidades'] <=> $a['unidades'];
    }
});

layout_inicio($conexion, 'Reporte por producto', 'reportes', [
    'eyebrow' => 'Análisis',
    'descripcion' => 'Qué se vende, en qué tienda y cuánto stock queda, todo cruzado en una sola tabla.',
]);
?>

<section class="rejilla c3">
    <article class="dato destacado"><p>Ingresos del periodo</p><strong><?= soles($totIng) ?></strong><span><?= h(date('d/m/Y', strtotime($desde))) ?> al <?= h(date('d/m/Y', strtotime($hasta))) ?></span></article>
    <article class="dato"><p>Unidades vendidas</p><strong><?= $totUnid ?></strong><span><?= $filtroSede ? 'En la tienda elegida' : 'Entre las dos tiendas' ?></span></article>
    <article class="dato"><p>Productos con ventas</p><strong><?= count(array_filter($filas, fn($f) => $f['unidades'] > 0)) ?></strong><span>De <?= count($filas) ?> en el catálogo</span></article>
</section>

<section class="panel">
    <form class="buscador" method="GET">
        <input type="search" name="buscar" placeholder="Producto, marca o código…" value="<?= h($buscar) ?>">
        <select name="marca" aria-label="Marca">
            <option value="0">Todas las marcas</option>
            <?php foreach ($marcas as $m): ?>
                <option value="<?= (int)$m['id_marca'] ?>" <?= $idMarca === (int)$m['id_marca'] ? 'selected' : '' ?>><?= h($m['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="sede" aria-label="Tienda">
            <option value="0">Ambas tiendas</option>
            <?php foreach ($sedes as $s): ?>
                <option value="<?= (int)$s['id_sede'] ?>" <?= $filtroSede === (int)$s['id_sede'] ? 'selected' : '' ?>><?= h($s['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="desde" value="<?= h($desde) ?>" aria-label="Desde">
        <input type="date" name="hasta" value="<?= h($hasta) ?>" aria-label="Hasta">
        <select name="orden" aria-label="Ordenar por">
            <option value="unidades" <?= $orden === 'unidades' ? 'selected' : '' ?>>Más vendidos</option>
            <option value="ingreso" <?= $orden === 'ingreso' ? 'selected' : '' ?>>Mayor ingreso</option>
            <option value="stock" <?= $orden === 'stock' ? 'selected' : '' ?>>Menor stock</option>
            <option value="nombre" <?= $orden === 'nombre' ? 'selected' : '' ?>>Nombre</option>
        </select>
        <button class="boton" type="submit">Aplicar</button>
    </form>

    <div class="tabla-wrap">
        <table>
            <thead>
                <tr>
                    <th>Producto</th><th>Código</th>
                    <?php foreach ($sedes as $s): ?><th class="num">Vendidas · <?= h($s['nombre']) ?></th><?php endforeach; ?>
                    <th class="num">Ingresos</th><th class="num">Stock actual</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$filas): ?><tr><td colspan="<?= 4 + count($sedes) ?>" class="vacio">Sin resultados.</td></tr><?php endif; ?>
            <?php foreach ($filas as $p): ?>
                <tr>
                    <td>
                        <div class="celda-producto">
                            <?= avatarProducto($p['marca'], $p['nombre']) ?>
                            <div>
                                <strong><a href="producto.php?id=<?= (int)$p['id_producto'] ?>" style="color:inherit;text-decoration:none"><?= h($p['nombre']) ?></a></strong>
                                <?php if ($p['marca']): ?><span class="marca-tag"><?= h($p['marca']) ?></span><?php endif; ?>
                                <?php if (!$p['activo']): ?><span class="pill inactivo">Inactivo</span><?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="mono"><?= h($p['codigo'] ?: '—') ?></td>
                    <?php foreach ($sedes as $s): ?><td class="num"><?= (int)$p['por_sede'][(int)$s['id_sede']] ?></td><?php endforeach; ?>
                    <td class="num"><b><?= soles($p['ingreso']) ?></b></td>
                    <td class="num"><?= (int)$p['stock_total'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php layout_fin(); ?>
