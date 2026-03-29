<?php
echo json_encode([
    'MYSQLHOST'      => getenv('MYSQLHOST'),
    'MYSQLDATABASE'  => getenv('MYSQLDATABASE'),
    'MYSQL_DATABASE' => getenv('MYSQL_DATABASE'),
    'MYSQLUSER'      => getenv('MYSQLUSER'),
    'MYSQLPORT'      => getenv('MYSQLPORT'),
    'MYSQLPASSWORD'  => getenv('MYSQLPASSWORD') ? 'SET' : 'NOT SET',
]);