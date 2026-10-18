<?php
// Configuración segura para Render + Aiven mediante variables de entorno.
$host = getenv('DB_HOST') ?: '';
$port = (int)(getenv('DB_PORT') ?: 3306);
$dbname = getenv('DB_NAME') ?: 'defaultdb';
$user = getenv('DB_USER') ?: '';
$password = getenv('DB_PASSWORD') ?: '';

$ca = __DIR__ . '/certificados/ca.pem';

if ($host === '' || $user === '' || $password === '') {
    error_log('Faltan variables de entorno de la base de datos (DB_HOST, DB_USER o DB_PASSWORD).');
    http_response_code(500);
    exit('La conexión con la base de datos no está configurada. Revisa las variables de entorno en Render.');
}

if (!is_file($ca)) {
    error_log('No se encontró el certificado CA de Aiven en config/certificados/ca.pem.');
    http_response_code(500);
    exit('Falta el certificado de seguridad de la base de datos.');
}

try {
    $conexion = new PDO(
        "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_SSL_CA => $ca,
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
            PDO::ATTR_TIMEOUT => 10
        ]
    );
} catch (PDOException $e) {
    error_log('Error de conexión MySQL: ' . $e->getMessage());
    http_response_code(500);
    exit('No se pudo conectar con la base de datos. Revisa las variables de entorno y los registros de Render.');
}
