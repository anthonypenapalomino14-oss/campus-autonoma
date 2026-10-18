<?php
require_once __DIR__ . '/_comun.php';

$sedes = $conexion->query("SELECT id_sede, nombre, color FROM sedes WHERE activo = 1 ORDER BY id_sede")->fetchAll();

$base = "SELECT p.id_producto, p.codigo, p.nombre, p.descripcion, p.precio, p.imagen, m.nombre AS marca
         FROM productos p LEFT JOIN marcas m ON m.id_marca = p.id_marca
         WHERE p.activo = 1";

$codigo = trim($_POST['codigo'] ?? '');
$encontrado = null;
$errorConsulta = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($codigo === '') {
        $errorConsulta = 'Escribe el código del producto.';
    } else {
        try {
            $stmt = $conexion->prepare($base . " AND p.codigo = ? LIMIT 1");
            $stmt->execute([$codigo]);
            $encontrado = $stmt->fetch();
            if (!$encontrado) {
                $errorConsulta = 'No encontramos un producto con ese código. Revísalo e inténtalo otra vez.';
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $errorConsulta = 'No pudimos consultar el precio. Inténtalo en un momento.';
        }
    }
}

try {
    $productos = $conexion->query($base . " ORDER BY p.nombre")->fetchAll();
    $inv = [];
    foreach ($conexion->query("SELECT id_producto, id_sede, stock, stock_minimo FROM inventario")->fetchAll() as $f) {
        $inv[(int)$f['id_producto']][(int)$f['id_sede']] = $f;
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    $productos = [];
    $inv = [];
}

// Texto de disponibilidad para el cliente (sin números internos de más)
function disponibilidad(?array $f): array
{
    $stock = (int)($f['stock'] ?? 0);
    $min = (int)($f['stock_minimo'] ?? 5);
    if ($stock <= 0) return ['agotado', 'Agotado'];
    if ($stock <= $min) return ['bajo', 'Últimas ' . $stock . ' unidades'];
    return ['ok', 'Disponible'];
}

tienda_inicio('Productos y precios');
?>

<section class="portada">
    <h1>Consulta el precio y dónde hay stock</h1>
    <p>Escribe el código del producto, o recorre el catálogo de abajo. Te mostramos la disponibilidad en cada una de nuestras tiendas.</p>
    <form method="POST" action="index.php">
        <input name="codigo" placeholder="Código del producto" value="<?= h($codigo) ?>" autocomplete="off">
        <button class="boton" type="submit">Consultar</button>
    </form>

    <?php if ($errorConsulta): ?>
        <div class="aviso mal" style="margin-top:16px;max-width:520px"><span><?= h($errorConsulta) ?></span></div>
    <?php endif; ?>

    <?php if ($encontrado): $idp = (int)$encontrado['id_producto']; ?>
        <div class="resultado-precio">
            <?= fotoProducto($encontrado['imagen'], $encontrado['marca'], $encontrado['nombre'], 56) ?>
            <div>
                <strong><?= h($encontrado['nombre']) ?></strong><br>
                <small><?= h($encontrado['marca'] ?: 'Sin marca') ?> · código <?= h($encontrado['codigo']) ?></small>
            </div>
            <div class="precio"><?= soles($encontrado['precio']) ?></div>
        </div>
    <?php endif; ?>
</section>

<div class="buscador">
    <input type="search" id="filtro" placeholder="Filtrar catálogo por nombre, marca o código…" autocomplete="off">
</div>

<?php if (!$productos): ?>
    <div class="panel estado-vacio"><strong>Aún no hay productos</strong>Vuelve pronto, estamos preparando el catálogo.</div>
<?php endif; ?>

<section class="catalogo" id="catalogo">
    <?php foreach ($productos as $p): $idp = (int)$p['id_producto']; ?>
        <article class="producto-card" data-texto="<?= h(mb_strtolower($p['nombre'] . ' ' . $p['marca'] . ' ' . $p['codigo'])) ?>">
            <?= fotoProducto($p['imagen'], $p['marca'], $p['nombre'], 0) ?>
            <?php if ($p['marca']): ?><span class="marca-tag" style="align-self:flex-start"><?= h($p['marca']) ?></span><?php endif; ?>
            <h3><?= h($p['nombre']) ?></h3>
            <?php if ($p['descripcion']): ?><small><?= h($p['descripcion']) ?></small><?php endif; ?>
            <div class="precio"><?= soles($p['precio']) ?></div>
            <small class="mono">Código: <?= h($p['codigo'] ?: '—') ?></small>
            <div class="disp">
                <?php foreach ($sedes as $s): [$clase, $texto] = disponibilidad($inv[$idp][(int)$s['id_sede']] ?? null); ?>
                    <div class="disp-linea">
                        <span class="chip-sede"><i style="background:<?= h($s['color']) ?>"></i><?= h($s['nombre']) ?></span>
                        <span class="pill <?= $clase ?>"><?= h($texto) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </article>
    <?php endforeach; ?>
</section>
<p id="sin-resultados" class="estado-vacio" style="display:none"><strong>No encontramos coincidencias</strong>Prueba con otra palabra o con el código.</p>

<script>
(function () {
    var f = document.getElementById('filtro');
    var tarjetas = document.querySelectorAll('#catalogo .producto-card');
    var vacio = document.getElementById('sin-resultados');
    f.addEventListener('input', function () {
        var t = f.value.trim().toLowerCase(), n = 0;
        tarjetas.forEach(function (c) {
            var ver = c.dataset.texto.indexOf(t) !== -1;
            c.style.display = ver ? '' : 'none';
            if (ver) n++;
        });
        vacio.style.display = n ? 'none' : 'block';
    });
})();
</script>

<?php tienda_fin(); ?>
