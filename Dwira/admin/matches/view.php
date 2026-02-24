<?php
require_once "../../config/auth.php";
require_once "../../config/db.php";

/* =========================
   Vérif ID
========================= */
if (!isset($_GET['id'])) {
    header("Location: list.php");
    exit;
}

$id = (int)$_GET['id'];

/* =========================
   Stats sidebar
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
   Marquer comme vu (GET)
========================= */
if (isset($_GET['vu'])) {
    $updateVu = $pdo->prepare("UPDATE matches SET vu = 1 WHERE id = ?");
    $updateVu->execute([$id]);
}

/* =========================
   Récup match + bien + demande
========================= */
$stmt = $pdo->prepare("
    SELECT 
        m.*,
        m.bien_id,
        m.demande_id,
        b.reference        AS bien_reference,
        b.titre            AS bien_titre,
        b.prix             AS bien_prix,
        b.type             AS bien_type,
        b.ville            AS bien_ville,
        b.chambres         AS bien_chambres,
        b.caracteristiques AS bien_caracteristiques,
        d.nom              AS client_nom,
        d.telephone        AS client_tel,
        d.budget_max       AS client_budget_max,
        d.type_bien        AS demande_type_bien,
        d.ville            AS demande_ville,
        d.chambres_min     AS demande_chambres_min,
        d.caracteristiques AS demande_caracteristiques
    FROM matches m
    JOIN biens b ON m.bien_id = b.id
    JOIN clients_demandes d ON m.demande_id = d.id
    WHERE m.id = ?
");
$stmt->execute([$id]);
$match = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$match) {
    echo "Match introuvable.";
    exit;
}

/* =========================
   Traitement POST : suivi
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $statut          = $_POST['statut'] ?? $match['statut'];
    $interetClient   = $_POST['interet_client'] !== '' ? (int)$_POST['interet_client'] : null;
    $decisionClient  = $_POST['decision_client'] !== '' ? $_POST['decision_client'] : null;
    $resultatFinal   = $_POST['resultat_final'] !== '' ? $_POST['resultat_final'] : null;
    $commentaire     = $_POST['commentaire_client'] ?? null;
    $prochainSuivi   = null;

    if (!empty($_POST['prochain_suivi_at'])) {
        $raw = $_POST['prochain_suivi_at']; // 2026-02-23T15:30
        $raw = str_replace('T', ' ', $raw);
        if (strlen($raw) === 16) {
            $raw .= ':00';
        }
        $prochainSuivi = $raw;
    }

    $upd = $pdo->prepare("
        UPDATE matches
        SET statut = ?,
            interet_client = ?,
            decision_client = ?,
            resultat_final = ?,
            commentaire_client = ?,
            prochain_suivi_at = ?
        WHERE id = ?
    ");

    $upd->execute([
        $statut,
        $interetClient,
        $decisionClient,
        $resultatFinal,
        $commentaire,
        $prochainSuivi,
        $id
    ]);

    header("Location: view.php?id=".$id."&saved=1");
    exit;
}

/* =========================
   Recharger après POST ou vu
========================= */
$stmt->execute([$id]);
$match = $stmt->fetch(PDO::FETCH_ASSOC);

/* =========================
   Caractéristiques
========================= */
$bienCaracs    = json_decode($match['bien_caracteristiques'] ?? "[]", true);
$demandeCaracs = json_decode($match['demande_caracteristiques'] ?? "[]", true);

if (!is_array($bienCaracs))    $bienCaracs = [];
if (!is_array($demandeCaracs)) $demandeCaracs = [];

$caracsCommuns = array_values(array_intersect($bienCaracs, $demandeCaracs));

/* =========================
   Explication du score
========================= */
$scoreGlobal = (float)$match['score'];

$pond = [
    'chambres'         => 15,
    'budget'           => 45,
    'ville'            => 15,
    'caracteristiques' => 25,
];

$scoreChambres = 0;
$scoreBudget   = 0;
$scoreVille    = 0;
$scoreCaracs   = 0;

