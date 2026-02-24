<?php
require_once "../../config/auth.php";
require_once "../../config/db.php";

if (!isset($_GET['id'])) {
    header("Location: list.php");
    exit;
}

$id = (int)$_GET['id'];

// Récupération bien original
$stmt = $pdo->prepare("SELECT * FROM biens WHERE id = ?");
$stmt->execute([$id]);
$bien = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bien) {
    echo "Bien introuvable.";
    exit;
}

// Nouvelle référence
$newRef = $bien['reference'] . "-COPY-" . rand(100,999);

// Duplication
$stmt = $pdo->prepare("
    INSERT INTO biens (reference, titre, telephone_proprietaire, prix, type, statut, ville, chambres, caracteristiques, details)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->execute([
    $newRef,
    $bien['titre'],
    $bien['telephone_proprietaire'],
    $bien['prix'],
    $bien['type'],
    $bien['statut'],
    $bien['ville'],
    $bien['chambres'],
    $bien['caracteristiques'],
    $bien['details']
]);

header("Location: list.php?dup=1");
exit;
