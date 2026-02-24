<?php
require_once "../../config/auth.php";
require_once "../../config/db.php";

// Filtres (même logique que list.php)
$where  = [];
$params = [];

$typeFilter   = $_GET['type']   ?? 'all';
$villeFilter  = $_GET['ville']  ?? 'all';
$statutFilter = $_GET['statut'] ?? 'all';
$minBudget    = $_GET['min_budget'] ?? '';
$maxBudget    = $_GET['max_budget'] ?? '';
$search       = trim($_GET['q'] ?? '');

if ($typeFilter !== 'all' && $typeFilter !== '') {
    $where[] = "type_bien = ?";
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
if ($minBudget !== '' && is_numeric($minBudget)) {
    $where[] = "budget_max >= ?";
    $params[] = (float)$minBudget;
}
if ($maxBudget !== '' && is_numeric($maxBudget)) {
    $where[] = "budget_max <= ?";
    $params[] = (float)$maxBudget;
}
if ($search !== '') {
    $where[] = "(nom LIKE ? OR telephone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql = "SELECT * FROM clients_demandes";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Envoi CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="demandes_export.csv"');

$output = fopen("php://output", "w");

// En-têtes
fputcsv($output, [
    "ID",
    "Nom",
    "Téléphone",
    "Budget max",
    "Type de bien",
    "Statut",
    "Ville",
    "Chambres min",
    "Caractéristiques",
    "Créée le"
]);

foreach ($demandes as $d) {
    fputcsv($output, [
        $d['id'],
        $d['nom'],
        $d['telephone'],
        $d['budget_max'],
        $d['type_bien'],
        $d['statut'],
        $d['ville'],
        $d['chambres_min'],
        $d['caracteristiques'],
        $d['created_at']
    ]);
}

fclose($output);
exit;
