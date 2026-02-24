<?php
require_once "../../config/auth.php";
require_once "../../config/db.php";

if (!isset($_GET['id'])) {
    header("Location: list.php");
    exit;
}

$id = (int) $_GET['id'];

// Vérifier que le bien existe
$stmt = $pdo->prepare("SELECT id FROM biens WHERE id = ?");
$stmt->execute([$id]);

if (!$stmt->fetch()) {
    header("Location: list.php");
    exit;
}

// Supprimer (les matchs liés seront supprimés grâce au ON DELETE CASCADE)
$delete = $pdo->prepare("DELETE FROM biens WHERE id = ?");
$delete->execute([$id]);

header("Location: list.php");
exit;
