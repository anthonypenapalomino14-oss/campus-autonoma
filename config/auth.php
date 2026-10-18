<?php
// Sesión, roles y sede activa de Market Donna
const BASE_URL = '/sistema_ventas';
date_default_timezone_set('America/Lima');

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

// A dónde va cada perfil al entrar
function rutaInicio(string $rol): string
{
    switch ($rol) {
        case 'admin':    return BASE_URL . '/index.php';
        case 'vendedor': return BASE_URL . '/ventas/';
        case 'soporte':  return BASE_URL . '/soporte/';
        default:         return BASE_URL . '/login.php';
    }
}

// Proteger páginas según el rol
function exigirRol(array $rolesPermitidos): void
{
    if (empty($_SESSION['usuario_id']) || empty($_SESSION['rol'])) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }

    if (!in_array($_SESSION['rol'], $rolesPermitidos, true)) {
        header('Location: ' . rutaInicio($_SESSION['rol']));
        exit;
    }
}

function rolActual(): string
{
    return (string)($_SESSION['rol'] ?? '');
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfValido(?string $token): bool
{
    return !empty($_SESSION['csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

// Lista de tiendas activas (son 2)
function listarSedes(PDO $conexion): array
{
    return $conexion->query(
        "SELECT id_sede, nombre, direccion, color
         FROM sedes WHERE activo = 1 ORDER BY id_sede"
    )->fetchAll();
}

// Sede con la que se está trabajando.
// Vendedor: la que tiene asignada. Admin/soporte: la que elijan arriba
// (0 = ver ambas tiendas en los listados que lo permiten).
function sedeActualId(PDO $conexion): int
{
    $rol = rolActual();

    if ($rol === 'vendedor') {
        if (empty($_SESSION['id_sede'])) {
            $stmt = $conexion->prepare(
                "SELECT id_sede FROM usuarios WHERE id_usuario = ?"
            );
            $stmt->execute([(int)$_SESSION['usuario_id']]);
            $_SESSION['id_sede'] = (int)($stmt->fetchColumn() ?: 1);
        }
        return (int)$_SESSION['id_sede'];
    }

    return (int)($_SESSION['id_sede'] ?? 1);
}
