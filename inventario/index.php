<?php
require_once __DIR__ . '/../config/auth.php';
exigirRol(['admin', 'vendedor', 'soporte']);
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/layout.php';

$esAdmin = rolActual() === 'admin';
$sedes = listarSedes($conexion);
$idUsuario = (int)$_SESSION['usuario_id'];
$ok = $_SESSION['flash_ok'] ?? null;
unset($_SESSION['flash_ok']);
$error = null;

// ---------- Acciones (solo administración) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$esAdmin) {
        http_response_code(403);
        exit('No tienes permiso para modificar el inventario.');
    }

    if (!csrfValido($_POST['csrf_token'] ?? '')) {
        $error = 'La sesión de seguridad expiró. Recarga la página.';
    } else {
        $accion = $_POST['accion'] ?? '';
        $idProducto = filter_var($_POST['id_producto'] ?? null, FILTER_VALIDATE_INT);
        $cantidad = filter_var($_POST['cantidad'] ?? null, FILTER_VALIDATE_INT);
        $motivo = trim((string)($_POST['motivo'] ?? ''));
        $idSede = filter_var($_POST['id_sede'] ?? null, FILTER_VALIDATE_INT);

        try {
            if (!$idProducto || !$cantidad || $cantidad < 1 || $cantidad > 1000000) {
                throw new RuntimeException('Elige un producto e indica una cantidad mayor a cero.');
            }
            if (mb_strlen($motivo) > 200) {
                throw new RuntimeException('El motivo es demasiado largo.');
            }

            $conexion->beginTransaction();

            $existe = $conexion->prepare("SELECT COUNT(*) FROM productos WHERE id_producto = ?");
            $existe->execute([$idProducto]);
            if (!$existe->fetchColumn()) {
                throw new RuntimeException('El producto no existe.');
            }

            if ($accion === 'entrada') {
                moverStock($conexion, $idProducto, (int)$idSede, $cantidad, 'entrada',
                    $motivo !== '' ? $motivo : 'Ingreso de mercadería', $idUsuario);
                $ok = 'Ingreso registrado: +' . $cantidad . ' unidades.';
            } elseif ($accion === 'salida') {
                if ($motivo === '') {
                    throw new RuntimeException('Cuéntanos el motivo de la salida (merma, vencido, daño…).');
                }
                moverStock($conexion, $idProducto, (int)$idSede, -$cantidad, 'ajuste', $motivo, $idUsuario);
                $ok = 'Salida registrada: −' . $cantidad . ' unidades.';
            } elseif ($accion === 'transferir') {
                $origen = filter_var($_POST['sede_origen'] ?? null, FILTER_VALIDATE_INT);
                $destino = filter_var($_POST['sede_destino'] ?? null, FILTER_VALIDATE_INT);
                if (!$origen || !$destino || $origen === $destino) {
                    throw new RuntimeException('Elige dos tiendas distintas para transferir.');
                }
                $ref = 'T' . date('ymdHis') . strtoupper(bin2hex(random_bytes(2)));
                $nota = $motivo !== '' ? $motivo : 'Transferencia entre tiendas';
                moverStock($conexion, $idProducto, $origen, -$cantidad, 'transferencia_salida', $nota, $idUsuario, null, $ref);
                moverStock($conexion, $idProducto, $destino, $cantidad, 'transferencia_entrada', $nota, $idUsuario, null, $ref);
                $ok = 'Transferencia lista: ' . $cantidad . ' unidad(es) movidas entre tiendas.';
            } else {
                throw new RuntimeException('Acción no reconocida.');
            }

            $conexion->commit();
            $_SESSION['flash_ok'] = $ok;
            header('Location: index.php');
            exit;
        } catch (Throwable $e) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }
            if ($e instanceof PDOException) {
                error_log($e->getMessage());
                $error = 'No se pudo guardar el movimiento.';
            } else {
                $error = $e->getMessage();
            }
            $ok = null;
        }
    }
}

// ---------- Datos ----------
$vista = ($_GET['vista'] ?? 'stock') === 'movimientos' ? 'movimientos' : 'stock';
$q = trim($_GET['q'] ?? '');
$filtroEstado = in_array($_GET['estado'] ?? '', ['bajo', 'agotado'], true) ? $_GET['estado'] : '';
$filtroSede = (int)($_GET['sede'] ?? 0);

