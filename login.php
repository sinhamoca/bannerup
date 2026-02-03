<?php
session_start();
require_once 'db/configdb.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8",
            DB_USER,
            DB_PASS
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->prepare("SELECT id, username, password, nivel FROM usuarios WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION["user_id"] = $user['id'];
            $_SESSION["usuario"] = $user['username'];
            $_SESSION["nivel"] = $user['nivel'];
            header("Location: index.php");
            exit();
        } else {
            header("Location: login.php?error=invalid_credentials");
            exit();
        }
    } catch (PDOException $e) {
        error_log(date('Y-m-d H:i:s') . " - Erro no login: " . $e->getMessage() . "\n", 3, 'session_debug.log');
        header("Location: login.php?error=database_error");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Painel Administrativo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --primary-dark: #3a56d4;
            --secondary-color: #3f37c9;
            --dark-bg: #1a1a2d;
            --card-bg: rgba(44, 47, 74, 0.7);
            --text-light: #f8f9fa;
            --text-muted: #adb5bd;
            --danger-color: #f72585;
            --success-color: #4cc9f0;
            --border-radius: 12px;
            --box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--dark-bg);
            color: var(--text-light);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-image: 
                radial-gradient(circle at 25% 25%, rgba(67, 97, 238, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 75% 75%, rgba(63, 55, 201, 0.15) 0%, transparent 50%);
        }
        
        .login-container {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            padding: 2.5rem;
            border-radius: var(--border-radius);
            width: 100%;
            max-width: 420px;
            box-shadow: var(--box-shadow);
            transform: translateY(0);
            transition: var(--transition);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .login-container:hover {
            transform: translateY(-5px);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        
        .logo i {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .logo h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .logo p {
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        
        .form-group {
            margin-bottom: 1.25rem;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-light);
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--border-radius);
            background: rgba(0, 0, 0, 0.2);
            color: var(--text-light);
            font-family: 'Poppins', sans-serif;
            transition: var(--transition);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }
        
        .input-icon {
            position: absolute;
            right: 1rem;
            top: 2.5rem;
            color: var(--text-muted);
        }
        
        .btn {
            width: 100%;
            padding: 0.75rem;
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: var(--border-radius);
            color: white;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            font-family: 'Poppins', sans-serif;
            margin-top: 0.5rem;
        }
        
        .btn:hover {
            background: linear-gradient(to right, var(--primary-dark), var(--secondary-color));
            transform: translateY(-2px);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .alert {
            padding: 0.75rem 1rem;
            margin-bottom: 1.25rem;
            border-radius: var(--border-radius);
            color: white;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
        }
        
        .alert i {
            margin-right: 0.5rem;
        }
        
        .alert-danger {
            background: var(--danger-color);
        }
        
        .forgot-password {
            text-align: right;
            margin-bottom: 1.5rem;
        }
        
        .forgot-password a {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.85rem;
            transition: var(--transition);
        }
        
        .forgot-password a:hover {
            color: var(--primary-color);
        }
        
        .footer-text {
            text-align: center;
            margin-top: 1.5rem;
            color: var(--text-muted);
            font-size: 0.85rem;
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 1.5rem;
                margin: 0 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <i class="fas fa-lock"></i>
            <h1>Painel Administrativo</h1>
            <p>Faça login para acessar o sistema</p>
        </div>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php
                if ($_GET['error'] == 'invalid_credentials') {
                    echo "Usuário ou senha inválidos.";
                } elseif ($_GET['error'] == 'session_invalid') {
                    echo "Sessão inválida. Faça login novamente.";
                } elseif ($_GET['error'] == 'access_denied') {
                    echo "Acesso negado. Você não tem permissão.";
                } elseif ($_GET['error'] == 'database_error') {
                    echo "Erro no banco de dados. Tente novamente.";
                }
                ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="username">Usuário</label>
                <input type="text" id="username" name="username" class="form-control" required>
                <i class="fas fa-user input-icon"></i>
            </div>
            
            <div class="form-group">
                <label for="password">Senha</label>
                <input type="password" id="password" name="password" class="form-control" required>
                <i class="fas fa-key input-icon"></i>
            </div>
            
            <div class="forgot-password">
                <a href="recu_senha.php">Esqueceu sua senha?</a>
            </div>
            
            <button type="submit" class="btn">
                <i class="fas fa-sign-in-alt"></i> Entrar
            </button>
        </form>
        
        <div class="footer-text">
            &copy; <?php echo date('Y'); ?> Todos os direitos reservados
        </div>
    </div>
</body>
</html>