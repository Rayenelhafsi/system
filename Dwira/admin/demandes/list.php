<?php
require_once "../../config/auth.php";
require_once "../../config/db.php";

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
   Filtres avancés
========================= */

// Même liste de villes que pour les biens
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

$where  = [];
$params = [];

// Type de bien
$typeFilter = $_GET['type'] ?? 'all';
if ($typeFilter !== 'all' && $typeFilter !== '') {
    $where[] = "type_bien = ?";
    $params[] = $typeFilter;
}

// Ville
$villeFilter = $_GET['ville'] ?? 'all';
if ($villeFilter !== 'all' && $villeFilter !== '') {
    $where[] = "ville = ?";
    $params[] = $villeFilter;
}

// Statut (Vente / Location)
$statutFilter = $_GET['statut'] ?? 'all';
if ($statutFilter !== 'all' && $statutFilter !== '') {
    $where[] = "statut = ?";
    $params[] = $statutFilter;
}

// Budget min / max
$minBudget = $_GET['min_budget'] ?? '';
$maxBudget = $_GET['max_budget'] ?? '';

if ($minBudget !== '' && is_numeric($minBudget)) {
    $where[] = "budget_max >= ?";
    $params[] = (float)$minBudget;
}

if ($maxBudget !== '' && is_numeric($maxBudget)) {
    $where[] = "budget_max <= ?";
    $params[] = (float)$maxBudget;
}

// Recherche texte nom / téléphone
$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $where[] = "(nom LIKE ? OR telephone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Tri
$sort    = $_GET['sort'] ?? 'recent';
$orderBy = "created_at DESC";

if ($sort === 'budget_asc')  $orderBy = "budget_max ASC";
if ($sort === 'budget_desc') $orderBy = "budget_max DESC";
if ($sort === 'ville')       $orderBy = "ville ASC";

$sql = "
    SELECT d.*,
           (
               SELECT COUNT(*) 
               FROM matches m 
               WHERE m.demande_id = d.id
           ) AS nb_matches
    FROM clients_demandes d
