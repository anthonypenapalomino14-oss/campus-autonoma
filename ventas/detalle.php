<?php
// Esta pantalla ahora vive en Facturación.
$id = (int)($_GET['id'] ?? 0);
header('Location: ../facturacion/detalle.php' . ($id > 0 ? '?id=' . $id : ''));
exit;
