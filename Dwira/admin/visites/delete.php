<?php
require_once "../../config/auth.php";
require_once "../../config/db.php";

if (!isset($_GET['id'])) {
    header("Location: list.php");
    exit;
}

$id = (int)$_GET['id'];

// Vérifier que la visite existe (optionnel mais propre)
$stmtCheck = $pdo->prepare("SELECT id FROM visites WHERE id = ?");
$stmtCheck->execute([$id]);
$visite = $stmtCheck->fetch(PDO::FETCH_ASSOC);

if ($visite) {
    $stmt = $pdo->prepare("DELETE FROM visites WHERE id = ?");
    $stmt->execute([$id]);
}

// Rediriger avec flag "deleted"
header("Location: list.php?deleted=1");
exit;
