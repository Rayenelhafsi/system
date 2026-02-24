<?php
require_once "../../config/auth.php";
require_once "../../config/db.php";

if (!isset($_GET['id'])) {
    header("Location: list.php");
    exit;
}

$id = (int)$_GET['id'];

/* =========================
   Stats pour sidebar
========================= */

$totalMatchesNonVus = (int)$pdo->query("
    SELECT COUNT(*) 
    FROM matches 
    WHERE vu = 0
")->fetchColumn();

$totalMatchesToFollow = (int)$pdo->query("
    SELECT COUNT(*) 
    FROM matches
    WHERE resultat_final IS NULL
      AND prochain_suivi_at IS NOT NULL
      AND prochain_suivi_at <= NOW()
")->fetchColumn();

/* =========================
   Récup demande
========================= */

// Récupérer la demande
$stmt = $pdo->prepare("SELECT * FROM clients_demandes WHERE id = ?");
$stmt->execute([$id]);
$demande = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$demande) {
    echo "Demande introuvable.";
    exit;
}

// Caractéristiques globales de la demande (noms uniquement, comme dans add/edit)
$demandeCaracs = json_decode($demande['caracteristiques'] ?? "[]", true);
if (!is_array($demandeCaracs)) {
    $demandeCaracs = [];
}

// Détails spécifiques (critères) depuis JSON
$details = json_decode($demande['details'] ?? "{}", true);
if (!is_array($details)) {
    $details = [];
}

// Récupérer les biens matchés pour cette demande
$stmt = $pdo->prepare("
    SELECT 
        m.id AS match_id,
        m.score,
        m.vu,
        m.created_at,
        b.id   AS bien_id,
        b.reference,
        b.titre,
        b.prix,
        b.type,
        b.ville,
        b.chambres,
        b.telephone_proprietaire,
        b.caracteristiques
    FROM matches m
    JOIN biens b ON m.bien_id = b.id
    WHERE m.demande_id = ?
    ORDER BY m.score DESC, m.created_at DESC
");
$stmt->execute([$id]);
$matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Détail Demande - DWIRA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --bg: #f1f4f9;
            --card-bg: #ffffff;
            --text: #111827;
            --sidebar: #111827;
        }

        .dark-mode {
            --bg: #0f172a;
            --card-bg: #1e293b;
            --text: #f1f5f9;
            --sidebar: #0f172a;
        }

        body {
            background: var(--bg);
            color: var(--text);
            transition: 0.3s ease;
            font-family: Arial, sans-serif;
        }

        .sidebar {
            height: 100vh;
            width: 250px;
            position: fixed;
            background: var(--sidebar);
            padding-top: 20px;
            transition: 0.3s;
            color: #e5e7eb;
        }

        .sidebar.collapsed {
            width: 80px;
        }

        .sidebar .brand {
            font-weight: 700;
            margin-bottom: 20px;
        }

        .sidebar a {
            display: block;
            color: #cbd5e1;
            padding: 8px 18px;
            text-decoration: none;
            transition: 0.3s;
            border-radius: 6px;
            font-size: 14px;
        }

        .sidebar a i {
            margin-right: 6px;
        }

        .sidebar a:hover {
            background: #2563eb;
            color: white;
        }

        .badge-sidebar {
            font-size: 11px;
            vertical-align: middle;
        }

        .main {
            margin-left: 250px;
            padding: 30px;
            transition: 0.3s;
        }

        .main.expanded {
            margin-left: 80px;
        }

        .card-modern {
            background: var(--card-bg);
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            border: none;
        }

        .badge-carac {
            background:#e5e7eb;
            color:#111827;
            font-size:11px;
            margin-right:3px;
        }

        .btn-rdv { font-size: 13px; font-weight: 600; }
        .details-table td { padding: 3px 6px; font-size: 13px; }
    </style>

<link rel='stylesheet' href='/Dwira/assets/css/admin-unified.css?v=202602248'>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar" id="sidebar">
    <div class="text-center text-white brand">🏠 DWIRA</div>

    <a href="../dashboard.php">
        <i class="bi bi-speedometer2"></i> Dashboard
    </a>

    <a href="../biens/list.php">
        <i class="bi bi-building"></i> Biens
    </a>

    <a href="list.php">
        <i class="bi bi-person-lines-fill"></i> Demandes
    </a>

    <a href="../matches/list.php">
        <i class="bi bi-link-45deg"></i> Matchs
        <?php if ($totalMatchesNonVus > 0): ?>
            <span class="badge bg-danger badge-sidebar ms-1"><?= $totalMatchesNonVus ?></span>
        <?php endif; ?>
    </a>

    <a href="../visites/list.php">
        <i class="bi bi-calendar-event"></i> Visites
    </a>

    <a href="../suivi_commercial/list.php">
        <i class="bi bi-telephone-outbound"></i> Suivi commercial
        <?php if ($totalMatchesToFollow > 0): ?>
            <span class="badge bg-warning text-dark badge-sidebar ms-1"><?= $totalMatchesToFollow ?></span>
        <?php endif; ?>
    </a>

    <a href="../caracteristiques/list.php">
        <i class="bi bi-star"></i> Caractéristiques
    </a>

    <hr class="bg-light">

    <a onclick="toggleTheme()" class="theme-toggle">
        <i class="bi bi-moon-stars-fill"></i> Mode sombre
    </a>

    <a href="../logout.php">
        <i class="bi bi-door-closed"></i> Logout
    </a>
</div>

<!-- MAIN -->
<div class="main" id="main">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>👤 Détail Demande #<?= (int)$demande['id'] ?></h2>
        <div class="d-flex">
            <button class="btn btn-outline-primary me-2" onclick="toggleSidebar()">
                <i class="bi bi-list"></i>
            </button>
            <a href="edit.php?id=<?= (int)$demande['id'] ?>" class="btn btn-warning me-2">
                <i class="bi bi-pencil"></i> Modifier
            </a>
            <a href="list.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Retour à la liste
            </a>
        </div>
    </div>

    <!-- Infos Demande -->
    <div class="card card-modern mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <span>Informations Client & Demande</span>
            <span class="badge bg-light text-dark">
                <?= htmlspecialchars($demande['type_bien'] ?? '') ?> • <?= htmlspecialchars($demande['statut'] ?? '') ?>
            </span>
        </div>
        <div class="card-body row">
            <div class="col-md-6">
                <p><strong>Nom :</strong> <?= htmlspecialchars($demande['nom'] ?? '') ?></p>
                <p><strong>Téléphone :</strong> 
                    <span class="badge bg-dark"><?= htmlspecialchars($demande['telephone'] ?? '') ?></span>
                </p>
                <p><strong>Budget max :</strong> 
                    <?php if(isset($demande['budget_max'])): ?>
                        <span class="badge bg-success">
                            <?= number_format((float)$demande['budget_max'], 0, ',', ' ') ?> DT
                        </span>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </p>
                <p><strong>Créée le :</strong> 
                    <?php
                        if (!empty($demande['created_at'])) {
                            echo date("d/m/Y H:i", strtotime($demande['created_at']));
                        } else {
                            echo "-";
                        }
                    ?>
                </p>
            </div>
            <div class="col-md-6">
                <p><strong>Type de bien :</strong> <?= htmlspecialchars($demande['type_bien'] ?? '') ?></p>
                <p><strong>Statut :</strong> <?= htmlspecialchars($demande['statut'] ?? '') ?></p>
                <p><strong>Ville :</strong> <?= htmlspecialchars($demande['ville'] ?? '') ?></p>
                <p><strong>Chambres min :</strong> <?= (int)($demande['chambres_min'] ?? 0) ?></p>
            </div>

            <div class="col-12 mt-3">
                <strong>Caractéristiques souhaitées :</strong><br>
                <?php if(count($demandeCaracs)): ?>
                    <?php foreach($demandeCaracs as $c): ?>
                        <span class="badge-carac"><?= htmlspecialchars($c) ?></span>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span class="text-muted">Aucune caractéristique spécifique.</span>
                <?php endif; ?>
            </div>

            <!-- Critères détaillés par type -->
            <?php if(!empty($details)): ?>
                <div class="col-12 mt-4">
                    <h6 class="fw-bold">Critères détaillés (<?= htmlspecialchars($demande['type_bien'] ?? '') ?>)</h6>

                    <?php if (($demande['type_bien'] ?? '') === 'Terrain'): ?>
                        <table class="details-table">
                            <tbody>
                                <?php if (!empty($details['surface_min_m2']) || !empty($details['surface_max_m2'])): ?>
                                    <tr>
                                        <td><strong>Surface souhaitée :</strong></td>
                                        <td>
                                            <?= htmlspecialchars($details['surface_min_m2'] ?? '') ?>
                                            <?php if(!empty($details['surface_min_m2']) || !empty($details['surface_max_m2'])): ?> m²<?php endif; ?>
                                             – 
                                            <?= htmlspecialchars($details['surface_max_m2'] ?? '') ?>
                                            <?php if(!empty($details['surface_max_m2'])): ?> m²<?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>

                                <?php if (!empty($details['facade_min_m'])): ?>
                                    <tr>
                                        <td><strong>Façade min :</strong></td>
                                        <td><?= htmlspecialchars($details['facade_min_m']) ?> m</td>
                                    </tr>
                                <?php endif; ?>

                                <?php if (!empty($details['type_terrain'])): ?>
                                    <tr>
                                        <td><strong>Type de terrain :</strong></td>
                                        <td><?= htmlspecialchars($details['type_terrain']) ?></td>
                                    </tr>
                                <?php endif; ?>

                                <?php if (!empty($details['zone_souhaitee'])): ?>
                                    <tr>
                                        <td><strong>Zone souhaitée :</strong></td>
                                        <td><?= htmlspecialchars($details['zone_souhaitee']) ?></td>
                                    </tr>
                                <?php endif; ?>

                                <?php if (!empty($details['distance_plage_max_m'])): ?>
                                    <tr>
                                        <td><strong>Distance plage max :</strong></td>
                                        <td><?= htmlspecialchars($details['distance_plage_max_m']) ?> m</td>
                                    </tr>
                                <?php endif; ?>

                                <?php if (!empty($details['type_rue'])): ?>
                                    <tr>
                                        <td><strong>Type de rue :</strong></td>
                                        <td><?= htmlspecialchars($details['type_rue']) ?></td>
                                    </tr>
                                <?php endif; ?>

                                <?php if (!empty($details['type_papier'])): ?>
                                    <tr>
                                        <td><strong>Type de papier :</strong></td>
                                        <td><?= htmlspecialchars($details['type_papier']) ?></td>
                                    </tr>
                                <?php endif; ?>

                                <?php
                                    $boolsTerrain = [
                                        'constructible_souhaite' => "Constructible",
                                        'coin_angle_souhaite'    => "Terrain d'angle",
                                    ];
                                ?>
                                <?php foreach($boolsTerrain as $k => $label): ?>
                                    <?php if(isset($details[$k])): ?>
                                        <tr>
                                            <td><strong><?= $label ?> :</strong></td>
                                            <td><?= $details[$k] ? 'Oui' : 'Non' ?></td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                    <?php elseif (($demande['type_bien'] ?? '') === 'Appartement'): ?>
                        <table class="details-table">
                            <tbody>
                                <?php if (!empty($details['surface_min_m2']) || !empty($details['surface_max_m2'])): ?>
                                    <tr>
                                        <td><strong>Superficie souhaitée :</strong></td>
                                        <td>
                                            <?= htmlspecialchars($details['surface_min_m2'] ?? '') ?>
                                            <?php if(!empty($details['surface_min_m2']) || !empty($details['surface_max_m2'])): ?> m²<?php endif; ?>
                                             – 
                                            <?= htmlspecialchars($details['surface_max_m2'] ?? '') ?>
                                            <?php if(!empty($details['surface_max_m2'])): ?> m²<?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>

                                <?php if (!empty($details['etage_min']) || !empty($details['etage_max'])): ?>
                                    <tr>
                                        <td><strong>Étage souhaité :</strong></td>
                                        <td>
                                            <?= htmlspecialchars($details['etage_min'] ?? '') ?> – 
                                            <?= htmlspecialchars($details['etage_max'] ?? '') ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>

                                <?php if (!empty($details['config_souhaitee'])): ?>
                                    <tr>
                                        <td><strong>Configuration :</strong></td>
                                        <td><?= htmlspecialchars($details['config_souhaitee']) ?></td>
                                    </tr>
                                <?php endif; ?>

                                <?php if (!empty($details['nb_sdb_min'])): ?>
                                    <tr>
                                        <td><strong>Nombre SDB min :</strong></td>
                                        <td><?= htmlspecialchars($details['nb_sdb_min']) ?></td>
                                    </tr>
                                <?php endif; ?>

                                <?php if (!empty($details['annee_min']) || !empty($details['annee_max'])): ?>
                                    <tr>
                                        <td><strong>Année construction :</strong></td>
                                        <td>
                                            <?= htmlspecialchars($details['annee_min'] ?? '') ?> – 
                                            <?= htmlspecialchars($details['annee_max'] ?? '') ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>

                                <?php if (!empty($details['type_rue'])): ?>
                                    <tr>
                                        <td><strong>Type de rue :</strong></td>
                                        <td><?= htmlspecialchars($details['type_rue']) ?></td>
                                    </tr>
                                <?php endif; ?>

                                <?php if (!empty($details['type_papier'])): ?>
                                    <tr>
                                        <td><strong>Type de papier :</strong></td>
                                        <td><?= htmlspecialchars($details['type_papier']) ?></td>
                                    </tr>
                                <?php endif; ?>

                                <?php if (!empty($details['distance_plage_max_m'])): ?>
                                    <tr>
                                        <td><strong>Distance plage max :</strong></td>
                                        <td><?= htmlspecialchars($details['distance_plage_max_m']) ?> m</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>

                    <?php elseif (($demande['type_bien'] ?? '') === 'Villa'): ?>
                        <table class="details-table">
                            <tbody>
                                <?php if (!empty($details['surface_terrain_min_m2']) || !empty($details['surface_terrain_max_m2'])): ?>
                                    <tr>
                                        <td><strong>Surface terrain :</strong></td>
                                        <td>
                                            <?= htmlspecialchars($details['surface_terrain_min_m2'] ?? '') ?> m² – 
                                            <?= htmlspecialchars($details['surface_terrain_max_m2'] ?? '') ?> m²
                                        </td>
                                    </tr>
                                <?php endif; ?>

                                <?php if (!empty($details['surface_couverte_min_m2']) || !empty($details['surface_couverte_max_m2'])): ?>
                                    <tr>
                                        <td><strong>Surface couverte :</strong></td>
                                        <td>
                                            <?= htmlspecialchars($details['surface_couverte_min_m2'] ?? '') ?> m² – 
                                            <?= htmlspecialchars($details['surface_couverte_max_m2'] ?? '') ?> m²
                                        </td>
                                    </tr>
                                <?php endif; ?>

                                <?php if (!empty($details['nb_chambres_min'])): ?>
                                    <tr>
                                        <td><strong>Chambres min :</strong></td>
                                        <td><?= htmlspecialchars($details['nb_chambres_min']) ?></td>
                                    </tr>
                                <?php endif; ?>

                                <?php if (!empty($details['nb_sdb_min'])): ?>
                                    <tr>
                                        <td><strong>SDB min :</strong></td>
                                        <td><?= htmlspecialchars($details['nb_sdb_min']) ?></td>
                                    </tr>
                                <?php endif; ?>

                                <?php if (!empty($details['nb_etages_min']) || !empty($details['nb_etages_max'])): ?>
                                    <tr>
                                        <td><strong>Étages souhaités :</strong></td>
                                        <td>
                                            <?= htmlspecialchars($details['nb_etages_min'] ?? '') ?> – 
                                            <?= htmlspecialchars($details['nb_etages_max'] ?? '') ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>

                                <?php if (!empty($details['annee_min']) || !empty($details['annee_max'])): ?>
                                    <tr>
                                        <td><strong>Année construction :</strong></td>
                                        <td>
                                            <?= htmlspecialchars($details['annee_min'] ?? '') ?> – 
                                            <?= htmlspecialchars($details['annee_max'] ?? '') ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>

                                <?php if (!empty($details['type_rue'])): ?>
                                    <tr>
                                        <td><strong>Type de rue :</strong></td>
                                        <td><?= htmlspecialchars($details['type_rue']) ?></td>
                                    </tr>
                                <?php endif; ?>

                                <?php if (!empty($details['type_papier'])): ?>
                                    <tr>
                                        <td><strong>Type de papier :</strong></td>
                                        <td><?= htmlspecialchars($details['type_papier']) ?></td>
                                    </tr>
                                <?php endif; ?>

                                <?php if (!empty($details['distance_plage_max_m'])): ?>
                                    <tr>
                                        <td><strong>Distance plage max :</strong></td>
                                        <td><?= htmlspecialchars($details['distance_plage_max_m']) ?> m</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>

                    <?php elseif (($demande['type_bien'] ?? '') === 'Local commercial'): ?>
                        <table class="details-table">
                            <tbody>
                                <?php if (!empty($details['surface_min_m2']) || !empty($details['surface_max_m2'])): ?>
                                    <tr>
                                        <td><strong>Surface souhaitée :</strong></td>
                                        <td>
                                            <?= htmlspecialchars($details['surface_min_m2'] ?? '') ?> m² – 
                                            <?= htmlspecialchars($details['surface_max_m2'] ?? '') ?> m²
                                        </td>
                                    </tr>
                                <?php endif; ?>

                                <?php if (!empty($details['facade_min_m'])): ?>
                                    <tr>
                                        <td><strong>Façade min :</strong></td>
                                        <td><?= htmlspecialchars($details['facade_min_m']) ?> m</td>
                                    </tr>
                                <?php endif; ?>

                                <?php if (!empty($details['hauteur_min_m']) || !empty($details['hauteur_max_m'])): ?>
                                    <tr>
                                        <td><strong>Hauteur :</strong></td>
                                        <td>
                                            <?= htmlspecialchars($details['hauteur_min_m'] ?? '') ?> m – 
                                            <?= htmlspecialchars($details['hauteur_max_m'] ?? '') ?> m
                                        </td>
                                    </tr>
                                <?php endif; ?>

                                <?php if (!empty($details['activite_souhaitee'])): ?>
                                    <tr>
                                        <td><strong>Activité souhaitée :</strong></td>
                                        <td><?= htmlspecialchars($details['activite_souhaitee']) ?></td>
                                    </tr>
                                <?php endif; ?>

                                <?php if (!empty($details['type_rue'])): ?>
                                    <tr>
                                        <td><strong>Type de rue :</strong></td>
                                        <td><?= htmlspecialchars($details['type_rue']) ?></td>
                                    </tr>
                                <?php endif; ?>

                                <?php if (!empty($details['type_papier'])): ?>
                                    <tr>
                                        <td><strong>Type de papier :</strong></td>
                                        <td><?= htmlspecialchars($details['type_papier']) ?></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>

                    <?php else: ?>
                        <!-- Pour Immeuble ou tout autre type (générique) -->
                        <table class="details-table">
                            <tbody>
                            <?php foreach($details as $k => $v): ?>
                                <?php
                                    if (is_bool($v))      $v = $v ? 'Oui' : 'Non';
                                    if ($v === null || $v === '' || $v === false) continue;
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars(str_replace('_',' ', ucfirst($k))) ?> :</strong></td>
                                    <td><?= htmlspecialchars((string)$v) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Biens matchés -->
    <div id="matches" class="card card-modern">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <span>🔗 Biens matchés avec cette demande</span>
            <span class="badge bg-light text-dark">
                <?= count($matches) ?> match(s)
            </span>
        </div>
        <div class="card-body">
            <?php if(!count($matches)): ?>
                <div class="alert alert-info mb-0">
                    Aucun bien ne correspond à cette demande pour le moment.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Réf</th>
                                <th>Titre</th>
                                <th>Prix</th>
                                <th>Type</th>
                                <th>Ville</th>
                                <th>Chambres</th>
                                <th>📞 Propriétaire</th>
                                <th>Score</th>
                                <th>Vu ?</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach($matches as $m): ?>
                            <?php
                                $bienCaracs = json_decode($m['caracteristiques'] ?? "[]", true);
                                if (!is_array($bienCaracs)) $bienCaracs = [];
                            ?>
                            <tr>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($m['reference'] ?? '') ?></span></td>
                                <td><?= htmlspecialchars($m['titre'] ?? '') ?></td>
                                <td>
                                    <?php if(isset($m['prix'])): ?>
                                        <span class="badge bg-success">
                                            <?= number_format((float)$m['prix'], 0, ',', ' ') ?> DT
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($m['type'] ?? '') ?></td>
                                <td><?= htmlspecialchars($m['ville'] ?? '') ?></td>
                                <td><?= (int)($m['chambres'] ?? 0) ?></td>
                                <td>
                                    <span class="badge bg-dark">
                                        <?= htmlspecialchars($m['telephone_proprietaire'] ?? '') ?>
                                    </span>
                                </td>
                                <td style="width:180px;">
                                    <?php $score = (float)($m['score'] ?? 0); ?>
                                    <div class="progress">
                                        <div 
                                            class="progress-bar 
                                                <?= $score >= 80 ? 'bg-success' : ($score >= 60 ? 'bg-warning' : 'bg-danger') ?>"
                                            role="progressbar"
                                            style="width: <?= $score ?>%">
                                            <?= $score ?>%
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if(!empty($m['vu'])): ?>
                                        <span class='badge bg-success'>Vu</span>
                                    <?php else: ?>
                                        <span class='badge bg-danger'>Non vu</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <!-- Voir match -->
                                    <a href="../matches/view.php?id=<?= (int)$m['match_id'] ?>" class="btn btn-sm btn-info mb-1">
                                        <i class="bi bi-eye"></i>
                                    </a>

                                    <!-- Créer RDV -->
                                    <a href="../visites/add.php?bien_id=<?= (int)$m['bien_id'] ?>&demande_id=<?= (int)$demande['id'] ?>&match_id=<?= (int)$m['match_id'] ?>"
                                       class="btn btn-sm btn-success btn-rdv mb-1">
                                        <i class="bi bi-calendar-plus"></i> RDV
                                    </a>
                                </td>
                            </tr>

                            <?php if(count($bienCaracs)): ?>
                                <tr>
                                    <td></td>
                                    <td colspan="9">
                                        <small class="text-muted">Caractéristiques du bien :</small><br>
                                        <?php foreach($bienCaracs as $c): ?>
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

<script>
function toggleTheme() {
    document.body.classList.toggle("dark-mode");

    if(document.body.classList.contains("dark-mode")) {
        localStorage.setItem("dwira-theme", "dark");
    } else {
        localStorage.setItem("dwira-theme", "light");
    }
}

if(localStorage.getItem("dwira-theme") === "dark") {
    document.body.classList.add("dark-mode");
}

function toggleSidebar() {
    document.getElementById("sidebar").classList.toggle("collapsed");
    document.getElementById("main").classList.toggle("expanded");
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script src='/Dwira/assets/js/admin-unified.js?v=202602247'></script>
</body>
</html>