";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY $orderBy";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalDemandes = count($demandes);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Liste des Demandes - DWIRA</title>
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

        .table-modern {
            border-radius: 12px;
            overflow: hidden;
        }

        .table-modern thead {
            background: #2563eb;
            color: white;
        }

        .theme-toggle {
            cursor: pointer;
        }

        .badge-carac {
            background:#e5e7eb;
            color:#111827;
            font-size:11px;
            margin-right:3px;
        }

        .badge-sidebar {
            font-size: 11px;
            vertical-align: middle;
        }
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
        <h2 class="fw-bold">👤 Liste des Demandes</h2>

        <div class="d-flex flex-wrap gap-2 justify-content-start justify-content-md-end">
            <!-- Export -->
            <a href="export_excel.php?<?= http_build_query($_GET) ?>" class="btn btn-success me-2">
                <i class="bi bi-file-earmark-excel"></i> Excel
            </a>
            <a href="export_csv.php?<?= http_build_query($_GET) ?>" class="btn btn-outline-success me-2">
                <i class="bi bi-filetype-csv"></i> CSV
            </a>

            <!-- Sidebar + Ajouter -->
            <button class="btn btn-outline-primary me-2" onclick="toggleSidebar()">
                <i class="bi bi-list"></i>
            </button>

            <a href="add.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Ajouter une demande
            </a>
        </div>
    </div>

    <!-- Résumé -->
    <div class="alert alert-light border d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <strong><?= $totalDemandes ?></strong> demande(s) trouvée(s)
            <?php if($typeFilter !== 'all' || $villeFilter !== 'all' || $statutFilter !== 'all' || $search || $minBudget || $maxBudget): ?>
                <span class="text-muted"> – filtres actifs</span>
            <?php endif; ?>
        </div>
        <?php if($typeFilter !== 'all' || $villeFilter !== 'all' || $statutFilter !== 'all' || $search || $minBudget || $maxBudget): ?>
            <a href="list.php" class="btn btn-sm btn-outline-secondary">Réinitialiser</a>
        <?php endif; ?>
    </div>

    <!-- Filtres avancés -->
    <div class="card card-modern p-3 mb-3">
        <form method="GET" class="row g-2 align-items-end">

            <div class="col-md-2">
                <label class="form-label">Type de bien</label>
                <select name="type" class="form-select">
                    <option value="all">Tous</option>
                    <?php
                    $types = ["Appartement","Villa","Terrain","Local commercial","Immeuble"];
                    foreach($types as $t): ?>
                        <option value="<?= $t ?>" <?= ($typeFilter === $t) ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label">Ville</label>
                <select name="ville" class="form-select">
                    <option value="all">Toutes</option>
                    <?php foreach($villes as $v): ?>
                        <option value="<?= htmlspecialchars($v) ?>" <?= ($villeFilter === $v) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($v) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label">Statut</label>
                <select name="statut" class="form-select">
                    <option value="all">Tous</option>
                    <option value="Vente"    <?= $statutFilter === 'Vente' ? 'selected' : '' ?>>Vente</option>
                    <option value="Location" <?= $statutFilter === 'Location' ? 'selected' : '' ?>>Location</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label">Budget min</label>
                <input type="number" name="min_budget" class="form-control" value="<?= htmlspecialchars($minBudget) ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label">Budget max</label>
                <input type="number" name="max_budget" class="form-control" value="<?= htmlspecialchars($maxBudget) ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label">Tri</label>
                <select name="sort" class="form-select">
                    <option value="recent"      <?= $sort === 'recent' ? 'selected' : '' ?>>Plus récents</option>
                    <option value="budget_asc"  <?= $sort === 'budget_asc' ? 'selected' : '' ?>>Budget ↑</option>
                    <option value="budget_desc" <?= $sort === 'budget_desc' ? 'selected' : '' ?>>Budget ↓</option>
                    <option value="ville"       <?= $sort === 'ville' ? 'selected' : '' ?>>Ville A→Z</option>
                </select>
            </div>

            <div class="col-md-4 mt-2">
                <label class="form-label">Recherche (nom / téléphone)</label>
                <input type="text" name="q" class="form-control" placeholder="Ex: Ahmed, 52080695..." value="<?= htmlspecialchars($search) ?>">
            </div>

            <div class="col-md-2 mt-2">
                <button class="btn btn-primary w-100">
                    <i class="bi bi-funnel"></i> Filtrer
                </button>
            </div>
        </form>
    </div>

    <!-- TABLEAU -->
    <div class="card card-modern p-4">

        <div class="table-responsive table-modern">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Nom</th>
                        <th>Téléphone</th>
                        <th>Budget max</th>
                        <th>Type</th>
                        <th>Statut</th>
                        <th>Ville</th>
                        <th>Ch. min</th>
                        <th>Matchs</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($demandes as $demande): ?>
                    <?php
                        $caracs = json_decode($demande['caracteristiques'] ?? "[]", true);
                        if (!is_array($caracs)) $caracs = [];

                        $createdAt = $demande['created_at'] ?? null;
                        $createdAtLabel = $createdAt ? date('d/m/Y', strtotime($createdAt)) : '-';
                    ?>
                    <tr>
                        <td><?= (int)$demande['id'] ?></td>
                        <td><small class="text-muted"><?= htmlspecialchars($createdAtLabel) ?></small></td>
                        <td><?= htmlspecialchars($demande['nom'] ?? '') ?></td>
                        <td><span class="badge bg-dark"><?= htmlspecialchars($demande['telephone'] ?? '') ?></span></td>
                        <td>
                            <?php if(isset($demande['budget_max'])): ?>
                                <span class="badge bg-success">
                                    <?= number_format((float)$demande['budget_max'], 0, ',', ' ') ?> DT
                                </span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($demande['type_bien'] ?? '') ?></td>
                        <td><?= htmlspecialchars($demande['statut'] ?? '') ?></td>
                        <td><?= htmlspecialchars($demande['ville'] ?? '') ?></td>
                        <td><?= (int)($demande['chambres_min'] ?? 0) ?></td>

                        <!-- 🔗 Badge Matchs -->
                        <td>
                            <?php if((int)($demande['nb_matches'] ?? 0) > 0): ?>
                                <a href="view.php?id=<?= (int)$demande['id'] ?>#matches" 
                                   class="badge bg-primary text-decoration-none">
                                    <?= (int)$demande['nb_matches'] ?> match(s)
                                </a>
                            <?php else: ?>
                                <span class="badge bg-secondary">0</span>
                            <?php endif; ?>
                        </td>

                        <td class="text-center">
                            <a href="view.php?id=<?= (int)$demande['id'] ?>" class="btn btn-sm btn-info mb-1">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="edit.php?id=<?= (int)$demande['id'] ?>" class="btn btn-sm btn-warning mb-1">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <a href="duplicate.php?id=<?= (int)$demande['id'] ?>" 
                               class="btn btn-sm btn-secondary mb-1"
                               onclick="return confirm('Dupliquer cette demande ?')">
                                <i class="bi bi-files"></i>
                            </a>
                            <a href="delete.php?id=<?= (int)$demande['id'] ?>" 
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Supprimer cette demande ?')">
                                <i class="bi bi-trash"></i>
                            </a>
                        </td>
                    </tr>

                    <!-- Ligne des caractéristiques demandées -->
                    <?php if(count($caracs)): ?>
                        <tr>
                            <td></td>
                            <td colspan="10">
                                <small class="text-muted">Caractéristiques demandées :</small><br>
                                <?php foreach($caracs as $c): ?>
                                    <span class="badge-carac"><?= htmlspecialchars($c) ?></span>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    <?php endif; ?>

                <?php endforeach; ?>
                </tbody>
            </table>
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