// Productos para los selectores de los formularios
$listaProd = $conexion->query(
    "SELECT p.id_producto, p.nombre, p.codigo, m.nombre AS marca
     FROM productos p LEFT JOIN marcas m ON m.id_marca = p.id_marca
     WHERE p.activo = 1 ORDER BY p.nombre"
)->fetchAll();

$acciones = '';
if ($esAdmin) {
    $acciones =
        '<button class="boton sec" type="button" data-abrir="m-entrada">' . icono('plus') . ' Ingreso</button>' .
        '<button class="boton sec" type="button" data-abrir="m-salida">Salida / merma</button>' .
        '<button class="boton" type="button" data-abrir="m-transferir">' . icono('swap') . ' Transferir entre tiendas</button>';
}

layout_inicio($conexion, 'Inventario', 'inventario', [
    'eyebrow' => 'Almacén',
    'descripcion' => $esAdmin
        ? 'El mismo producto, con su stock en cada tienda y todo el historial de movimientos.'
        : 'Consulta cuánto hay de cada producto en cada tienda.',
    'acciones' => $acciones,
]);

echo avisoFlash($ok, $error);

$qs = function (array $extra = []) use ($q, $filtroEstado, $filtroSede, $vista) {
    return '?' . http_build_query(array_filter(array_merge(
        ['vista' => $vista, 'q' => $q, 'estado' => $filtroEstado, 'sede' => $filtroSede ?: null], $extra
    ), fn($v) => $v !== null && $v !== ''));
};
?>

<nav class="pestanas">
    <a class="<?= $vista === 'stock' ? 'activa' : '' ?>" href="<?= h($qs(['vista' => 'stock'])) ?>">Stock por tienda</a>
    <a class="<?= $vista === 'movimientos' ? 'activa' : '' ?>" href="<?= h($qs(['vista' => 'movimientos'])) ?>">Movimientos (kardex)</a>
</nav>

<?php if ($vista === 'stock'):
    $sql = "SELECT p.id_producto, p.codigo, p.nombre, p.precio, p.imagen, m.nombre AS marca
            FROM productos p LEFT JOIN marcas m ON m.id_marca = p.id_marca
            WHERE p.activo = 1";
    $params = [];
    if ($q !== '') {
        $sql .= " AND (p.nombre LIKE ? OR p.codigo LIKE ? OR m.nombre LIKE ?)";
        array_push($params, "%$q%", "%$q%", "%$q%");
    }
    $sql .= " ORDER BY p.nombre";
    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);
    $productos = $stmt->fetchAll();

    $mapa = [];
    foreach ($conexion->query("SELECT id_producto, id_sede, stock, stock_minimo FROM inventario")->fetchAll() as $f) {
        $mapa[(int)$f['id_producto']][(int)$f['id_sede']] = $f;
    }

    // Filtro por estado (en alguna tienda / en la tienda elegida)
    $filas = [];
    $resumen = ['unidades' => 0, 'bajos' => 0, 'agotados' => 0];
    foreach ($productos as $p) {
        $idp = (int)$p['id_producto'];
        $total = 0;
        $coincide = $filtroEstado === '';
        foreach ($sedes as $s) {
            $idS = (int)$s['id_sede'];
            $f = $mapa[$idp][$idS] ?? ['stock' => 0, 'stock_minimo' => 5];
            $total += (int)$f['stock'];
            if ($filtroSede && $filtroSede !== $idS) continue;
            $e = estadoStock((int)$f['stock'], (int)$f['stock_minimo'])['clase'];
            if ($e === $filtroEstado) $coincide = true;
            if (!$filtroSede || $filtroSede === $idS) {
                $resumen['unidades'] += (int)$f['stock'];
                if ($e === 'bajo') $resumen['bajos']++;
                if ($e === 'agotado') $resumen['agotados']++;
            }
        }
        if ($coincide) {
            $p['total'] = $total;
            $filas[] = $p;
        }
    }
