<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/conexion.php';
exigirRol(['admin', 'soporte']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValido($_POST['csrf_token'] ?? '')) {
    $id = (int)($_POST['id_sede'] ?? 0);

    $stmt = $conexion->prepare("SELECT COUNT(*) FROM sedes WHERE id_sede = ? AND activo = 1");
    $stmt->execute([$id]);

    if ($stmt->fetchColumn()) {
        $_SESSION['id_sede'] = $id;
    }
}

// Volver solo a páginas internas del sistema
$volver = (string)($_POST['volver'] ?? '');
if (strpos($volver, BASE_URL . '/') !== 0 || strpos($volver, '//') !== false) {
    $volver = rutaInicio(rolActual());
}

header('Location: ' . $volver);
exit;
