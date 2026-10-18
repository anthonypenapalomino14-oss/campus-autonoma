<?php
// Estructura común del panel interno: menú lateral, saludo y cierre.
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/inventario.php';

if (!function_exists('h')) {
    function h($valor): string
    {
        return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
    }
}

function soles($valor): string
{
    return 'S/ ' . number_format((float)$valor, 2);
}

function icono(string $nombre, int $tam = 18): string
{
    $trazos = [
        'home'    => '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/>',
        'cart'    => '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.5 12h11L21 7H6"/>',
        'box'     => '<path d="M21 8l-9-5-9 5v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/>',
        'layers'  => '<path d="M12 3l9 5-9 5-9-5z"/><path d="M3 13l9 5 9-5"/>',
        'receipt' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/>',
        'chart'   => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'users'   => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.6-3.5 3-5.5 6.5-5.5s5.9 2 6.5 5.5"/><path d="M16 4.5a3.5 3.5 0 010 7M18 14.8c1.8.7 3 2.5 3.5 5.2"/>',
        'life'    => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3.5"/><path d="M5.6 5.6l3.9 3.9M14.5 14.5l3.9 3.9M18.4 5.6l-3.9 3.9M9.5 14.5l-3.9 3.9"/>',
        'team'    => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/>',
        'logout'  => '<path d="M9 4H5v16h4M16 8l4 4-4 4M20 12H9"/>',
        'store'   => '<path d="M4 9l1.5-5h13L20 9"/><path d="M4 9a2.7 2.7 0 005.3 0 2.7 2.7 0 005.4 0A2.7 2.7 0 0020 9M5 12v8h14v-8"/>',
        'search'  => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
        'plus'    => '<path d="M12 5v14M5 12h14"/>',
        'alert'   => '<path d="M12 3l10 18H2z"/><path d="M12 10v5M12 18v.01"/>',
        'swap'    => '<path d="M4 8h14l-3-3M20 16H6l3 3"/>',
        'menu'    => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'check'   => '<path d="M5 12l5 5 9-10"/>',
        'print'   => '<path d="M7 9V3h10v6M7 17H4v-7h16v7h-3M7 14h10v7H7z"/>',
        'edit'    => '<path d="M4 20h4L19 9l-4-4L4 16z"/>',
        'arrow'   => '<path d="M5 12h14M13 6l6 6-6 6"/>',
    ];

    return '<svg class="ico" width="' . $tam . '" height="' . $tam .
        '" viewBox="0 0 24 24" fill="none" stroke="currentColor" ' .
        'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" ' .
        'aria-hidden="true">' . ($trazos[$nombre] ?? '') . '</svg>';
}

// Avatar de producto: color estable según la marca
function avatarProducto(?string $marca, string $nombre, int $tam = 40): string
{
    $base = trim((string)$marca) !== '' ? $marca : $nombre;
    $tono = hexdec(substr(md5(mb_strtolower($base)), 0, 4)) % 360;
    $letras = mb_strtoupper(mb_substr(preg_replace('/[^\p{L}\p{N}]/u', '', $base), 0, 2));

    return '<span class="avatar-prod" style="width:' . $tam . 'px;height:' . $tam .
        'px;background:hsl(' . $tono . ',60%,92%);color:hsl(' . $tono . ',45%,28%)">' .
        h($letras) . '</span>';
}

function chipSede(array $sede): string
{
    return '<span class="chip-sede"><i style="background:' . h($sede['color']) .
        '"></i>' . h($sede['nombre']) . '</span>';
}

function pillStock(int $stock, int $minimo): string
{
    $e = estadoStock($stock, $minimo);
    return '<span class="pill ' . $e['clase'] . '">' . $e['texto'] . '</span>';
}

function menuPorRol(string $rol): array
{
    $b = BASE_URL;
    $caja = ['caja', 'Caja registradora', $b . '/ventas/', 'cart'];
    $inv = ['inventario', 'Inventario', $b . '/inventario/', 'layers'];
    $fac = ['facturacion', 'Facturación', $b . '/facturacion/', 'receipt'];
    $cli = ['clientes', 'Clientes', $b . '/clientes/', 'users'];
    $sop = ['soporte', 'Soporte', $b . '/soporte/', 'life'];

    switch ($rol) {
        case 'admin':
            return [
                'Principal' => [['panel', 'Panel', $b . '/index.php', 'home'], $caja],
                'Almacén' => [
                    ['productos', 'Productos', $b . '/productos/', 'box'],
                    $inv,
                ],
                'Administración' => [
                    $fac,
                    ['reportes', 'Reporte por producto', $b . '/reportes/', 'chart'],
                    $cli,
                    $sop,
                    ['usuarios', 'Equipo', $b . '/usuarios/', 'team'],
                ],
            ];
        case 'vendedor':
            return [
                'Mi turno' => [$caja, $inv, $fac],
            ];
        case 'soporte':
            return [
                'Atención' => [$sop, $cli, $fac, $inv],
            ];
    }

    return [];
}

function etiquetaRol(string $rol): string
{
    return [
        'admin' => 'Administración',
        'vendedor' => 'Ventas',
        'soporte' => 'Soporte',
    ][$rol] ?? ucfirst($rol);
}

function saludo(): string
{
    $hora = (int)date('G');
    if ($hora < 12) return 'Buenos días';
    if ($hora < 19) return 'Buenas tardes';
    return 'Buenas noches';
}

