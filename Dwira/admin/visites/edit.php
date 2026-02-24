<?php
require_once "../../config/auth.php";
require_once "../../config/db.php";

// 1) Vérifier l'ID
if (!isset($_GET['id'])) {
    header("Location: list.php");
    exit;
}

$id = (int)$_GET['id'];

// 2) Récupérer la visite + bien + client (LEFT JOIN pour ne pas perdre les visites sans demande)
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
    WHERE v.id = ?
");
$stmt->execute([$id]);
$visite = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$visite) {
    echo "Visite introuvable.";
    exit;
}

$message = "";

// 3) Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $dateVisiteInput = $_POST['date_visite'] ?? '';
    $lieu            = trim($_POST['lieu'] ?? '');
    $statut          = $_POST['statut'] ?? 'Prévue';
    $note            = trim($_POST['note'] ?? '');

    // Conversion datetime-local -> format SQL
    // ex: "2026-02-22T15:30" -> "2026-02-22 15:30"
    $dateVisiteSql = null;
    if (!empty($dateVisiteInput)) {
        $dateVisiteSql = str_replace('T', ' ', $dateVisiteInput);
    }

    $stmtUpdate = $pdo->prepare("
        UPDATE visites
        SET date_visite = ?, 
            lieu        = ?, 
            statut      = ?, 
            note        = ?
        WHERE id = ?
    ");

    $stmtUpdate->execute([
        $dateVisiteSql,
        $lieu,
        $statut,
        $note,
        $id
    ]);

    $message = "Visite mise à jour avec succès ✔️";

    // Recharger les données à jour (toujours en LEFT JOIN)
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
        WHERE v.id = ?
    ");
    $stmt->execute([$id]);
    $visite = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Préparer la valeur pour input datetime-local
$datetimeLocalValue = "";
if (!empty($visite['date_visite'])) {
    $datetimeLocalValue = date("Y-m-d\TH:i", strtotime($visite['date_visite']));
}

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
    <title>Éditer la visite - DWIRA</title>
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

        label { font-weight: 500; }
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

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-outline-primary btn-sm d-md-none" type="button" onclick="toggleSidebar()">
                <i class="bi bi-list"></i>
            </button>
            <h2 class="fw-bold mb-0">✏️ Éditer la visite #<?= (int)$visite['id'] ?></h2>
        </div>

        <a href="list.php" class="btn btn-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Retour à la liste
        </a>
    </div>

    <?php if($message): ?>
        <div class="alert alert-success"><?= $message ?></div>
    <?php endif; ?>

    <!-- Rappel bien + client -->
    <div class="card card-modern mb-3">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <span>Contexte de la visite</span>
            <?php if(!empty($visite['match_id'])): ?>
                <span class="badge bg-info text-dark badge-small">
                    Via match #<?= (int)$visite['match_id'] ?>
                </span>
            <?php endif; ?>
        </div>
        <div class="card-body row">
            <div class="col-12 col-md-6 mb-2 mb-md-0">
                <p class="mb-1"><strong>Bien :</strong></p>
                <?php if(!empty($visite['bien_id']) && !empty($visite['bien_titre'])): ?>
                    <p class="mb-0">
                        <a href="../biens/view.php?id=<?= (int)$visite['bien_id'] ?>">
                            <?php if(!empty($visite['reference'])): ?>
                                <span class="badge bg-secondary">
                                    <?= htmlspecialchars($visite['reference']) ?>
                                </span>
                            <?php endif; ?>
                            <?= htmlspecialchars($visite['bien_titre']) ?>
                        </a>
                    </p>
                <?php else: ?>
                    <p class="mb-0 text-muted">Aucun bien lié (enregistrement incomplet).</p>
                <?php endif; ?>
            </div>
            <div class="col-12 col-md-6">
                <p class="mb-1"><strong>Client :</strong></p>
                <?php if(!empty($visite['client_nom'])): ?>
                    <p class="mb-0">
                        <?= htmlspecialchars($visite['client_nom']) ?>
                        <?php if(!empty($visite['client_tel'])): ?>
                            – <span class="badge bg-dark"><?= htmlspecialchars($visite['client_tel']) ?></span>
                        <?php endif; ?>
                    </p>
                <?php else: ?>
                    <p class="mb-0 text-muted">Pas de demande client liée.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Formulaire d'édition -->
    <div class="card card-modern p-4">
        <form method="POST">
            <div class="row mb-3">
                <div class="col-12 col-md-4 mb-2 mb-md-0">
                    <label class="form-label">Date & heure de visite</label>
                    <input 
                        type="datetime-local" 
                        name="date_visite" 
                        class="form-control"
                        value="<?= htmlspecialchars($datetimeLocalValue) ?>"
                        required
                    >
                </div>
                <div class="col-12 col-md-4 mb-2 mb-md-0">
                    <label class="form-label">Lieu de RDV</label>
                    <input 
                        type="text" 
                        name="lieu" 
                        class="form-control"
                        placeholder="Devant le bien, agence..."
                        value="<?= htmlspecialchars($visite['lieu'] ?? '') ?>"
                    >
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label">Statut</label>
                    <select name="statut" class="form-select">
                        <?php
                            $statutActuel = $visite['statut'] ?? 'Prévue';
                        ?>
                        <option value="Prévue"   <?= $statutActuel === 'Prévue'   ? 'selected' : '' ?>>Prévue</option>
                        <option value="Réalisée" <?= $statutActuel === 'Réalisée' ? 'selected' : '' ?>>Réalisée</option>
                        <option value="Annulée"  <?= $statutActuel === 'Annulée'  ? 'selected' : '' ?>>Annulée</option>
                        <option value="No show"  <?= $statutActuel === 'No show'  ? 'selected' : '' ?>>No show</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Note interne</label>
                <textarea 
                    name="note" 
                    rows="4" 
                    class="form-control"
                    placeholder="Impression du client, raisons d'annulation, intérêt pour d'autres biens..."
                ><?= htmlspecialchars($visite['note'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn btn-warning">
                <i class="bi bi-save"></i> Mettre à jour la visite
            </button>
        </form>
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










