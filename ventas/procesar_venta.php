<?php
require_once __DIR__ . '/../config/auth.php';
exigirRol(['admin', 'vendedor']);
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/inventario.php';

header('Content-Type: application/json; charset=utf-8');

function responder(int $estado, array $datos): void
{
    http_response_code($estado);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(405, ['ok' => false, 'mensaje' => 'Método no permitido.']);
}

$datos = json_decode(file_get_contents('php://input'), true);

if (!is_array($datos)) {
    responder(400, ['ok' => false, 'mensaje' => 'Los datos recibidos no son válidos.']);
}

if (!csrfValido((string)($datos['csrf_token'] ?? ''))) {
    responder(403, ['ok' => false, 'mensaje' => 'La sesión de seguridad expiró. Recarga la caja.']);
}

$carrito = $datos['carrito'] ?? [];
$dni = trim((string)($datos['cliente_dni'] ?? ''));
$nombreCliente = trim((string)($datos['cliente_nombre'] ?? ''));

if (!is_array($carrito) || count($carrito) === 0) {
    responder(400, ['ok' => false, 'mensaje' => 'Agrega productos antes de cobrar.']);
}

if (!preg_match('/^\d{8}$/', $dni)) {
    responder(400, ['ok' => false, 'mensaje' => 'El DNI debe contener exactamente 8 dígitos.']);
}

if (mb_strlen($nombreCliente) > 150) {
    responder(400, ['ok' => false, 'mensaje' => 'El nombre del cliente es demasiado largo.']);
}

$idUsuario = (int)($_SESSION['usuario_id'] ?? 0);
if ($idUsuario < 1) {
    responder(401, ['ok' => false, 'mensaje' => 'Inicia sesión nuevamente.']);
}

// La venta sale del stock de la tienda en la que se está trabajando
$idSede = sedeActualId($conexion);

