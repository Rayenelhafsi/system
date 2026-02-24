<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>DWIRA - Gestion Immobilière</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Style interne -->
    <style>
        body {
            overflow-x: hidden;
        }
        .sidebar {
            height: 100vh;
            position: fixed;
            width: 240px;
            background: #1e1e2f;
            color: white;
        }
        .sidebar a {
            color: #ccc;
            display: block;
            padding: 12px 20px;
            text-decoration: none;
        }
        .sidebar a:hover {
            background: #343a40;
            color: white;
        }
        .content {
            margin-left: 240px;
            padding: 20px;
        }
        .navbar-brand {
            font-weight: bold;
        }
    </style>
</head>

<body>

<!-- Top Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <span class="navbar-brand">🏠 DWIRA</span>

        <div class="d-flex text-white">
            Bonjour, <?= $_SESSION['username'] ?? 'Admin' ?>
            <a href="../logout.php" class="btn btn-sm btn-danger ms-3">Déconnexion</a>
        </div>
    </div>
</nav>
