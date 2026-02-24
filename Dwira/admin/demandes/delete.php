<?php
require_once "../../config/auth.php";
require_once "../../config/db.php";

if (!isset($_GET['id'])) {
    header("Location: list.php");
    exit;
}

$id = (int)$_GET['id'];

// Vérifier que la demande existe
$stmt = $pdo->prepare("SELECT id FROM clients_demandes WHERE id = ?");
$stmt->execute([$id]);

if (!$stmt->fetch()) {
    header("Location: list.php");
    exit;
}

// Supprimer la demande (les matchs liés seront supprimés grâce à ON DELETE CASCADE)
$delete = $pdo->prepare("DELETE FROM clients_demandes WHERE id = ?");
$delete->execute([$id]);

header("Location: list.php");
exit;
