<?php
require_once "../../config/auth.php";
require_once "../../config/db.php";
require_once "../../services/MatchService.php";

$message = "";

/* =========================
   STATS POUR SIDEBAR
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
   CARACTÉRISTIQUES
   (colonne `types` JSON)
========================= */

$caracteristiquesOptions = $pdo->query("
    SELECT id, nom, types
    FROM caracteristiques
    ORDER BY nom ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Liste des villes (même liste que pour les biens)
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

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Caractéristiques souhaitées par le client (badges)
    $caracteristiques = $_POST['caracteristiques'] ?? [];
    $caracteristiquesJson = json_encode($caracteristiques);

    $typeDemande = $_POST['type'] ?? '';

    // ================== DÉTAILS CRITÈRES PAR TYPE ================== //
    $details = [];

    /* ----- DEMANDE TERRAIN ----- */
    if ($typeDemande === 'Terrain') {
        $details['surface_min_m2']       = $_POST['surface_min_terrain']     ?? null;
        $details['surface_max_m2']       = $_POST['surface_max_terrain']     ?? null;
        $details['facade_min_m']         = $_POST['facade_min_terrain']      ?? null;
        $details['type_terrain']         = $_POST['type_terrain']            ?? null;
        $details['zone_souhaitee']       = $_POST['zone_terrain']            ?? null;

        $details['distance_plage_max_m'] = $_POST['distance_plage_max_terrain'] ?? null;
        $details['type_rue']             = $_POST['type_rue_terrain']        ?? null;
        $details['type_papier']          = $_POST['type_papier_terrain']     ?? null;

        $details['constructible_souhaite'] = isset($_POST['constructible_terrain']);
        $details['coin_angle_souhaite']    = isset($_POST['coin_angle_terrain']);
    }

    /* ----- DEMANDE APPARTEMENT ----- */
    if ($typeDemande === 'Appartement') {
        $details['surface_min_m2']       = $_POST['surface_min_appart']      ?? null;
        $details['surface_max_m2']       = $_POST['surface_max_appart']      ?? null;
        $details['etage_min']            = $_POST['etage_min_appart']        ?? null;
        $details['etage_max']            = $_POST['etage_max_appart']        ?? null;
        $details['config_souhaitee']     = $_POST['config_appart']           ?? null; // S+2, S+3...
        $details['nb_sdb_min']           = $_POST['nb_sdb_min_appart']       ?? null;

        $details['annee_min']            = $_POST['annee_min_appart']        ?? null;
        $details['annee_max']            = $_POST['annee_max_appart']        ?? null;

        $details['type_rue']             = $_POST['type_rue_appart']         ?? null;
        $details['type_papier']          = $_POST['type_papier_appart']      ?? null;
        $details['distance_plage_max_m'] = $_POST['distance_plage_max_appart'] ?? null;
    }

    /* ----- DEMANDE VILLA ----- */
    if ($typeDemande === 'Villa') {
        $details['surface_terrain_min_m2']  = $_POST['surface_terrain_min_villa']   ?? null;
        $details['surface_terrain_max_m2']  = $_POST['surface_terrain_max_villa']   ?? null;
        $details['surface_couverte_min_m2'] = $_POST['surface_couverte_min_villa']  ?? null;
        $details['surface_couverte_max_m2'] = $_POST['surface_couverte_max_villa']  ?? null;

        $details['nb_chambres_min']         = $_POST['nb_chambres_min_villa']       ?? null;
        $details['nb_sdb_min']              = $_POST['nb_sdb_min_villa']            ?? null;
        $details['nb_etages_min']           = $_POST['nb_etages_min_villa']         ?? null;
        $details['nb_etages_max']           = $_POST['nb_etages_max_villa']         ?? null;

        $details['annee_min']               = $_POST['annee_min_villa']             ?? null;
        $details['annee_max']               = $_POST['annee_max_villa']             ?? null;

        $details['type_rue']                = $_POST['type_rue_villa']              ?? null;
        $details['type_papier']             = $_POST['type_papier_villa']           ?? null;
        $details['distance_plage_max_m']    = $_POST['distance_plage_max_villa']    ?? null;
    }

    /* ----- DEMANDE LOCAL COMMERCIAL ----- */
    if ($typeDemande === 'Local commercial') {
        $details['surface_min_m2']        = $_POST['surface_min_local']    ?? null;
        $details['surface_max_m2']        = $_POST['surface_max_local']    ?? null;
        $details['facade_min_m']          = $_POST['facade_min_local']     ?? null;
        $details['hauteur_min_m']         = $_POST['hauteur_min_local']    ?? null;
        $details['hauteur_max_m']         = $_POST['hauteur_max_local']    ?? null;
        $details['activite_souhaitee']    = $_POST['activite_recommandee'] ?? null;

        $details['type_rue']              = $_POST['type_rue_local']       ?? null;
        $details['type_papier']           = $_POST['type_papier_local']    ?? null;
    }

    /* ----- DEMANDE IMMEUBLE ----- */
    if ($typeDemande === 'Immeuble') {
        $details['surface_terrain_min_m2']  = $_POST['surface_terrain_min_immeuble']   ?? null;
        $details['surface_terrain_max_m2']  = $_POST['surface_terrain_max_immeuble']   ?? null;

        $details['surface_batie_min_m2']    = $_POST['surface_batie_min_immeuble']     ?? null;
        $details['surface_batie_max_m2']    = $_POST['surface_batie_max_immeuble']     ?? null;

        $details['nb_niveaux_min']          = $_POST['nb_niveaux_min_immeuble']        ?? null;
        $details['nb_niveaux_max']          = $_POST['nb_niveaux_max_immeuble']        ?? null;

        $details['nb_appartements_min']     = $_POST['nb_appartements_min']            ?? null;
        $details['nb_appartements_max']     = $_POST['nb_appartements_max']            ?? null;

        $details['nb_locaux_min']           = $_POST['nb_locaux_min']                  ?? null;
        $details['nb_locaux_max']           = $_POST['nb_locaux_max']                  ?? null;

        $details['nb_garages_min']          = $_POST['nb_garages_min']                 ?? null;
        $details['nb_garages_max']          = $_POST['nb_garages_max']                 ?? null;

        $details['distance_plage_max_m']    = $_POST['distance_plage_max_immeuble']    ?? null;
        $details['type_rue']                = $_POST['type_rue_immeuble']              ?? null;
        $details['type_papier']             = $_POST['type_papier_immeuble']           ?? null;
    }

    $detailsJson = json_encode($details);

    $stmt = $pdo->prepare(
        "INSERT INTO clients_demandes (
            nom, 
            telephone, 
            budget_max, 
            type_bien, 
            statut, 
            ville, 
            chambres_min,
            caracteristiques,
            details
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $stmt->execute([
        htmlspecialchars($_POST['nom']),
        htmlspecialchars($_POST['telephone']),
        $_POST['budget'],
        htmlspecialchars($typeDemande),
        htmlspecialchars($_POST['statut']),
        htmlspecialchars($_POST['ville']),
        $_POST['chambres'],
        $caracteristiquesJson,
        $detailsJson
    ]);

    $demandeId = $pdo->lastInsertId();

    // Matching avec tous les biens existants
    MatchService::matchWithBiens($pdo, $demandeId);

    $message = "Demande ajoutée avec succès ✔️";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajouter une Demande - DWIRA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { font-family: Arial, sans-serif; background: #f1f4f9; }
        .sidebar {
            width: 250px;
            float: left;
            height: 100vh;
            background: #222;
            color: white;
            padding: 20px;
        }
        .sidebar h3 { margin-bottom: 20px; }
        .sidebar a {
            color: white;
            display: block;
            margin-bottom: 10px;
            text-decoration: none;
            transition: 0.3s;
            padding: 6px 8px;
            border-radius: 6px;
        }
        .sidebar a:hover {
            background: #444;
            padding-left: 12px;
        }
        .main { margin-left: 260px; padding: 20px; }
        .card { border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); border:none; }
        label { font-weight: 500; }
        .bloc-type { display: none; }
        .badge-sidebar {
            font-size: 11px;
            vertical-align: middle;
        }
    </style>

<link rel='stylesheet' href='/Dwira/assets/css/admin-unified.css?v=202602248'>
</head>
<body>

<div class="sidebar">
    <h3>🏠 DWIRA</h3>

    <a href="../dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>

    <a href="../biens/list.php"><i class="bi bi-building"></i> Biens</a>

    <a href="list.php"><i class="bi bi-person-lines-fill"></i> Demandes</a>

    <a href="../matches/list.php">
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

    <hr style="border-color: #444;">

    <a href="../logout.php"><i class="bi bi-door-closed"></i> Logout</a>
</div>

<div class="main">
    <h2>➕ Ajouter une Demande</h2>
    <a href="list.php" class="btn btn-secondary mb-3">
        <i class="bi bi-arrow-left"></i> Retour à la liste
    </a>

    <?php if($message): ?>
        <div class="alert alert-success"><?= $message ?></div>
    <?php endif; ?>

    <div class="card p-4">
        <form method="POST">
            <!-- Nom + Téléphone -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <label>Nom client</label>
                    <input type="text" name="nom" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label>Téléphone</label>
                    <input type="text" name="telephone" class="form-control" required>
                </div>
            </div>

            <!-- Budget + Ville + Chambres min -->
            <div class="row mb-3">
                <div class="col-md-4">
                    <label>Budget max (DT)</label>
                    <input type="number" name="budget" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label>Ville souhaitée</label>
                    <select name="ville" class="form-select" required>
                        <option value="">-- Choisir une ville --</option>
                        <?php foreach($villes as $v): ?>
                            <option value="<?= htmlspecialchars($v) ?>"><?= htmlspecialchars($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label>Chambres minimum</label>
                    <input type="number" name="chambres" class="form-control" required>
                </div>
            </div>

            <!-- Type + Statut -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <label>Type de bien</label>
                    <select name="type" class="form-select" id="typeDemande">
                        <option>Appartement</option>
                        <option>Villa</option>
                        <option>Terrain</option>
                        <option>Local commercial</option>
                        <option>Immeuble</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label>Statut</label>
                    <select name="statut" class="form-select">
                        <option>Vente</option>
                        <option>Location</option>
                    </select>
                </div>
            </div>

            <!-- =================== CRITÈRES TERRAIN =================== -->
            <div class="bloc-type" data-type="Terrain">
                <h5 class="mt-3">⚙️ Critères Terrain souhaité</h5>
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label>Surface min (m²)</label>
                        <input type="number" step="0.01" name="surface_min_terrain" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label>Surface max (m²)</label>
                        <input type="number" step="0.01" name="surface_max_terrain" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label>Façade min (m)</label>
                        <input type="number" step="0.01" name="facade_min_terrain" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label>Type de terrain</label>
                        <select name="type_terrain" class="form-select">
                            <option value="">-- Choisir --</option>
                            <option value="Agricole">Agricole</option>
                            <option value="Habitation">Habitation</option>
                            <option value="Industriel">Industriel</option>
                            <option value="Loisir">Loisir</option>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label>Distance plage max (m)</label>
                        <input type="number" name="distance_plage_max_terrain" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label>Type de rue</label>
                        <select name="type_rue_terrain" class="form-select">
                            <option value="">-- Choisir --</option>
                            <?php foreach($typeRueOptions as $r): ?>
                                <option value="<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($r) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>Type de papier</label>
                        <select name="type_papier_terrain" class="form-select">
                            <option value="">-- Choisir --</option>
                            <?php foreach($typePapierOptions as $p): ?>
                                <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex flex-column justify-content-end">
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" name="constructible_terrain" id="constructible_terrain">
                            <label class="form-check-label" for="constructible_terrain">Constructible</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="coin_angle_terrain" id="coin_angle_terrain">
                            <label class="form-check-label" for="coin_angle_terrain">Terrain d'angle</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- =================== CRITÈRES APPARTEMENT =================== -->
            <div class="bloc-type" data-type="Appartement">
                <h5 class="mt-3">⚙️ Critères Appartement souhaité</h5>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label>Superficie min (m²)</label>
                        <input type="number" step="0.01" name="surface_min_appart" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label>Superficie max (m²)</label>
                        <input type="number" step="0.01" name="surface_max_appart" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label>Étage min</label>
                        <input type="number" name="etage_min_appart" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label>Étage max</label>
                        <input type="number" name="etage_max_appart" class="form-control">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label>Configuration</label>
                        <input type="text" name="config_appart" class="form-control" placeholder="S+2, S+3...">
                    </div>
                    <div class="col-md-3">
                        <label>Nombre SDB min</label>
                        <input type="number" name="nb_sdb_min_appart" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label>Année min</label>
                        <input type="number" name="annee_min_appart" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label>Année max</label>
                        <input type="number" name="annee_max_appart" class="form-control">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label>Type de rue</label>
                        <select name="type_rue_appart" class="form-select">
                            <option value="">-- Choisir --</option>
                            <?php foreach($typeRueOptions as $r): ?>
                                <option value="<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($r) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>Type de papier</label>
                        <select name="type_papier_appart" class="form-select">
                            <option value="">-- Choisir --</option>
                            <?php foreach($typePapierOptions as $p): ?>
                                <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>Distance plage max (m)</label>
                        <input type="number" name="distance_plage_max_appart" class="form-control">
                    </div>
                </div>
            </div>

            <!-- =================== CRITÈRES VILLA =================== -->
            <div class="bloc-type" data-type="Villa">
                <h5 class="mt-3">⚙️ Critères Villa souhaitée</h5>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label>Surface terrain min (m²)</label>
                        <input type="number" step="0.01" name="surface_terrain_min_villa" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label>Surface terrain max (m²)</label>
                        <input type="number" step="0.01" name="surface_terrain_max_villa" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label>Surface couverte min (m²)</label>
                        <input type="number" step="0.01" name="surface_couverte_min_villa" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label>Surface couverte max (m²)</label>
                        <input type="number" step="0.01" name="surface_couverte_max_villa" class="form-control">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label>Chambres min</label>
                        <input type="number" name="nb_chambres_min_villa" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label>SDB min</label>
                        <input type="number" name="nb_sdb_min_villa" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label>Étages min</label>
                        <input type="number" name="nb_etages_min_villa" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label>Étages max</label>
                        <input type="number" name="nb_etages_max_villa" class="form-control">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label>Année min</label>
                        <input type="number" name="annee_min_villa" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label>Année max</label>
                        <input type="number" name="annee_max_villa" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label>Type de rue</label>
                        <select name="type_rue_villa" class="form-select">
                            <option value="">-- Choisir --</option>
                            <?php foreach($typeRueOptions as $r): ?>
                                <option value="<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($r) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>Type de papier</label>
                        <select name="type_papier_villa" class="form-select">
                            <option value="">-- Choisir --</option>
                            <?php foreach($typePapierOptions as $p): ?>
                                <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label>Distance plage max (m)</label>
                        <input type="number" name="distance_plage_max_villa" class="form-control">
                    </div>
                </div>
            </div>

            <!-- =================== CRITÈRES LOCAL COMMERCIAL =================== -->
            <div class="bloc-type" data-type="Local commercial">
                <h5 class="mt-3">⚙️ Critères Local commercial souhaité</h5>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label>Surface min (m²)</label>
                        <input type="number" step="0.01" name="surface_min_local" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label>Surface max (m²)</label>
                        <input type="number" step="0.01" name="surface_max_local" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label>Façade min (m)</label>
                        <input type="number" step="0.01" name="facade_min_local" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label>Hauteur min (m)</label>
                        <input type="number" step="0.01" name="hauteur_min_local" class="form-control">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label>Hauteur max (m)</label>
                        <input type="number" step="0.01" name="hauteur_max_local" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label>Activité souhaitée</label>
                        <input type="text" name="activite_recommandee" class="form-control" placeholder="café, boutique...">
                    </div>
                    <div class="col-md-3">
                        <label>Type de rue</label>
                        <select name="type_rue_local" class="form-select">
                            <option value="">-- Choisir --</option>
                            <?php foreach($typeRueOptions as $r): ?>
                                <option value="<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($r) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>Type de papier</label>
                        <select name="type_papier_local" class="form-select">
                            <option value="">-- Choisir --</option>
                            <?php foreach($typePapierOptions as $p): ?>
                                <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- =================== CRITÈRES IMMEUBLE =================== -->
            <div class="bloc-type" data-type="Immeuble">
                <h5 class="mt-3">⚙️ Critères Immeuble souhaité</h5>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label>Surface terrain min (m²)</label>
                        <input type="number" step="0.01" name="surface_terrain_min_immeuble" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label>Surface terrain max (m²)</label>
                        <input type="number" step="0.01" name="surface_terrain_max_immeuble" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label>Surface bâtie min (m²)</label>
                        <input type="number" step="0.01" name="surface_batie_min_immeuble" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label>Surface bâtie max (m²)</label>
                        <input type="number" step="0.01" name="surface_batie_max_immeuble" class="form-control">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label>Niveaux min</label>
                        <input type="number" name="nb_niveaux_min_immeuble" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label>Niveaux max</label>
                        <input type="number" name="nb_niveaux_max_immeuble" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label>Appartements min</label>
                        <input type="number" name="nb_appartements_min" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label>Appartements max</label>
                        <input type="number" name="nb_appartements_max" class="form-control">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label>Locaux commerciaux min</label>
                        <input type="number" name="nb_locaux_min" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label>Locaux commerciaux max</label>
                        <input type="number" name="nb_locaux_max" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label>Garages min</label>
                        <input type="number" name="nb_garages_min" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label>Garages max</label>
                        <input type="number" name="nb_garages_max" class="form-control">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label>Distance plage max (m)</label>
                        <input type="number" name="distance_plage_max_immeuble" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label>Type de rue</label>
                        <select name="type_rue_immeuble" class="form-select">
                            <option value="">-- Choisir --</option>
                            <?php foreach($typeRueOptions as $r): ?>
                                <option value="<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($r) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>Type de papier</label>
                        <select name="type_papier_immeuble" class="form-select">
                            <option value="">-- Choisir --</option>
                            <?php foreach($typePapierOptions as $p): ?>
                                <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Caractéristiques souhaitées (basées sur la colonne `types`) -->
            <div class="mb-3">
                <label>Caractéristiques souhaitées</label>
                <div class="d-flex flex-wrap" id="caracs-container-demande">
                    <?php foreach($caracteristiquesOptions as $option): ?>
                        <?php
                            $typesList = [];
                            if (!empty($option['types'])) {
                                $decoded = json_decode($option['types'], true);
                                if (is_array($decoded)) {
                                    $typesList = $decoded;
                                }
                            }
                            $dataTypes = !empty($typesList) ? implode(',', $typesList) : '';
                        ?>
                        <div class="form-check me-3 mb-2 carac-item"
                             data-types="<?= htmlspecialchars($dataTypes) ?>">
                            <input class="form-check-input"
                                   type="checkbox"
                                   name="caracteristiques[]"
                                   value="<?= htmlspecialchars($option['nom']) ?>"
                                   id="carac_<?= $option['id'] ?>">
                            <label class="form-check-label" for="carac_<?= $option['id'] ?>">
                                <?= htmlspecialchars($option['nom']) ?>
                                <?php if(!empty($typesList)): ?>
                                    <small class="text-muted">(<?= htmlspecialchars(implode(', ', $typesList)) ?>)</small>
                                <?php else: ?>
                                    <small class="text-muted">(Global)</small>
                                <?php endif; ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <small class="text-muted">
                    Affiche les caractéristiques compatibles avec le type choisi,
                    plus celles <strong>Global</strong>.
                </small>
            </div>

            <button type="submit" class="btn btn-success">
                <i class="bi bi-save"></i> Enregistrer la demande
            </button>
        </form>
    </div>
</div>

<script>
    function refreshTypeBlocksDemande() {
        const type = document.getElementById('typeDemande').value;

        // Afficher / cacher les blocs de critères
        document.querySelectorAll('.bloc-type').forEach(function(div) {
            div.style.display = (div.dataset.type === type) ? 'block' : 'none';
        });

        // Filtrer les caractéristiques selon data-types (colonne `types`)
        document.querySelectorAll('.carac-item').forEach(function(item) {
            const raw = item.getAttribute('data-types') || '';
            const allowed = raw
                .split(',')
                .map(s => s.trim())
                .filter(s => s.length > 0);

            // Global = pas de types -> visible partout
            if (allowed.length === 0 || allowed.includes(type)) {
                item.style.display = 'inline-block';
            } else {
                item.style.display = 'none';
                const checkbox = item.querySelector('input[type="checkbox"]');
                if (checkbox) checkbox.checked = false;
            }
        });
    }

    document.getElementById('typeDemande').addEventListener('change', refreshTypeBlocksDemande);
    document.addEventListener('DOMContentLoaded', refreshTypeBlocksDemande);
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script src='/Dwira/assets/js/admin-unified.js?v=202602247'></script>
</body>
</html>










