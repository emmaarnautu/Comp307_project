<?php

$pdo = new PDO(
    "mysql:host=localhost;dbname=comp-307-db;charset=utf8mb4",
    "cs307-user",
    "uDNUffZKnwAkfQ4wPeaV",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);