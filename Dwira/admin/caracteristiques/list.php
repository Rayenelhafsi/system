<?php
require_once "../../config/auth.php";
require_once "../../config/db.php";

/* ========================
   FILTRES / RECHERCHE
======================== */
$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 10;
$offset = ($page - 1) * $limit;

$where  = "";
$params = [];

if ($search !== '') {
    $where = "WHERE nom LIKE :search";
    $params[':search'] = "%$search%";
}

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
   TOTAL & PAGINATION
======================== */

$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM caracteristiques $where");
$stmtTotal->execute($params);
$total = (int)$stmtTotal->fetchColumn();

$totalPages = max(1, (int)ceil($total / $limit));

/* ========================
   DATA
======================== */

$sql = "
    SELECT id, nom, types
    FROM caracteristiques
    $where
    ORDER BY nom ASC
    LIMIT $limit OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$caracts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Caractéristiques - DWIRA</title>

<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

<style>
body {
    background:#f1f4f9;
    font-family: Arial, sans-serif;
}

/* SIDEBAR */
.sidebar {
    width:250px;
    float:left;
    height:100vh;
    background:#111827;
    color:white;
    padding:20px;
}
.sidebar h3 {
    font-size:22px;
    margin-bottom:22px;
}
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
    color:white;
    padding-left:16px;
}

/* MAIN */
.main {
    margin-left:260px;
    padding:30px 25px 40px;
}

.card-modern {
    background:#ffffff;
    border-radius:18px;
    box-shadow:0 10px 25px rgba(0,0,0,.05);
    border:none;
}

.table thead {
    background:linear-gradient(45deg,#2563eb,#1e40af);
    color:white;
}
.badge-count {
    font-size:14px;
}
.badge-type {
    background:#e5e7eb;
    color:#111827;
    font-size:11px;
    margin-right:4px;
}
.badge-type-global {
    background:#0ea5e9;
    font-size:11px;
}
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
        <h2 class="fw-bold mb-2 mb-md-0">⚙️ Caractéristiques</h2>

        <div class="d-flex align-items-center">
            <span class="badge bg-dark badge-count me-2">
                <?= $total ?> au total
            </span>

            <a href="add.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Ajouter
            </a>
        </div>
    </div>

    <!-- RECHERCHE -->
    <div class="card card-modern p-3 mb-4">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-10 col-12">
                <input
                    type="text"
                    name="search"
                    class="form-control"
                    placeholder="Rechercher une caractéristique..."
                    value="<?= htmlspecialchars($search) ?>"
                >
            </div>
            <div class="col-md-2 col-12 mt-2 mt-md-0">
                <button class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> Rechercher
                </button>
            </div>
        </form>
    </div>

    <!-- TABLE -->
    <div class="card card-modern p-3">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:70px;">ID</th>
                        <th>Nom</th>
                        <th style="width:280px;">Types de biens concernés</th>
                        <th class="text-center" style="width:160px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(count($caracts)): ?>
                    <?php foreach($caracts as $c): ?>
                        <?php
                            $types = json_decode($c['types'] ?? '[]', true);
                            if (!is_array($types)) $types = [];
                        ?>
                        <tr>
                            <td><?= (int)$c['id'] ?></td>

                            <td class="fw-semibold">
                                <i class="bi bi-check2-circle text-primary me-2"></i>
                                <?= htmlspecialchars($c['nom']) ?>
                            </td>

                            <td>
                                <?php if (!count($types)): ?>
                                    <span class="badge badge-type-global">
                                        Global (tous types)
                                    </span>
                                <?php else: ?>
                                    <?php foreach($types as $t): ?>
                                        <span class="badge badge-type">
                                            <?= htmlspecialchars($t) ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>

                            <td class="text-center">
                                <a href="edit.php?id=<?= (int)$c['id'] ?>"
                                   class="btn btn-sm btn-warning me-1">
                                    <i class="bi bi-pencil"></i>
                                </a>

                                <a href="delete.php?id=<?= (int)$c['id'] ?>"
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Supprimer cette caractéristique ?');">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted py-3">
                            Aucune caractéristique trouvée.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- PAGINATION -->
        <nav class="mt-3">
            <ul class="pagination justify-content-center mb-0">
                <?php for($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link"
                           href="?page=<?= $i ?>&search=<?= urlencode($search) ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script src='/Dwira/assets/js/admin-unified.js?v=202602247'></script>
</body>
</html>










