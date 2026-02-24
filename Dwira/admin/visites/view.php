<?php
require_once "../../config/auth.php";
require_once "../../config/db.php";

if (!isset($_GET['id'])) {
    header("Location: list.php");
    exit;
}
$id = (int)$_GET['id'];

/* =========================
   Mise à jour du statut (POST)
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newStatut = $_POST['statut'] ?? null;
    if ($newStatut) {
        $stmt = $pdo->prepare("UPDATE visites SET statut = ? WHERE id = ?");
        $stmt->execute([$newStatut, $id]);
    }
    header("Location: view.php?id=".$id);
    exit;
}

/* =========================
   Récupération visite + bien + demande
========================= */
$stmt = $pdo->prepare("
    SELECT 
        v.*,
        b.reference,
        b.titre AS bien_titre,
        b.ville AS bien_ville,
        b.prix  AS bien_prix,
        b.type  AS bien_type,
        b.chambres AS bien_chambres,
        b.telephone_proprietaire,
        d.nom       AS demande_nom,
        d.telephone AS demande_tel,
        d.budget_max,
        d.ville     AS demande_ville,
        d.type_bien AS demande_type_bien
    FROM visites v
    JOIN biens b ON v.bien_id = b.id
    LEFT JOIN clients_demandes d ON v.demande_id = d.id
    WHERE v.id = ?
");
$stmt->execute([$id]);
$visite = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$visite) {
    echo "Visite introuvable.";
    exit;
}

