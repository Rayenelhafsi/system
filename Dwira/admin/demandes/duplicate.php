<?php
require_once "../../config/auth.php";
require_once "../../config/db.php";

if (!isset($_GET['id'])) {
    header("Location: list.php");
    exit;
}

$id = (int)$_GET['id'];

// Récupération de la demande originale
$stmt = $pdo->prepare("SELECT * FROM clients_demandes WHERE id = ?");
$stmt->execute([$id]);
$demande = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$demande) {
    echo "Demande introuvable.";
    exit;
}

// Nouveau nom (copie)
$newNom = $demande['nom'] . " (copie)";

// Duplication
$stmt = $pdo->prepare("
    INSERT INTO clients_demandes
    (nom, telephone, budget_max, type_bien, statut, ville, chambres_min, caracteristiques, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
");

$stmt->execute([
    $newNom,
    $demande['telephone'],
    $demande['budget_max'],
    $demande['type_bien'],
    $demande['statut'],
    $demande['ville'],
    $demande['chambres_min'],
    $demande['caracteristiques']
]);

header("Location: list.php?dup=1");
exit;
