<?php
require_once "../../config/auth.php";
require_once "../../config/db.php";

/* ========================
   STATS POUR SIDEBAR
======================== */
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

/* ========================
   PARAMÈTRES & FILTRES
======================== */
$filter = $_GET['filter'] ?? 'all';
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 10;
$offset = ($page - 1) * $limit;

$whereParts = [];
$params = [];

// Filtre principal
switch ($filter) {
    case 'new':
        $whereParts[] = "m.vu = 0";
        break;
    case 'seen':
        $whereParts[] = "m.vu = 1";
        break;
    case 'to_follow':
        // à relancer : pas clôturé + prochain suivi dépassé ou aujourd'hui
        $whereParts[] = "m.resultat_final IS NULL";
        $whereParts[] = "m.prochain_suivi_at IS NOT NULL";
        $whereParts[] = "m.prochain_suivi_at <= NOW()";
        break;
    case 'won':
        $whereParts[] = "m.resultat_final = 'gagne'";
        break;
    case 'lost':
        $whereParts[] = "m.resultat_final = 'perdu'";
        break;
    case 'ignored':
        $whereParts[] = "m.resultat_final = 'ignore'";
        break;
    case 'cancelled':
        $whereParts[] = "m.resultat_final = 'annule_demande'";
        break;
    case 'all':
    default:
        // pas de condition
        break;
}

$whereSql = "";
if (!empty($whereParts)) {
    $whereSql = "WHERE " . implode(" AND ", $whereParts);
}

