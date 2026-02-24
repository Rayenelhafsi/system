<?php
$host = "sql211.infinityfree.com";
$port = 3306;
$dbname = "if0_41093564_dwira_db";
$user = "if0_41093564";
$pass = "zjPKeAyYuJHRHA";

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Erreur DB : " . $e->getMessage());
}
