<?php
require_once __DIR__ . '/../config/auth.php';
exigirRol(['admin']);
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/layout.php';
require_once __DIR__ . '/_comun.php';

$ok = isset($_GET['guardado']) ? 'Producto registrado. Ya aparece en las dos tiendas.' : (isset($_GET['actualizado']) ? 'Producto actualizado.' : null);
$error = null;
$sedes = listarSedes($conexion);
$form = ['codigo' => '', 'nombre' => '', 'marca' => '', 'descripcion' => '', 'precio' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido($_POST['csrf_token'] ?? '')) {
        $error = 'La sesión de seguridad expiró. Recarga la página.';
    } elseif (($_POST['accion'] ?? '') === 'estado') {
        $id = entero($_POST['id_producto'] ?? null, 1, PHP_INT_MAX);
        $nuevo = ($_POST['activo'] ?? '1') === '1' ? 1 : 0;
        if ($id) {
            $conexion->prepare("UPDATE productos SET activo = ? WHERE id_producto = ?")->execute([$nuevo, $id]);
            header('Location: index.php?' . ($nuevo ? 'actualizado=1' : 'actualizado=1'));
            exit;
        }
    } else {
        foreach ($form as $k => $_) {
            $form[$k] = trim((string)($_POST[$k] ?? ''));
        }
        $precio = $form['precio'];
        $minimo = entero($_POST['stock_minimo'] ?? 5, 0, 100000);

        $stocksIniciales = [];
        $stockValido = true;
        foreach ($sedes as $s) {
            $n = entero($_POST['stock_' . $s['id_sede']] ?? 0, 0, 1000000);
            if ($n === null) {
                $stockValido = false;
            }
            $stocksIniciales[(int)$s['id_sede']] = (int)$n;
        }

        if (
            $form['nombre'] === '' || mb_strlen($form['nombre']) > 150 ||
            !is_numeric($precio) || (float)$precio < 0 || (float)$precio > 99999999.99 ||
            !$stockValido || $minimo === null
        ) {
            $error = 'Revisa el nombre, el precio y los stocks: deben ser números válidos.';
        } else {
            try {
                $img = guardarImagenProducto($_FILES['imagen'] ?? null);
                $conexion->beginTransaction();

                $idMarca = obtenerOCrearMarca($conexion, $form['marca']);

                $conexion->prepare(
                    "INSERT INTO productos (codigo, id_marca, nombre, descripcion, precio, stock, imagen)
                     VALUES (?, ?, ?, ?, ?, 0, ?)"
                )->execute([
                    $form['codigo'] !== '' ? $form['codigo'] : null,
                    $idMarca,
                    $form['nombre'],
                    $form['descripcion'] !== '' ? $form['descripcion'] : null,
                    round((float)$precio, 2),
                    $img,
                ]);
                $idProducto = (int)$conexion->lastInsertId();

                foreach ($sedes as $s) {
                    $idS = (int)$s['id_sede'];
                    $conexion->prepare(
                        "INSERT INTO inventario (id_producto, id_sede, stock, stock_minimo)
                         VALUES (?, ?, 0, ?)"
                    )->execute([$idProducto, $idS, $minimo]);

                    if ($stocksIniciales[$idS] > 0) {
                        moverStock(
                            $conexion, $idProducto, $idS, $stocksIniciales[$idS],
                            'entrada', 'Stock inicial al crear el producto',
                            (int)$_SESSION['usuario_id']
                        );
                    }
                }

                $conexion->commit();
                header('Location: index.php?guardado=1');
                exit;
            } catch (PDOException $e) {
                if ($conexion->inTransaction()) {
                    $conexion->rollBack();
                }
                if ($e->getCode() === '23000') {
                    $error = 'Ese código ya existe en otro producto. Usa uno diferente.';
                } else {
                    error_log($e->getMessage());
                    $error = 'No se pudo guardar el producto.';
                }
            } catch (RuntimeException $e) {
                if ($conexion->inTransaction()) {
                    $conexion->rollBack();
                }
                $error = $e->getMessage();
            } catch (Throwable $e) {
                if ($conexion->inTransaction()) {
                    $conexion->rollBack();
                }
                error_log($e->getMessage());
                $error = 'No se pudo guardar el producto.';
            }
        }
    }
}