?>
    <section class="rejilla c3">
        <article class="dato"><p>Unidades en stock</p><strong><?= $resumen['unidades'] ?></strong><span><?= $filtroSede ? 'En la tienda elegida' : 'Entre las dos tiendas' ?></span></article>
        <article class="dato <?= $resumen['bajos'] ? 'alerta' : '' ?>"><p>Con stock bajo</p><strong><?= $resumen['bajos'] ?></strong><span>Conviene reponer pronto</span></article>
        <article class="dato <?= $resumen['agotados'] ? 'alerta' : '' ?>"><p>Agotados</p><strong><?= $resumen['agotados'] ?></strong><span>No se pueden vender</span></article>
    </section>

    <section class="panel">
        <form class="buscador" method="GET">
            <input type="hidden" name="vista" value="stock">
            <input type="search" name="q" placeholder="Buscar por producto, marca o código…" value="<?= h($q) ?>">
            <select name="sede" aria-label="Tienda">
                <option value="0">Ambas tiendas</option>
                <?php foreach ($sedes as $s): ?>
                    <option value="<?= (int)$s['id_sede'] ?>" <?= $filtroSede === (int)$s['id_sede'] ? 'selected' : '' ?>><?= h($s['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="estado" aria-label="Estado del stock">
                <option value="">Todo el stock</option>
                <option value="bajo" <?= $filtroEstado === 'bajo' ? 'selected' : '' ?>>Stock bajo</option>
                <option value="agotado" <?= $filtroEstado === 'agotado' ? 'selected' : '' ?>>Agotados</option>
            </select>
            <button class="boton" type="submit">Filtrar</button>
        </form>

        <div class="tabla-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Producto</th><th>Código</th>
                        <?php foreach ($sedes as $s): if ($filtroSede && $filtroSede !== (int)$s['id_sede']) continue; ?>
                            <th class="num"><?= h($s['nombre']) ?></th>
                        <?php endforeach; ?>
                        <th class="num">Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$filas): ?>
                    <tr><td colspan="<?= 3 + count($sedes) ?>" class="vacio">No hay productos para mostrar con estos filtros.</td></tr>
                <?php endif; ?>
                <?php foreach ($filas as $p): $idp = (int)$p['id_producto']; ?>
                    <tr>
                        <td>
                            <div class="celda-producto">
                                <?= fotoProducto($p['imagen'], $p['marca'], $p['nombre'], 52) ?>
                                <div>
                                    <strong><a href="../reportes/producto.php?id=<?= $idp ?>" style="color:inherit;text-decoration:none"><?= h($p['nombre']) ?></a></strong>
                                    <?php if ($p['marca']): ?><span class="marca-tag"><?= h($p['marca']) ?></span><?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="mono"><?= h($p['codigo'] ?: '—') ?></td>
                        <?php foreach ($sedes as $s): $idS = (int)$s['id_sede'];
                            if ($filtroSede && $filtroSede !== $idS) continue;
                            $f = $mapa[$idp][$idS] ?? ['stock' => 0, 'stock_minimo' => 5]; ?>
                            <td class="num">
                                <b><?= (int)$f['stock'] ?></b>
                                <?= pillStock((int)$f['stock'], (int)$f['stock_minimo']) ?>
                            </td>
                        <?php endforeach; ?>
                        <td class="num"><b><?= (int)$p['total'] ?></b></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

<?php else:
    $sql = "SELECT mv.*, p.nombre, p.codigo, m.nombre AS marca, s.nombre AS sede, s.color,
                   u.nombres, u.apellidos, v.numero_venta
            FROM movimientos_inventario mv
            INNER JOIN productos p ON p.id_producto = mv.id_producto
            INNER JOIN sedes s ON s.id_sede = mv.id_sede
            LEFT JOIN marcas m ON m.id_marca = p.id_marca
            LEFT JOIN usuarios u ON u.id_usuario = mv.id_usuario
            LEFT JOIN ventas v ON v.id_venta = mv.id_venta
            WHERE 1=1";
    $params = [];
    if ($q !== '') {
        $sql .= " AND (p.nombre LIKE ? OR p.codigo LIKE ? OR m.nombre LIKE ?)";
        array_push($params, "%$q%", "%$q%", "%$q%");
    }
    if ($filtroSede) {
        $sql .= " AND mv.id_sede = ?";
        $params[] = $filtroSede;
    }
    $sql .= " ORDER BY mv.id_movimiento DESC LIMIT 200";
    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);
    $movs = $stmt->fetchAll();

    $etiquetas = [
        'entrada' => ['Ingreso', 'ok'], 'venta' => ['Venta', 'neutro'], 'ajuste' => ['Ajuste / merma', 'bajo'],
        'transferencia_salida' => ['Transf. salida', 'abierto'], 'transferencia_entrada' => ['Transf. entrada', 'abierto'],
    ];
