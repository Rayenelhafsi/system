<?php
require_once "../../config/auth.php";
require_once "../../config/db.php";

$message = "";

// Récupérer éventuellement un match_id (si on vient du module Matchs)
$matchId = isset($_GET['match_id']) ? (int)$_GET['match_id'] : 0;

// Récupérer bien_id / demande_id depuis l'URL (si fournis)
$bienId    = isset($_GET['bien_id']) ? (int)$_GET['bien_id'] : 0;
$demandeId = isset($_GET['demande_id']) ? (int)$_GET['demande_id'] : 0;

// Récupérer les infos du bien (si fourni)
$bien = null;
if ($bienId > 0) {
    $stmt = $pdo->prepare("SELECT id, reference, titre, ville, telephone_proprietaire FROM biens WHERE id = ?");
    $stmt->execute([$bienId]);
    $bien = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Récupérer les infos de la demande (si fournie)
$demande = null;
if ($demandeId > 0) {
    $stmt = $pdo->prepare("SELECT id, nom, telephone, budget_max, ville FROM clients_demandes WHERE id = ?");
    $stmt->execute([$demandeId]);
    $demande = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Si on est en mode "manuel" (ou on veut pouvoir choisir)
$listeBiens = [];
$listeDemandes = [];

if (!$bien) {
    $stmt = $pdo->query("
        SELECT id, reference, titre, ville 
        FROM biens 
        ORDER BY created_at DESC 
        LIMIT 200
    ");
    $listeBiens = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
if (!$demande) {
    $stmt = $pdo->query("
        SELECT id, nom, telephone, ville 
        FROM clients_demandes 
        ORDER BY created_at DESC 
        LIMIT 200
    ");
    $listeDemandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Gestion du POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $matchId   = isset($_POST['match_id']) ? (int)$_POST['match_id'] : 0;

    // Si on avait un bien/demande pré-rempli, ils arrivent en hidden
    // sinon ils viennent des <select>
    $bienId    = (int)($_POST['bien_id'] ?? 0);
    $demandeId = (int)($_POST['demande_id'] ?? 0);

    $date      = $_POST['date_visite'] ?? '';
    $heure     = $_POST['heure_visite'] ?? '';
    $lieu      = trim($_POST['lieu'] ?? '');
    $statut    = $_POST['statut'] ?? 'Prévue';
    $note      = trim($_POST['note'] ?? '');

    // Fusion date + heure
    $dateTime = null;
    if ($date && $heure) {
        $dateTime = $date . ' ' . $heure . ':00';
    }

    // Contrôles minimum : bien, demande, date/heure
    if ($bienId > 0 && $demandeId > 0 && $dateTime) {

        $stmt = $pdo->prepare("
            INSERT INTO visites (bien_id, demande_id, match_id, date_visite, lieu, statut, note)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $bienId,
            $demandeId,
            $matchId > 0 ? $matchId : null,
            $dateTime,
            $lieu !== '' ? $lieu : null,
            $statut,
            $note !== '' ? $note : null
        ]);

        $message = "✅ Rendez-vous de visite créé avec succès";

        // Si on vient d'un match, on peut revenir au match
        if ($matchId > 0) {
            header("Location: ../matches/view.php?id=".$matchId."&saved=1");
            exit;
        }

    } else {
        $message = "❌ Merci de choisir un bien, un client, et de remplir la date + l'heure.";
    }
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
    <title>Créer un RDV de visite - DWIRA</title>
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
            <h2 class="fw-bold mb-0">📅 Créer un RDV de visite</h2>
        </div>

        <a href="list.php" class="btn btn-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Retour à la liste
        </a>
    </div>

    <?php if($message): ?>
        <div class="alert alert-info"><?= $message ?></div>
    <?php endif; ?>

    <div class="card card-modern p-4">
        <form method="POST">

            <!-- match éventuel -->
            <input type="hidden" name="match_id" value="<?= (int)$matchId ?>">

            <div class="row mb-3">
                <!-- Bloc Bien -->
                <div class="col-12 col-md-6 mb-3 mb-md-0">
                    <h5 class="mb-2">🏠 Bien</h5>

                    <?php if($bien): ?>
                        <!-- Bien pré-rempli (depuis match ou fiche bien) -->
                        <input type="hidden" name="bien_id" value="<?= (int)$bien['id'] ?>">
                        <div class="p-2 border rounded bg-light">
                            <p class="mb-1">
                                <strong>Réf :</strong>
                                <span class="badge bg-secondary"><?= htmlspecialchars($bien['reference']) ?></span>
                            </p>
                            <p class="mb-1">
                                <strong>Titre :</strong> <?= htmlspecialchars($bien['titre']) ?>
                            </p>
                            <p class="mb-1">
                                <strong>Ville :</strong> <?= htmlspecialchars($bien['ville']) ?>
                            </p>
                            <p class="mb-0">
                                <strong>Propriétaire :</strong>
                                <span class="badge bg-dark"><?= htmlspecialchars($bien['telephone_proprietaire'] ?? '') ?></span>
                            </p>
                        </div>
                    <?php else: ?>
                        <!-- Choix manuel du bien -->
                        <div class="mb-2">
                            <label class="form-label">Choisir un bien</label>
                            <select name="bien_id" class="form-select" required>
                                <option value="">-- Sélectionner un bien --</option>
                                <?php foreach($listeBiens as $b): ?>
                                    <option value="<?= (int)$b['id'] ?>">
                                        [<?= htmlspecialchars($b['reference']) ?>]
                                        <?= htmlspecialchars($b['titre']) ?> - <?= htmlspecialchars($b['ville']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                </div>

                <!-- Bloc Client / Demande -->
                <div class="col-12 col-md-6">
                    <h5 class="mb-2">👤 Client</h5>

                    <?php if($demande): ?>
                        <!-- Demande pré-remplie -->
                        <input type="hidden" name="demande_id" value="<?= (int)$demande['id'] ?>">
                        <div class="p-2 border rounded bg-light">
                            <p class="mb-1">
                                <strong>Nom :</strong> <?= htmlspecialchars($demande['nom']) ?>
                            </p>
                            <p class="mb-1">
                                <strong>Téléphone :</strong>
                                <span class="badge bg-dark"><?= htmlspecialchars($demande['telephone']) ?></span>
                            </p>
                            <p class="mb-1">
                                <strong>Budget max :</strong>
                                <span class="badge bg-success">
                                    <?= number_format((float)$demande['budget_max'], 0, ',', ' ') ?> DT
                                </span>
                            </p>
                            <p class="mb-0">
                                <strong>Ville souhaitée :</strong> <?= htmlspecialchars($demande['ville']) ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <!-- Choix manuel de la demande client -->
                        <div class="mb-2">
                            <label class="form-label">Choisir un client (demande)</label>
                            <select name="demande_id" class="form-select" required>
                                <option value="">-- Sélectionner un client --</option>
                                <?php foreach($listeDemandes as $d): ?>
                                    <option value="<?= (int)$d['id'] ?>">
                                        <?= htmlspecialchars($d['nom']) ?>
                                        (<?= htmlspecialchars($d['telephone']) ?>)
                                        - <?= htmlspecialchars($d['ville']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Bouton NOUVEAU CLIENT / DEMANDE -->
                        <a href="../demandes/add.php?source=visite&bien_id=<?= (int)$bienId ?>"
                           class="btn btn-sm btn-outline-primary"
                           target="_blank">
                            <i class="bi bi-person-plus"></i> Nouveau client / demande
                        </a>
                        <small class="text-muted d-block mt-1">
                            Ouvre dans un nouvel onglet. Après création, revenez ici et rafraîchissez la page pour voir le client dans la liste.
                        </small>
                    <?php endif; ?>

                </div>
            </div>

            <hr>

            <div class="row mb-3">
                <div class="col-12 col-md-4 mb-2 mb-md-0">
                    <label class="form-label">Date de visite</label>
                    <input type="date" name="date_visite" class="form-control" required>
                </div>
                <div class="col-12 col-md-4 mb-2 mb-md-0">
                    <label class="form-label">Heure</label>
                    <input type="time" name="heure_visite" class="form-control" required>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label">Statut</label>
                    <select name="statut" class="form-select">
                        <option value="Prévue">Prévue</option>
                        <option value="Réalisée">Réalisée</option>
                        <option value="Annulée">Annulée</option>
                        <option value="No show">No show</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Lieu / Point de rendez-vous</label>
                <input type="text" name="lieu" class="form-control"
                       placeholder="Ex : Devant le bien, agence DWIRA, café...">
            </div>

            <div class="mb-3">
                <label class="form-label">Note interne</label>
                <textarea name="note" class="form-control" rows="3"
                          placeholder="Infos supplémentaires sur la visite..."></textarea>
            </div>

            <button type="submit" class="btn btn-success">
                <i class="bi bi-calendar-plus"></i> Enregistrer le RDV
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










