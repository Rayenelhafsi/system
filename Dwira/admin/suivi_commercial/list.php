<?php
require_once "../../config/auth.php";
require_once "../../config/db.php";

/* ========================
   PARAMÈTRES / FILTRES
======================== */
$filter      = $_GET['filter']      ?? 'all';
$searchNom   = trim($_GET['search_nom']   ?? '');
$searchTel   = trim($_GET['search_tel']   ?? '');
$searchRef   = trim($_GET['search_ref']   ?? '');

// Filtres pour la requête sur MATCHES
$whereParts = [];
$params     = [];

// Filtres sur le résultat / statut du match
switch ($filter) {
    case 'to_follow':
        // à relancer : pas clôturé + prochain suivi arrivé
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
        // aucun filtre
        break;
}

// Filtres de recherche (client / téléphone / réf bien)
if ($searchNom !== '') {
    $whereParts[] = "d.nom LIKE ?";
    $params[]     = "%{$searchNom}%";
}
if ($searchTel !== '') {
    $whereParts[] = "d.telephone LIKE ?";
    $params[]     = "%{$searchTel}%";
}
if ($searchRef !== '') {
    $whereParts[] = "b.reference LIKE ?";
    $params[]     = "%{$searchRef}%";
}

$whereSql = "";
if (!empty($whereParts)) {
    $whereSql = "WHERE " . implode(" AND ", $whereParts);
}

/* ========================
   1) DATA DES MATCHES
   + STATS VISITES (toutes origines)
======================== */

/**
 * Sous-requête qui agrège les visites par (bien_id, demande_id)
 * - nb_visites : nombre total
 * - derniere_visite : dernière date/heure
 */
$sqlMatches = "
    SELECT
        m.id,
        m.score,
        m.vu,
        m.created_at,
        m.statut,
        m.interet_client,
        m.decision_client,
        m.resultat_final,
        m.prochain_suivi_at,
        m.demande_id,
        m.bien_id,

        b.reference     AS bien_reference,
        b.titre         AS bien_titre,
        b.ville         AS bien_ville,

        d.id            AS client_id,
        d.nom           AS client_nom,
        d.telephone     AS client_tel,

        vstat.nb_visites,
        vstat.derniere_visite
    FROM matches m
    JOIN biens b            ON m.bien_id    = b.id
    JOIN clients_demandes d ON m.demande_id = d.id

    LEFT JOIN (
        SELECT 
            bien_id,
            demande_id,
            COUNT(*)         AS nb_visites,
            MAX(date_visite) AS derniere_visite
        FROM visites
        GROUP BY bien_id, demande_id
    ) vstat
        ON vstat.bien_id    = m.bien_id
       AND vstat.demande_id = m.demande_id

    $whereSql
    ORDER BY d.nom ASC, d.telephone ASC, m.created_at DESC
";

$stmt = $pdo->prepare($sqlMatches);
$stmt->execute($params);
$rowsMatches = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ========================
   2) VISITES SANS MATCH
   (clients qui ont seulement des visites manuelles)
======================== */

// On applique uniquement les filtres de recherche client/tel/référence
$whereVisits = ["m.id IS NULL"];
$paramsVisits = [];

if ($searchNom !== '') {
    $whereVisits[]   = "d.nom LIKE ?";
    $paramsVisits[]  = "%{$searchNom}%";
}
if ($searchTel !== '') {
    $whereVisits[]   = "d.telephone LIKE ?";
    $paramsVisits[]  = "%{$searchTel}%";
}
if ($searchRef !== '') {
    $whereVisits[]   = "b.reference LIKE ?";
    $paramsVisits[]  = "%{$searchRef}%";
}

$whereVisitsSql = "WHERE " . implode(" AND ", $whereVisits);

$sqlVisitsNoMatch = "
    SELECT
        v.*,
        b.reference AS bien_reference,
        b.titre     AS bien_titre,
        b.ville     AS bien_ville,
        d.id        AS client_id,
        d.nom       AS client_nom,
        d.telephone AS client_tel
    FROM visites v
    JOIN biens b            ON v.bien_id    = b.id
    JOIN clients_demandes d ON v.demande_id = d.id
    LEFT JOIN matches m 
           ON m.bien_id    = v.bien_id
          AND m.demande_id = v.demande_id
    $whereVisitsSql
    ORDER BY d.nom ASC, d.telephone ASC, v.date_visite DESC
";

$stmt2 = $pdo->prepare($sqlVisitsNoMatch);
$stmt2->execute($paramsVisits);
$rowsVisitsNoMatch = $stmt2->fetchAll(PDO::FETCH_ASSOC);

/* ========================
   3) REGROUPEMENT PAR CLIENT
======================== */
$clients = [];

