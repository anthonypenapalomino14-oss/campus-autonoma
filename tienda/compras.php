<?php
require_once __DIR__ . '/_comun.php';
exigirCliente();

$idCliente = (int)$_SESSION['cliente_id'];
$idVenta = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$idVenta || $idVenta < 1) {
    http_response_code(400);
    exit('Número de compra no válido.');
}

$stmt = $conexion->prepare(
    "SELECT v.id_venta, v.numero_venta, v.fecha_hora, v.total, v.estado, v.cliente_nombre,
            b.codigo_verificacion, b.fecha_emision, s.nombre AS sede
     FROM ventas v
     INNER JOIN sedes s ON s.id_sede = v.id_sede
     LEFT JOIN boletas b ON b.id_venta = v.id_venta
     WHERE v.id_venta = ? AND v.id_cliente = ? LIMIT 1"
);
$stmt->execute([$idVenta, $idCliente]);
$venta = $stmt->fetch();

if (!$venta) {
    http_response_code(404);
    exit('No encontramos esa compra en tu cuenta.');
}
if ($venta['estado'] !== 'completada') {
    http_response_code(403);
    exit('Esta compra no tiene boleta disponible.');
}

$stmt = $conexion->prepare(
    "SELECT d.cantidad, d.precio_unitario, d.subtotal, p.nombre, m.nombre AS marca
     FROM detalle_venta d
     INNER JOIN productos p ON p.id_producto = d.id_producto
     LEFT JOIN marcas m ON m.id_marca = p.id_marca
     WHERE d.id_venta = ? ORDER BY d.id_detalle"
);
$stmt->execute([$idVenta]);
$detalles = $stmt->fetchAll();

// Enlace de verificación (mismo servidor desde el que se abre la boleta)
$esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$rutaBase = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
$urlVerificacion = $esquema . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $rutaBase .
    '/ventas/verificar_boleta.php?codigo=' . rawurlencode((string)$venta['codigo_verificacion']);

tienda_inicio('Boleta ' . $venta['numero_venta'], 'angosto');
?>

<div class="no-imprimir" style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap">
    <a class="boton sec" href="mi_cuenta.php">← Mis compras</a>
    <button class="boton" type="button" onclick="window.print()"><?= '🖨' ?> Imprimir / guardar PDF</button>
</div>

<article class="panel" style="padding:30px">
    <div style="text-align:center;margin-bottom:18px">
        <div style="font-family:'Baloo 2',Arial,sans-serif;font-size:34px;font-weight:800;line-height:1.1;color:#131311">Market <span style="color:#f28c28">Donna</span></div>
        <small>Comprobante de compra · <?= h($venta['sede']) ?></small>
        <p class="mono" style="margin-top:6px">N.º <?= h($venta['numero_venta']) ?></p>
    </div>

    <p><strong>Fecha:</strong> <?= h(date('d/m/Y H:i', strtotime($venta['fecha_hora']))) ?></p>
    <p><strong>Cliente:</strong> <?= h($venta['cliente_nombre'] ?: 'Cliente registrado') ?></p>

    <div class="tabla-wrap" style="margin-top:16px">
        <table>
            <thead><tr><th>Producto</th><th class="num">Cant.</th><th class="num">Precio</th><th class="num">Subtotal</th></tr></thead>
            <tbody>
            <?php foreach ($detalles as $d): ?>
                <tr>
                    <td><?= h($d['nombre']) ?><?= $d['marca'] ? '<br><small>' . h($d['marca']) . '</small>' : '' ?></td>
                    <td class="num"><?= (int)$d['cantidad'] ?></td>
                    <td class="num"><?= soles($d['precio_unitario']) ?></td>
                    <td class="num"><?= soles($d['subtotal']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p class="resumen-total" style="display:flex;justify-content:space-between;border-top:1px dashed var(--linea-fuerte);margin-top:14px;padding-top:14px;font-size:24px;font-weight:800">
        <span>Total</span><span><?= soles($venta['total']) ?></span>
    </p>

    <?php if ($venta['codigo_verificacion']): ?>
        <div style="text-align:center;margin-top:24px">
            <p><strong>Escanea para verificar esta compra</strong></p>
            <div id="qrcode" style="display:inline-block;margin:10px 0"></div>
            <p class="mono" style="word-break:break-all"><?= h($venta['codigo_verificacion']) ?></p>
        </div>
    <?php endif; ?>

    <p class="nota-suave" style="text-align:center;margin-top:18px">Gracias por tu compra. Este documento es un comprobante interno de Market Donna.</p>
</article>

<?php if ($venta['codigo_verificacion']): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
(function () {
    var caja = document.getElementById('qrcode');
    if (window.QRCode) {
        new QRCode(caja, { text: <?= json_encode($urlVerificacion, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, width: 160, height: 160 });
    } else {
        caja.textContent = 'No se pudo cargar el QR. Revisa tu conexión.';
    }
})();
</script>
<?php endif; ?>

<?php tienda_fin(); ?>