/* =========================
   Match éventuellement lié
========================= */
$match = null;
if (!empty($visite['match_id'])) {
    $stm2 = $pdo->prepare("
        SELECT m.*, 
               b.titre AS match_bien_titre, 
               d.nom   AS match_client_nom
        FROM matches m
        JOIN biens b ON m.bien_id = b.id
        JOIN clients_demandes d ON m.demande_id = d.id
        WHERE m.id = ?
    ");
    $stm2->execute([$visite['match_id']]);
    $match = $stm2->fetch(PDO::FETCH_ASSOC);
}

/* =========================
   Statuts possibles
========================= */
$allStatus = ["Prévue","Réalisée","Annulée","No show","Perdue","Ignorée"];

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

/* Helper badge statut visite */
function badgeStatutVisite($statut) {
    $badge = 'bg-secondary';
    $extra = '';
    if ($statut === 'Prévue')   $badge = 'bg-primary';
    if ($statut === 'Réalisée') $badge = 'bg-success';
    if ($statut === 'Annulée')  $badge = 'bg-danger';
    if ($statut === 'No show')  $badge = 'bg-warning text-dark';
    if ($statut === 'Perdue')   $badge = 'bg-danger';
    if ($statut === 'Ignorée')  $badge = 'bg-secondary';
    return "<span class='badge $badge'>".htmlspecialchars($statut)."</span>";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Détail visite - DWIRA</title>
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

    /* SIDEBAR */
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
    .badge-small { font-size:11px; }

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
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="d-flex align-items-center gap-2">
            <!-- Burger mobile -->
            <button class="btn btn-outline-primary btn-sm d-md-none" type="button" onclick="toggleSidebar()">
                <i class="bi bi-list"></i>
            </button>
            <h2 class="fw-bold mb-0">📅 Détail visite #<?= (int)$visite['id'] ?></h2>
        </div>

        <a href="list.php" class="btn btn-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Retour aux visites
        </a>
    </div>

    <!-- Ligne 1 : Résumé + changement de statut -->
    <div class="card card-modern mb-3 p-3">
        <div class="row align-items-center">
            <div class="col-md-7 mb-3 mb-md-0">
                <p class="mb-1">
                    <strong>Date / heure :</strong>
                    <?= $visite['date_visite']
                        ? date("d/m/Y H:i", strtotime($visite['date_visite']))
                        : "<span class='text-muted'>—</span>" ?>
                </p>
                <p class="mb-1">
                    <strong>Source :</strong>
                    <?php if(($visite['source'] ?? '') === 'match'): ?>
                        <span class="badge bg-info text-dark badge-small">Match</span>
                    <?php else: ?>
                        <span class="badge bg-secondary badge-small">Manuel</span>
                    <?php endif; ?>
                </p>
                <p class="mb-0">
                    <strong>Créée le :</strong>
                    <?= $visite['created_at']
                        ? date("d/m/Y H:i", strtotime($visite['created_at']))
                        : "<span class='text-muted'>—</span>" ?>
                </p>
            </div>

            <div class="col-md-5">
                <form method="POST" class="d-flex flex-wrap gap-2 justify-content-md-end align-items-center">
                    <label class="me-1 mb-0"><strong>Statut :</strong></label>
                    <select name="statut" class="form-select form-select-sm w-auto">
                        <?php foreach($allStatus as $st): ?>
                            <option value="<?= $st ?>" <?= $visite['statut']===$st?'selected':'' ?>>
                                <?= $st ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-sm btn-primary">
                        <i class="bi bi-arrow-repeat"></i> Mettre à jour
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Ligne 2 : Bien + Client -->
    <div class="row mb-3">
        <!-- Bien -->
        <div class="col-md-6 mb-3 mb-md-0">
            <div class="card card-modern p-3 h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="mb-0">🏠 Bien visité</h5>
                    <a href="../biens/view.php?id=<?= (int)$visite['bien_id'] ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-eye"></i> Fiche bien
                    </a>
                </div>
                <p class="mb-1">
                    <strong>Réf :</strong>
                    <?= !empty($visite['reference'])
                        ? htmlspecialchars($visite['reference'])
                        : "<span class='text-muted'>—</span>" ?>
                </p>
                <p class="mb-1"><strong>Titre :</strong> <?= htmlspecialchars($visite['bien_titre']) ?></p>
                <p class="mb-1">
                    <strong>Prix :</strong>
                    <?php if(!empty($visite['bien_prix'])): ?>
                        <span class="badge bg-success"><?= number_format($visite['bien_prix']) ?> DT</span>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </p>
                <p class="mb-1"><strong>Type :</strong> <?= htmlspecialchars($visite['bien_type']) ?></p>
                <p class="mb-1"><strong>Ville :</strong> <?= htmlspecialchars($visite['bien_ville']) ?></p>
                <p class="mb-1"><strong>Chambres :</strong> <?= (int)$visite['bien_chambres'] ?></p>
                <p class="mb-0">
                    <strong>Propriétaire :</strong>
                    <?php if(!empty($visite['telephone_proprietaire'])): ?>
                        <span class="badge bg-dark"><?= htmlspecialchars($visite['telephone_proprietaire']) ?></span>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <!-- Client -->
        <div class="col-md-6">
            <div class="card card-modern p-3 h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="mb-0">👤 Client</h5>
                    <?php if(!empty($visite['demande_id'])): ?>
                        <a href="../demandes/view.php?id=<?= (int)$visite['demande_id'] ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye"></i> Fiche demande
                        </a>
                    <?php endif; ?>
                </div>

                <?php
                    // Nom + tel : priorité fiche demande, sinon champs sur visites (client_nom / client_tel)
                    $nomClient = $visite['demande_nom'] ?: ($visite['client_nom'] ?? '');
                    $telClient = $visite['demande_tel'] ?: ($visite['client_tel'] ?? '');
                ?>

                <p class="mb-1">
                    <strong>Nom :</strong> 
                    <?= $nomClient
                        ? htmlspecialchars($nomClient)
                        : "<span class='text-muted'>—</span>" ?>
                </p>
                <p class="mb-1">
                    <strong>Téléphone :</strong>
                    <?php if($telClient): ?>
                        <span class="badge bg-dark"><?= htmlspecialchars($telClient) ?></span>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </p>

                <?php if(!empty($visite['demande_id'])): ?>
                    <p class="mb-1">
                        <strong>Budget max :</strong>
                            <?php if(!empty($visite['budget_max'])): ?>
                                <span class="badge bg-success"><?= number_format($visite['budget_max']) ?> DT</span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                    </p>
                    <p class="mb-1">
                        <strong>Ville souhaitée :</strong> 
                        <?= $visite['demande_ville']
                            ? htmlspecialchars($visite['demande_ville'])
                            : "<span class='text-muted'>—</span>" ?>
                    </p>
                    <p class="mb-0">
                        <strong>Type souhaité :</strong> 
                        <?= $visite['demande_type_bien']
                            ? htmlspecialchars($visite['demande_type_bien'])
                            : "<span class='text-muted'>—</span>" ?>
                    </p>
                <?php else: ?>
                    <p class="text-muted mb-0">
                        Client sans fiche demande (visite manuelle).
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Ligne 3 : Lieu + Note + Match -->
    <div class="row mb-3">
        <div class="col-md-6 mb-3 mb-md-0">
            <div class="card card-modern p-3 mb-3">
                <h5 class="mb-2">📍 Infos RDV</h5>
                <p class="mb-2">
                    <strong>Lieu :</strong>
                    <?= $visite['lieu']
                        ? htmlspecialchars($visite['lieu'])
                        : "<span class='text-muted'>—</span>" ?>
                </p>
                <p class="mb-0">
                    <strong>Statut actuel :</strong>
                    <?= badgeStatutVisite($visite['statut']) ?>
                </p>
            </div>

            <?php if(!empty($visite['note'])): ?>
                <div class="card card-modern p-3">
                    <h6 class="mb-2">📝 Note interne</h6>
                    <div><?= nl2br(htmlspecialchars($visite['note'])) ?></div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-md-6">
            <?php if($match): ?>
                <div class="card card-modern p-3">
                    <h5 class="mb-2">🔗 Match lié</h5>
                    <p class="mb-1">
                        <strong>Match ID :</strong> #<?= (int)$match['id'] ?>
                    </p>
                    <p class="mb-1">
                        <strong>Score :</strong>
                        <span class="badge bg-primary"><?= (float)$match['score'] ?>%</span>
                    </p>
                    <p class="mb-2">
                        <strong>Client / Bien :</strong><br>
                        <?= htmlspecialchars($match['match_client_nom']) ?> ⇄ 
                        <?= htmlspecialchars($match['match_bien_titre']) ?>
                    </p>
                    <a href="../matches/view.php?id=<?= (int)$match['id'] ?>" class="btn btn-sm btn-outline-info">
                        <i class="bi bi-eye"></i> Voir le match
                    </a>
                </div>
            <?php else: ?>
                <div class="card card-modern p-3">
                    <h5 class="mb-2">🔗 Match</h5>
                    <p class="mb-0 text-muted">
                        Cette visite n’est pas liée à un match (créée manuellement).
                    </p>
                </div>
            <?php endif; ?>
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










