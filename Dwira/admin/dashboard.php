<?php
require_once "../config/auth.php";
require_once "../config/db.php";

/* ============================
   1) STATS GLOBALES
   ============================ */

// Total biens
$totalBiens = (int)$pdo->query("SELECT COUNT(*) FROM biens")->fetchColumn();

// Total demandes
$totalDemandes = (int)$pdo->query("SELECT COUNT(*) FROM clients_demandes")->fetchColumn();

// Visites à venir (à partir d'aujourd'hui)
$totalVisitesAVenir = (int)$pdo->query("
    SELECT COUNT(*) 
    FROM visites 
    WHERE date_visite >= NOW()
")->fetchColumn();

// Nouveaux matchs non vus
$totalMatchesNonVus = (int)$pdo->query("
    SELECT COUNT(*) 
    FROM matches 
    WHERE vu = 0
")->fetchColumn();

// Matchs à relancer (suivi commercial) : en cours + date de suivi arrivée
$totalMatchesToFollow = (int)$pdo->query("
    SELECT COUNT(*) 
    FROM matches
    WHERE resultat_final IS NULL
      AND prochain_suivi_at IS NOT NULL
      AND prochain_suivi_at <= NOW()
")->fetchColumn();

/* ============================
   2) VISITES DU JOUR
   ============================ */

$stmt = $pdo->prepare("
    SELECT 
        v.*,
        b.reference,
        b.titre AS bien_titre,
        d.nom AS client_nom,
        d.telephone AS client_tel
    FROM visites v
    LEFT JOIN biens b ON v.bien_id = b.id
    LEFT JOIN clients_demandes d ON v.demande_id = d.id
    WHERE DATE(v.date_visite) = CURDATE()
    ORDER BY v.date_visite ASC
    LIMIT 10
");
$stmt->execute();
$visitesToday = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ============================
   3) NOTIFICATIONS INTERNES
   ============================ */

$notifications = [];

// a) Nouveaux matchs non vus
if ($totalMatchesNonVus > 0) {
    $notifications[] = [
        'type'    => 'match',
        'message' => $totalMatchesNonVus . " nouveau(x) match(s) à traiter",
        'url'     => "./matches/list.php?filter=new"
    ];
}

// b) Matchs à relancer (suivi commercial)
if ($totalMatchesToFollow > 0) {
    $notifications[] = [
        'type'    => 'follow',
        'message' => $totalMatchesToFollow . " match(s) à relancer (suivi commercial)",
        'url'     => "./suivi_commercial/list.php?filter=to_follow"
    ];
}

// c) Visites prévues aujourd'hui
$countVisitesPrevuesJour = (int)$pdo->query("
    SELECT COUNT(*) 
    FROM visites 
    WHERE DATE(date_visite) = CURDATE()
      AND statut = 'Prévue'
")->fetchColumn();

if ($countVisitesPrevuesJour > 0) {
    $notifications[] = [
        'type'    => 'visite',
        'message' => $countVisitesPrevuesJour . " visite(s) prévue(s) aujourd'hui",
        'url'     => "./visites/list.php?statut=Prévue"
    ];
}

// d) Nouvelles demandes aujourd'hui
$countDemandesJour = (int)$pdo->query("
    SELECT COUNT(*) 
    FROM clients_demandes
    WHERE DATE(created_at) = CURDATE()
")->fetchColumn();

if ($countDemandesJour > 0) {
    $notifications[] = [
        'type'    => 'demande',
        'message' => $countDemandesJour . " nouvelle(s) demande(s) client aujourd'hui",
        'url'     => "./demandes/list.php"
    ];
}

// e) Nouveaux biens ajoutés aujourd'hui
$countBiensJour = (int)$pdo->query("
    SELECT COUNT(*) 
    FROM biens
    WHERE DATE(created_at) = CURDATE()
")->fetchColumn();

if ($countBiensJour > 0) {
    $notifications[] = [
        'type'    => 'bien',
        'message' => $countBiensJour . " nouveau(x) bien(s) ajouté(s) aujourd'hui",
        'url'     => "./biens/list.php"
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - DWIRA</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        :root {
            --sidebar-bg: #111827;
            --sidebar-link: #cbd5e1;
            --sidebar-link-hover-bg: #2563eb;
            --sidebar-link-hover-text: #ffffff;
            --page-bg: #f1f4f9;
            --card-bg: #ffffff;
        }

        body {
            font-family: Arial, sans-serif;
            background: var(--page-bg);
        }

        .sidebar {
            width: 250px;
            height: 100vh;
            background: var(--sidebar-bg);
            color: #fff;
            padding: 20px;
            position: fixed;
            top: 0;
            left: 0;
        }

        .sidebar h3 {
            font-size: 22px;
            margin-bottom: 25px;
        }

        .sidebar a {
            color: var(--sidebar-link);
            display: block;
            margin-bottom: 10px;
            text-decoration: none;
            padding: 8px 10px;
            border-radius: 6px;
            font-size: 14px;
            transition: 0.25s;
        }

        .sidebar a i {
            margin-right: 6px;
        }

        .sidebar a:hover {
            background: var(--sidebar-link-hover-bg);
            color: var(--sidebar-link-hover-text);
            padding-left: 16px;
        }

        .main {
            margin-left: 260px;
            padding: 20px 20px 40px;
        }

        .card {
            border-radius: 14px;
            border: none;
            background: var(--card-bg);
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
        }

        .stat-card-icon {
            font-size: 30px;
            opacity: 0.85;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
        }

        .small-muted {
            font-size: 12px;
            color: #64748b;
        }

        /* Mobile / tablette */
        @media (max-width: 991.98px) {
            .sidebar {
                position: static;
                width: 100%;
                height: auto;
            }
            .main {
                margin-left: 0;
                padding-top: 10px;
            }
        }
    </style>

<link rel='stylesheet' href='/Dwira/assets/css/admin-unified.css?v=202602248'>
</head>
<body>

<div class="sidebar">
    <h3>🏠 DWIRA</h3>

    <a href="./dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>

    <a href="./biens/list.php">
        <i class="bi bi-building"></i> Biens
        <?php if ($countBiensJour > 0): ?>
            <span class="badge bg-success ms-1"><?= $countBiensJour ?></span>
        <?php endif; ?>
    </a>

    <a href="./demandes/list.php">
        <i class="bi bi-person-lines-fill"></i> Demandes
        <?php if ($countDemandesJour > 0): ?>
            <span class="badge bg-info text-dark ms-1"><?= $countDemandesJour ?></span>
        <?php endif; ?>
    </a>

    <a href="./matches/list.php">
        <i class="bi bi-link-45deg"></i> Matchs
        <?php if ($totalMatchesNonVus > 0): ?>
            <span class="badge bg-danger ms-1"><?= $totalMatchesNonVus ?></span>
        <?php endif; ?>
    </a>

    <a href="./visites/list.php">
        <i class="bi bi-calendar-event"></i> Visites
        <?php if ($totalVisitesAVenir > 0): ?>
            <span class="badge bg-warning text-dark ms-1"><?= $totalVisitesAVenir ?></span>
        <?php endif; ?>
    </a>

    <a href="./suivi_commercial/list.php">
        <i class="bi bi-telephone-outbound"></i> Suivi commercial
        <?php if ($totalMatchesToFollow > 0): ?>
            <span class="badge bg-warning text-dark ms-1"><?= $totalMatchesToFollow ?></span>
        <?php endif; ?>
    </a>

    <hr style="border-color: #334155;">

    <a href="./logout.php"><i class="bi bi-door-closed"></i> Logout</a>
</div>

<div class="main">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="mb-0">📊 Dashboard</h2>
            <div class="small-muted">Vue globale de l’activité DWIRA (biens, demandes, visites, suivi commercial)</div>
        </div>
        <div class="d-flex gap-2">
            <a href="./demandes/add.php" class="btn btn-sm btn-primary">
                <i class="bi bi-person-plus"></i> Nouvelle demande
            </a>
            <a href="./biens/add.php" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-building-add"></i> Nouveau bien
            </a>
        </div>
    </div>

    <!-- STATS RAPIDES -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card p-3 d-flex flex-row align-items-center">
                <div class="flex-grow-1">
                    <div class="text-muted small">Biens</div>
                    <div class="fs-4 fw-bold"><?= $totalBiens ?></div>
                    <?php if ($countBiensJour > 0): ?>
                        <div class="small-muted"><?= $countBiensJour ?> ajouté(s) aujourd’hui</div>
                    <?php endif; ?>
                </div>
                <div class="stat-card-icon text-primary">
                    <i class="bi bi-building"></i>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card p-3 d-flex flex-row align-items-center">
                <div class="flex-grow-1">
                    <div class="text-muted small">Demandes clients</div>
                    <div class="fs-4 fw-bold"><?= $totalDemandes ?></div>
                    <?php if ($countDemandesJour > 0): ?>
                        <div class="small-muted"><?= $countDemandesJour ?> aujourd’hui</div>
                    <?php endif; ?>
                </div>
                <div class="stat-card-icon text-success">
                    <i class="bi bi-people"></i>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card p-3 d-flex flex-row align-items-center">
                <div class="flex-grow-1">
                    <div class="text-muted small">Visites à venir</div>
                    <div class="fs-4 fw-bold"><?= $totalVisitesAVenir ?></div>
                    <?php if ($countVisitesPrevuesJour > 0): ?>
                        <div class="small-muted"><?= $countVisitesPrevuesJour ?> prévue(s) aujourd’hui</div>
                    <?php endif; ?>
                </div>
                <div class="stat-card-icon text-warning">
                    <i class="bi bi-calendar-event"></i>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card p-3 d-flex flex-row align-items-center">
                <div class="flex-grow-1">
                    <div class="text-muted small">Matchs / Suivi</div>
                    <div class="fs-4 fw-bold"><?= $totalMatchesNonVus ?></div>
                    <div class="small-muted">
                        <?php if ($totalMatchesToFollow > 0): ?>
                            <?= $totalMatchesToFollow ?> à relancer
                        <?php else: ?>
                            Aucun suivi urgent
                        <?php endif; ?>
                    </div>
                </div>
                <div class="stat-card-icon text-danger">
                    <i class="bi bi-bell"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- NOTIFICATIONS INTERNES -->
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <span class="section-title text-white">🔔 Notifications internes</span>
                    <span class="badge bg-light text-dark"><?= count($notifications) ?></span>
                </div>
                <div class="card-body p-0">
                    <?php if (!count($notifications)): ?>
                        <div class="p-3 text-muted">
                            Aucune notification pour le moment. 👌
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach($notifications as $n): ?>
                                <?php
                                    $icon = "bi-info-circle";
                                    $badgeClass = "bg-secondary";
                                    if ($n['type'] === 'match')   { $icon = "bi-link-45deg";           $badgeClass = "bg-danger";             }
                                    if ($n['type'] === 'follow')  { $icon = "bi-telephone-outbound";   $badgeClass = "bg-warning text-dark";  }
                                    if ($n['type'] === 'visite')  { $icon = "bi-calendar-event";       $badgeClass = "bg-primary";            }
                                    if ($n['type'] === 'demande') { $icon = "bi-person-plus";          $badgeClass = "bg-success";            }
                                    if ($n['type'] === 'bien')    { $icon = "bi-building-add";         $badgeClass = "bg-warning text-dark";  }
                                ?>
                                <a href="<?= htmlspecialchars($n['url']) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <span>
                                        <i class="bi <?= $icon ?> me-2"></i>
                                        <?= htmlspecialchars($n['message']) ?>
                                    </span>
                                    <span class="badge <?= $badgeClass ?>">Voir</span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- VISITES DU JOUR -->
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <span class="section-title text-white">📅 Visites du jour</span>
                    <a href="./visites/list.php" class="btn btn-sm btn-light">
                        Voir toutes les visites
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if (!count($visitesToday)): ?>
                        <div class="p-3 text-muted">
                            Aucune visite prévue aujourd'hui.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="width:90px;">Heure</th>
                                        <th>Bien</th>
                                        <th>Client</th>
                                        <th>📞 Client</th>
                                        <th style="width:120px;">Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach($visitesToday as $v): ?>
                                    <tr>
                                        <td><?= date("H:i", strtotime($v['date_visite'])) ?></td>
                                        <td>
                                            <?php if($v['bien_id'] && $v['bien_titre']): ?>
                                                <a href="./biens/view.php?id=<?= (int)$v['bien_id'] ?>">
                                                    <?php if(!empty($v['reference'])): ?>
                                                        <strong><?= htmlspecialchars($v['reference']) ?></strong> – 
                                                    <?php endif; ?>
                                                    <?= htmlspecialchars($v['bien_titre']) ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= $v['client_nom']
                                                ? htmlspecialchars($v['client_nom'])
                                                : "<span class='text-muted'>—</span>" ?>
                                        </td>
                                        <td>
                                            <?php if($v['client_tel']): ?>
                                                <span class="badge bg-dark"><?= htmlspecialchars($v['client_tel']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                                $badge = 'bg-secondary';
                                                if ($v['statut'] === 'Prévue')   $badge = 'bg-primary';
                                                if ($v['statut'] === 'Réalisée') $badge = 'bg-success';
                                                if ($v['statut'] === 'Annulée')  $badge = 'bg-danger';
                                                if ($v['statut'] === 'No show')  $badge = 'bg-warning text-dark';
                                            ?>
                                            <span class="badge <?= $badge ?>"><?= htmlspecialchars($v['statut']) ?></span>
                                        </td>
                                    </tr>
                                    <?php if (!empty($v['lieu']) || !empty($v['note'])): ?>
                                        <tr>
                                            <td></td>
                                            <td colspan="4">
                                                <?php if (!empty($v['lieu'])): ?>
                                                    <small class="text-muted">Lieu :</small>
                                                    <span><?= htmlspecialchars($v['lieu']) ?></span><br>
                                                <?php endif; ?>
                                                <?php if (!empty($v['note'])): ?>
                                                    <small class="text-muted">Note :</small>
                                                    <div><?= nl2br(htmlspecialchars($v['note'])) ?></div>
                                                <?php endif; ?>
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

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script src='/Dwira/assets/js/admin-unified.js?v=202602247'></script>
</body>
</html>










