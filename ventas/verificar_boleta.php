
<?php
require_once __DIR__ . '/../config/conexion.php';

$codigo = trim($_GET['codigo'] ?? '');
$venta = null;

if (preg_match('/^[a-f0-9]{32}$/', $codigo)) {
    $stmt = $conexion->prepare(
        "SELECT v.numero_venta, v.fecha_hora, v.total, v.estado,
                b.fecha_emision
         FROM boletas b
         INNER JOIN ventas v ON v.id_venta = b.id_venta
         WHERE b.codigo_verificacion = ?
         LIMIT 1"
    );
    $stmt->execute([$codigo]);
    $venta = $stmt->fetch();
}

$valida = $venta && $venta['estado'] === 'completada';

function h($valor): string {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verificar comprobante | Market Donna</title>
    <style>
        body {
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            display: grid;
            place-items: center;
            box-sizing: border-box;
            background: #f4f6f2;
            font-family: Arial, sans-serif;
            color: #172033;
        }
        .tarjeta {
            width: 100%;
            max-width: 430px;
            box-sizing: border-box;
            background: white;
            padding: 28px;
            border-radius: 15px;
            box-shadow: 0 8px 28px #0f172a14;
        }
        .centro { text-align: center; }
        .estado {
            font-size: 20px;
            font-weight: bold;
            margin: 20px 0 10px;
        }
        .valida { color: #15803d; }
        .invalida { color: #b91c1c; }
        .dato {
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
            overflow-wrap: anywhere;
        }
        .nota { color: #64748b; font-size: 13px; line-height: 1.5; }
    </style>
</head>
<body>
    <main class="tarjeta">
        <div class="centro">
            <div style="font-family:'Baloo 2',Arial,sans-serif;font-size:34px;font-weight:800;line-height:1.1;color:#131311">Market <span style="color:#f28c28">Donna</span></div>
            <p>Consulta de comprobante interno</p>

            <?php if ($valida): ?>
                <div class="estado valida">✓ Comprobante válido</div>
                <p class="nota">El comprobante existe en el sistema y la venta figura como completada.</p>
            <?php else: ?>
                <div class="estado invalida">Comprobante no válido</div>
                <p class="nota">No se encontró un comprobante válido con ese código, o la venta no figura como completada.</p>
            <?php endif; ?>
        </div>

        <?php if ($valida): ?>
            <div class="dato"><strong>N.º de venta:</strong> <?= h($venta['numero_venta']) ?></div>
            <div class="dato"><strong>Fecha:</strong> <?= h($venta['fecha_hora']) ?></div>
            <div class="dato"><strong>Total:</strong> S/ <?= number_format((float)$venta['total'], 2) ?></div>
            <div class="dato"><strong>Estado:</strong> <?= h($venta['estado']) ?></div>
        <?php endif; ?>

        <p class="nota">
            Esta consulta corresponde únicamente al sistema Market Donna y no constituye una validación de SUNAT.
        </p>
    </main>
</body>
</html>
