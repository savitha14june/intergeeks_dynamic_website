<?php

declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_NAME = 'buecherverwaltung';
const DB_USER = 'root';
const DB_PASS = '';


$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
//$dsn = 'mysql:host=' . DB_HOST . ';charset=utf8mb4';

$optionen = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try{
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $optionen);   

}catch (PDOException $e){
    exit('Datenbankverbinding fehgeschlagen: ' . $e->getMessage());
}

if (isset($_GET['mode']) && $_GET['mode'] === 'install') {

$stmt_01 = <<<SQL
CREATE DATABASE buecherverwaltung
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
SQL;

$stmt_02 = <<<SQL
USE buecherverwaltung;
SQL;

$stmt_03 = <<<SQL
CREATE TABLE buecher (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titel       VARCHAR(200)    NOT NULL,
    autor      VARCHAR(150)    NOT NULL,
    isbn        CHAR(13)        NULL,
    erscheinungsjahr    SMALLINT    UNSIGNED    NULL,
    seiten      SMALLINT UNSIGNED NULL,
    preis       DECIMAL(8,2)    NULL,
    beschreibung    TEXT    NULL,
    cover_datei VARCHAR(255) NULL,
    angelegt_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    geaendert_am DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_isbn (isbn),
    KEY idx_autor (autor)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE buch_bilder (
    id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    buch_id INT UNSIGNED NOT NULL,
    datei   VARCHAR(255) NOT NULL,
    position TINYINT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT fk_bild_buch FOREIGN KEY (buch_id)
    REFERENCES buecher(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SQL;

//$pdo->exec($stmt_01);
//   echo "Database created successfully.<br>";

$pdo->exec($stmt_02);
$pdo->exec($stmt_03);
die("Installation erfolgerish.");
}
?>
