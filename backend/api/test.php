<?php
echo json_encode([
    'MYSQLHOST'     => getenv('MYSQLHOST'),
    'MYSQLDATABASE' => getenv('MYSQLDATABASE'),
    'MYSQLUSER'     => getenv('MYSQLUSER'),
    'MYSQLPORT'     => getenv('MYSQLPORT'),
    'env'           => $_ENV,
    'server_keys'   => array_keys($_SERVER),
]);