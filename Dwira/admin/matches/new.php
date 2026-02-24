<?php
require_once "../../config/auth.php";
require_once "../../config/db.php";

$matches = $pdo->query("
    SELECT m.id, b.titre AS bien_titre, d.nom AS client_nom, m.score
    FROM matches m
    JOIN biens b ON m.bien_id = b.id
    JOIN clients_demandes d ON m.demande_id = d.id
    WHERE m.vu = 0
    ORDER BY m.created_at DESC
")->fetchAll();
?>

<div class="container mt-4">
    <h2>Nouveaux Matchs</h2>

    <table class="table table-striped table-bordered">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Bien</th>
                <th>Client</th>
                <th>Score</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($matches as $match): ?>
            <tr>
                <td><?= $match['id'] ?></td>
                <td><?= $match['bien_titre'] ?></td>
                <td><?= $match['client_nom'] ?></td>
                <td><?= $match['score'] ?>%</td>
                <td>
                    <a href="mark_seen.php?id=<?= $match['id'] ?>" class="btn btn-sm btn-success">✔️ Vu</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

