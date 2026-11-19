
<?php

$pdo = new PDO(
    "mysql:host=localhost;dbname=comp-307-db;charset=utf8mb4",
    "cs307-user",
    trim(file_get_contents("/home/cs307-user/mysqlpass.txt")),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
