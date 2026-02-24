<?php
require_once "../../config/auth.php";
require_once "../../config/db.php";
require "../../vendor/autoload.php";

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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

// Création Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$headers = [
    "A1" => "ID",
    "B1" => "Nom",
    "C1" => "Téléphone",
    "D1" => "Budget max",
    "E1" => "Type de bien",
    "F1" => "Statut",
    "G1" => "Ville",
    "H1" => "Chambres min",
    "I1" => "Caractéristiques",
    "J1" => "Créée le"
];

foreach ($headers as $cell => $value) {
    $sheet->setCellValue($cell, $value);
}

$row = 2;
foreach ($demandes as $d) {
    $sheet->setCellValue("A$row", $d['id']);
    $sheet->setCellValue("B$row", $d['nom']);
    $sheet->setCellValue("C$row", $d['telephone']);
    $sheet->setCellValue("D$row", $d['budget_max']);
    $sheet->setCellValue("E$row", $d['type_bien']);
    $sheet->setCellValue("F$row", $d['statut']);
    $sheet->setCellValue("G$row", $d['ville']);
    $sheet->setCellValue("H$row", $d['chambres_min']);
    $sheet->setCellValue("I$row", $d['caracteristiques']);
    $sheet->setCellValue("J$row", $d['created_at']);
    $row++;
}

// Envoi au navigateur
$filename = "demandes_export.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");

$writer = new Xlsx($spreadsheet);
$writer->save("php://output");
exit;
