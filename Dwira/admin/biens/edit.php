<?php
require_once "../../config/auth.php";
require_once "../../config/db.php";
require_once "../../services/MatchService.php";

$message = "";

/* ============================
   0) ID du bien
   ============================ */
if (!isset($_GET['id'])) {
    header("Location: list.php");
    exit;
}

$id = (int)$_GET['id'];

/* ============================
   1) Compteurs pour la sidebar
   ============================ */

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

/* ============================
   2) Récup du bien
   ============================ */

$stmt = $pdo->prepare("SELECT * FROM biens WHERE id = ?");
$stmt->execute([$id]);
$bien = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bien) {
    echo "Bien introuvable.";
    exit;
}

// Caractéristiques du bien (JSON → array)
$bienCaracteristiques = json_decode($bien['caracteristiques'] ?? "[]", true);
if (!is_array($bienCaracteristiques)) {
    $bienCaracteristiques = [];
}

// Détails du bien (JSON → array)
$details = json_decode($bien['details'] ?? "{}", true);
if (!is_array($details)) {
    $details = [];
}

// Caractéristiques disponibles
$caracteristiquesOptions = $pdo->query("
    SELECT id, nom, types
    FROM caracteristiques
    ORDER BY nom ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Liste des villes
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

// Liste type de rue
$typeRueOptions = [
    "Piste",
    "Route goudronnée",
    "Rue résidentielle"
];

// Liste type de papier
$typePapierOptions = [
    "Titre foncier individuel",
    "Titre foncier collectif",
    "Contrat seulement",
    "Sans papier"
];

/* ============================
   3) Traitement POST
   ============================ */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $caracteristiques     = $_POST['caracteristiques'] ?? [];
    $caracteristiquesJson = json_encode($caracteristiques);

    $typeBien = $_POST['type'] ?? '';

    // Détails spécifiques par type (même structure que add.php)
    $details = [];

    /* ----- TERRAIN ----- */
    if ($typeBien === 'Terrain') {
        $details['facade_m']          = $_POST['facade'] ?? null;
        $details['surface_m2']        = $_POST['surface'] ?? null;
        $details['type_terrain']      = $_POST['type_terrain'] ?? null;
        $details['zone']              = $_POST['zone_terrain'] ?? null;
        $details['distance_plage_m']  = $_POST['distance_plage'] ?? null;
        $details['type_rue']          = $_POST['type_rue_terrain'] ?? null;
        $details['type_papier']       = $_POST['type_papier_terrain'] ?? null;
    }

    /* ----- APPARTEMENT ----- */
    if ($typeBien === 'Appartement') {
        $details['surface_m2']            = $_POST['surface_appart'] ?? null;
        $details['etage']                 = $_POST['etage_appart'] ?? null;
        $details['configuration']         = $_POST['config_appart'] ?? null;
        $details['nb_sdb']                = $_POST['nb_sdb_appart'] ?? null;
        $details['annee_construction']    = $_POST['annee_construction_appart'] ?? null;
        $details['distance_plage_m']      = $_POST['distance_plage_appart'] ?? null;
        $details['type_rue']              = $_POST['type_rue_appart'] ?? null;
        $details['type_papier']           = $_POST['type_papier_appart'] ?? null;
    }

    /* ----- VILLA ----- */
    if ($typeBien === 'Villa') {
        $details['surface_terrain_m2']    = $_POST['surface_terrain_villa'] ?? null;
        $details['surface_couverte_m2']   = $_POST['surface_couverte_villa'] ?? null;
        $details['nb_etages']             = $_POST['nb_etages_villa'] ?? null;
        $details['nb_sdb']                = $_POST['nb_sdb_villa'] ?? null;
        $details['nb_chambres']           = $_POST['nb_chambres_villa'] ?? null;
        $details['annee_construction']    = $_POST['annee_construction_villa'] ?? null;
        $details['distance_plage_m']      = $_POST['distance_plage_villa'] ?? null;
        $details['type_rue']              = $_POST['type_rue_villa'] ?? null;
        $details['type_papier']           = $_POST['type_papier_villa'] ?? null;
    }

    /* ----- LOCAL COMMERCIAL ----- */
    if ($typeBien === 'Local commercial') {
        $details['surface_m2']           = $_POST['surface_local'] ?? null;
        $details['facade_m']             = $_POST['facade_local'] ?? null;
        $details['hauteur_plafond_m']    = $_POST['hauteur_plafond'] ?? null;
        $details['activite_recommandee'] = $_POST['activite_recommandee'] ?? null;
        $details['type_rue']             = $_POST['type_rue_local'] ?? null;
        $details['type_papier']          = $_POST['type_papier_local'] ?? null;
    }

    /* ----- IMMEUBLE ----- */
    if ($typeBien === 'Immeuble') {
        $details['surface_terrain_m2']    = $_POST['surface_terrain_immeuble'] ?? null;
        $details['surface_totale_m2']     = $_POST['surface_totale_immeuble'] ?? null;
        $details['nb_niveaux']            = $_POST['nb_niveaux_immeuble'] ?? null;
        $details['nb_appartements']       = $_POST['nb_appartements_immeuble'] ?? null;
        $details['nb_locaux_commerciaux'] = $_POST['nb_locaux_immeuble'] ?? null;
        $details['nb_garages']            = $_POST['nb_garages_immeuble'] ?? null;
        $details['type_rue']              = $_POST['type_rue_immeuble'] ?? null;
        $details['type_papier']           = $_POST['type_papier_immeuble'] ?? null;
        $details['note_composition']      = $_POST['note_composition_immeuble'] ?? null;
        $details['distance_plage_m']      = $_POST['distance_plage_immeuble'] ?? null;
    }

    $detailsJson = json_encode($details);

    // UPDATE
    $stmt = $pdo->prepare("
        UPDATE biens
        SET reference = ?,
            titre = ?,
            telephone_proprietaire = ?,
            prix = ?,
            type = ?,
            statut = ?,
            ville = ?,
            chambres = ?,
            caracteristiques = ?,
            details = ?
        WHERE id = ?
    ");

    $stmt->execute([
        htmlspecialchars($_POST['reference']),
        htmlspecialchars($_POST['titre']),
        htmlspecialchars($_POST['telephone_proprietaire']),
        $_POST['prix'],
        htmlspecialchars($typeBien),
        htmlspecialchars($_POST['statut']),
        htmlspecialchars($_POST['ville']),
        $_POST['chambres'],
        $caracteristiquesJson,
        $detailsJson,
        $id
    ]);

    $message = "Bien mis à jour avec succès ✔️";

    // Recharger les données
    $stmt = $pdo->prepare("SELECT * FROM biens WHERE id = ?");
    $stmt->execute([$id]);
    $bien = $stmt->fetch(PDO::FETCH_ASSOC);

    $bienCaracteristiques = json_decode($bien['caracteristiques'] ?? "[]", true);
    if (!is_array($bienCaracteristiques)) $bienCaracteristiques = [];

    $details = json_decode($bien['details'] ?? "{}", true);
    if (!is_array($details)) $details = [];

    // Recalcul des matchs (re-scoring)
    MatchService::matchWithDemandes($pdo, $id);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Éditer un Bien - DWIRA</title>
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
            font-family: Arial, sans-serif;
            background: var(--page-bg);
        }

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

        .sidebar h3 { font-size: 22px; margin-bottom: 25px; }

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
        .sidebar a i { margin-right: 6px; }
        .sidebar a:hover {
            background: var(--sidebar-link-hover-bg);
            color: var(--sidebar-link-hover-text);
            padding-left: 16px;
        }

        .main {
            margin-left: 260px;
            padding: 20px 20px 40px;
        }

        .card {
            border-radius: 14px;
            border: none;
            background: var(--card-bg);
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
        }

        label { font-weight: 500; }
        .bloc-type { display: none; }

        .section-title {
            font-size: 18px;
            font-weight: 600;
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

<div class="sidebar">
    <h3>🏠 DWIRA</h3>
    <a href="../dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
    <a href="list.php"><i class="bi bi-building"></i> Biens</a>
    <a href="../demandes/list.php"><i class="bi bi-person-lines-fill"></i> Demandes</a>
    <a href="../matches/list.php">
        <i class="bi bi-link-45deg"></i> Matchs
        <?php if ($totalMatchesNonVus > 0): ?>
            <span class="badge bg-danger ms-1"><?= $totalMatchesNonVus ?></span>
        <?php endif; ?>
    </a>
    <a href="../visites/list.php"><i class="bi bi-calendar-event"></i> Visites</a>
    <a href="../suivi_commercial/list.php">
        <i class="bi bi-telephone-outbound"></i> Suivi commercial
        <?php if ($totalMatchesToFollow > 0): ?>
            <span class="badge bg-warning text-dark ms-1"><?= $totalMatchesToFollow ?></span>
        <?php endif; ?>
    </a>
    <a href="../caracteristiques/list.php"><i class="bi bi-star"></i> Caractéristiques</a>
    <hr style="border-color:#334155;">
    <a href="../logout.php"><i class="bi bi-door-closed"></i> Logout</a>
</div>

<div class="main">

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h2 class="mb-0">✏️ Éditer le Bien #<?= (int)$bien['id'] ?></h2>
            <div class="small-muted">
                Toute modification mettra à jour le scoring des matchs avec les clients.
            </div>
        </div>
        <div>
            <a href="list.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Retour à la liste
            </a>
        </div>
    </div>

    <?php if($message): ?>
        <div class="alert alert-success"><?= $message ?></div>
    <?php endif; ?>

    <div class="card p-4 mb-4">
        <form method="POST">

            <!-- SECTION 1 : INFOS GÉNÉRALES -->
            <div class="mb-3">
                <span class="section-title">1️⃣ Informations générales</span>
                <hr>
            </div>

            <!-- Référence + Titre -->
            <div class="row mb-3">
                <div class="col-md-4">
                    <label>Référence <span class="text-danger">*</span></label>
                    <input type="text" name="reference" class="form-control" required
                           value="<?= htmlspecialchars($bien['reference']) ?>">
                </div>
                <div class="col-md-8">
                    <label>Titre de l’annonce <span class="text-danger">*</span></label>
                    <input type="text" name="titre" class="form-control" required
                           value="<?= htmlspecialchars($bien['titre']) ?>">
                </div>
            </div>

            <!-- Téléphone propriétaire -->
            <div class="row mb-3">
                <div class="col-md-4">
                    <label>Téléphone Propriétaire <span class="text-danger">*</span></label>
                    <input type="text" 
                           name="telephone_proprietaire" 
                           class="form-control"
                           value="<?= htmlspecialchars($bien['telephone_proprietaire'] ?? '') ?>"
                           required>
                </div>
            </div>

            <!-- Prix + Ville + Chambres -->
            <div class="row mb-3">
                <div class="col-md-4">
                    <label>Prix (DT) <span class="text-danger">*</span></label>
                    <input type="number" name="prix" class="form-control" required min="0"
                           value="<?= htmlspecialchars($bien['prix']) ?>">
                </div>
                <div class="col-md-4">
                    <label>Ville / Zone <span class="text-danger">*</span></label>
                    <select name="ville" class="form-select" required>
                        <option value="">-- Choisir une ville --</option>
                        <?php foreach($villes as $v): ?>
                            <option value="<?= htmlspecialchars($v) ?>"
                                <?= ($bien['ville'] === $v) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($v) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label>Nombre de chambres <span class="text-danger">*</span></label>
                    <input type="number" name="chambres" class="form-control" required min="0"
                           value="<?= (int)$bien['chambres'] ?>">
                </div>
            </div>

            <!-- Type + Statut -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <label>Type de bien <span class="text-danger">*</span></label>
                    <select name="type" class="form-select" id="typeBien">
                        <?php
                            $types = ["Appartement","Villa","Terrain","Local commercial","Immeuble"];
                            foreach ($types as $t):
                        ?>
                            <option value="<?= $t ?>" <?= ($bien['type'] === $t) ? 'selected' : '' ?>>
                                <?= $t ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="small-muted mt-1">
                        Le bloc de détails s’adapte automatiquement au type choisi.
                    </div>
                </div>
                <div class="col-md-6">
                    <label>Statut <span class="text-danger">*</span></label>
                    <select name="statut" class="form-select">
                        <option <?= $bien['statut'] === 'Vente' ? 'selected' : '' ?>>Vente</option>
                        <option <?= $bien['statut'] === 'Location' ? 'selected' : '' ?>>Location</option>
                    </select>
                </div>
            </div>

            <!-- SECTION 2 : DÉTAILS SPÉCIFIQUES -->
            <div class="mb-3">
                <span class="section-title">2️⃣ Détails spécifiques selon le type</span>
                <hr>
            </div>

            <!-- ========== TERRAIN ========== -->
            <div class="bloc-type" data-type="Terrain">
                <h5 class="mt-2 mb-3">⚙️ Détails Terrain</h5>
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label>Façade (m)</label>
                        <input type="number" step="0.01" name="facade" class="form-control"
                               value="<?= htmlspecialchars($details['facade_m'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label>Surface (m²)</label>
                        <input type="number" step="0.01" name="surface" class="form-control"
                               value="<?= htmlspecialchars($details['surface_m2'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label>Type de terrain</label>
                        <select name="type_terrain" class="form-select">
                            <option value="">-- Choisir --</option>
                            <?php
                            $typesTerrain = ["Agricole","Habitation","Industriel","Loisir"];
                            foreach($typesTerrain as $t):
                            ?>
                                <option value="<?= $t ?>"
                                    <?= (($details['type_terrain'] ?? '') === $t) ? 'selected' : '' ?>>
                                    <?= $t ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>Zone</label>
                        <input type="text" name="zone_terrain" class="form-control"
                               value="<?= htmlspecialchars($details['zone'] ?? '') ?>"
                               placeholder="urbaine / touristique...">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label>Distance plage (m)</label>
                        <input type="number" name="distance_plage" class="form-control"
                               value="<?= htmlspecialchars($details['distance_plage_m'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label>Type de rue</label>
                        <select name="type_rue_terrain" class="form-select">
                            <option value="">-- Choisir --</option>
                            <?php foreach($typeRueOptions as $r): ?>
                                <option value="<?= htmlspecialchars($r) ?>"
                                    <?= (($details['type_rue'] ?? '') === $r) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($r) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>Type de papier</label>
                        <select name="type_papier_terrain" class="form-select">
                            <option value="">-- Choisir --</option>
                            <?php foreach($typePapierOptions as $p): ?>
                                <option value="<?= htmlspecialchars($p) ?>"
                                    <?= (($details['type_papier'] ?? '') === $p) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- ========== APPARTEMENT ========== -->
            <div class="bloc-type" data-type="Appartement">
                <h5 class="mt-2 mb-3">⚙️ Détails Appartement</h5>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label>Superficie (m²)</label>
                        <input type="number" step="0.01" name="surface_appart" class="form-control"
                               value="<?= htmlspecialchars($details['surface_m2'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label>Étage</label>
                        <input type="number" name="etage_appart" class="form-control"
                               value="<?= htmlspecialchars($details['etage'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label>Configuration</label>
                        <input type="text" name="config_appart" class="form-control"
                               value="<?= htmlspecialchars($details['configuration'] ?? '') ?>"
                               placeholder="S+2, S+3...">
                    </div>
                    <div class="col-md-3">
                        <label>Nombre de SDB</label>
                        <input type="number" name="nb_sdb_appart" class="form-control"
                               value="<?= htmlspecialchars($details['nb_sdb'] ?? '') ?>">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label>Année construction</label>
                        <input type="number" name="annee_construction_appart" class="form-control"
                               value="<?= htmlspecialchars($details['annee_construction'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label>Type de rue</label>
                        <select name="type_rue_appart" class="form-select">
                            <option value="">-- Choisir --</option>
                            <?php foreach($typeRueOptions as $r): ?>
                                <option value="<?= htmlspecialchars($r) ?>"
                                    <?= (($details['type_rue'] ?? '') === $r) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($r) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>Type de papier</label>
                        <select name="type_papier_appart" class="form-select">
                            <option value="">-- Choisir --</option>
                            <?php foreach($typePapierOptions as $p): ?>
                                <option value="<?= htmlspecialchars($p) ?>"
                                    <?= (($details['type_papier'] ?? '') === $p) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>Distance plage (m)</label>
                        <input type="number" name="distance_plage_appart" class="form-control"
                               value="<?= htmlspecialchars($details['distance_plage_m'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- ========== VILLA ========== -->
            <div class="bloc-type" data-type="Villa">
                <h5 class="mt-2 mb-3">⚙️ Détails Villa</h5>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label>Surface terrain (m²)</label>
                        <input type="number" step="0.01" name="surface_terrain_villa" class="form-control"
                               value="<?= htmlspecialchars($details['surface_terrain_m2'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label>Surface couverte (m²)</label>
                        <input type="number" step="0.01" name="surface_couverte_villa" class="form-control"
                               value="<?= htmlspecialchars($details['surface_couverte_m2'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label>Nombre d'étages</label>
                        <input type="number" name="nb_etages_villa" class="form-control"
                               value="<?= htmlspecialchars($details['nb_etages'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label>Nombre de SDB</label>
                        <input type="number" name="nb_sdb_villa" class="form-control"
                               value="<?= htmlspecialchars($details['nb_sdb'] ?? '') ?>">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label>Nombre de chambres</label>
                        <input type="number" name="nb_chambres_villa" class="form-control"
                               value="<?= htmlspecialchars($details['nb_chambres'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label>Année construction</label>
                        <input type="number" name="annee_construction_villa" class="form-control"
                               value="<?= htmlspecialchars($details['annee_construction'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label>Type de rue</label>
                        <select name="type_rue_villa" class="form-select">
                            <option value="">-- Choisir --</option>
                            <?php foreach($typeRueOptions as $r): ?>
                                <option value="<?= htmlspecialchars($r) ?>"
                                    <?= (($details['type_rue'] ?? '') === $r) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($r) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>Type de papier</label>
                        <select name="type_papier_villa" class="form-select">
                            <option value="">-- Choisir --</option>
                            <?php foreach($typePapierOptions as $p): ?>
                                <option value="<?= htmlspecialchars($p) ?>"
                                    <?= (($details['type_papier'] ?? '') === $p) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label>Distance plage (m)</label>
                        <input type="number" name="distance_plage_villa" class="form-control"
                               value="<?= htmlspecialchars($details['distance_plage_m'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- ========== LOCAL COMMERCIAL ========== -->
            <div class="bloc-type" data-type="Local commercial">
                <h5 class="mt-2 mb-3">⚙️ Détails Local commercial</h5>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label>Surface (m²)</label>
                        <input type="number" step="0.01" name="surface_local" class="form-control"
                               value="<?= htmlspecialchars($details['surface_m2'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label>Façade (m)</label>
                        <input type="number" step="0.01" name="facade_local" class="form-control"
                               value="<?= htmlspecialchars($details['facade_m'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label>Hauteur plafond (m)</label>
                        <input type="number" step="0.01" name="hauteur_plafond" class="form-control"
                               value="<?= htmlspecialchars($details['hauteur_plafond_m'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label>Activité recommandée</label>
                        <input type="text" name="activite_recommandee" class="form-control"
                               value="<?= htmlspecialchars($details['activite_recommandee'] ?? '') ?>"
                               placeholder="café, boutique...">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label>Type de rue</label>
                        <select name="type_rue_local" class="form-select">
                            <option value="">-- Choisir --</option>
                            <?php foreach($typeRueOptions as $r): ?>
                                <option value="<?= htmlspecialchars($r) ?>"
                                    <?= (($details['type_rue'] ?? '') === $r) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($r) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>Type de papier</label>
                        <select name="type_papier_local" class="form-select">
                            <option value="">-- Choisir --</option>
                            <?php foreach($typePapierOptions as $p): ?>
                                <option value="<?= htmlspecialchars($p) ?>"
                                    <?= (($details['type_papier'] ?? '') === $p) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- ========== IMMEUBLE ========== -->
            <div class="bloc-type" data-type="Immeuble">
                <h5 class="mt-2 mb-3">⚙️ Détails Immeuble</h5>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label>Surface terrain (m²)</label>
                        <input type="number" step="0.01" name="surface_terrain_immeuble" class="form-control"
                               value="<?= htmlspecialchars($details['surface_terrain_m2'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label>Surface totale bâtie (m²)</label>
                        <input type="number" step="0.01" name="surface_totale_immeuble" class="form-control"
                               value="<?= htmlspecialchars($details['surface_totale_m2'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label>Nombre de niveaux</label>
                        <input type="number" name="nb_niveaux_immeuble" class="form-control"
                               value="<?= htmlspecialchars($details['nb_niveaux'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label>Nombre d'appartements</label>
                        <input type="number" name="nb_appartements_immeuble" class="form-control"
                               value="<?= htmlspecialchars($details['nb_appartements'] ?? '') ?>">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label>Nombre de locaux commerciaux</label>
                        <input type="number" name="nb_locaux_immeuble" class="form-control"
                               value="<?= htmlspecialchars($details['nb_locaux_commerciaux'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label>Nombre de garages</label>
                        <input type="number" name="nb_garages_immeuble" class="form-control"
                               value="<?= htmlspecialchars($details['nb_garages'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label>Type de rue</label>
                        <select name="type_rue_immeuble" class="form-select">
                            <option value="">-- Choisir --</option>
                            <?php foreach($typeRueOptions as $r): ?>
                                <option value="<?= htmlspecialchars($r) ?>"
                                    <?= (($details['type_rue'] ?? '') === $r) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($r) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>Type de papier</label>
                        <select name="type_papier_immeuble" class="form-select">
                            <option value="">-- Choisir --</option>
                            <?php foreach($typePapierOptions as $p): ?>
                                <option value="<?= htmlspecialchars($p) ?>"
                                    <?= (($details['type_papier'] ?? '') === $p) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label>Distance plage (m)</label>
                        <input type="number" name="distance_plage_immeuble" class="form-control"
                               value="<?= htmlspecialchars($details['distance_plage_m'] ?? '') ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label>Composition (note interne)</label>
                    <textarea name="note_composition_immeuble" class="form-control" rows="2"
                              placeholder="Ex : 2 appartements S+2 + 1 local commercial au RDC..."><?= htmlspecialchars($details['note_composition'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- SECTION 3 : CARACTÉRISTIQUES -->
            <div class="mt-4 mb-2">
                <span class="section-title">3️⃣ Caractéristiques générales</span>
                <div class="small-muted">
                    Les options sont filtrées automatiquement selon le type du bien.
                </div>
                <hr>
            </div>

            <div class="mb-4">
                <div class="d-flex flex-wrap" id="caracs-container">
                    <?php foreach($caracteristiquesOptions as $option): ?>
                        <?php
                            $types = json_decode($option['types'] ?? '[]', true);
                            if (!is_array($types)) $types = [];
                            $typesLabel = empty($types) ? 'Global' : implode(', ', $types);
                            $nomCarac   = $option['nom'];
                        ?>
                        <div class="form-check me-3 mb-2 carac-item"
                             data-types='<?= htmlspecialchars(json_encode($types)) ?>'>
                            <input class="form-check-input" 
                                   type="checkbox" 
                                   name="caracteristiques[]"
                                   value="<?= htmlspecialchars($nomCarac) ?>"
                                   id="carac_<?= $option['id'] ?>"
                                   <?= in_array($nomCarac, $bienCaracteristiques) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="carac_<?= $option['id'] ?>">
                                <?= htmlspecialchars($nomCarac) ?>
                                <small class="text-muted">(<?= htmlspecialchars($typesLabel) ?>)</small>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" class="btn btn-warning">
                <i class="bi bi-pencil-square"></i> Mettre à jour le bien
            </button>

        </form>
    </div>
</div>

<script>
    function refreshTypeBlocks() {
        const type = document.getElementById('typeBien').value;

        // Afficher seulement le bloc du type choisi
        document.querySelectorAll('.bloc-type').forEach(function(div) {
            div.style.display = (div.dataset.type === type) ? 'block' : 'none';
        });

        // Filtrer les caractéristiques (globales ou liées à ce type)
        document.querySelectorAll('.carac-item').forEach(function(item) {
            const json = item.dataset.types || '[]';
            let types = [];
            try {
                types = JSON.parse(json);
            } catch (e) {
                types = [];
            }
            if (!Array.isArray(types)) {
                types = [];
            }

            const isGlobal  = (types.length === 0);
            const isForType = types.includes(type);

            if (isGlobal || isForType) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
                const checkbox = item.querySelector('input[type="checkbox"]');
                if (checkbox && checkbox.checked) {
                    checkbox.checked = false;
                }
            }
        });
    }

    document.getElementById('typeBien').addEventListener('change', refreshTypeBlocks);
    document.addEventListener('DOMContentLoaded', refreshTypeBlocks);
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script src='/Dwira/assets/js/admin-unified.js?v=202602247'></script>
</body>
</html>










