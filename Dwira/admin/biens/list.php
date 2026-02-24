<?php
require_once "../../config/auth.php";
require_once "../../config/db.php";

/* =========================
   0) Compteurs sidebar
========================= */

// Nouveaux matchs non vus
$totalMatchesNonVus = (int)$pdo->query("
    SELECT COUNT(*) 
    FROM matches 
    WHERE vu = 0
")->fetchColumn();

// Matchs à relancer (suivi commercial)
$totalMatchesToFollow = (int)$pdo->query("
    SELECT COUNT(*) 
    FROM matches
    WHERE resultat_final IS NULL
      AND prochain_suivi_at IS NOT NULL
      AND prochain_suivi_at <= NOW()
")->fetchColumn();

/* =========================
   1) Filtres avancés
========================= */

// mêmes villes que dans add.php
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
    $where[]   = "type = ?";
    $params[]  = $typeFilter;
}

// Ville
$villeFilter = $_GET['ville'] ?? 'all';
if ($villeFilter !== 'all' && $villeFilter !== '') {
    $where[]   = "ville = ?";
    $params[]  = $villeFilter;
}

// Statut Vente/Location
$statutFilter = $_GET['statut'] ?? 'all';
if ($statutFilter !== 'all' && $statutFilter !== '') {
    $where[]   = "statut = ?";
    $params[]  = $statutFilter;
}

// Prix min/max
$minPrix = $_GET['min_prix'] ?? '';
$maxPrix = $_GET['max_prix'] ?? '';

if ($minPrix !== '' && is_numeric($minPrix)) {
    $where[]   = "prix >= ?";
    $params[]  = (float)$minPrix;
}
if ($maxPrix !== '' && is_numeric($maxPrix)) {
    $where[]   = "prix <= ?";
    $params[]  = (float)$maxPrix;
}

// Recherche texte (réf, titre, tél proprio)
$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $where[]  = "(reference LIKE ? OR titre LIKE ? OR telephone_proprietaire LIKE ?)";
    $like     = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

// Tri
$sort    = $_GET['sort'] ?? 'recent';
$orderBy = "created_at DESC";

if ($sort === 'prix_asc')  $orderBy = "prix ASC";
if ($sort === 'prix_desc') $orderBy = "prix DESC";
if ($sort === 'ville')     $orderBy = "ville ASC";
if ($sort === 'type')      $orderBy = "type ASC";

$sql = "
    SELECT 
        b.*,
        (
            SELECT COUNT(*) 
            FROM matches m 
            WHERE m.bien_id = b.id
        ) AS nb_matches
    FROM biens b
";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY $orderBy";

$stmt  = $pdo->prepare($sql);
$stmt->execute($params);
$biens = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalBiens = count($biens);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Liste des Biens - DWIRA</title>
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
    background: var(--page-bg);
    font-family: Arial, sans-serif;
}

/* SIDEBAR */
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

/* MAIN */
.main {
    margin-left: 260px;
    padding: 20px 20px 40px;
}

/* CARDS / TABLE */
.card-modern {
    background: var(--card-bg);
    border-radius: 14px;
    border: none;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
}

.table-modern thead {
    background: #2563eb;
    color: #ffffff;
}

.table-modern thead th {
    border-bottom: none;
}

.badge-ref {
    background:#e5e7eb;
    color:#111827;
}

.badge-statut {
    font-size: 11px;
    padding: 4px 8px;
}

.small-muted {
    font-size: 12px;
    color: #64748b;
}

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

<!-- SIDEBAR -->
<div class="sidebar">
    <h3>🏠 DWIRA</h3>

    <a href="../dashboard.php">
        <i class="bi bi-speedometer2"></i> Dashboard
    </a>

    <a href="list.php">
        <i class="bi bi-building"></i> Biens
    </a>

    <a href="../demandes/list.php">
        <i class="bi bi-person-lines-fill"></i> Demandes
    </a>

    <a href="../matches/list.php">
        <i class="bi bi-link-45deg"></i> Matchs
        <?php if ($totalMatchesNonVus > 0): ?>
            <span class="badge bg-danger ms-1"><?= $totalMatchesNonVus ?></span>
        <?php endif; ?>
    </a>

    <a href="../visites/list.php">
        <i class="bi bi-calendar-event"></i> Visites
    </a>

    <a href="../suivi_commercial/list.php">
        <i class="bi bi-telephone-outbound"></i> Suivi commercial
        <?php if ($totalMatchesToFollow > 0): ?>
            <span class="badge bg-warning text-dark ms-1"><?= $totalMatchesToFollow ?></span>
        <?php endif; ?>
    </a>

    <a href="../caracteristiques/list.php">
        <i class="bi bi-star"></i> Caractéristiques
    </a>

    <hr style="border-color:#334155;">

    <a href="../logout.php">
        <i class="bi bi-door-closed"></i> Logout
    </a>
</div>

