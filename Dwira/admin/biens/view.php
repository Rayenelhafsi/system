<?php
require_once "../../config/auth.php";
require_once "../../config/db.php";

/* ============================
   1) Récupération du bien
============================ */

if (!isset($_GET['id'])) {
    header("Location: list.php");
    exit;
}
$id = (int)$_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM biens WHERE id = ?");
$stmt->execute([$id]);
$bien = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bien) {
    echo "Bien introuvable.";
    exit;
}

/* ============================
   2) Caractéristiques & Détails
============================ */

$bienCaracs = json_decode($bien['caracteristiques'] ?? "[]", true);
if (!is_array($bienCaracs)) $bienCaracs = [];

$details = json_decode($bien['details'] ?? "{}", true);
if (!is_array($details)) $details = [];

/* ============================
   3) Compteurs Sidebar
============================ */

$totalMatchesNonVus = (int)$pdo->query("SELECT COUNT(*) FROM matches WHERE vu = 0")->fetchColumn();

$totalMatchesToFollow = (int)$pdo->query("
    SELECT COUNT(*) 
    FROM matches
    WHERE resultat_final IS NULL
      AND prochain_suivi_at <= NOW()
      AND prochain_suivi_at IS NOT NULL
")->fetchColumn();

/* ============================
   4) Matches liés au bien
============================ */

$stmt = $pdo->prepare("
    SELECT 
        m.id AS match_id,
        m.score,
        m.vu,
        m.created_at,
        d.id AS demande_id,
        d.nom,
        d.telephone,
        d.budget_max,
        d.type_bien,
        d.ville,
        d.chambres_min,
        d.statut,
        d.caracteristiques
    FROM matches m
    JOIN clients_demandes d ON m.demande_id = d.id
    WHERE m.bien_id = ?
    ORDER BY m.score DESC, m.created_at DESC
");
$stmt->execute([$id]);
$matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Détail Bien - DWIRA</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body { background:#f1f4f9; font-family:Arial,sans-serif; }

        /* Sidebar */
        .sidebar {
            width: 250px;
            height: 100vh;
            background:#111827;
            color:white;
            padding:20px;
            position:fixed;
            top:0; left:0;
        }
        .sidebar h3 { font-size:22px; margin-bottom:25px; }
        .sidebar a {
            display:block; padding:8px 10px; color:#cbd5e1;
            text-decoration:none; border-radius:6px; margin-bottom:8px;
            transition:0.25s;
        }
        .sidebar a:hover {
            background:#2563eb; padding-left:16px; color:white;
        }

        /* Main */
        .main {
            margin-left:260px;
            padding:20px 20px 40px;
        }

        .card { border-radius:14px; box-shadow:0 4px 12px rgba(0,0,0,0.06); }

        .badge-carac { background:#e5e7eb; color:#111827; font-size:11px; margin-right:4px; }

        .details-table td { padding:3px 6px; font-size:13px; }

        @media(max-width:992px) {
            .sidebar { position:static; width:100%; height:auto; }
            .main { margin-left:0; }
        }
    </style>

<link rel='stylesheet' href='/Dwira/assets/css/admin-unified.css?v=202602248'>
</head>

<body>

<!-- ======================== SIDEBAR ======================== -->
<div class="sidebar">
    <h3>🏠 DWIRA</h3>

    <a href="../dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>

    <a href="list.php"><i class="bi bi-building"></i> Biens</a>

    <a href="../demandes/list.php"><i class="bi bi-person-lines-fill"></i> Demandes</a>

    <a href="../matches/list.php">
        <i class="bi bi-link-45deg"></i> Matchs
        <?php if ($totalMatchesNonVus > 0): ?>
            <span class="badge bg-danger ms-1"><?= $totalMatchesNonVus ?></span>
        <?php endif; ?>
    </a>

    <a href="../visites/list.php"><i class="bi bi-calendar-event"></i> Visites</a>

    <a href="../suivi_commercial/list.php">
        <i class="bi bi-telephone-outbound"></i> Suivi commercial
        <?php if ($totalMatchesToFollow > 0): ?>
            <span class="badge bg-warning text-dark ms-1"><?= $totalMatchesToFollow ?></span>
        <?php endif; ?>
    </a>

    <a href="../caracteristiques/list.php"><i class="bi bi-star"></i> Caractéristiques</a>

    <hr class="text-secondary">
    <a href="../logout.php"><i class="bi bi-door-closed"></i> Logout</a>
</div>

<!-- ======================== MAIN ======================== -->
<div class="main">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
        <h2 class="fw-bold">🏠 Détail Bien #<?= $bien['id'] ?></h2>

        <div>
            <a href="edit.php?id=<?= $bien['id'] ?>" class="btn btn-warning me-2">
                <i class="bi bi-pencil"></i> Modifier
            </a>
            <a href="list.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Retour
            </a>
        </div>
    </div>

    <!-- ======================== Infos Bien ======================== -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between">
            <span><i class="bi bi-info-circle"></i> Informations du bien</span>
            <span class="badge bg-light text-dark">
                <?= htmlspecialchars($bien['type']) ?> • <?= htmlspecialchars($bien['statut']) ?>
            </span>
        </div>

        <div class="card-body row">

            <div class="col-md-6">
                <p><strong>Référence :</strong>
                    <span class="badge bg-secondary"><?= htmlspecialchars($bien['reference']) ?></span>
                </p>

                <p><strong>Titre :</strong> <?= htmlspecialchars($bien['titre']) ?></p>

                <p><strong>Prix :</strong>
                    <span class="badge bg-success"><?= number_format($bien['prix']) ?> DT</span>
                </p>

                <p><strong>Ville :</strong> <?= htmlspecialchars($bien['ville']) ?></p>

                <p><strong>Chambres :</strong> <?= (int)$bien['chambres'] ?></p>
            </div>

            <div class="col-md-6">
                <p><strong>Téléphone propriétaire :</strong>
                    <span class="badge bg-dark"><?= htmlspecialchars($bien['telephone_proprietaire'] ?? '') ?></span>
                </p>

                <p><strong>Date création :</strong>
                    <?= isset($bien['created_at']) ? date("d/m/Y H:i", strtotime($bien['created_at'])) : '-' ?>
                </p>

                <p><strong>Caractéristiques globales :</strong><br>
                    <?php if(count($bienCaracs)): ?>
                        <?php foreach($bienCaracs as $c): ?>
                            <span class="badge-carac"><?= htmlspecialchars($c) ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="text-muted">Aucune caractéristique.</span>
                    <?php endif; ?>
                </p>
            </div>

            <!-- ======================== Détails spécifiques ======================== -->
            <?php if(!empty($details)): ?>
                <div class="col-12 mt-3">
                    <h6 class="fw-bold">Détails spécifiques (<?= htmlspecialchars($bien['type']) ?>)</h6>

                    <table class="details-table">
                        <tbody>
                        <?php foreach($details as $k => $v): ?>
                            <?php if ($v === null || $v === "") continue; ?>
                            <tr>
                                <td><strong><?= htmlspecialchars(str_replace('_',' ', ucfirst($k))) ?> :</strong></td>
                                <td><?= htmlspecialchars($v) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- ======================== MATCHES ======================== -->
    <div class="card" id="matches">
        <div class="card-header bg-dark text-white d-flex justify-content-between">
            <span>🔗 Demandes matchées</span>
            <span class="badge bg-light text-dark"><?= count($matches) ?> match(s)</span>
        </div>

        <div class="card-body">
            <?php if(!count($matches)): ?>
                <div class="alert alert-info">Aucun match pour ce bien pour l’instant.</div>
            <?php else: ?>

            <div class="table-responsive">
                <table class="table align-middle table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>Client</th>
                            <th>Téléphone</th>
                            <th>Budget</th>
                            <th>Type / Statut</th>
                            <th>Ville</th>
                            <th>Ch.</th>
                            <th>Score</th>
                            <th>Vu</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach($matches as $m): ?>
                        <?php
                            $demCaracs = json_decode($m['caracteristiques'], true);
                            if (!is_array($demCaracs)) $demCaracs = [];
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($m['nom']) ?></td>

                            <td><span class="badge bg-dark"><?= htmlspecialchars($m['telephone']) ?></span></td>

                            <td>
                                <span class="badge bg-success"><?= number_format($m['budget_max']) ?> DT</span>
                            </td>

                            <td><?= htmlspecialchars($m['type_bien']) ?> – <span class="text-muted"><?= $m['statut'] ?></span></td>

                            <td><?= htmlspecialchars($m['ville']) ?></td>

                            <td><?= (int)$m['chambres_min'] ?></td>

                            <td style="width:150px;">
                                <div class="progress">
                                    <div 
                                        class="progress-bar 
                                            <?= $m['score'] >= 80 ? 'bg-success' : ($m['score'] >= 60 ? 'bg-warning' : 'bg-danger') ?>"
                                        style="width: <?= $m['score'] ?>%">
                                        <?= $m['score'] ?>%
                                    </div>
                                </div>
                            </td>

                            <td>
                                <?= $m['vu'] 
                                    ? "<span class='badge bg-success'>Vu</span>" 
                                    : "<span class='badge bg-danger'>Non vu</span>" ?>
                            </td>

                            <td class="text-center">

                                <a href="../matches/view.php?id=<?= $m['match_id'] ?>"
                                   class="btn btn-sm btn-info mb-1">
                                    <i class="bi bi-eye"></i>
                                </a>

                                <a href="../visites/add.php?bien_id=<?= $bien['id'] ?>&demande_id=<?= $m['demande_id'] ?>&match_id=<?= $m['match_id'] ?>"
                                   class="btn btn-sm btn-primary mb-1">
                                    <i class="bi bi-calendar-plus"></i>
                                </a>

                            </td>
                        </tr>

                        <?php if(count($demCaracs)): ?>
                            <tr>
                                <td></td>
                                <td colspan="8">
                                    <small class="text-muted">Caractéristiques demandées :</small><br>
                                    <?php foreach($demCaracs as $c): ?>
                                        <span class="badge-carac"><?= htmlspecialchars($c) ?></span>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        <?php endif; ?>

                    <?php endforeach; ?>
                    </tbody>

                </table>
            </div>

            <?php endif; ?>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script src='/Dwira/assets/js/admin-unified.js?v=202602247'></script>
</body>
</html>










