
<?php
require_once __DIR__ . '/../config/auth.php';
exigirRol(['admin', 'vendedor']);
require_once __DIR__ . '/../config/conexion.php';

header('Content-Type: application/json; charset=utf-8');

$dni = trim($_GET['dni'] ?? '');

if (!preg_match('/^\d{8}$/', $dni)) {
    echo json_encode([
        'ok' => false,
        'mensaje' => 'Ingresa un DNI de 8 dígitos.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $conexion->prepare(
    "SELECT nombres, apellidos
     FROM usuarios
     WHERE dni = ? AND rol = 'cliente'
     LIMIT 1"
);

$stmt->execute([$dni]);
$cliente = $stmt->fetch();

if ($cliente) {
    echo json_encode([
        'ok' => true,
        'encontrado' => true,
        'nombre' => trim(
            $cliente['nombres'] . ' ' . $cliente['apellidos']
        )
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'ok' => true,
        'encontrado' => false,
        'nombre' => '',
        'mensaje' => 'Cliente nuevo. Ingresa sus nombres y apellidos.'
    ], JSON_UNESCAPED_UNICODE);
}
