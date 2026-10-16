<?php
session_start();
if (isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit;
}
$error = isset($_GET['error']) ? $_GET['error'] : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - GeoPortal Módulo BCI</title>
    <!-- Google Fonts (Inter) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body,
        html {
            height: 100%;
            background-color: var(--bg-color);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: auto;
        }

        .login-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 400px;
            padding: 40px 30px;
            animation: fadeIn 0.4s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 8px;
        }

        .login-header h1 span {
            color: var(--accent-color);
        }

        .login-header p {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .form-group {
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group label {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-muted);
        }

        .form-control {
            background-color: var(--sidebar-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-color);
            padding: 12px 16px;
            font-size: 0.9rem;
            outline: none;
            transition: border-color 0.2s;
            width: 100%;
        }

        .form-control:focus {
            border-color: var(--accent-color);
        }

        .btn-submit {
            background-color: var(--accent-color);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
            width: 100%;
            margin-top: 10px;
        }

        .btn-submit:hover {
            background-color: var(--accent-hover);
        }

        .error-alert {
            background-color: rgba(239, 68, 68, 0.15);
            border: 1px solid #ef4444;
            color: #f87171;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>

<body>

    <div class="login-card">
        <div class="login-header">
            <h1>GeoPortal <span>Módulo BCI</span></h1>
            <p>Insira suas credenciais para acessar o sistema</p>
        </div>

        <?php if ($error): ?>
            <div class="error-alert">
                <?php
                if ($error == 'invalid') {
                    echo "Usuário ou senha incorretos.";
                } elseif ($error == 'blocked') {
                    echo "Este usuário está bloqueado.";
                } else {
                    echo "Acesso restrito. Faça login primeiro.";
                }
                ?>
            </div>
        <?php endif; ?>

        <form action="login_action.php" method="POST">
            <div class="form-group">
                <label for="username">Usuário</label>
                <input type="text" id="username" name="username" class="form-control" placeholder="Digite seu usuário"
                    required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Senha</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Digite sua senha"
                    required>
            </div>

            <button type="submit" class="btn-submit">Entrar no Sistema</button>
        </form>
    </div>

</body>

</html>