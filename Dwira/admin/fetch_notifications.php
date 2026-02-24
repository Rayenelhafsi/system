<?php
require_once "../config/auth.php";
require_once "../config/db.php";

$stmt = $pdo->query("
    SELECT m.id, b.titre AS bien_titre, d.nom AS client_nom, m.score
    FROM matches m
    JOIN biens b ON m.bien_id = b.id
    JOIN clients_demandes d ON m.demande_id = d.id
    WHERE m.vu = 0
    ORDER BY m.created_at DESC
    LIMIT 10
");

$matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'count' => count($matches),
    'matches' => $matches
]);

