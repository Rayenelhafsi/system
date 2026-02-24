<?php
require_once "../config/db.php";
session_start();

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Identifiants incorrects";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion - DWIRA</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        :root{
            --bg1:#0f172a;
            --bg2:#1d4ed8;
            --card-bg:rgba(15,23,42,0.85);
            --accent:#38bdf8;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            font-family: system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
            background: radial-gradient(circle at top left, #1d4ed8 0, #0f172a 45%, #020617 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color:#e5e7eb;
        }

        .login-wrapper {
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }

        .login-card {
            background: var(--card-bg);
            border-radius: 20px;
            box-shadow: 0 18px 50px rgba(0,0,0,0.55);
            border: 1px solid rgba(148,163,184,0.2);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            padding: 26px 24px 24px;
            position: relative;
            overflow: hidden;
        }

        .login-card::before{
            content:"";
            position:absolute;
            inset:-40%;
            background:
                radial-gradient(circle at 0 0, rgba(56,189,248,0.18) 0, transparent 55%),
                radial-gradient(circle at 100% 0, rgba(129,140,248,0.18) 0, transparent 55%);
            opacity:0.6;
            pointer-events:none;
        }

        .login-card-inner{
            position:relative;
            z-index:1;
        }

        .brand-badge{
            width:54px;
            height:54px;
            border-radius:18px;
            background: radial-gradient(circle at 30% 10%, #facc15 0, #f97316 35%, #dc2626 100%);
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:24px;
            box-shadow:0 10px 25px rgba(0,0,0,0.45);
        }

        .brand-title{
            font-weight:700;
            font-size:22px;
            letter-spacing:0.03em;
        }

        .brand-sub{
            font-size:13px;
            color:#9ca3af;
        }

        .form-label{
            font-size:13px;
            color:#cbd5f5;
        }

        .form-control{
            background:rgba(15,23,42,0.9);
            border-radius:12px;
            border:1px solid rgba(148,163,184,0.5);
            color:#e5e7eb;
            font-size:14px;
            padding-left:40px;
        }

        .form-control:focus{
            background:rgba(15,23,42,0.95);
            border-color: var(--accent);
            box-shadow:0 0 0 1px rgba(56,189,248,0.4);
            color:#e5e7eb;
        }

        .input-icon{
            position:absolute;
            left:12px;
            top:50%;
            transform:translateY(-50%);
            color:#64748b;
            font-size:16px;
        }

        .password-toggle{
            position:absolute;
            right:10px;
            top:50%;
            transform:translateY(-50%);
            border:none;
            background:transparent;
            color:#64748b;
            cursor:pointer;
            font-size:16px;
        }

        .btn-login{
            width:100%;
            border-radius:14px;
            background:linear-gradient(135deg,#38bdf8,#6366f1);
            border:none;
            color:white;
            font-weight:600;
            font-size:14px;
            padding:10px 0;
            box-shadow:0 12px 30px rgba(56,189,248,0.45);
            transition: all .15s ease-out;
        }

        .btn-login:hover{
            transform:translateY(-1px);
            box-shadow:0 18px 40px rgba(56,189,248,0.65);
            filter:brightness(1.03);
        }

        .btn-login:active{
            transform:translateY(1px) scale(0.99);
            box-shadow:0 10px 22px rgba(15,23,42,0.8);
        }

        .footer-text{
            font-size:11px;
            color:#6b7280;
            text-align:center;
            margin-top:10px;
        }

        .footer-text span{
            color:#e5e7eb;
        }

        .error-alert{
            font-size:13px;
            padding:8px 10px;
            border-radius:10px;
        }

        @media (max-width: 480px){
            .login-card{
                padding:22px 18px 20px;
                border-radius:18px;
            }
            .brand-title{ font-size:20px; }
        }
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="login-card">
        <div class="login-card-inner">
            <!-- Header / Brand -->
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="brand-badge">
                        <span>DW</span>
                    </div>
                    <div>
                        <div class="brand-title">DWIRA Back-Office</div>
                        <div class="brand-sub">Espace agence immobilière</div>
                    </div>
                </div>
                <i class="bi bi-shield-lock-fill text-info fs-4"></i>
            </div>

            <div class="mb-3">
                <h5 class="mb-1">Connexion</h5>
                <p class="mb-0" style="font-size:13px;color:#9ca3af;">
                    Connectez-vous pour gérer vos biens, demandes et visites.
                </p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger error-alert d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off" class="mt-2">
                <!-- Username -->
                <div class="mb-3">
                    <label class="form-label" for="username">Nom d’utilisateur</label>
                    <div class="position-relative">
                        <span class="input-icon">
                            <i class="bi bi-person"></i>
                        </span>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            class="form-control"
                            placeholder="Ex : admin"
                            required
                        >
                    </div>
                </div>

                <!-- Password -->
                <div class="mb-2">
                    <label class="form-label" for="password">Mot de passe</label>
                    <div class="position-relative">
                        <span class="input-icon">
                            <i class="bi bi-lock"></i>
                        </span>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-control"
                            placeholder="Votre mot de passe"
                            required
                        >
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <i class="bi bi-eye-slash" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3" style="font-size:12px;">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="" id="rememberMe" disabled>
                        <label class="form-check-label text-secondary" for="rememberMe">
                            Garder la session
                        </label>
                    </div>
                    <span class="text-secondary">
                        <i class="bi bi-lock-fill me-1"></i>Connexion sécurisée
                    </span>
                </div>

                <button type="submit" class="btn btn-login">
                    Se connecter
                </button>
            </form>

            <div class="footer-text">
                © <?= date('Y') ?> <span>DWIRA</span> · Panel interne
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword(){
    const input = document.getElementById('password');
    const icon  = document.getElementById('eyeIcon');
    if(input.type === 'password'){
        input.type = 'text';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    } else {
        input.type = 'password';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    }
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
