<?php
declare(strict_types=1);

$root = realpath(__DIR__ . "/..");
if ($root === false) {
    http_response_code(500);
    exit("Server configuration error.");
}

$requestedPath = $_GET["path"] ?? "index.php";
$requestedPath = ltrim(rawurldecode(str_replace("\\", "/", $requestedPath)), "/");

if ($requestedPath === "") {
    $requestedPath = "index.php";
}

if (str_contains($requestedPath, "..") || str_contains($requestedPath, "\0")) {
    http_response_code(400);
    exit("Invalid path.");
}

$target = realpath($root . DIRECTORY_SEPARATOR . $requestedPath);

if ($target === false || !is_file($target) || strncmp($target, $root, strlen($root)) !== 0) {
    http_response_code(404);
    exit("Not found.");
}

if (strtolower(pathinfo($target, PATHINFO_EXTENSION)) !== "php") {
    http_response_code(403);
    exit("Forbidden.");
}

require $target;