/**
 * Abre la página. Opciones: eyebrow, descripcion, acciones (HTML),
 * ancho ('angosto'), cabeza (HTML extra para <head>).
 */
function layout_inicio(PDO $conexion, string $titulo, string $activo, array $op = []): void
{
    $rol = rolActual();
    $nombre = trim(($_SESSION['nombre'] ?? '') . ' ' . ($_SESSION['apellidos'] ?? ''));
    $primerNombre = explode(' ', trim((string)($_SESSION['nombre'] ?? 'equipo')))[0];
    $iniciales = mb_strtoupper(
        mb_substr((string)($_SESSION['nombre'] ?? 'U'), 0, 1) .
        mb_substr((string)($_SESSION['apellidos'] ?? ''), 0, 1)
    );

    $sedes = listarSedes($conexion);
    $idSede = sedeActualId($conexion);
    $sedeActual = $sedes[0] ?? ['id_sede' => 1, 'nombre' => 'Tienda', 'color' => '#0f766e'];
    foreach ($sedes as $s) {
        if ((int)$s['id_sede'] === $idSede) {
            $sedeActual = $s;
        }
    }

    $menu = menuPorRol($rol);
    $rutaActual = $_SERVER['REQUEST_URI'] ?? '';
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($titulo) ?> · Market Donna</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/estilos.css">
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/icono.png">
    <?= $op['cabeza'] ?? '' ?>
</head>
<body>
<div class="app">
    <aside class="lateral" id="lateral">
        <a class="logo" href="<?= h(rutaInicio($rol)) ?>">
            <span class="logo-texto">Market <b>Donna</b></span>
        </a>

        <nav class="menu">
            <?php foreach ($menu as $grupo => $items): ?>
                <p class="menu-grupo"><?= h($grupo) ?></p>
                <?php foreach ($items as $it): ?>
                    <a href="<?= h($it[2]) ?>"
                       class="<?= $it[0] === $activo ? 'activo' : '' ?>">
                        <?= icono($it[3]) ?><span><?= h($it[1]) ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>

        <div class="usuario">
            <span class="avatar"><?= h($iniciales) ?></span>
            <div class="usuario-datos">
                <strong><?= h($nombre ?: 'Usuario') ?></strong>
                <small><?= h(etiquetaRol($rol)) ?></small>
            </div>
            <form action="<?= BASE_URL ?>/logout.php" method="POST">
                <button type="submit" class="icono-btn" title="Cerrar sesión"
                        aria-label="Cerrar sesión"><?= icono('logout') ?></button>
            </form>
        </div>
    </aside>

    <div class="principal">
        <header class="barra">
            <button type="button" class="icono-btn menu-movil" id="abrir-menu"
                    aria-label="Abrir menú"><?= icono('menu', 22) ?></button>
            <p class="saludo"><?= saludo() ?>, <strong><?= h($primerNombre) ?></strong></p>

            <?php if ($rol === 'vendedor'): ?>
                <span class="sede-fija" title="Tu tienda asignada">
                    <?= icono('store', 16) ?> <?= chipSede($sedeActual) ?>
                </span>
            <?php else: ?>
                <form class="selector-sede" method="POST"
                      action="<?= BASE_URL ?>/config/cambiar_sede.php">
                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                    <input type="hidden" name="volver" value="<?= h($rutaActual) ?>">
                    <label for="sede-sel"><?= icono('store', 16) ?> Trabajando en</label>
                    <select id="sede-sel" name="id_sede" onchange="this.form.submit()">
                        <?php foreach ($sedes as $s): ?>
                            <option value="<?= (int)$s['id_sede'] ?>"
                                <?= (int)$s['id_sede'] === $idSede ? 'selected' : '' ?>>
                                <?= h($s['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            <?php endif; ?>
        </header>

        <main class="contenido <?= h($op['ancho'] ?? '') ?>">
            <div class="cabecera-pagina">
                <div>
                    <?php if (!empty($op['eyebrow'])): ?>
                        <p class="eyebrow"><?= h($op['eyebrow']) ?></p>
                    <?php endif; ?>
                    <h1><?= h($titulo) ?></h1>
                    <?php if (!empty($op['descripcion'])): ?>
                        <p class="bajada"><?= h($op['descripcion']) ?></p>
                    <?php endif; ?>
                </div>
                <?php if (!empty($op['acciones'])): ?>
                    <div class="acciones"><?= $op['acciones'] ?></div>
                <?php endif; ?>
            </div>
    <?php
}

function layout_fin(string $scripts = ''): void
{
    ?>
        </main>
    </div>
</div>
<script>
(function () {
    var b = document.getElementById('abrir-menu');
    var l = document.getElementById('lateral');
    if (b && l) {
        b.addEventListener('click', function () { l.classList.toggle('abierto'); });
        document.addEventListener('click', function (e) {
            if (l.classList.contains('abierto') && !l.contains(e.target) && !b.contains(e.target)) {
                l.classList.remove('abierto');
            }
        });
    }
})();
</script>
<?= $scripts ?>
</body>
</html>
    <?php
}

function avisoFlash(?string $ok, ?string $error): string
{
    $html = '';
    if ($ok) {
        $html .= '<div class="aviso ok" role="status">' . icono('check', 18) . '<span>' . h($ok) . '</span></div>';
    }
    if ($error) {
        $html .= '<div class="aviso mal" role="alert">' . icono('alert', 18) . '<span>' . h($error) . '</span></div>';
    }
    return $html;
}
