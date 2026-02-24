<?php
require_once "../../config/auth.php";
require_once "../../config/db.php";

// Même logique de filtres que list.php
$villes = [
    "Kelibia",
    "Manzel Tmim",
    "Hammem Ghzez",
    "Hammem Jabli",
    "Ezzahra Hammem Jabli",
    "Dar Allouche",
    "Karkouane",
    "Haouria",
    "Tamozrat",
    "Azmour"
];

$where = [];
$params = [];

// Reprise filtres
$typeFilter   = $_GET['type']   ?? 'all';
$villeFilter  = $_GET['ville']  ?? 'all';
$statutFilter = $_GET['statut'] ?? 'all';
$minPrix      = $_GET['min_prix'] ?? '';
$maxPrix      = $_GET['max_prix'] ?? '';
$search       = trim($_GET['q'] ?? '');

if ($typeFilter !== 'all' && $typeFilter !== '') {
    $where[] = "type = ?";
    $params[] = $typeFilter;
}
if ($villeFilter !== 'all' && $villeFilter !== '') {
    $where[] = "ville = ?";
    $params[] = $villeFilter;
}
if ($statutFilter !== 'all' && $statutFilter !== '') {
    $where[] = "statut = ?";
    $params[] = $statutFilter;
}
if ($minPrix !== '' && is_numeric($minPrix)) {
    $where[] = "prix >= ?";
    $params[] = (float)$minPrix;
}
if ($maxPrix !== '' && is_numeric($maxPrix)) {
    $where[] = "prix <= ?";
    $params[] = (float)$maxPrix;
}
if ($search !== '') {
    $where[] = "(titre LIKE ? OR reference LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql = "SELECT * FROM biens";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$biens = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Envoi CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="biens_export.csv"');

$output = fopen("php://output", "w");

// En-têtes
fputcsv($output, [
    "ID",
    "Référence",
    "Titre",
    "Téléphone propriétaire",
    "Prix",
    "Type",
    "Statut",
    "Ville",
    "Chambres",
    "Caractéristiques",
    "Détails"
]);

foreach ($biens as $b) {
    fputcsv($output, [
        $b['id'],
        $b['reference'],
        $b['titre'],
        $b['telephone_proprietaire'],
        $b['prix'],
        $b['type'],
        $b['statut'],
        $b['ville'],
        $b['chambres'],
        $b['caracteristiques'],
        $b['details']
    ]);
}

fclose($output);
exit;
