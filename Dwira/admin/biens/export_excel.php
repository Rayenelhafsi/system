<?php
require_once "../../config/auth.php";
require_once "../../config/db.php";
require "../../vendor/autoload.php";

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Filtres (même logique)
$where = [];
$params = [];

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

// Création Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$headers = [
    "A1" => "ID",
    "B1" => "Référence",
    "C1" => "Titre",
    "D1" => "Téléphone propriétaire",
    "E1" => "Prix",
    "F1" => "Type",
    "G1" => "Statut",
    "H1" => "Ville",
    "I1" => "Chambres",
    "J1" => "Caractéristiques",
    "K1" => "Détails"
];

foreach ($headers as $cell => $value) {
    $sheet->setCellValue($cell, $value);
}

$row = 2;
foreach ($biens as $b) {
    $sheet->setCellValue("A$row", $b['id']);
    $sheet->setCellValue("B$row", $b['reference']);
    $sheet->setCellValue("C$row", $b['titre']);
    $sheet->setCellValue("D$row", $b['telephone_proprietaire']);
    $sheet->setCellValue("E$row", $b['prix']);
    $sheet->setCellValue("F$row", $b['type']);
    $sheet->setCellValue("G$row", $b['statut']);
    $sheet->setCellValue("H$row", $b['ville']);
    $sheet->setCellValue("I$row", $b['chambres']);
    $sheet->setCellValue("J$row", $b['caracteristiques']);
    $sheet->setCellValue("K$row", $b['details']);

    $row++;
}

// Envoi au navigateur
$filename = "biens_export.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");

$writer = new Xlsx($spreadsheet);
$writer->save("php://output");
exit;
