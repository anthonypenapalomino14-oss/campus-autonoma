<?php
require_once __DIR__ . '/../config/auth.php';
exigirRol(['admin']);
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/layout.php';
require_once __DIR__ . '/_comun.php';

$id = entero($_GET['id'] ?? 0, 1, PHP_INT_MAX);
if (!$id) {
    header('Location: index.php');
    exit;
}

$stmt = $conexion->prepare(
    "SELECT p.*, m.nombre AS marca FROM productos p
     LEFT JOIN marcas m ON m.id_marca = p.id_marca WHERE p.id_producto = ?"
);
$stmt->execute([$id]);
$p = $stmt->fetch();
if (!$p) {
    header('Location: index.php');
    exit;
}

$sedes = listarSedes($conexion);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = trim($_POST['codigo'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $marca = trim($_POST['marca'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $precio = $_POST['precio'] ?? '';
    $activo = ($_POST['activo'] ?? '1') === '1' ? 1 : 0;

    $minimos = [];
    $minValido = true;
    foreach ($sedes as $s) {
        $n = entero($_POST['minimo_' . $s['id_sede']] ?? 0, 0, 100000);
        if ($n === null) $minValido = false;
        $minimos[(int)$s['id_sede']] = (int)$n;
    }

    if (!csrfValido($_POST['csrf_token'] ?? '')) {
        $error = 'La sesión de seguridad expiró. Recarga la página.';
    } elseif ($nombre === '' || !is_numeric($precio) || (float)$precio < 0 || !$minValido) {
        $error = 'Revisa el nombre, el precio y los mínimos.';
    } else {
        try {
            $conexion->beginTransaction();
            $idMarca = obtenerOCrearMarca($conexion, $marca);

            $img = $p['imagen'];
            $nueva = guardarImagenProducto($_FILES['imagen'] ?? null);
            if ($nueva) {
                $img = $nueva;
            } elseif (!empty($_POST['quitar_imagen'])) {
                $img = null;
            }

            $conexion->prepare(
                "UPDATE productos SET codigo = ?, id_marca = ?, nombre = ?, descripcion = ?,
                        precio = ?, activo = ?, imagen = ? WHERE id_producto = ?"
            )->execute([
                $codigo !== '' ? $codigo : null, $idMarca, $nombre,
                $descripcion !== '' ? $descripcion : null,
                round((float)$precio, 2), $activo, $img, $id
            ]);
            if ($img !== $p['imagen']) {
                borrarImagenProducto($p['imagen']);
            }

            foreach ($minimos as $idS => $min) {
                $conexion->prepare(
                    "INSERT INTO inventario (id_producto, id_sede, stock, stock_minimo)
                     VALUES (?, ?, 0, ?)
                     ON DUPLICATE KEY UPDATE stock_minimo = VALUES(stock_minimo)"
                )->execute([$id, $idS, $min]);
            }

            $conexion->commit();
            header('Location: index.php?actualizado=1');
            exit;
        } catch (RuntimeException $e) {
            if ($conexion->inTransaction()) $conexion->rollBack();
            $error = $e->getMessage();
        } catch (PDOException $e) {
            if ($conexion->inTransaction()) $conexion->rollBack();
            $error = $e->getCode() === '23000'
                ? 'Ese código ya pertenece a otro producto.'
                : 'No se pudo actualizar el producto.';
            if ($e->getCode() !== '23000') error_log($e->getMessage());
        }
        $p = array_merge($p, [
            'codigo' => $codigo, 'nombre' => $nombre, 'marca' => $marca,
            'descripcion' => $descripcion, 'precio' => $precio, 'activo' => $activo
        ]);
    }
}

$inv = [];
$q = $conexion->prepare("SELECT id_sede, stock, stock_minimo FROM inventario WHERE id_producto = ?");
$q->execute([$id]);
foreach ($q->fetchAll() as $f) $inv[(int)$f['id_sede']] = $f;

layout_inicio($conexion, 'Editar producto', 'productos', [
    'eyebrow' => 'Catálogo',
    'descripcion' => 'Para sumar o restar unidades usa Inventario: así queda registrado quién y por qué.',
    'ancho' => 'angosto',
    'marcas' => null,
    'acciones' => '<a class="boton sec" href="index.php">← Volver</a>',
]);
echo avisoFlash(null, $error);
$marcas = listaMarcas($conexion);
?>

<form method="POST" enctype="multipart/form-data" class="panel">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <div class="form-rejilla">
        <div class="campo">
            <label for="codigo">Código</label>
            <input id="codigo" name="codigo" maxlength="30" value="<?= h($p['codigo']) ?>">
        </div>
        <div class="campo">
            <label for="marca">Marca</label>
            <input id="marca" name="marca" maxlength="80" list="lista-marcas" value="<?= h($p['marca']) ?>">
            <datalist id="lista-marcas"><?php foreach ($marcas as $m): ?><option value="<?= h($m['nombre']) ?>"><?php endforeach; ?></datalist>
        </div>
        <div class="campo ancho">
            <label for="nombre">Nombre *</label>
            <input id="nombre" name="nombre" maxlength="150" required value="<?= h($p['nombre']) ?>">
        </div>
        <div class="campo">
            <label for="precio">Precio (S/) *</label>
            <input id="precio" name="precio" type="number" min="0" step="0.01" required value="<?= h($p['precio']) ?>">
        </div>
        <div class="campo">
            <label for="activo">Estado</label>
            <select id="activo" name="activo">
                <option value="1" <?= $p['activo'] ? 'selected' : '' ?>>Activo (se puede vender)</option>
                <option value="0" <?= !$p['activo'] ? 'selected' : '' ?>>Inactivo (oculto en caja y tienda)</option>
            </select>
        </div>
        <?php foreach ($sedes as $s): $idS = (int)$s['id_sede']; ?>
            <div class="campo">
                <label for="minimo_<?= $idS ?>">Avisar cuando queden — <?= h($s['nombre']) ?>
                    <small>(hoy hay <?= (int)($inv[$idS]['stock'] ?? 0) ?>)</small></label>
                <input id="minimo_<?= $idS ?>" name="minimo_<?= $idS ?>" type="number" min="0"
                       value="<?= (int)($inv[$idS]['stock_minimo'] ?? 5) ?>">
            </div>
        <?php endforeach; ?>
        <?= campoImagen($p['imagen'] ?? null) ?>
        <div class="campo ancho">
            <label for="descripcion">Descripción</label>
            <textarea id="descripcion" name="descripcion"><?= h($p['descripcion']) ?></textarea>
        </div>
    </div>
    <div style="margin-top:18px;display:flex;gap:10px">
        <button class="boton" type="submit">Guardar cambios</button>
        <a class="boton sec" href="../inventario/?q=<?= urlencode($p['codigo'] ?: $p['nombre']) ?>">Ajustar stock</a>
    </div>
</form>

<?php layout_fin(); ?>
