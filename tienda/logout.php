
<?php
session_name('VENTAPRO_CLIENTE');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Limpiar los datos de la sesión del cliente.
$_SESSION = [];

// Eliminar la cookie de sesión, si se está utilizando.
if (ini_get('session.use_cookies')) {
    $parametros = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $parametros['path'],
        $parametros['domain'],
        $parametros['secure'],
        $parametros['httponly']
    );
}

// Destruir la sesión.
session_destroy();

// Regresar a la página principal de la tienda.
header('Location: index.php');
exit;