?>
    <section class="panel">
        <form class="buscador" method="GET">
            <input type="hidden" name="vista" value="movimientos">
            <input type="search" name="q" placeholder="Buscar producto, marca o código…" value="<?= h($q) ?>">
            <select name="sede" aria-label="Tienda">
                <option value="0">Ambas tiendas</option>
                <?php foreach ($sedes as $s): ?>
                    <option value="<?= (int)$s['id_sede'] ?>" <?= $filtroSede === (int)$s['id_sede'] ? 'selected' : '' ?>><?= h($s['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="boton" type="submit">Filtrar</button>
        </form>
        <p class="nota-suave" style="margin-bottom:12px">Se muestran los últimos 200 movimientos.</p>

        <div class="tabla-wrap">
            <table>
                <thead><tr><th>Fecha</th><th>Producto</th><th>Tienda</th><th>Tipo</th><th class="num">Cant.</th><th class="num">Quedó</th><th>Detalle</th></tr></thead>
                <tbody>
                <?php if (!$movs): ?><tr><td colspan="7" class="vacio">Todavía no hay movimientos.</td></tr><?php endif; ?>
                <?php foreach ($movs as $m): $et = $etiquetas[$m['tipo']] ?? [$m['tipo'], 'neutro']; ?>
                    <tr>
                        <td class="mono"><?= h(date('d/m/Y H:i', strtotime($m['creado_en']))) ?></td>
                        <td>
                            <div class="celda-producto"><?= avatarProducto($m['marca'], $m['nombre'], 32) ?>
                                <div><strong><?= h($m['nombre']) ?></strong><small><?= h($m['marca'] ?: '') ?></small></div></div>
                        </td>
                        <td><?= chipSede(['nombre' => $m['sede'], 'color' => $m['color']]) ?></td>
                        <td><span class="pill <?= $et[1] ?>"><?= h($et[0]) ?></span></td>
                        <td class="num"><b style="color:<?= $m['cantidad'] >= 0 ? '#166534' : '#b42318' ?>"><?= $m['cantidad'] > 0 ? '+' : '' ?><?= (int)$m['cantidad'] ?></b></td>
                        <td class="num"><?= (int)$m['stock_resultante'] ?></td>
                        <td>
                            <small>
                                <?= h($m['motivo'] ?: '') ?>
                                <?php if ($m['numero_venta']): ?> · <a href="../facturacion/detalle.php?id=<?= (int)$m['id_venta'] ?>"><?= h($m['numero_venta']) ?></a><?php endif; ?>
                                <?php if ($m['nombres']): ?><br>por <?= h(trim($m['nombres'] . ' ' . $m['apellidos'])) ?><?php endif; ?>
                            </small>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php if ($esAdmin):
    $opcionesProd = '';
    foreach ($listaProd as $p) {
        $opcionesProd .= '<option value="' . (int)$p['id_producto'] . '">' .
            h(($p['marca'] ? $p['marca'] . ' · ' : '') . $p['nombre'] . ($p['codigo'] ? ' (' . $p['codigo'] . ')' : '')) . '</option>';
    }
    $opcionesSede = '';
    foreach ($sedes as $s) {
        $opcionesSede .= '<option value="' . (int)$s['id_sede'] . '">' . h($s['nombre']) . '</option>';
    }
    $tokenHtml = '<input type="hidden" name="csrf_token" value="' . h(csrfToken()) . '">';
?>
<div class="modal" id="m-entrada" role="dialog" aria-modal="true" aria-labelledby="t-entrada">
    <form class="modal-caja" method="POST">
        <?= $tokenHtml ?><input type="hidden" name="accion" value="entrada">
        <h2 id="t-entrada">Ingreso de mercadería</h2>
        <p class="nota-suave">Suma unidades al stock de una tienda.</p>
        <div class="form-rejilla" style="grid-template-columns:1fr">
            <div class="campo"><label>Producto</label><select name="id_producto" required><?= $opcionesProd ?></select></div>
            <div class="campo"><label>Tienda</label><select name="id_sede" required><?= $opcionesSede ?></select></div>
            <div class="campo"><label>Cantidad</label><input type="number" name="cantidad" min="1" required></div>
            <div class="campo"><label>Nota <small>(proveedor, guía…)</small></label><input name="motivo" maxlength="200"></div>
        </div>
        <div class="modal-pie"><button type="button" class="boton sec" data-cerrar>Cancelar</button><button class="boton">Registrar ingreso</button></div>
    </form>
</div>

<div class="modal" id="m-salida" role="dialog" aria-modal="true" aria-labelledby="t-salida">
    <form class="modal-caja" method="POST">
        <?= $tokenHtml ?><input type="hidden" name="accion" value="salida">
        <h2 id="t-salida">Salida o merma</h2>
        <p class="nota-suave">Resta unidades por daño, vencimiento, pérdida o corrección de conteo.</p>
        <div class="form-rejilla" style="grid-template-columns:1fr">
            <div class="campo"><label>Producto</label><select name="id_producto" required><?= $opcionesProd ?></select></div>
            <div class="campo"><label>Tienda</label><select name="id_sede" required><?= $opcionesSede ?></select></div>
            <div class="campo"><label>Cantidad</label><input type="number" name="cantidad" min="1" required></div>
            <div class="campo"><label>Motivo *</label><input name="motivo" maxlength="200" required placeholder="Ej.: vencido, dañado, conteo físico"></div>
        </div>
        <div class="modal-pie"><button type="button" class="boton sec" data-cerrar>Cancelar</button><button class="boton">Registrar salida</button></div>
    </form>
</div>

<div class="modal" id="m-transferir" role="dialog" aria-modal="true" aria-labelledby="t-transf">
    <form class="modal-caja" method="POST">
        <?= $tokenHtml ?><input type="hidden" name="accion" value="transferir">
        <h2 id="t-transf">Transferir entre tiendas</h2>
        <p class="nota-suave">Mueve unidades de una tienda a la otra. Queda registrado en ambas.</p>
        <div class="form-rejilla" style="grid-template-columns:1fr">
            <div class="campo"><label>Producto</label><select name="id_producto" required><?= $opcionesProd ?></select></div>
            <div class="form-rejilla">
                <div class="campo"><label>Desde</label><select name="sede_origen" required><?= $opcionesSede ?></select></div>
                <div class="campo"><label>Hacia</label>
                    <select name="sede_destino" required>
                        <?php foreach (array_reverse($sedes) as $s): ?>
                            <option value="<?= (int)$s['id_sede'] ?>"><?= h($s['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select></div>
            </div>
            <div class="campo"><label>Cantidad</label><input type="number" name="cantidad" min="1" required></div>
            <div class="campo"><label>Nota</label><input name="motivo" maxlength="200" placeholder="Opcional"></div>
        </div>
        <div class="modal-pie"><button type="button" class="boton sec" data-cerrar>Cancelar</button><button class="boton">Transferir</button></div>
    </form>
</div>

<script>
document.querySelectorAll('[data-abrir]').forEach(function (b) {
    b.addEventListener('click', function () { document.getElementById(b.dataset.abrir).classList.add('abierto'); });
});
document.querySelectorAll('.modal').forEach(function (m) {
    m.addEventListener('click', function (e) { if (e.target === m) m.classList.remove('abierto'); });
    m.querySelectorAll('[data-cerrar]').forEach(function (c) { c.addEventListener('click', function () { m.classList.remove('abierto'); }); });
});
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') document.querySelectorAll('.modal.abierto').forEach(function (m) { m.classList.remove('abierto'); });
});
</script>
<?php endif; ?>

<?php layout_fin(); ?>
