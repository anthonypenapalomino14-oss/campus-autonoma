<?php
require_once __DIR__ . '/../config/auth.php';
exigirRol(['admin', 'vendedor']);
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/imagenes.php';

header('Content-Type: application/json; charset=utf-8');

function salir(int $estado, array $datos): void
{
    http_response_code($estado);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

$codigo = trim($_GET['codigo'] ?? '');
$q = trim($_GET['q'] ?? '');
$idSede = sedeActualId($conexion);

$base = "SELECT p.id_producto, p.codigo, p.nombre, p.precio, p.imagen, m.nombre AS marca,
                COALESCE(i.stock, 0) AS stock
         FROM productos p
         LEFT JOIN marcas m ON m.id_marca = p.id_marca
         LEFT JOIN inventario i ON i.id_producto = p.id_producto AND i.id_sede = ?
         WHERE p.activo = 1";

// Stock de las demás tiendas, para avisar al cajero
function otrasTiendas(PDO $conexion, int $idProducto, int $idSede): string
{
    $stmt = $conexion->prepare(
        "SELECT s.nombre, i.stock
         FROM inventario i INNER JOIN sedes s ON s.id_sede = i.id_sede
         WHERE i.id_producto = ? AND i.id_sede <> ? AND i.stock > 0 AND s.activo = 1"
    );
    $stmt->execute([$idProducto, $idSede]);
    $partes = [];
    foreach ($stmt->fetchAll() as $f) {
        $partes[] = $f['nombre'] . ': ' . (int)$f['stock'];
    }
    return implode(' · ', $partes);
}

function formatear(array $p): array
{
    return [
        'id' => (int)$p['id_producto'],
        'codigo' => $p['codigo'],
        'nombre' => $p['nombre'],
        'marca' => $p['marca'],
        'imagen' => urlImagenProducto($p['imagen'] ?? null),
        'precio' => (float)$p['precio'],
        'stock' => (int)$p['stock'],
    ];
}

try {
    if ($codigo === '' && $q === '') {
        salir(400, ['ok' => false, 'mensaje' => 'Escribe un código o el nombre del producto.']);
    }

    // Búsqueda por nombre / marca / código parcial
    if ($codigo === '') {
        $stmt = $conexion->prepare(
            $base . " AND (p.nombre LIKE ? OR m.nombre LIKE ? OR p.codigo LIKE ?)
                      ORDER BY (COALESCE(i.stock,0) > 0) DESC, p.nombre LIMIT 8"
        );
        $like = '%' . $q . '%';
        $stmt->execute([$idSede, $like, $like, $like]);

        $resultados = [];
        foreach ($stmt->fetchAll() as $p) {
            $f = formatear($p);
            $f['otras'] = $f['stock'] <= 0 ? otrasTiendas($conexion, $f['id'], $idSede) : '';
            $resultados[] = $f;
        }
        salir(200, ['ok' => true, 'resultados' => $resultados]);
    }

    // Código exacto (lector de barras)
    $stmt = $conexion->prepare($base . " AND p.codigo = ? LIMIT 1");
    $stmt->execute([$idSede, $codigo]);
    $producto = $stmt->fetch();

    if (!$producto) {
        salir(404, ['ok' => false, 'mensaje' => 'No hay un producto activo con ese código.']);
    }

    if ((int)$producto['stock'] <= 0) {
        $otras = otrasTiendas($conexion, (int)$producto['id_producto'], $idSede);
        salir(409, [
            'ok' => false,
            'mensaje' => 'Sin stock en esta tienda.' . ($otras ? ' Hay en: ' . $otras . '.' : '')
        ]);
    }

    salir(200, ['ok' => true, 'producto' => formatear($producto)]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    salir(500, ['ok' => false, 'mensaje' => 'Error al consultar el producto.']);
}
