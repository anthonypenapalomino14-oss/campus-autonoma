<?php
require_once __DIR__ . '/imagenes.php';
// Todo cambio de stock pasa por aquí: actualiza la tienda, deja registro
// en el kardex y mantiene el total en productos.stock.
// Debe llamarse dentro de una transacción abierta por quien llama.

function moverStock(
    PDO $conexion,
    int $idProducto,
    int $idSede,
    int $delta,
    string $tipo,
    ?string $motivo,
    ?int $idUsuario,
    ?int $idVenta = null,
    ?string $referencia = null
): int {
    $conexion->prepare(
        "INSERT IGNORE INTO inventario (id_producto, id_sede, stock)
         VALUES (?, ?, 0)"
    )->execute([$idProducto, $idSede]);

    $stmt = $conexion->prepare(
        "SELECT stock FROM inventario
         WHERE id_producto = ? AND id_sede = ?
         FOR UPDATE"
    );
    $stmt->execute([$idProducto, $idSede]);
    $actual = (int)$stmt->fetchColumn();
    $nuevo = $actual + $delta;

    if ($nuevo < 0) {
        throw new RuntimeException(
            'Stock insuficiente en esta tienda. Disponible: ' . $actual
        );
    }

    $conexion->prepare(
        "UPDATE inventario SET stock = ?
         WHERE id_producto = ? AND id_sede = ?"
    )->execute([$nuevo, $idProducto, $idSede]);

    $conexion->prepare(
        "INSERT INTO movimientos_inventario
         (id_producto, id_sede, tipo, cantidad, stock_resultante,
          motivo, referencia, id_usuario, id_venta)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    )->execute([
        $idProducto, $idSede, $tipo, $delta, $nuevo,
        $motivo, $referencia, $idUsuario, $idVenta
    ]);

    $conexion->prepare(
        "UPDATE productos
         SET stock = (SELECT COALESCE(SUM(stock), 0)
                      FROM inventario WHERE id_producto = ?)
         WHERE id_producto = ?"
    )->execute([$idProducto, $idProducto]);

    return $nuevo;
}

// Estado visual de un stock
function estadoStock(int $stock, int $minimo): array
{
    if ($stock <= 0) {
        return ['clase' => 'agotado', 'texto' => 'Agotado'];
    }
    if ($stock <= $minimo) {
        return ['clase' => 'bajo', 'texto' => 'Stock bajo'];
    }
    return ['clase' => 'ok', 'texto' => 'Disponible'];
}