/* ========================
   TOTAL
======================== */
$stmtTotal = $pdo->prepare("
    SELECT COUNT(*)
    FROM matches m
    JOIN biens b ON m.bien_id = b.id
    JOIN clients_demandes d ON m.demande_id = d.id
    $whereSql
");
$stmtTotal->execute($params);
$total = (int)$stmtTotal->fetchColumn();
$totalPages = max(1, ceil($total / $limit));

/* ========================
   DATA
======================== */
$stmt = $pdo->prepare("
    SELECT 
        m.id,
        m.score,
        m.vu,
        m.created_at,
        m.statut,
        m.interet_client,
        m.resultat_final,
        m.prochain_suivi_at,
        b.titre      AS bien_titre,
        b.reference  AS bien_reference,
        b.ville      AS bien_ville,
        d.nom        AS client_nom,
        d.telephone  AS client_tel
    FROM matches m
    JOIN biens b ON m.bien_id = b.id
    JOIN clients_demandes d ON m.demande_id = d.id
    $whereSql
    ORDER BY m.created_at DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ========================
   Helpers affichage
======================== */
function badgeStatut($statut, $resultatFinal) {
    if ($resultatFinal === 'gagne') {
        return "<span class='badge bg-success'>Gagné</span>";
    }
    if ($resultatFinal === 'perdu') {
        return "<span class='badge bg-danger'>Perdu</span>";
    }
    if ($resultatFinal === 'ignore') {
        return "<span class='badge bg-secondary'>Ignoré</span>";
    }
    if ($resultatFinal === 'annule_demande') {
        return "<span class='badge bg-warning text-dark'>Demande annulée</span>";
    }

    switch ($statut) {
        case 'nouveau':
            return "<span class='badge bg-info text-dark'>Nouveau</span>";
        case 'visite_planifiee':
            return "<span class='badge bg-primary'>Visite planifiée</span>";
        case 'visite_realisee':
            return "<span class='badge bg-primary'>Visite réalisée</span>";
        case 'offre':
            return "<span class='badge bg-dark'>Offre en cours</span>";
        default:
            return "<span class='badge bg-light text-dark'>En cours</span>";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Matchs - DWIRA</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

<style>
:root {
    --bg:#f1f4f9;
    --card-bg:#ffffff;
    --sidebar:#111827;
    --text:#111827;
}

.dark-mode {
    --bg:#0f172a;
    --card-bg:#1e293b;
    --sidebar:#0f172a;
    --text:#f1f5f9;
}

body {
    background:var(--bg);
    color:var(--text);
    transition:.3s;
    font-family: Arial, sans-serif;
}

/* SIDEBAR */
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
    background:#2563eb;
    color:white;
}

.badge-sidebar {
    font-size:11px;
    vertical-align: middle;
}

/* MAIN */
.main {
    margin-left:250px;
    padding:30px;
    transition:0.3s;
}

.main.expanded {
    margin-left:80px;
}

/* CARDS & TABLE */
.card-modern {
    background:var(--card-bg);
    border-radius:18px;
    box-shadow:0 10px 25px rgba(0,0,0,.05);
    border:none;
}

.table thead {
    background:linear-gradient(45deg,#2563eb,#1e40af);
    color:white;
}

.progress {
    height:20px;
}

.badge-small {
    font-size:11px;
}

.filter-pill {
    margin-right:4px;
    margin-bottom:4px;
}
</style>

<link rel='stylesheet' href='/Dwira/assets/css/admin-unified.css?v=202602248'>
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar" id="sidebar">
    <div class="text-center text-white brand">🔗 DWIRA</div>

    <a href="../dashboard.php">
        <i class="bi bi-speedometer2"></i> Dashboard
    </a>

    <a href="../biens/list.php">
        <i class="bi bi-house"></i> Biens
    </a>

    <a href="../demandes/list.php">
        <i class="bi bi-people"></i> Demandes
    </a>

    <a href="list.php">
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

    <a onclick="toggleTheme()" style="cursor:pointer;">
        <i class="bi bi-moon-stars-fill"></i> Mode sombre
    </a>

    <a href="../logout.php">
        <i class="bi bi-box-arrow-right"></i> Logout
    </a>
</div>

<!-- MAIN -->
<div class="main" id="main">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="fw-bold">🔗 Liste des Matchs</h2>

        <div class="d-flex align-items-center flex-wrap gap-2">
            <span class="badge bg-dark me-2"><?= $total ?> résultat(s)</span>
            <button class="btn btn-outline-primary btn-sm" onclick="toggleSidebar()">
                <i class="bi bi-list"></i>
            </button>
        </div>
    </div>

    <!-- Résumé / filtre actif -->
    <div class="alert alert-light border d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <strong>Vue :</strong>
            <?php
            $labels = [
                'all'       => 'Tous les matchs',
                'new'       => 'Matchs non vus',
                'seen'      => 'Matchs vus',
                'to_follow' => 'Matchs à relancer',
                'won'       => 'Matchs gagnés',
                'lost'      => 'Matchs perdus',
                'ignored'   => 'Matchs ignorés',
                'cancelled' => 'Demandes annulées',
            ];
            echo htmlspecialchars($labels[$filter] ?? 'Tous les matchs');
            ?>
        </div>
        <?php if($filter !== 'all'): ?>
            <a href="list.php" class="btn btn-sm btn-outline-secondary">Réinitialiser</a>
        <?php endif; ?>
    </div>

    <!-- FILTRES -->
    <div class="mb-3 d-flex flex-wrap">
        <a href="?filter=all" class="btn btn-sm filter-pill <?= $filter==='all'?'btn-primary':'btn-outline-secondary' ?>">Tous</a>
        <a href="?filter=new" class="btn btn-sm filter-pill <?= $filter==='new'?'btn-danger':'btn-outline-danger' ?>">Non vus</a>
        <a href="?filter=seen" class="btn btn-sm filter-pill <?= $filter==='seen'?'btn-success':'btn-outline-success' ?>">Vus</a>
        <a href="?filter=to_follow" class="btn btn-sm filter-pill <?= $filter==='to_follow'?'btn-warning':'btn-outline-warning' ?>">À relancer</a>
        <a href="?filter=won" class="btn btn-sm filter-pill <?= $filter==='won'?'btn-success':'btn-outline-success' ?>">Gagnés</a>
        <a href="?filter=lost" class="btn btn-sm filter-pill <?= $filter==='lost'?'btn-danger':'btn-outline-danger' ?>">Perdus</a>
        <a href="?filter=ignored" class="btn btn-sm filter-pill <?= $filter==='ignored'?'btn-secondary':'btn-outline-secondary' ?>">Ignorés</a>
        <a href="?filter=cancelled" class="btn btn-sm filter-pill <?= $filter==='cancelled'?'btn-warning':'btn-outline-warning' ?>">Demande annulée</a>
    </div>

    <!-- TABLE -->
    <div class="card card-modern p-3">
        <div class="table-responsive">
            <table class="table align-middle mb-0">

                <thead>
                <tr>
                    <th>#</th>
                    <th>Bien / Client</th>
                    <th>Score</th>
                    <th>Statut</th>
                    <th>Intérêt</th>
                    <th>Suivi</th>
                    <th class="text-center">Actions</th>
                </tr>
                </thead>

                <tbody>

                <?php if($matches): ?>
                    <?php foreach($matches as $match): ?>
                        <?php
                        $score = (float)$match['score'];
                        $color = $score >= 80 ? "bg-success" :
                                 ($score >= 60 ? "bg-warning" : "bg-danger");

                        $nextFollow = $match['prochain_suivi_at'] 
                            ? date("d/m/Y H:i", strtotime($match['prochain_suivi_at']))
                            : null;
                        ?>
                        <tr>
                            <td><?= (int)$match['id'] ?></td>

                            <td>
                                <div class="fw-semibold">
                                    <?= htmlspecialchars($match['bien_titre']) ?>
                                    <?php if(!empty($match['bien_reference'])): ?>
                                        <span class="badge badge-small bg-secondary">
                                            <?= htmlspecialchars($match['bien_reference']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-muted" style="font-size:12px;">
                                    Client : <?= htmlspecialchars($match['client_nom']) ?>
                                    <?php if(!empty($match['client_tel'])): ?>
                                        • <span class="badge badge-small bg-dark"><?= htmlspecialchars($match['client_tel']) ?></span>
                                    <?php endif; ?>
                                    <?php if(!empty($match['bien_ville'])): ?>
                                        • <?= htmlspecialchars($match['bien_ville']) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="text-muted" style="font-size:11px;">
                                    Créé le <?= date("d/m/Y H:i", strtotime($match['created_at'])) ?>
                                </div>
                            </td>

                            <td style="width:220px;">
                                <div class="progress">
                                    <div class="progress-bar <?= $color ?>"
                                         role="progressbar"
                                         style="width: <?= $score ?>%">
                                        <?= $score ?>%
                                    </div>
                                </div>
                            </td>

                            <td class="text-center">
                                <?= badgeStatut($match['statut'], $match['resultat_final']) ?><br>
                                <?php if($match['vu']): ?>
                                    <span class="badge badge-small bg-success mt-1">Vu</span>
                                <?php else: ?>
                                    <span class="badge badge-small bg-danger mt-1">Non vu</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-center">
                                <?php if($match['interet_client'] !== null): ?>
                                    <span class="badge bg-info text-dark">
                                        <?= (int)$match['interet_client'] ?>/10
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size:12px;">Non renseigné</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if($nextFollow): ?>
                                    <span class="badge badge-small bg-warning text-dark">
                                        Suivi : <?= $nextFollow ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size:12px;">Aucun suivi planifié</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-center">
                                <a href="view.php?id=<?= (int)$match['id'] ?>"
                                   class="btn btn-sm btn-info mb-1">
                                    <i class="bi bi-eye"></i>
                                </a>

                                <?php if(!$match['vu']): ?>
                                    <a href="view.php?id=<?= (int)$match['id'] ?>&vu=1"
                                       class="btn btn-sm btn-success mb-1"
                                       onclick="return confirm('Marquer ce match comme vu ?')">
                                        <i class="bi bi-check2"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                <tr>
                    <td colspan="7" class="text-center text-muted">
                        Aucun match trouvé.
                    </td>
                </tr>
                <?php endif; ?>

                </tbody>
            </table>
        </div>

        <!-- PAGINATION -->
        <nav class="mt-3">
            <ul class="pagination justify-content-center">
            <?php for($i=1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i==$page?'active':'' ?>">
                    <a class="page-link"
                       href="?page=<?= $i ?>&filter=<?= htmlspecialchars($filter) ?>">
                       <?= $i ?>
                    </a>
                </li>
            <?php endfor; ?>
            </ul>
        </nav>
    </div>
</div>

<script>
function toggleTheme(){
    document.body.classList.toggle("dark-mode");
    localStorage.setItem("dwira-theme",
        document.body.classList.contains("dark-mode") ? "dark" : "light"
    );
}
if(localStorage.getItem("dwira-theme")==="dark"){
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










