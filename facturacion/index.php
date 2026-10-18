<?php
require_once __DIR__ . '/../config/auth.php';
exigirRol(['admin', 'vendedor', 'soporte']);
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/layout.php';

$rol = rolActual();
$sedes = listarSedes($conexion);

$buscar = trim($_GET['buscar'] ?? '');
$desde = trim($_GET['desde'] ?? '');
$hasta = trim($_GET['hasta'] ?? '');
$estado = in_array($_GET['estado'] ?? '', ['completada', 'anulada'], true) ? $_GET['estado'] : '';
$filtroSede = (int)($_GET['sede'] ?? 0);

// El vendedor solo ve la facturación de su tienda
if ($rol === 'vendedor') {
    $filtroSede = sedeActualId($conexion);
}

$sql = "SELECT v.id_venta, v.numero_venta, v.cliente_dni, v.cliente_nombre, v.fecha_hora,
               v.total, v.estado, u.nombres AS vn, u.apellidos AS va,
               s.nombre AS sede, s.color, b.id_boleta
        FROM ventas v
        INNER JOIN usuarios u ON u.id_usuario = v.id_vendedor
        INNER JOIN sedes s ON s.id_sede = v.id_sede
        LEFT JOIN boletas b ON b.id_venta = v.id_venta
        WHERE 1 = 1";
$params = [];

if ($buscar !== '') {
    $sql .= " AND (v.numero_venta LIKE ? OR v.cliente_dni LIKE ? OR v.cliente_nombre LIKE ?
                   OR CONCAT(u.nombres, ' ', u.apellidos) LIKE ?)";
    $t = "%$buscar%";
    array_push($params, $t, $t, $t, $t);
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
    $sql .= " AND v.fecha_hora >= ?";
    $params[] = $desde . ' 00:00:00';
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
    $sql .= " AND v.fecha_hora < DATE_ADD(?, INTERVAL 1 DAY)";
    $params[] = $hasta;
}
if ($estado !== '') {
    $sql .= " AND v.estado = ?";
    $params[] = $estado;
}
if ($filtroSede > 0) {
    $sql .= " AND v.id_sede = ?";
    $params[] = $filtroSede;
}
$sql .= " ORDER BY v.fecha_hora DESC, v.id_venta DESC LIMIT 500";

$stmt = $conexion->prepare($sql);
$stmt->execute($params);
$ventas = $stmt->fetchAll();

$monto = 0.0;
$completadas = 0;
$anuladas = 0;
foreach ($ventas as $v) {
    if ($v['estado'] === 'completada') {
        $completadas++;
        $monto += (float)$v['total'];
    } else {
        $anuladas++;
    }
}

layout_inicio($conexion, 'Facturación', 'facturacion', [
    'eyebrow' => 'Comprobantes',
    'descripcion' => $rol === 'vendedor'
        ? 'Las ventas y boletas de tu tienda.'
        : 'Todas las ventas y boletas, de una o de ambas tiendas.',
]);
?>

<section class="rejilla c3">
    <article class="dato destacado"><p>Total facturado</p><strong><?= soles($monto) ?></strong><span>Según los filtros aplicados</span></article>
    <article class="dato"><p>Ventas completadas</p><strong><?= $completadas ?></strong><span>Con boleta emitida</span></article>
    <article class="dato"><p>Anuladas</p><strong><?= $anuladas ?></strong><span>No suman al total</span></article>
</section>

<section class="panel">
    <form class="buscador" method="GET">
        <input type="search" name="buscar" placeholder="N.º de venta, DNI, cliente o vendedor…" value="<?= h($buscar) ?>">
        <?php if ($rol !== 'vendedor'): ?>
            <select name="sede" aria-label="Tienda">
                <option value="0">Ambas tiendas</option>
                <?php foreach ($sedes as $s): ?>
                    <option value="<?= (int)$s['id_sede'] ?>" <?= $filtroSede === (int)$s['id_sede'] ? 'selected' : '' ?>><?= h($s['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        <select name="estado" aria-label="Estado">
            <option value="">Todos los estados</option>
            <option value="completada" <?= $estado === 'completada' ? 'selected' : '' ?>>Completadas</option>
            <option value="anulada" <?= $estado === 'anulada' ? 'selected' : '' ?>>Anuladas</option>
        </select>
        <input type="date" name="desde" value="<?= h($desde) ?>" aria-label="Desde">
        <input type="date" name="hasta" value="<?= h($hasta) ?>" aria-label="Hasta">
        <button class="boton" type="submit">Filtrar</button>
    </form>

    <div class="tabla-wrap">
        <table>
            <thead>
                <tr><th>Venta</th><th>Cliente</th><th>Tienda</th><th>Vendedor</th><th class="num">Total</th><th>Estado</th><th></th></tr>
            </thead>
            <tbody>
            <?php if (!$ventas): ?>
                <tr><td colspan="7" class="vacio">No hay ventas con esos filtros.</td></tr>
            <?php endif; ?>
            <?php foreach ($ventas as $v): ?>
                <tr>
                    <td>
                        <strong class="mono" style="color:var(--tinta)"><?= h($v['numero_venta']) ?></strong><br>
                        <small><?= h(date('d/m/Y H:i', strtotime($v['fecha_hora']))) ?></small>
                    </td>
                    <td><?= h($v['cliente_nombre'] ?: 'Cliente general') ?><br><small>DNI <?= h($v['cliente_dni'] ?: '—') ?></small></td>
                    <td><?= chipSede(['nombre' => $v['sede'], 'color' => $v['color']]) ?></td>
                    <td><?= h(trim($v['vn'] . ' ' . $v['va'])) ?></td>
                    <td class="num"><b><?= soles($v['total']) ?></b></td>
                    <td><span class="pill <?= h($v['estado']) ?>"><?= h(ucfirst($v['estado'])) ?></span></td>
                    <td class="num">
                        <a class="boton sec chico" href="detalle.php?id=<?= (int)$v['id_venta'] ?>">Detalle</a>
                        <?php if ($v['id_boleta']): ?>
                            <a class="boton sec chico" target="_blank" rel="noopener"
                               href="../ventas/boleta.php?numero=<?= rawurlencode($v['numero_venta']) ?>"><?= icono('print', 15) ?> Boleta</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (count($ventas) === 500): ?><p class="nota-suave" style="margin-top:12px">Se muestran las 500 más recientes. Afina los filtros para ver otras.</p><?php endif; ?>
</section>

<?php layout_fin(); ?>