try {
    $conexion->beginTransaction();

    $stmt = $conexion->prepare(
        "SELECT id_usuario, rol FROM usuarios
         WHERE id_usuario = ? AND activo = 1 LIMIT 1"
    );
    $stmt->execute([$idUsuario]);
    $usuario = $stmt->fetch();

    if (!$usuario || !in_array($usuario['rol'], ['admin', 'vendedor'], true)) {
        throw new RuntimeException('El usuario no tiene permiso para registrar ventas.');
    }

    $stmt = $conexion->prepare("SELECT COUNT(*) FROM sedes WHERE id_sede = ? AND activo = 1");
    $stmt->execute([$idSede]);
    if (!$stmt->fetchColumn()) {
        throw new RuntimeException('La tienda seleccionada no está disponible.');
    }

    // Cliente existente o nuevo
    $stmt = $conexion->prepare(
        "SELECT id_usuario, nombres, apellidos FROM usuarios
         WHERE dni = ? AND rol = 'cliente' LIMIT 1"
    );
    $stmt->execute([$dni]);
    $cliente = $stmt->fetch();

    if ($cliente) {
        $idCliente = (int)$cliente['id_usuario'];
        $nombreCliente = trim($cliente['nombres'] . ' ' . $cliente['apellidos']);
    } else {
        if ($nombreCliente === '') {
            throw new RuntimeException('Cliente nuevo: ingresa sus nombres y apellidos.');
        }

        $partes = preg_split('/\s+/', $nombreCliente);
        $apellidos = array_pop($partes);
        $nombres = implode(' ', $partes);

        if ($nombres === '' || $apellidos === '') {
            throw new RuntimeException('Ingresa los nombres y apellidos completos del cliente.');
        }

        $passwordHash = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);

        $stmt = $conexion->prepare(
            "INSERT INTO usuarios (dni, nombres, apellidos, password_hash, rol, creado_en)
             VALUES (?, ?, ?, ?, 'cliente', NOW())"
        );
        $stmt->execute([$dni, $nombres, $apellidos, $passwordHash]);

        $idCliente = (int)$conexion->lastInsertId();
        $nombreCliente = trim($nombres . ' ' . $apellidos);
    }

    // Validar productos con precio y stock reales de ESTA tienda
    $items = [];
    $total = 0;
    $idsProcesados = [];

    foreach ($carrito as $item) {
        if (!is_array($item)) {
            throw new RuntimeException('Hay un producto inválido.');
        }

        $idProducto = filter_var($item['id'] ?? null, FILTER_VALIDATE_INT);
        $cantidad = filter_var($item['cantidad'] ?? null, FILTER_VALIDATE_INT);

        if ($idProducto === false || $idProducto < 1 || $cantidad === false || $cantidad < 1) {
            throw new RuntimeException('Revisa los productos y sus cantidades.');
        }

        if (isset($idsProcesados[$idProducto])) {
            throw new RuntimeException('El carrito contiene productos duplicados.');
        }
        $idsProcesados[$idProducto] = true;

        $stmt = $conexion->prepare(
            "SELECT id_producto, nombre, precio FROM productos
             WHERE id_producto = ? AND activo = 1 FOR UPDATE"
        );
        $stmt->execute([$idProducto]);
        $producto = $stmt->fetch();

        if (!$producto) {
            throw new RuntimeException('Uno de los productos ya no está disponible.');
        }

        $stmt = $conexion->prepare(
            "SELECT stock FROM inventario
             WHERE id_producto = ? AND id_sede = ? FOR UPDATE"
        );
        $stmt->execute([$idProducto, $idSede]);
        $disponible = (int)$stmt->fetchColumn();

        if ($cantidad > $disponible) {
            throw new RuntimeException(
                'Stock insuficiente en esta tienda para ' . $producto['nombre'] .
                '. Disponible: ' . $disponible
            );
        }

        $precio = (float)$producto['precio'];
        $subtotal = round($precio * $cantidad, 2);
        $total = round($total + $subtotal, 2);

        $items[] = [
            'id' => (int)$producto['id_producto'],
            'cantidad' => $cantidad,
            'precio' => $precio,
            'subtotal' => $subtotal
        ];
    }

    $numeroVenta = 'V-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));

    $stmt = $conexion->prepare(
        "INSERT INTO ventas
         (numero_venta, id_cliente, id_vendedor, id_sede,
          cliente_dni, cliente_nombre, fecha_hora, total, estado)
         VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, 'completada')"
    );
    $stmt->execute([$numeroVenta, $idCliente, $idUsuario, $idSede, $dni, $nombreCliente, $total]);

    $idVenta = (int)$conexion->lastInsertId();

    $stmtDetalle = $conexion->prepare(
        "INSERT INTO detalle_venta (id_venta, id_producto, cantidad, precio_unitario, subtotal)
         VALUES (?, ?, ?, ?, ?)"
    );

    foreach ($items as $item) {
        $stmtDetalle->execute([
            $idVenta, $item['id'], $item['cantidad'], $item['precio'], $item['subtotal']
        ]);

        moverStock(
            $conexion, $item['id'], $idSede, -$item['cantidad'],
            'venta', 'Venta ' . $numeroVenta, $idUsuario, $idVenta
        );
    }

    $stmtBoleta = $conexion->prepare(
        "INSERT INTO boletas (id_venta, codigo_verificacion, fecha_emision)
         VALUES (?, ?, NOW())"
    );
    $stmtBoleta->execute([$idVenta, bin2hex(random_bytes(16))]);

    $conexion->commit();

    responder(200, [
        'ok' => true,
        'mensaje' => 'Venta registrada correctamente.',
        'numero_venta' => $numeroVenta,
        'total' => number_format($total, 2, '.', ''),
        'boleta_url' => 'boleta.php?numero=' . rawurlencode($numeroVenta)
    ]);

} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    if ($e instanceof PDOException) {
        error_log($e->getMessage());
        responder(500, [
            'ok' => false,
            'mensaje' => 'No se pudo guardar la venta. Revisa la configuración de la base de datos.'
        ]);
    }

    responder(400, ['ok' => false, 'mensaje' => $e->getMessage()]);
}
