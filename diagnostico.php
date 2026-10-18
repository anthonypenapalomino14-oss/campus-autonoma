<?php
echo '<pre>';
echo 'PHP: ' . PHP_VERSION . PHP_EOL;
echo 'PDO disponible: ' . (extension_loaded('pdo') ? 'Sí' : 'No') . PHP_EOL;
echo 'PDO MySQL disponible: ' . (extension_loaded('pdo_mysql') ? 'Sí' : 'No') . PHP_EOL;
echo 'OpenSSL disponible: ' . (extension_loaded('openssl') ? 'Sí' : 'No') . PHP_EOL;
echo '</pre>';