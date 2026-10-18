
<?php
require_once __DIR__ . '/../config/auth.php';
exigirRol(['admin', 'vendedor', 'soporte']);
require_once __DIR__ . '/../config/conexion.php';

$numero = trim($_GET['numero'] ?? '');

if ($numero === '') {
    http_response_code(400);
    exit('No se indicó el número de venta.');
}

$stmt = $conexion->prepare(
    "SELECT v.id_venta, v.numero_venta, v.cliente_dni,
            v.cliente_nombre, v.fecha_hora, v.total, v.estado,
            u.nombres AS vendedor_nombres,
            u.apellidos AS vendedor_apellidos,
            b.codigo_verificacion, b.fecha_emision,
            se.nombre AS sede_nombre
     FROM ventas v
     INNER JOIN sedes se ON se.id_sede = v.id_sede
     INNER JOIN usuarios u ON u.id_usuario = v.id_vendedor
     INNER JOIN boletas b ON b.id_venta = v.id_venta
     WHERE v.numero_venta = ?
     LIMIT 1"
);
$stmt->execute([$numero]);
$venta = $stmt->fetch();

if (!$venta) {
    http_response_code(404);
    exit('No se encontró la boleta solicitada.');
}

$stmt = $conexion->prepare(
    "SELECT p.nombre, d.cantidad, d.precio_unitario, d.subtotal
     FROM detalle_venta d
     INNER JOIN productos p ON p.id_producto = d.id_producto
     WHERE d.id_venta = ?
     ORDER BY d.id_detalle ASC"
);
$stmt->execute([$venta['id_venta']]);
$productos = $stmt->fetchAll();

function h($valor): string {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

$verificacionUrl = 'verificar_boleta.php?codigo=' .
    rawurlencode($venta['codigo_verificacion']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Boleta <?= h($venta['numero_venta']) ?> | Market Donna</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 25px 12px;
            background: #f4f6f2;
            color: #172033;
            font-family: Arial, sans-serif;
        }
        .boleta {
            width: 100%;
            max-width: 520px;
            margin: auto;
            padding: 28px;
            background: white;
            border-radius: 14px;
            box-shadow: 0 8px 28px #0f172a14;
        }
        .centro { text-align: center; }
        .marca { font-size: 28px; font-weight: 800; margin-bottom: 5px; }
        .subtitulo { color: #64748b; font-size: 13px; }
        .estado {
            display: inline-block;
            margin: 15px 0;
            padding: 7px 12px;
            border-radius: 20px;
            background: #dcfce7;
            color: #166534;
            font-size: 12px;
            font-weight: bold;
        }
        hr { border: 0; border-top: 1px dashed #cbd5e1; margin: 18px 0; }
        .dato { margin: 8px 0; font-size: 13px; overflow-wrap: anywhere; }
        .dato strong { display: inline-block; min-width: 105px; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; }
        th, td { padding: 10px 4px; text-align: left; font-size: 12px; border-bottom: 1px solid #e2e8f0; }
        th:last-child, td:last-child { text-align: right; }
        .producto { max-width: 190px; overflow-wrap: anywhere; }
        .total { display: flex; justify-content: space-between; font-size: 21px; font-weight: bold; margin-top: 20px; }
        .qr { display: flex; flex-direction: column; align-items: center; margin-top: 22px; text-align: center; }
        #qrcode { margin: 12px auto; min-height: 10px; }
        .codigo { font-size: 10px; overflow-wrap: anywhere; color: #475569; }
        .acciones { display: flex; gap: 10px; justify-content: center; margin: 20px auto; }
        .boton { border: 0; border-radius: 8px; padding: 12px 18px; cursor: pointer; font-weight: bold; }
        .imprimir { background: #16803c; color: white; }
        .volver { background: #e2e8f0; color: #172033; }
        a { text-decoration: none; }
        @media print {
            body { background: white; padding: 0; }
            .boleta { box-shadow: none; border-radius: 0; padding: 10px; }
            .acciones { display: none; }
        }
        @media (max-width: 400px) {
            .boleta { padding: 18px 14px; }
            th, td { font-size: 11px; }
        }
    </style>
</head>
<body>
<main class="boleta">
    <header class="centro">
        <div style="font-family:'Baloo 2',Arial,sans-serif;font-size:34px;font-weight:800;line-height:1.1;color:#131311">Market <span style="color:#f28c28">Donna</span></div>
        <div class="subtitulo">COMPROBANTE INTERNO DE VENTA</div>
        <div class="estado"><?= h(strtoupper($venta['estado'])) ?></div>
    </header>

    <hr>

    <div class="dato"><strong>N.º de venta:</strong> <?= h($venta['numero_venta']) ?></div>
    <div class="dato"><strong>Fecha:</strong> <?= h($venta['fecha_hora']) ?></div>
    <div class="dato"><strong>Tienda:</strong> <?= h($venta['sede_nombre']) ?></div>
    <div class="dato"><strong>Vendedor:</strong>
        <?= h($venta['vendedor_nombres'] . ' ' . $venta['vendedor_apellidos']) ?>
    </div>

    <hr>

    <div class="dato"><strong>DNI cliente:</strong> <?= h($venta['cliente_dni'] ?: 'No registrado') ?></div>
    <div class="dato"><strong>Cliente:</strong> <?= h($venta['cliente_nombre'] ?: 'Cliente general') ?></div>

    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th>Cant.</th>
                <th>Precio</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($productos as $producto): ?>
            <tr>
                <td class="producto"><?= h($producto['nombre']) ?></td>
                <td><?= (int)$producto['cantidad'] ?></td>
                <td>S/ <?= number_format((float)$producto['precio_unitario'], 2) ?></td>
                <td>S/ <?= number_format((float)$producto['subtotal'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="total">
        <span>TOTAL</span>
        <span>S/ <?= number_format((float)$venta['total'], 2) ?></span>
    </div>

    <hr>

    <section class="qr">
        <strong>Verificación interna</strong>
        <div id="qrcode"></div>
        <div class="subtitulo">Escanea el QR para consultar la validez de este comprobante dentro de Market Donna.</div>
        <p class="codigo">Código: <?= h($venta['codigo_verificacion']) ?></p>
    </section>

    <p class="centro subtitulo">
        Este documento es un comprobante interno del sistema y no reemplaza una boleta electrónica oficial.
    </p>
</main>

<div class="acciones">
    <button class="boton imprimir" onclick="window.print()">Imprimir boleta</button>
    <?php if (rolActual() === 'soporte'): ?>
        <a class="boton volver" href="../facturacion/">Volver a facturación</a>
    <?php else: ?>
        <a class="boton volver" href="index.php">Volver a caja</a>
        <a class="boton volver" href="../facturacion/">Facturación</a>
    <?php endif; ?>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
const urlVerificacion = new URL(
    <?= json_encode($verificacionUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    window.location.href
).href;

if (window.QRCode) {
    new QRCode(document.getElementById('qrcode'), {
        text: urlVerificacion,
        width: 160,
        height: 160,
        correctLevel: QRCode.CorrectLevel.M
    });
} else {
    document.getElementById('qrcode').textContent =
        'No se pudo cargar el QR. Revisa tu conexión a Internet.';
}
</script>
</body>
</html>