<!-- MAIN -->
<div class="main" id="main">

    <!-- Title + actions -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold mb-0">🏠 Liste des Biens</h2>
            <div class="small-muted">
                Gestion de ton stock de biens (filtre par type, ville, statut, prix…)
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <!-- Export -->
            <a href="export_excel.php?<?= http_build_query($_GET) ?>" class="btn btn-success btn-sm">
                <i class="bi bi-file-earmark-excel"></i> Excel
            </a>
            <a href="export_csv.php?<?= http_build_query($_GET) ?>" class="btn btn-outline-success btn-sm">
                <i class="bi bi-filetype-csv"></i> CSV
            </a>

            <a href="add.php" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle"></i> Ajouter un bien
            </a>
        </div>
    </div>

    <!-- Résumé -->
    <div class="alert alert-light border d-flex justify-content-between align-items-center">
        <div>
            <strong><?= $totalBiens ?></strong> bien(s) trouvé(s)
            <?php if($typeFilter !== 'all' || $villeFilter !== 'all' || $statutFilter !== 'all' || $search || $minPrix || $maxPrix): ?>
                <span class="text-muted"> – filtres actifs</span>
            <?php endif; ?>
        </div>
        <?php if($typeFilter !== 'all' || $villeFilter !== 'all' || $statutFilter !== 'all' || $search || $minPrix || $maxPrix): ?>
            <a href="list.php" class="btn btn-sm btn-outline-secondary">Réinitialiser</a>
        <?php endif; ?>
    </div>

    <!-- Filtres avancés -->
    <div class="card card-modern p-3 mb-3">
        <form method="GET" class="row g-2 align-items-end">

            <div class="col-md-2">
                <label class="form-label">Type</label>
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
                <label class="form-label">Prix min</label>
                <input type="number" name="min_prix" class="form-control" value="<?= htmlspecialchars($minPrix) ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label">Prix max</label>
                <input type="number" name="max_prix" class="form-control" value="<?= htmlspecialchars($maxPrix) ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label">Tri</label>
                <select name="sort" class="form-select">
                    <option value="recent"      <?= $sort === 'recent' ? 'selected' : '' ?>>Plus récents</option>
                    <option value="prix_asc"    <?= $sort === 'prix_asc' ? 'selected' : '' ?>>Prix ↑</option>
                    <option value="prix_desc"   <?= $sort === 'prix_desc' ? 'selected' : '' ?>>Prix ↓</option>
                    <option value="ville"       <?= $sort === 'ville' ? 'selected' : '' ?>>Ville A→Z</option>
                    <option value="type"        <?= $sort === 'type' ? 'selected' : '' ?>>Type A→Z</option>
                </select>
            </div>

            <div class="col-md-4 mt-2">
                <label class="form-label">Recherche (réf / titre / tél)</label>
                <input type="text" name="q" class="form-control"
                       placeholder="Ex : Ref001, duplex, 52080695..."
                       value="<?= htmlspecialchars($search) ?>">
            </div>

            <div class="col-md-2 mt-2">
                <button class="btn btn-primary w-100">
                    <i class="bi bi-funnel"></i> Filtrer
                </button>
            </div>
        </form>
    </div>

    <!-- TABLEAU -->
    <div class="card card-modern p-3">
        <div class="table-responsive">
            <table class="table align-middle mb-0 table-modern">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Réf</th>
                        <th>Titre</th>
                        <th>Prix</th>
                        <th>Type</th>
                        <th>Statut</th>
                        <th>Ville</th>
                        <th>Ch.</th>
                        <th>📞 Propriétaire</th>
                        <th>Matchs</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($biens as $bien): ?>
                    <tr>
                        <td><?= (int)$bien['id'] ?></td>
                        <td>
                            <span class="badge badge-ref">
                                <?= htmlspecialchars($bien['reference']) ?>
                            </span>
                        </td>
                        <td>
                            <?= htmlspecialchars($bien['titre']) ?><br>
                            <span class="small-muted">
                                Ajouté le <?= date("d/m/Y", strtotime($bien['created_at'])) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-success">
                                <?= number_format($bien['prix'], 0, ',', ' ') ?> DT
                            </span>
                        </td>
                        <td><?= htmlspecialchars($bien['type']) ?></td>
                        <td>
                            <?php
                                $stat = $bien['statut'];
                                $cls  = 'bg-secondary';
                                if ($stat === 'Vente')    $cls = 'bg-primary';
                                if ($stat === 'Location') $cls = 'bg-info text-dark';
                            ?>
                            <span class="badge badge-statut <?= $cls ?>">
                                <?= htmlspecialchars($stat) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($bien['ville']) ?></td>
                        <td><?= (int)$bien['chambres'] ?></td>

                        <td>
                            <?php if(!empty($bien['telephone_proprietaire'])): ?>
                                <span class="badge bg-dark">
                                    <?= htmlspecialchars($bien['telephone_proprietaire']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>

                        <!-- 🔗 nombre de demandes matchées -->
                        <td>
                            <?php if((int)$bien['nb_matches'] > 0): ?>
                                <a href="view.php?id=<?= (int)$bien['id'] ?>#matches"
                                   class="badge bg-primary text-decoration-none">
                                    <?= (int)$bien['nb_matches'] ?> match(s)
                                </a>
                            <?php else: ?>
                                <span class="badge bg-secondary">0</span>
                            <?php endif; ?>
                        </td>

                        <td class="text-center">
                            <a href="view.php?id=<?= (int)$bien['id'] ?>" class="btn btn-sm btn-info mb-1">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="edit.php?id=<?= (int)$bien['id'] ?>" class="btn btn-sm btn-warning mb-1">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <a href="duplicate.php?id=<?= (int)$bien['id'] ?>" 
                               class="btn btn-sm btn-secondary mb-1"
                               onclick="return confirm('Dupliquer ce bien ?');">
                                <i class="bi bi-files"></i>
                            </a>
                            <a href="delete.php?id=<?= (int)$bien['id'] ?>" 
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Supprimer ce bien ?');">
                                <i class="bi bi-trash"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if(!$totalBiens): ?>
                    <tr>
                        <td colspan="11" class="text-center text-muted py-3">
                            Aucun bien trouvé avec ces critères.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script src='/Dwira/assets/js/admin-unified.js?v=202602247'></script>
</body>
</html>










