<?php
define('DB_HOST', '*');
define('DB_USER', '*');
define('DB_PASS', '*');
define('DB_NAME', '*');

function getConnection(): PDO {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS Kuponi (
            id   INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Unikāls ieraksta identifikators',
            kods VARCHAR(10) NOT NULL             COMMENT 'Atlaižu kupona kods (piemēram, AABBBB)'
        ) COMMENT='Tabula saglabā ģenerētos atlaižu kuponu kodus'
    ");

    return $pdo;
}
