<?php
require_once "../../config/auth.php";
require_once "../../config/db.php";

if (!isset($_GET['id'])) die("ID manquant");

$id = $_GET['id'];
$pdo->prepare("DELETE FROM caracteristiques WHERE id=?")->execute([$id]);

header("Location: list.php");
exit;