// ---- Listado ----
$buscar = trim($_GET['buscar'] ?? '');
$idMarcaFiltro = entero($_GET['marca'] ?? 0, 0, PHP_INT_MAX) ?? 0;
$estadoFiltro = in_array($_GET['estado'] ?? 'activos', ['activos', 'inactivos', 'todos'], true)
    ? ($_GET['estado'] ?? 'activos') : 'activos';

$sql = "SELECT p.id_producto, p.codigo, p.nombre, p.descripcion, p.precio, p.activo, p.imagen,
               m.nombre AS marca
        FROM productos p
        LEFT JOIN marcas m ON m.id_marca = p.id_marca
        WHERE 1=1";
$params = [];

if ($estadoFiltro === 'activos') $sql .= " AND p.activo = 1";
if ($estadoFiltro === 'inactivos') $sql .= " AND p.activo = 0";
if ($buscar !== '') {
    $sql .= " AND (p.nombre LIKE ? OR p.codigo LIKE ? OR m.nombre LIKE ?)";
    array_push($params, "%$buscar%", "%$buscar%", "%$buscar%");
}
if ($idMarcaFiltro > 0) {
    $sql .= " AND p.id_marca = ?";
    $params[] = $idMarcaFiltro;
}
$sql .= " ORDER BY p.activo DESC, p.nombre ASC";

$stmt = $conexion->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll();

// Stock por sede de todos los productos listados (una sola consulta)
$stockMapa = [];
foreach ($conexion->query("SELECT id_producto, id_sede, stock, stock_minimo FROM inventario")->fetchAll() as $f) {
    $stockMapa[(int)$f['id_producto']][(int)$f['id_sede']] = $f;
}

$marcas = listaMarcas($conexion);

layout_inicio($conexion, 'Productos', 'productos', [
    'eyebrow' => 'Catálogo',
    'descripcion' => 'Un solo catálogo para las dos tiendas, con stock propio en cada una.',
    'acciones' => '<a class="boton sec" href="../inventario/">' . icono('layers') . ' Ir a inventario</a>',
]);

echo avisoFlash($ok, $error);
?>

<details class="panel" <?= $error ? 'open' : '' ?>>
    <summary style="cursor:pointer;font-weight:700;font-size:17px;list-style:none;display:flex;align-items:center;gap:8px">
        <?= icono('plus') ?> Registrar un producto nuevo
    </summary>

    <form method="POST" enctype="multipart/form-data" style="margin-top:20px">
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
        <div class="form-rejilla">
            <div class="campo">
                <label for="codigo">Código de barras / SKU <small>(opcional)</small></label>
                <input id="codigo" name="codigo" maxlength="30" value="<?= h($form['codigo']) ?>">
            </div>
            <div class="campo">
                <label for="marca">Marca</label>
                <input id="marca" name="marca" maxlength="80" list="lista-marcas"
                       placeholder="Elige una o escribe una nueva" value="<?= h($form['marca']) ?>">
                <datalist id="lista-marcas">
                    <?php foreach ($marcas as $m): ?><option value="<?= h($m['nombre']) ?>"><?php endforeach; ?>
                </datalist>
            </div>
            <div class="campo ancho">
                <label for="nombre">Nombre del producto *</label>
                <input id="nombre" name="nombre" maxlength="150" required value="<?= h($form['nombre']) ?>">
            </div>
            <div class="campo">
                <label for="precio">Precio de venta (S/) *</label>
                <input id="precio" name="precio" type="number" min="0" step="0.01" required value="<?= h($form['precio']) ?>">
            </div>
            <div class="campo">
                <label for="stock_minimo">Avisar cuando queden (por tienda)</label>
                <input id="stock_minimo" name="stock_minimo" type="number" min="0" step="1" value="5">
            </div>
            <?php foreach ($sedes as $s): ?>
                <div class="campo">
                    <label for="stock_<?= (int)$s['id_sede'] ?>">Stock inicial en <?= h($s['nombre']) ?></label>
                    <input id="stock_<?= (int)$s['id_sede'] ?>" name="stock_<?= (int)$s['id_sede'] ?>"
                           type="number" min="0" step="1" value="0">
                </div>
            <?php endforeach; ?>
            <?= campoImagen() ?>
            <div class="campo ancho">
                <label for="descripcion">Descripción <small>(opcional)</small></label>
                <textarea id="descripcion" name="descripcion"><?= h($form['descripcion']) ?></textarea>
            </div>
        </div>
        <div style="margin-top:18px"><button class="boton" type="submit">Guardar producto</button></div>
    </form>
