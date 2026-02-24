<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /Dwira/admin/login.php");
    exit;
}