// Clients issus des MATCHES
foreach ($rowsMatches as $r) {
    $cid = $r['client_id'];

    if (!isset($clients[$cid])) {
        $clients[$cid] = [
            'client_nom'          => $r['client_nom'],
            'client_tel'          => $r['client_tel'],
            'matches'             => [],
            'visites_sans_match'  => []
        ];
    }

    $clients[$cid]['matches'][] = $r;
}

// Clients issus uniquement de VISITES SANS MATCH
foreach ($rowsVisitsNoMatch as $r) {
    $cid = $r['client_id'];

    if (!isset($clients[$cid])) {
        $clients[$cid] = [
            'client_nom'          => $r['client_nom'],
            'client_tel'          => $r['client_tel'],
            'matches'             => [],
            'visites_sans_match'  => []
        ];
    }

    $clients[$cid]['visites_sans_match'][] = $r;
}

/* ========================
   Stats pour sidebar
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

/* Helpers badges */
function badgeResultat($resultatFinal) {
    if ($resultatFinal === 'gagne')          return "<span class='badge bg-success'>Gagné</span>";
    if ($resultatFinal === 'perdu')          return "<span class='badge bg-danger'>Perdu</span>";
    if ($resultatFinal === 'ignore')         return "<span class='badge bg-secondary'>Ignoré</span>";
    if ($resultatFinal === 'annule_demande') return "<span class='badge bg-warning text-dark'>Demande annulée</span>";
    return "<span class='badge bg-light text-dark'>En cours</span>";
}

function badgeStatutMatch($statut) {
    switch ($statut) {
        case 'nouveau':           return "<span class='badge bg-info text-dark'>Nouveau</span>";
        case 'visite_planifiee':  return "<span class='badge bg-primary'>Visite planifiée</span>";
        case 'visite_realisee':   return "<span class='badge bg-primary'>Visite réalisée</span>";
        case 'offre':             return "<span class='badge bg-dark'>Offre en cours</span>";
        default:                  return "<span class='badge bg-light text-dark'>—</span>";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Suivi commercial - DWIRA</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

<style>
:root {
    --bg:#f1f4f9;
    --card:#ffffff;
    --sidebar:#111827;
    --text:#111827;
}
.dark-mode {
    --bg:#0f172a;
    --card:#1e293b;
    --sidebar:#0f172a;
    --text:#f1f5f9;
}

body {
    font-family: Arial, sans-serif;
    background: var(--bg);
    color: var(--text);
    transition: .3s;
}

/* SIDEBAR MODERNE */
.sidebar {
    height: 100vh;
    width: 250px;
    position: fixed;
    background: var(--sidebar);
    padding-top: 20px;
    transition: 0.3s;
    color: #e5e7eb;
    z-index: 1000;
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
    color:#fff;
}
.badge-sidebar {
    font-size:11px;
    vertical-align: middle;
}

/* MAIN */
.main {
    margin-left: 250px;
    padding: 20px 20px 40px;
    transition: 0.3s;
}
.main.expanded {
    margin-left: 80px;
}

.card-modern {
    background: var(--card);
    border-radius: 16px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.05);
    border: none;
}

.card-client {
    border-radius: 16px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.04);
    border: none;
    background: var(--card);
}

.badge-small {
    font-size: 11px;
}

/* FILTRES */
.filters-bar {
    overflow-x: auto;
}

/* MOBILE */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
    }
    .sidebar.collapsed {
        transform: translateX(0);
        width: 220px;
    }
    .main {
        margin-left: 0;
        padding: 15px 10px 30px;
    }
    .main.expanded {
        margin-left: 0;
    }
}
</style>