// Chambres
$bienType   = mb_strtolower($match['bien_type'] ?? '');
$chBien     = (int)($match['bien_chambres'] ?? 0);
$chMin      = (int)($match['demande_chambres_min'] ?? 0);
if (in_array($bienType, ['appartement', 'villa'], true) && $chMin > 0 && $chBien >= $chMin) {
    $scoreChambres = $pond['chambres'];
}

// Budget
$prix      = (float)($match['bien_prix'] ?? 0);
$budgetMax = (float)($match['client_budget_max'] ?? 0);
if ($prix > 0 && $budgetMax > 0) {
    $depassement = ($prix - $budgetMax) / $budgetMax;

    if ($depassement <= 0) {
        $ecart = ($budgetMax - $prix) / $budgetMax;
        if ($ecart >= 0.20) {
            $scoreBudget = $pond['budget'];
        } elseif ($ecart >= 0.10) {
            $scoreBudget = $pond['budget'] * 0.9;
        } else {
            $scoreBudget = $pond['budget'] * 0.8;
        }
    } else {
        if ($depassement <= 0.05) {
            $scoreBudget = $pond['budget'] * 0.7;
        } elseif ($depassement <= 0.10) {
            $scoreBudget = $pond['budget'] * 0.4;
        } elseif ($depassement <= 0.30) {
            $scoreBudget = $pond['budget'] * 0.2;
        } else {
            $scoreBudget = 0;
        }
    }
} else {
    $scoreBudget = $pond['budget'] * 0.5;
}

// Ville
$villeBien    = trim($match['bien_ville'] ?? '');
$villeDemande = trim($match['demande_ville'] ?? '');
if ($villeBien !== '' && $villeDemande !== '') {

    $vb = mb_strtolower($villeBien);
    $vd = mb_strtolower($villeDemande);

    if ($vb === $vd) {
        $scoreVille = $pond['ville'];
    } else {
        $zoneKelibia = [
            'kelibia','manzel tmim','hammem ghzez','hammem jabli',
            'ezzahra hammem jabli','dar allouche','karkouane',
            'haouria','tamozrat','azmour'
        ];
        $vbIn = in_array($vb, $zoneKelibia, true);
        $vdIn = in_array($vd, $zoneKelibia, true);
        if ($vbIn && $vdIn) {
            $scoreVille = $pond['ville'] * 0.5;
        }
    }
}