</details>

<section class="panel">
    <div class="panel-cabecera">
        <div><h2>Catálogo</h2><p><?= count($productos) ?> producto(s)</p></div>
    </div>

    <form class="buscador" method="GET">
        <input type="search" name="buscar" placeholder="Buscar por nombre, marca o código…" value="<?= h($buscar) ?>">
        <select name="marca" aria-label="Marca">
            <option value="0">Todas las marcas</option>
            <?php foreach ($marcas as $m): ?>
                <option value="<?= (int)$m['id_marca'] ?>" <?= $idMarcaFiltro === (int)$m['id_marca'] ? 'selected' : '' ?>><?= h($m['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="estado" aria-label="Estado">
            <option value="activos" <?= $estadoFiltro === 'activos' ? 'selected' : '' ?>>Activos</option>
            <option value="inactivos" <?= $estadoFiltro === 'inactivos' ? 'selected' : '' ?>>Inactivos</option>
            <option value="todos" <?= $estadoFiltro === 'todos' ? 'selected' : '' ?>>Todos</option>
        </select>
        <button class="boton" type="submit">Filtrar</button>
    </form>

    <div class="tabla-wrap">
        <table>
            <thead>
                <tr>
                    <th>Producto</th><th>Código</th><th class="num">Precio</th>
                    <th>Stock por tienda</th><th>Estado</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$productos): ?>
                <tr><td colspan="6" class="vacio">No encontramos productos con esos filtros.</td></tr>
            <?php endif; ?>
            <?php foreach ($productos as $p): $idp = (int)$p['id_producto']; ?>
                <tr>
                    <td>
                        <div class="celda-producto">
                            <?= fotoProducto($p['imagen'], $p['marca'], $p['nombre'], 56) ?>
                            <div>
                                <strong><?= h($p['nombre']) ?></strong>
                                <?php if ($p['marca']): ?><span class="marca-tag"><?= h($p['marca']) ?></span><?php else: ?><small>Sin marca</small><?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="mono"><?= h($p['codigo'] ?: '—') ?></td>
                    <td class="num"><?= soles($p['precio']) ?></td>
                    <td>
                        <div class="stock-sedes">
                            <?php foreach ($sedes as $s):
                                $f = $stockMapa[$idp][(int)$s['id_sede']] ?? ['stock' => 0, 'stock_minimo' => 5];
                                $e = estadoStock((int)$f['stock'], (int)$f['stock_minimo']);
                                $color = $e['clase'] === 'ok' ? '#16a34a' : ($e['clase'] === 'bajo' ? '#d97706' : '#dc2626');
                                $ancho = min(100, max(4, (int)$f['stock'] * 100 / max(1, (int)$f['stock_minimo'] * 4)));
                            ?>
                                <div class="stock-linea">
                                    <?= chipSede($s) ?>
                                    <span class="barra-stock"><i style="width:<?= $ancho ?>%;background:<?= $color ?>"></i></span>
                                    <b><?= (int)$f['stock'] ?></b>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </td>
                    <td><span class="pill <?= $p['activo'] ? 'ok' : 'inactivo' ?>"><?= $p['activo'] ? 'Activo' : 'Inactivo' ?></span></td>
                    <td class="num">
                        <a class="boton sec chico" href="editar.php?id=<?= $idp ?>"><?= icono('edit', 15) ?> Editar</a>
                        <a class="boton sec chico" href="../reportes/producto.php?id=<?= $idp ?>"><?= icono('chart', 15) ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php layout_fin(); ?>
