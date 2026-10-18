<?php
require_once __DIR__ . '/conexion.php';

try {
    $tablas = $conexion->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    echo '<h2>Tablas encontradas en Aiven</h2>';

    foreach ($tablas as $tabla) {
        echo htmlspecialchars($tabla) . '<br>';
    }
} catch (PDOException $e) {
    echo 'No se pudo consultar la base de datos.';
}