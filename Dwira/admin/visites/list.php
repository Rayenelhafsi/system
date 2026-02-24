<?php
require_once "../../config/auth.php";
require_once "../../config/db.php";

// Filtre simple (statut)
$statutFilter = $_GET['statut'] ?? 'all';

$where  = [];
$params = [];

if ($statutFilter !== 'all' && $statutFilter !== '') {
    $where[]  = "v.statut = ?";
    $params[] = $statutFilter;
}

$sql = "
    SELECT 
        v.*,
        b.reference,
        b.titre AS bien_titre,
        d.nom AS client_nom,
        d.telephone AS client_tel
    FROM visites v
    LEFT JOIN biens b ON v.bien_id = b.id
    LEFT JOIN clients_demandes d ON v.demande_id = d.id
";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY v.date_visite DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$visites = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ========================
   Stats pour sidebar (badges)
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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Liste des visites - DWIRA</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

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

.table thead {
    background: linear-gradient(45deg,#2563eb,#1e40af);
    color: #fff;
}
.badge-small { font-size: 11px; }

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
    <div class="text-center text-white brand">📅 DWIRA</div>

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

    <a href="list.php">
        <i class="bi bi-calendar-event"></i> Visites
    </a>

    <a href="../suivi_commercial/list.php">
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

    <!-- TITRE + BOUTON AJOUT -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="d-flex align-items-center gap-2">
            <!-- Bouton burger mobile -->
            <button class="btn btn-outline-primary btn-sm d-md-none" type="button" onclick="toggleSidebar()">
                <i class="bi bi-list"></i>
            </button>
            <h2 class="fw-bold mb-0">📅 Liste des visites</h2>
        </div>

        <a href="add.php" class="btn btn-success btn-sm">
            <i class="bi bi-plus-circle"></i> Ajouter une visite
        </a>
    </div>

    <!-- Message après suppression -->
    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">
            ✔️ Visite supprimée avec succès.
        </div>
    <?php endif; ?>

    <!-- FILTRES -->
    <div class="card card-modern p-3 mb-3">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-12 col-sm-6 col-md-4">
                <label class="form-label mb-1">Statut de la visite</label>
                <select name="statut" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="all">Tous les statuts</option>
                    <option value="Prévue"   <?= $statutFilter==='Prévue' ? 'selected' : '' ?>>Prévue</option>
                    <option value="Réalisée" <?= $statutFilter==='Réalisée' ? 'selected' : '' ?>>Réalisée</option>
                    <option value="Annulée"  <?= $statutFilter==='Annulée' ? 'selected' : '' ?>>Annulée</option>
                    <option value="No show"  <?= $statutFilter==='No show' ? 'selected' : '' ?>>No show</option>
                </select>
            </div>
        </form>
    </div>

    <!-- TABLE -->
    <div class="card card-modern p-3">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Date / heure</th>
                    <th>Bien</th>
                    <th>Client</th>
                    <th>📞 Client</th>
                    <th>Lieu</th>
                    <th>Statut</th>
                    <th class="text-center">Actions</th>
                </tr>
                </thead>
                <tbody>

                <?php foreach($visites as $v): ?>
                    <tr>
                        <td><?= (int)$v['id'] ?></td>

                        <td>
                            <?= $v['date_visite']
                                    ? date("d/m/Y H:i", strtotime($v['date_visite']))
                                    : "<span class='text-muted'>—</span>" ?>

                            <?php if(!empty($v['match_id'])): ?>
                                <div class="mt-1">
                                    <a href="../matches/view.php?id=<?= (int)$v['match_id'] ?>"
                                       class="badge bg-info text-dark badge-small text-decoration-none">
                                        Via match #<?= (int)$v['match_id'] ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if($v['bien_id'] && $v['bien_titre']): ?>
                                <a href="../biens/view.php?id=<?= (int)$v['bien_id'] ?>">
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

                        <td><?= htmlspecialchars($v['lieu'] ?? '') ?></td>

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

                        <td class="text-center">
                            <a href="edit.php?id=<?= (int)$v['id'] ?>" class="btn btn-sm btn-warning mb-1">
                                <i class="bi bi-pencil"></i>
                            </a>

                            <a href="delete.php?id=<?= (int)$v['id'] ?>"
                               class="btn btn-sm btn-danger mb-1"
                               onclick="return confirm('❌ Supprimer cette visite ?');">
                                <i class="bi bi-trash"></i>
                            </a>
                        </td>
                    </tr>

                    <?php if (!empty($v['note'])): ?>
                        <tr>
                            <td></td>
                            <td colspan="7">
                                <small class="text-muted">Note :</small>
                                <div><?= nl2br(htmlspecialchars($v['note'])) ?></div>
                            </td>
                        </tr>
                    <?php endif; ?>

                <?php endforeach; ?>

                <?php if(!count($visites)): ?>
                    <tr><td colspan="8" class="text-center text-muted">Aucune visite enregistrée.</td></tr>
                <?php endif; ?>

                </tbody>
            </table>
        </div>
    </div>
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

    sidebar.classList.toggle("collapsed");
    main.classList.toggle("expanded");
}
</script>

<script src='/Dwira/assets/js/admin-unified.js?v=202602247'></script>
</body>
</html>