<link rel='stylesheet' href='/Dwira/assets/css/admin-unified.css?v=202602248'>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar collapsed" id="sidebar">
    <div class="text-center text-white brand">📞 DWIRA</div>

    <a href="../dashboard.php">
        <i class="bi bi-speedometer2"></i> Dashboard
    </a>

    <a href="../biens/list.php">
        <i class="bi bi-house"></i> Biens
    </a>

    <a href="../demandes/list.php">
        <i class="bi bi-people"></i> Demandes
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

    <a href="list.php">
        <i class="bi bi-telephone-outbound"></i> Suivi commercial
        <?php if ($totalMatchesToFollow > 0): ?>
            <span class="badge bg-warning text-dark badge-sidebar ms-1"><?= $totalMatchesToFollow ?></span>
        <?php endif; ?>
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

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-outline-primary btn-sm d-md-none" type="button" onclick="toggleSidebar()">
                <i class="bi bi-list"></i>
            </button>
            <h2 class="fw-bold mb-0">📞 Suivi commercial (par client)</h2>
        </div>
        <span class="badge bg-dark"><?= count($clients) ?> client(s)</span>
    </div>

    <!-- FILTRES ÉTAT MATCH -->
    <div class="filters-bar mb-2 d-flex flex-row flex-nowrap gap-2">
        <a href="?filter=all&search_nom=<?= urlencode($searchNom) ?>&search_tel=<?= urlencode($searchTel) ?>&search_ref=<?= urlencode($searchRef) ?>"
           class="btn btn-sm <?= $filter==='all'?'btn-primary':'btn-outline-secondary' ?>">Tous</a>

        <a href="?filter=to_follow&search_nom=<?= urlencode($searchNom) ?>&search_tel=<?= urlencode($searchTel) ?>&search_ref=<?= urlencode($searchRef) ?>"
           class="btn btn-sm <?= $filter==='to_follow'?'btn-warning':'btn-outline-warning' ?>">À relancer</a>

        <a href="?filter=won&search_nom=<?= urlencode($searchNom) ?>&search_tel=<?= urlencode($searchTel) ?>&search_ref=<?= urlencode($searchRef) ?>"
           class="btn btn-sm <?= $filter==='won'?'btn-success':'btn-outline-success' ?>">Gagnés</a>

        <a href="?filter=lost&search_nom=<?= urlencode($searchNom) ?>&search_tel=<?= urlencode($searchTel) ?>&search_ref=<?= urlencode($searchRef) ?>"
           class="btn btn-sm <?= $filter==='lost'?'btn-danger':'btn-outline-danger' ?>">Perdus</a>

        <a href="?filter=ignored&search_nom=<?= urlencode($searchNom) ?>&search_tel=<?= urlencode($searchTel) ?>&search_ref=<?= urlencode($searchRef) ?>"
           class="btn btn-sm <?= $filter==='ignored'?'btn-secondary':'btn-outline-secondary' ?>">Ignorés</a>

        <a href="?filter=cancelled&search_nom=<?= urlencode($searchNom) ?>&search_tel=<?= urlencode($searchTel) ?>&search_ref=<?= urlencode($searchRef) ?>"
           class="btn btn-sm <?= $filter==='cancelled'?'btn-warning':'btn-outline-warning' ?>">Demande annulée</a>
    </div>

    <!-- FILTRES RECHERCHE CLIENT / TEL / REF -->
    <form method="GET" class="card card-modern p-3 mb-3">
        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label">Client</label>
                <input type="text" name="search_nom" class="form-control form-control-sm"
                       placeholder="Nom client..."
                       value="<?= htmlspecialchars($searchNom) ?>">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label">Téléphone</label>
                <input type="text" name="search_tel" class="form-control form-control-sm"
                       placeholder="Numéro..."
                       value="<?= htmlspecialchars($searchTel) ?>">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label">Référence bien</label>
                <input type="text" name="search_ref" class="form-control form-control-sm"
                       placeholder="Ex : REF123"
                       value="<?= htmlspecialchars($searchRef) ?>">
            </div>
            <div class="col-12 col-md-2 text-md-end mt-2 mt-md-0">
                <button type="submit" class="btn btn-sm btn-primary mb-1">
                    <i class="bi bi-search"></i> Filtrer
                </button>
                <a href="list.php" class="btn btn-sm btn-outline-secondary mb-1">
                    Reset
                </a>
            </div>
        </div>
    </form>

    <?php if (!count($clients)): ?>
        <div class="alert alert-info">
            Aucun client trouvé pour ces filtres.
        </div>
    <?php endif; ?>

    <!-- LISTE GROUPÉE PAR CLIENT -->
    <?php foreach ($clients as $clientId => $c): ?>
        <div class="card card-client mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <strong><?= htmlspecialchars($c['client_nom']) ?></strong>
                    <?php if($c['client_tel']): ?>
                        <span class="badge bg-dark ms-2"><?= htmlspecialchars($c['client_tel']) ?></span>
                    <?php endif; ?>
                </div>
                <span class="badge bg-light text-dark badge-small">
                    <?= count($c['matches']) ?> match(s)
                </span>
            </div>
            <div class="card-body p-2">
                <!-- TABLE DES MATCHES -->
                <?php if (count($c['matches'])): ?>
                <div class="table-responsive mb-2">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Bien</th>
                                <th>Score</th>
                                <th>Statut</th>
                                <th>Résultat</th>
                                <th>Visites</th>
                                <th>Suivi</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach($c['matches'] as $m): ?>
                            <?php
                                $score   = (float)$m['score'];
                                $color   = $score >= 80 ? "bg-success" :
                                           ($score >= 60 ? "bg-warning" : "bg-danger");
                                $nextFollow = $m['prochain_suivi_at']
                                    ? date("d/m/Y H:i", strtotime($m['prochain_suivi_at']))
                                    : null;
                                $nbVisites = (int)($m['nb_visites'] ?? 0);
                                $derniereVisite = !empty($m['derniere_visite'])
                                    ? date("d/m/Y H:i", strtotime($m['derniere_visite']))
                                    : null;
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold">
                                        <?= htmlspecialchars($m['bien_titre']) ?>
                                        <?php if(!empty($m['bien_reference'])): ?>
                                            <span class="badge badge-small bg-secondary">
                                                <?= htmlspecialchars($m['bien_reference']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-muted" style="font-size:11px;">
                                        <?= htmlspecialchars($m['bien_ville'] ?? '') ?><br>
                                        <span class="badge badge-small bg-light text-muted">
                                            Match #<?= (int)$m['id'] ?> • <?= date("d/m/Y H:i", strtotime($m['created_at'])) ?>
                                        </span>
                                    </div>
                                </td>

                                <td style="width:160px;">
                                    <div class="progress" style="height:16px;">
                                        <div class="progress-bar <?= $color ?>"
                                             role="progressbar"
                                             style="width: <?= $score ?>%">
                                            <?= $score ?>%
                                        </div>
                                    </div>
                                </td>

                                <td class="text-center">
                                    <?= badgeStatutMatch($m['statut']) ?>
                                    <br>
                                    <?php if($m['vu']): ?>
                                        <span class="badge badge-small bg-success mt-1">Vu</span>
                                    <?php else: ?>
                                        <span class="badge badge-small bg-danger mt-1">Non vu</span>
                                    <?php endif; ?>

                                    <?php if($m['interet_client'] !== null): ?>
                                        <div class="mt-1">
                                            <span class="badge badge-small bg-info text-dark">
                                                Intérêt : <?= (int)$m['interet_client'] ?>/10
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td class="text-center">
                                    <?= badgeResultat($m['resultat_final']) ?>
                                    <?php if(!empty($m['decision_client'])): ?>
                                        <div class="mt-1">
                                            <span class="badge badge-small bg-light text-muted">
                                                <?= htmlspecialchars($m['decision_client']) ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- VISITES (toutes) -->
                                <td style="width:180px;">
                                    <?php if($nbVisites > 0): ?>
                                        <div>
                                            <span class="badge badge-small bg-primary">
                                                <?= $nbVisites ?> visite(s)
                                            </span>
                                        </div>
                                        <?php if($derniereVisite): ?>
                                            <div class="text-muted" style="font-size:11px;">
                                                Dernière : <?= $derniereVisite ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size:11px;">
                                            Aucune visite
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- SUIVI (rappel / relance) -->
                                <td style="width:180px;">
                                    <?php if($nextFollow): ?>
                                        <span class="badge badge-small bg-warning text-dark">
                                            Suivi : <?= $nextFollow ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size:11px;">Pas de suivi planifié</span>
                                    <?php endif; ?>
                                </td>

                                <td class="text-center">
                                    <a href="../matches/view.php?id=<?= (int)$m['id'] ?>"
                                       class="btn btn-sm btn-info mb-1">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if(!$m['vu']): ?>
                                        <a href="../matches/view.php?id=<?= (int)$m['id'] ?>&vu=1"
                                           class="btn btn-sm btn-success mb-1"
                                           onclick="return confirm('Marquer ce match comme vu ?');">
                                            <i class="bi bi-check2"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <!-- VISITES SANS MATCH -->
                <?php if (!empty($c['visites_sans_match'])): ?>
                    <div class="mt-2">
                        <small class="text-muted">🧾 Visites sans match</small>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Bien</th>
                                        <th>Statut</th>
                                        <th>Note</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($c['visites_sans_match'] as $v): ?>
                                    <tr>
                                        <td>
                                            <?= $v['date_visite']
                                                ? date("d/m/Y H:i", strtotime($v['date_visite']))
                                                : '—' ?>
                                        </td>
                                        <td>
                                            <?php if($v['bien_id'] && $v['bien_titre']): ?>
                                                <a href="../biens/view.php?id=<?= (int)$v['bien_id'] ?>">
                                                    <?php if(!empty($v['bien_reference'])): ?>
                                                        <strong><?= htmlspecialchars($v['bien_reference']) ?></strong> –
                                                    <?php endif; ?>
                                                    <?= htmlspecialchars($v['bien_titre']) ?>
                                                </a>
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
                                        <td style="font-size:11px;">
                                            <?= !empty($v['note'])
                                                ? nl2br(htmlspecialchars($v['note']))
                                                : '<span class="text-muted">—</span>' ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    <?php endforeach; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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
    const sidebar = document.getElementById("sidebar");
    const main = document.getElementById("main");

    // Sur desktop : largeur 250/80
    // Sur mobile : slide in/out via transform
    sidebar.classList.toggle("collapsed");
    main.classList.toggle("expanded");
}
</script>

<script src='/Dwira/assets/js/admin-unified.js?v=202602247'></script>
</body>
</html>










