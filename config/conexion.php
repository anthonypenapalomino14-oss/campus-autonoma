<?php

$host = 'mysql-2a123e6-anthonypenapalomino8-1777.f.aivencloud.com';
$port = 15349;
$dbname = 'defaultdb';
$user = 'avnadmin';
$password = 'AVNS_WxC6m0FthE1Gxbhil8w';

$ca = __DIR__ . '/certificados/ca.pem';
if (!file_exists($ca)) {
    exit('No se encuentra el certificado: ' . $ca);
}
try {
    $conexion = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
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
    error_log($e->getMessage());
    exit('Error de conexión: ' . htmlspecialchars($e->getMessage()));
}