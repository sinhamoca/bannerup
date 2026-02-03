<?php
require_once 'db/configdb.php';

// Configurações de debug (desative em produção)
define('DEBUG_MODE', 0);

if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

$message = '';
$error = '';
$showResetForm = false;
$email = '';
$solicitacoes_ativas = false;
$aprovacao_automatica = false;

function handleDatabaseError($e, $action) {
    error_log(date('Y-m-d H:i:s') . " - Erro durante $action: " . $e->getMessage() . "\n", 3, 'session_debug.log');
    return "Ocorreu um erro ao processar sua solicitação. Tente novamente mais tarde.";
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8",
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    // Verifica configurações do sistema
    $stmt = $pdo->prepare("SELECT solicitacoes_ativas, aprovacao_automatica FROM configuracoes_sistema WHERE id = 1");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    $solicitacoes_ativas = $config ? $config['solicitacoes_ativas'] : true;
    $aprovacao_automatica = $config ? $config['aprovacao_automatica'] : false;

    if ($solicitacoes_ativas && $_SERVER["REQUEST_METHOD"] == "POST") {
        if (isset($_POST['email']) && !isset($_POST['new_password'])) {
            // Fase 1: Verificar e-mail
            $email = trim($_POST['email']);
            
            try {
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    $showResetForm = true;
                    $message = "E-mail verificado. Defina sua nova senha.";
                } else {
                    $error = "E-mail não encontrado em nosso sistema.";
                }
            } catch (PDOException $e) {
                $error = handleDatabaseError($e, "verificação de e-mail");
            }
        } elseif (isset($_POST['new_password'])) {
            // Fase 2: Registrar solicitação com nova senha
            $email = trim($_POST['email']);
            $new_password = trim($_POST['new_password']);
            $confirm_password = trim($_POST['confirm_password']);
            
            if (empty($new_password) || strlen($new_password) < 8) {
                $error = "A senha deve ter no mínimo 8 caracteres.";
                $showResetForm = true;
            } elseif ($new_password !== $confirm_password) {
                $error = "As senhas não coincidem.";
                $showResetForm = true;
            } else {
                try {
                    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
                    $stmt->execute([$email]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($user) {
                        // Gera token e hash da nova senha
                        $token = bin2hex(random_bytes(32));
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $data_solicitacao = date("Y-m-d H:i:s");
                        $data_expiracao = date("Y-m-d H:i:s", time() + 24 * 3600); // Expira em 24 horas

                        // Registra solicitação no banco
                        $stmt = $pdo->prepare("INSERT INTO solicitacoes_redefinicao (email, token, nova_senha, status, data_solicitacao, data_expiracao) VALUES (?, ?, ?, ?, ?, ?)");
                        $status = $aprovacao_automatica ? 'aprovado' : 'pendente';
                        if ($stmt->execute([$email, $token, $hashed_password, $status, $data_solicitacao, $data_expiracao])) {
                            if ($aprovacao_automatica) {
                                // Aprova automaticamente: atualiza a senha na tabela usuarios
                                $stmt = $pdo->prepare("UPDATE usuarios SET password = ? WHERE email = ?");
                                if ($stmt->execute([$hashed_password, $email])) {
                                    $message = "Senha redefinida com sucesso! Você já pode fazer login com sua nova senha.";
                                } else {
                                    $error = "Falha ao atualizar a senha automaticamente.";
                                }
                            } else {
                                $message = "Solicitação de redefinição registrada. Aguarde a aprovação do administrador.";
                            }
                            $showResetForm = false;
                        } else {
                            $error = "Falha ao registrar solicitação.";
                            $showResetForm = true;
                        }
                    } else {
                        $error = "E-mail não encontrado.";
                        $showResetForm = false;
                    }
                } catch (PDOException $e) {
                    $error = handleDatabaseError($e, "registro de solicitação");
                    $showResetForm = true;
                }
            }
        }
    } elseif (!$solicitacoes_ativas) {
        $error = "No momento, não estamos aceitando solicitações de recuperação de senha. Procure falar com seu Master.";
    }
} catch (PDOException $e) {
    $error = "Erro na conexão com o banco de dados. Por favor, tente novamente mais tarde.";
    if (DEBUG_MODE) {
        $error .= "<br>Detalhes: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha - Painel Administrativo</title>
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
        
        .recovery-container {
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
        
        .recovery-container:hover {
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
        
        .alert-success {
            background: var(--success-color);
        }
        
        .back-to-login {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .back-to-login a {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.85rem;
            transition: var(--transition);
        }
        
        .back-to-login a:hover {
            color: var(--primary-color);
        }
        
        .footer-text {
            text-align: center;
            margin-top: 1.5rem;
            color: var(--text-muted);
            font-size: 0.85rem;
        }
        
        .password-strength {
            margin-top: 0.5rem;
            height: 5px;
            background: #ddd;
            border-radius: 5px;
            overflow: hidden;
        }
        
        .strength-meter {
            height: 100%;
            width: 0;
            transition: width 0.3s ease;
        }
        
        @media (max-width: 480px) {
            .recovery-container {
                padding: 1.5rem;
                margin: 0 1rem;
            }
        }
    </style>
    <script>
        function checkPasswordStrength(password) {
            let strength = 0;
            
            if (password.length >= 8) strength += 1;
            if (password.match(/([a-z].*[A-Z])|([A-Z].*[a-z])/)) strength += 1;
            if (password.match(/([0-9])/)) strength += 1;
            if (password.match(/([!,%,&,@,#,$,^,*,?,_,~])/)) strength += 1;
            
            const meter = document.getElementById('password-strength-meter');
            const strengthText = document.getElementById('password-strength-text');
            
            switch(strength) {
                case 0:
                    meter.style.width = '0%';
                    meter.style.backgroundColor = 'transparent';
                    strengthText.textContent = '';
                    break;
                case 1:
                    meter.style.width = '25%';
                    meter.style.backgroundColor = '#ff4d4d';
                    strengthText.textContent = 'Fraca';
                    break;
                case 2:
                    meter.style.width = '50%';
                    meter.style.backgroundColor = '#ffa64d';
                    strengthText.textContent = 'Moderada';
                    break;
                case 3:
                    meter.style.width = '75%';
                    meter.style.backgroundColor = '#99cc33';
                    strengthText.textContent = 'Forte';
                    break;
                case 4:
                    meter.style.width = '100%';
                    meter.style.backgroundColor = '#33cc33';
                    strengthText.textContent = 'Muito forte';
                    break;
            }
        }
    </script>
</head>
<body>
    <div class="recovery-container">
        <div class="logo">
            <i class="fas fa-key"></i>
            <h1>Recuperar Senha</h1>
            <p><?php echo $showResetForm ? 'Defina sua nova senha' : 'Digite seu email para verificação'; ?></p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($solicitacoes_ativas && !$showResetForm && !$message): ?>
            <!-- Formulário de verificação de e-mail -->
            <form method="POST" action="recu_senha.php">
                <div class="form-group">
                    <label for="email">Email cadastrado</label>
                    <input type="email" id="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($email); ?>">
                    <i class="fas fa-envelope input-icon"></i>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-search"></i> Verificar Email
                </button>
            </form>
        <?php elseif ($showResetForm): ?>
            <!-- Formulário de redefinição de senha -->
            <form method="POST" action="recu_senha.php">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                
                <div class="form-group">
                    <label for="new_password">Nova Senha (mínimo 8 caracteres)</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" required 
                           onkeyup="checkPasswordStrength(this.value)" minlength="8">
                    <i class="fas fa-lock input-icon"></i>
                    <div class="password-strength">
                        <div id="password-strength-meter" class="strength-meter"></div>
                    </div>
                    <small id="password-strength-text" class="text-muted"></small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirme a Nova Senha</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="8">
                    <i class="fas fa-lock input-icon"></i>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-sync-alt"></i> Solicitar Redefinição
                </button>
            </form>
        <?php endif; ?>
        
        <div class="back-to-login">
            <a href="login.php"><i class="fas fa-arrow-left"></i> Voltar para o login</a>
        </div>
        
        <div class="footer-text">
            © <?php echo date('Y'); ?> Todos os direitos reservados
        </div>
    </div>
</body>
</html>