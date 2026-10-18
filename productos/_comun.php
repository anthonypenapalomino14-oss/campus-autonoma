<?php
// Utilidades compartidas de productos: marcas y validación.

function obtenerOCrearMarca(PDO $conexion, string $nombre): ?int
{
    $nombre = trim(preg_replace('/\s+/', ' ', $nombre));
    if ($nombre === '') {
        return null;
    }

    $stmt = $conexion->prepare("SELECT id_marca FROM marcas WHERE nombre = ? LIMIT 1");
    $stmt->execute([$nombre]);
    $id = $stmt->fetchColumn();

    if ($id) {
        return (int)$id;
    }

    $conexion->prepare("INSERT INTO marcas (nombre) VALUES (?)")->execute([$nombre]);
    return (int)$conexion->lastInsertId();
}

function listaMarcas(PDO $conexion): array
{
    return $conexion->query("SELECT id_marca, nombre FROM marcas ORDER BY nombre")->fetchAll();
}

function entero($valor, int $min = 0, int $max = 1000000): ?int
{
    $n = filter_var($valor, FILTER_VALIDATE_INT);
    if ($n === false || $n < $min || $n > $max) {
        return null;
    }
    return (int)$n;
}
