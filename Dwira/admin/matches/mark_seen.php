<?php
require_once "../../config/auth.php";
require_once "../../config/db.php";

if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("UPDATE matches SET vu = 1 WHERE id = ?");
    $stmt->execute([$_GET['id']]);
}

header("Location: list.php");
exit;