// Caractéristiques
$totalCarac = count($demandeCaracs);
if ($totalCarac > 0) {
    $matchedCarac = count($caracsCommuns);
    $scoreCaracs  = $pond['caracteristiques'] * ($matchedCarac / $totalCarac);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Détail Match - DWIRA</title>
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
        .card-modern {
            background:var(--card-bg);
            border-radius:18px;
            box-shadow:0 10px 25px rgba(0,0,0,.05);
            border:none;
        }
        .badge-carac {
            background:#e5e7eb;
            color:#111827;
            font-size:11px;
            margin-right:3px;
        }
        .score-circle {
            width:70px; height:70px; border-radius:50%;
            display:flex; align-items:center; justify-content:center;
            font-weight:bold; font-size:18px;
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
        <h2 class="fw-bold">🔗 Détail du Match #<?= (int)$match['id'] ?></h2>

        <div class="d-flex gap-2">
            <a href="../visites/add.php?bien_id=<?= (int)$match['bien_id'] ?>&demande_id=<?= (int)$match['demande_id'] ?>&match_id=<?= (int)$match['id'] ?>"
               class="btn btn-success btn-sm">
                <i class="bi bi-calendar-plus"></i> Créer une visite
            </a>

            <?php if(!$match['vu']): ?>
                <a href="view.php?id=<?= (int)$match['id'] ?>&vu=1"
                   class="btn btn-outline-success btn-sm">
                    <i class="bi bi-check2"></i> Marquer comme vu
                </a>
            <?php endif; ?>

            <button class="btn btn-outline-primary btn-sm" onclick="toggleSidebar()">
                <i class="bi bi-list"></i>
            </button>

            <a href="list.php" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Retour à la liste
            </a>
        </div>
    </div>

    <?php if (isset($_GET['saved'])): ?>
        <div class="alert alert-success">Suivi mis à jour ✅</div>
    <?php endif; ?>

    <!-- SCORE GLOBAL + WHY -->
    <div class="card card-modern mb-4">
        <div class="card-body row">
            <div class="col-md-3 d-flex flex-column align-items-center justify-content-center">
                <?php
                    $scoreColor = $scoreGlobal >= 80 ? '#22c55e' :
                                  ($scoreGlobal >= 60 ? '#eab308' : '#ef4444');
                ?>
                <div class="score-circle" style="background: <?= $scoreColor ?>20; border:2px solid <?= $scoreColor ?>;">
                    <span style="color:<?= $scoreColor ?>;"><?= $scoreGlobal ?>%</span>
                </div>
                <div class="mt-2">
                    <?php if($match['vu']): ?>
                        <span class="badge bg-success">Vu</span>
                    <?php else: ?>
                        <span class="badge bg-danger">Non vu</span>
                    <?php endif; ?>
                </div>
                <div class="mt-2 text-muted" style="font-size:12px;">
                    Créé le <?= date("d/m/Y H:i", strtotime($match['created_at'])) ?>
                </div>
            </div>
            <div class="col-md-9">
                <h6 class="fw-bold mb-2">Pourquoi ce score ?</h6>
                <ul class="mb-0" style="font-size:14px;">
                    <li><strong>Budget :</strong>
                        <?= number_format($scoreBudget, 2) ?>/<?= $pond['budget'] ?> pts  
                        (Bien à <?= number_format($match['bien_prix']) ?> DT, 
                        budget max client <?= number_format($match['client_budget_max']) ?> DT)
                    </li>
                    <li><strong>Ville / zone :</strong>
                        <?= number_format($scoreVille, 2) ?>/<?= $pond['ville'] ?> pts  
                        (Bien : <?= htmlspecialchars($match['bien_ville']) ?> / Demande : <?= htmlspecialchars($match['demande_ville']) ?>)
                    </li>
                    <li><strong>Chambres :</strong>
                        <?= number_format($scoreChambres, 2) ?>/<?= $pond['chambres'] ?> pts  
                        (Bien : <?= (int)$match['bien_chambres'] ?> / Minimum demandé : <?= (int)$match['demande_chambres_min'] ?>)
                    </li>
                    <li><strong>Caractéristiques :</strong>
                        <?= number_format($scoreCaracs, 2) ?>/<?= $pond['caracteristiques'] ?> pts  
                        (<?= count($caracsCommuns) ?> / <?= count($demandeCaracs) ?> caractéristiques souhaitées en commun)
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- BIEN vs DEMANDE -->
    <div class="row mb-4">
        <!-- Bien -->
        <div class="col-md-6 mb-3">
            <div class="card card-modern">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <span>🏠 Bien Immobilier</span>
                    <?php if(!empty($match['bien_reference'])): ?>
                        <span class="badge bg-light text-dark"><?= htmlspecialchars($match['bien_reference']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <p><strong>Titre :</strong> <?= htmlspecialchars($match['bien_titre']) ?></p>
                    <p><strong>Prix :</strong> <span class="badge bg-success"><?= number_format($match['bien_prix']) ?> DT</span></p>
                    <p><strong>Type :</strong> <?= htmlspecialchars($match['bien_type']) ?></p>
                    <p><strong>Ville :</strong> <?= htmlspecialchars($match['bien_ville']) ?></p>
                    <p><strong>Chambres :</strong> <?= (int)$match['bien_chambres'] ?></p>

                    <p class="mb-1"><strong>Caractéristiques :</strong></p>
                    <?php if(count($bienCaracs)): ?>
                        <?php foreach($bienCaracs as $c): ?>
                            <span class="badge-carac"><?= htmlspecialchars($c) ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="text-muted">Aucune caractéristique.</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Demande -->
        <div class="col-md-6 mb-3">
            <div class="card card-modern">
                <div class="card-header bg-primary text-white">
                    👤 Demande Client
                </div>
                <div class="card-body">
                    <p><strong>Nom :</strong> <?= htmlspecialchars($match['client_nom']) ?></p>
                    <p><strong>Téléphone :</strong> 
                        <span class="badge bg-dark"><?= htmlspecialchars($match['client_tel']) ?></span>
                    </p>
                    <p><strong>Budget Max :</strong> 
                        <span class="badge bg-success"><?= number_format($match['client_budget_max']) ?> DT</span>
                    </p>
                    <p><strong>Type souhaité :</strong> <?= htmlspecialchars($match['demande_type_bien']) ?></p>
                    <p><strong>Ville souhaitée :</strong> <?= htmlspecialchars($match['demande_ville']) ?></p>
                    <p><strong>Chambres min :</strong> <?= (int)$match['demande_chambres_min'] ?></p>

                    <p class="mb-1"><strong>Caractéristiques recherchées :</strong></p>
                    <?php if(count($demandeCaracs)): ?>
                        <?php foreach($demandeCaracs as $c): ?>
                            <span class="badge-carac"><?= htmlspecialchars($c) ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="text-muted">Aucune caractéristique essentielle.</span>
                    <?php endif; ?>

                    <?php if(count($caracsCommuns)): ?>
                        <hr>
                        <p class="mb-1"><strong>En commun :</strong></p>
                        <?php foreach($caracsCommuns as $c): ?>
                            <span class="badge bg-success text-white" style="font-size:11px;"><?= htmlspecialchars($c) ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- SUIVI COMMERCIAL -->
    <div class="card card-modern mb-4">
        <div class="card-header bg-light">
            <strong>📋 Suivi commercial</strong>
        </div>
        <div class="card-body">
            <form method="POST" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Statut du match</label>
                    <select name="statut" class="form-select">
                        <?php
                        $statuts = [
                            'nouveau'           => 'Nouveau',
                            'visite_planifiee'  => 'Visite planifiée',
                            'visite_realisee'   => 'Visite réalisée',
                            'offre'             => 'Offre en cours',
                        ];
                        foreach($statuts as $value => $label):
                        ?>
                            <option value="<?= $value ?>" <?= $match['statut'] === $value ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Intérêt client (0 à 10)</label>
                    <input type="number" name="interet_client" min="0" max="10" class="form-control"
                           value="<?= $match['interet_client'] !== null ? (int)$match['interet_client'] : '' ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Décision actuelle</label>
                    <select name="decision_client" class="form-select">
                        <?php
                        $decisions = [
                            ''              => 'Non renseignée',
                            'interesse'     => 'Intéressé',
                            'pas_interesse' => 'Pas intéressé',
                            'a_reflechir'   => 'À réfléchir / à relancer',
                        ];
                        foreach($decisions as $value => $label):
                        ?>
                            <option value="<?= $value ?>" <?= ($match['decision_client'] ?? '') === $value ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Résultat final</label>
                    <select name="resultat_final" class="form-select">
                        <?php
                        $resultats = [
                            ''               => 'En cours',
                            'gagne'          => 'Gagné (vente/contrat conclu)',
                            'perdu'          => 'Perdu',
                            'ignore'         => 'Ignoré (client ne veut pas voir le bien)',
                            'annule_demande' => 'Demande annulée par le client',
                        ];
                        foreach($resultats as $value => $label):
                        ?>
                            <option value="<?= $value ?>" <?= ($match['resultat_final'] ?? '') === $value ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Prochain suivi / rappel</label>
                    <?php
                    $dtValue = '';
                    if (!empty($match['prochain_suivi_at'])) {
                        $dtValue = date('Y-m-d\TH:i', strtotime($match['prochain_suivi_at']));
                    }
                    ?>
                    <input type="datetime-local" name="prochain_suivi_at" class="form-control"
                           value="<?= $dtValue ?>">
                    <small class="text-muted">Laisse vide s'il n'y a pas de suivi prévu.</small>
                </div>

                <div class="col-md-8">
                    <label class="form-label">Commentaire / compte-rendu</label>
                    <textarea name="commentaire_client" class="form-control" rows="3"><?= htmlspecialchars($match['commentaire_client'] ?? '') ?></textarea>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Enregistrer le suivi
                    </button>
                    <a href="list.php" class="btn btn-outline-secondary ms-2">
                        <i class="bi bi-arrow-left"></i> Retour à la liste
                    </a>
                </div>
            </form>
        </div>
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










