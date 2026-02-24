<?php
require_once "../../config/auth.php";
require_once "../../config/db.php";

$message = "";
$error   = "";

// Types de biens disponibles
$typesBiens = [
    "Appartement",
    "Villa",
    "Terrain",
    "Local commercial",
    "Immeuble"
];

// Vérifier ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID manquant ou invalide");
}
$id = (int)$_GET['id'];

// Récupérer la caractéristique
$stmt = $pdo->prepare("SELECT * FROM caracteristiques WHERE id = ?");
$stmt->execute([$id]);
$caract = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$caract) {
    die("Caractéristique introuvable");
}

// Valeurs initiales
$nom       = $caract['nom'];
$typesJson = $caract['types'] ?? '[]';
$types     = json_decode($typesJson, true);
if (!is_array($types)) $types = [];

/* ============================
   Compteurs pour la sidebar
============================ */

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

/* ============================
   Traitement POST
============================ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nom   = trim($_POST['nom'] ?? '');
    $types = $_POST['types'] ?? [];

    if ($nom === '') {
        $error = "Le nom est obligatoire.";
    } else {
        // Vérif doublon sur le nom (autres enregistrements)
        $nomCheck = trim(mb_strtolower($nom));
        $check = $pdo->prepare(
            "SELECT id, nom FROM caracteristiques WHERE LOWER(nom) = ? AND id <> ?"
        );
        $check->execute([$nomCheck, $id]);

        if ($check->rowCount() > 0) {
            $row   = $check->fetch(PDO::FETCH_ASSOC);
            $error = "Cette caractéristique existe déjà : <strong>" . htmlspecialchars($row['nom']) . "</strong>.";
        } else {
            if (!is_array($types)) $types = [];
            $typesJson = json_encode(array_values($types));

            // Mise à jour
            $stmt = $pdo->prepare("UPDATE caracteristiques SET nom = ?, types = ? WHERE id = ?");
            $stmt->execute([$nom, $typesJson, $id]);

            $message = "Caractéristique mise à jour avec succès ✔️";

            // Recharger depuis DB
            $stmt = $pdo->prepare("SELECT * FROM caracteristiques WHERE id = ?");
            $stmt->execute([$id]);
            $caract = $stmt->fetch(PDO::FETCH_ASSOC);

            $nom       = $caract['nom'];
            $typesJson = $caract['types'] ?? '[]';
            $types     = json_decode($typesJson, true);
            if (!is_array($types)) $types = [];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier Caractéristique - DWIRA</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body { font-family: Arial, sans-serif; background:#f1f4f9; }

        .sidebar {
            width:250px;
            float:left;
            height:100vh;
            background:#111827;
            color:white;
            padding:20px;
        }
        .sidebar h3 { font-size:22px; margin-bottom:22px; }
        .sidebar a {
            display:block;
            color:#cbd5e1;
            margin-bottom:10px;
            text-decoration:none;
            padding:8px 10px;
            border-radius:6px;
            transition:.25s;
        }
        .sidebar a:hover {
            background:#2563eb;
            padding-left:16px;
            color:#fff;
        }

        .main {
            margin-left:260px;
            padding:20px 20px 40px;
        }

        .card-modern {
            background:#ffffff;
            border-radius:16px;
            box-shadow:0 8px 20px rgba(0,0,0,.05);
            border:none;
        }

        label { font-weight:500; }
        small.hint { font-size:12px; color:#6b7280; }
    </style>

<link rel='stylesheet' href='/Dwira/assets/css/admin-unified.css?v=202602248'>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <h3>⚙️ DWIRA</h3>

    <a href="../dashboard.php">
        <i class="bi bi-speedometer2"></i> Dashboard
    </a>

    <a href="../biens/list.php">
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

    <a href="list.php">
        <i class="bi bi-sliders"></i> Caractéristiques
    </a>

    <hr style="border-color:#444;">

    <a href="../logout.php">
        <i class="bi bi-door-closed"></i> Logout
    </a>
</div>

<!-- MAIN -->
<div class="main">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
        <h2 class="fw-bold mb-2 mb-md-0">✏️ Modifier la caractéristique</h2>

        <a href="list.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Retour à la liste
        </a>
    </div>

    <div class="card card-modern p-4 col-lg-7 col-md-9 col-12">

        <?php if($error): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <?php if($message): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i> <?= $message ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <!-- Nom -->
            <div class="mb-3">
                <label class="form-label">Nom de la caractéristique</label>
                <input
                    type="text"
                    name="nom"
                    class="form-control"
                    value="<?= htmlspecialchars($nom) ?>"
                    required
                >
                <small class="hint">
                    Libellé utilisé comme <strong>badge global</strong> dans les fiches Biens & Demandes.
                </small>
            </div>

            <!-- Types de biens -->
            <div class="mb-3">
                <label class="form-label">Types de biens concernés (optionnel)</label>
                <div class="d-flex flex-wrap">
                    <?php foreach($typesBiens as $t): ?>
                        <div class="form-check me-3 mb-2">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="types[]"
                                value="<?= htmlspecialchars($t) ?>"
                                id="type_<?= htmlspecialchars($t) ?>"
                                <?= in_array($t, $types) ? 'checked' : '' ?>
                            >
                            <label class="form-check-label" for="type_<?= htmlspecialchars($t) ?>">
                                <?= htmlspecialchars($t) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <small class="hint">
                    Sert à filtrer automatiquement les caractéristiques par type de bien dans les formulaires.
                    Ex : « Vue mer » pour Appartement, Villa, Immeuble.
                </small>
            </div>

            <button class="btn btn-success w-100">
                <i class="bi bi-save"></i> Enregistrer les modifications
            </button>
        </form>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script src='/Dwira/assets/js/admin-unified.js?v=202602247'></script>
</body>
</html>










