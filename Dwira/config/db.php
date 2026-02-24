<?php
$host = getenv("DB_HOST") ?: getenv("MYSQL_HOST") ?: "localhost";
$port = (int) (getenv("DB_PORT") ?: getenv("MYSQL_PORT") ?: 3307);
$dbname = getenv("DB_NAME") ?: getenv("MYSQL_DATABASE") ?: "dwira_db";
$user = getenv("DB_USER") ?: getenv("MYSQL_USER") ?: "root";
$pass = getenv("DB_PASSWORD");

if ($pass === false) {
    $pass = getenv("MYSQL_PASSWORD");
}
if ($pass === false) {
    $pass = "";
}

$databaseUrl = getenv("DATABASE_URL");
if ($databaseUrl) {
    $parts = parse_url($databaseUrl);
    if (is_array($parts) && isset($parts["host"])) {
        $host = $parts["host"];
        $port = isset($parts["port"]) ? (int) $parts["port"] : $port;
        $dbname = isset($parts["path"]) ? ltrim($parts["path"], "/") : $dbname;
        $user = isset($parts["user"]) ? urldecode($parts["user"]) : $user;
        $pass = isset($parts["pass"]) ? urldecode($parts["pass"]) : $pass;
    }
}

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    if (getenv("APP_ENV") === "production") {
        http_response_code(500);
        die("Database connection failed.");
    }
    die("Erreur DB : " . $e->getMessage());
}
