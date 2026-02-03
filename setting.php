<?php
session_start();
require_once 'db/configdb.php';

// Função de depuração para registrar erros de sessão
function debugSession($message) {
    error_log(date('Y-m-d H:i:s') . " - $message\n", 3, 'session_debug.log');
}

// Verificar se o usuário está logado
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["usuario"]) || !isset($_SESSION["nivel"])) {
    $user_id = isset($_SESSION["user_id"]) ? $_SESSION["user_id"] : 'não definido';
    $nivel = isset($_SESSION["nivel"]) ? $_SESSION["nivel"] : 'não definido';
    debugSession("Sessão inválida: user_id=$user_id, nivel=$nivel");
    header("Location: login.php?error=session_invalid");
    exit();
}

// Conexão com o banco de dados MySQL
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8",
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    debugSession("Erro na conexão com o banco de dados: " . $e->getMessage());
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}

// Verificar nível do usuário
$nivel = isset($_SESSION["nivel"]) ? $_SESSION["nivel"] : 3;
$mensagem = "";

// Processar formulário
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if ($nivel <= 2) {
        $usuario_atual = $_SESSION["usuario"];
        $novo_usuario = trim($_POST["novo_usuario"]);
        $senha_atual = trim($_POST["senha_atual"]);
        $nova_senha = trim($_POST["nova_senha"]);
        $confirmar_senha = trim($_POST["confirmar_senha"]);

        try {
            $stmt = $pdo->prepare("SELECT id, password FROM usuarios WHERE username = ?");
            $stmt->execute([$usuario_atual]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($senha_atual, $user["password"])) {
                $mensagem = '<div class="alert alert-danger">❌ Senha atual incorreta!</div>';
            } elseif ($nova_senha !== $confirmar_senha) {
                $mensagem = '<div class="alert alert-danger">❌ As novas senhas não coincidem!</div>';
            } elseif (!empty($nova_senha) && strlen($nova_senha) < 6) {
                $mensagem = '<div class="alert alert-danger">❌ A nova senha deve ter pelo menos 6 caracteres!</div>';
            } elseif (empty($novo_usuario)) {
                $mensagem = '<div class="alert alert-danger">❌ O nome de usuário não pode estar vazio!</div>';
            } else {
                $nova_senha_hash = !empty($nova_senha) ? password_hash($nova_senha, PASSWORD_DEFAULT) : $user["password"];
                
                $stmt = $pdo->prepare("UPDATE usuarios SET username = ?, password = ? WHERE id = ?");
                $stmt->execute([$novo_usuario, $nova_senha_hash, $user["id"]]);

                $_SESSION["usuario"] = $novo_usuario;
                $mensagem = '<div class="alert alert-success">✅ Configurações alteradas com sucesso!</div>';
            }
        } catch (PDOException $e) {
            debugSession("Erro ao atualizar usuário: user_id={$_SESSION["user_id"]}, erro=" . $e->getMessage());
            $mensagem = '<div class="alert alert-danger">❌ Erro ao atualizar: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    } else {
        debugSession("Acesso negado para user_id={$_SESSION["user_id"]}, nivel=$nivel");
        $mensagem = '<div class="alert alert-danger">❌ Acesso negado: seu nível de usuário não permite esta ação!</div>';
    }
}

// Definir título da página
$pageTitle = "Configurações da Conta";

include "includes/header.php";
?>

<style>
    /* Estilos dos formulários importados do nosso design */
    .form-group { position: relative; margin-bottom: 20px; }
    .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-muted); }
    .input-field {
        width: 100%; padding: 12px 15px; background: rgba(0, 0, 0, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 8px;
        color: #fff; font-size: 1em; transition: all 0.3s ease;
    }
    .input-field:focus { outline: none; border-color: var(--accent-color); }
    .input-field:disabled { background: rgba(0, 0, 0, 0.1); cursor: not-allowed; }
    
    /* Ícone para mostrar/ocultar senha */
    .password-toggle {
        position: absolute;
        top: 50%;
        right: 15px;
        transform: translateY(-50%);
        color: var(--text-muted);
        cursor: pointer;
        padding-top: 15px; /* Alinhamento vertical com o campo */
    }

    .submit-btn {
        width: 100%; padding: 12px 30px; border: none; border-radius: 8px;
        background-color: var(--success-color); color: #fff; font-size: 1.1em;
        font-weight: bold; cursor: pointer; transition: all 0.3s ease;
        margin-top: 10px; display: flex; align-items: center; justify-content: center;
    }
    .submit-btn i { margin-right: 8px; }
    .submit-btn:hover { background-color: #218838; transform: translateY(-2px); }
    .submit-btn:disabled { background-color: #6c757d; cursor: not-allowed; }

    /* Estilos para as mensagens de alerta */
    .alert { padding: 15px; margin-bottom: 20px; border-radius: 8px; font-weight: 500; }
    .alert-success { background-color: rgba(40, 167, 69, 0.2); border: 1px solid rgba(40, 167, 69, 0.5); color: #28a745; }
    .alert-danger { background-color: rgba(231, 76, 60, 0.2); border: 1px solid rgba(231, 76, 60, 0.5); color: #e74c3c; }

    hr { border-color: rgba(255, 255, 255, 0.1); margin: 30px 0; }
    
    .content-card {
        max-width: 500px;
        margin: 0 auto;
        padding: 30px;
    }
    
    .page-header {
        text-align: center;
        margin-bottom: 30px;
    }
    
    .page-header h1 {
        color: var(--accent-color);
    }
</style>

<div class="content-card">
    <div class="page-header">
        <h1><i class="fas fa-user-cog"></i> Configurações da Conta</h1>
    </div>
    
    <?php if ($mensagem) echo $mensagem; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="novo_usuario">Nome de Usuário</label>
            <input type="text" id="novo_usuario" name="novo_usuario" class="input-field" 
                   value="<?php echo htmlspecialchars($_SESSION['usuario']); ?>" 
                   <?php echo $nivel > 2 ? 'disabled' : 'required'; ?>>
        </div>

        <hr>

        <h3 style="margin-bottom: 20px; font-weight: 600;">Alterar Senha</h3>
        
        <div class="form-group">
            <label for="senha_atual">Senha Atual</label>
            <input type="password" id="senha_atual" name="senha_atual" class="input-field" 
                   placeholder="Digite sua senha atual para confirmar" 
                   <?php echo $nivel > 2 ? 'disabled' : 'required'; ?>>
            <i class="fas fa-eye password-toggle"></i>
        </div>

        <div class="form-group">
            <label for="nova_senha">Nova Senha</label>
            <input type="password" id="nova_senha" name="nova_senha" class="input-field" 
                   placeholder="Mínimo de 6 caracteres" 
                   <?php echo $nivel > 2 ? 'disabled' : ''; ?>>
            <i class="fas fa-eye password-toggle"></i>
        </div>

        <div class="form-group">
            <label for="confirmar_senha">Confirmar Nova Senha</label>
            <input type="password" id="confirmar_senha" name="confirmar_senha" class="input-field" 
                   placeholder="Repita a nova senha" 
                   <?php echo $nivel > 2 ? 'disabled' : ''; ?>>
            <i class="fas fa-eye password-toggle"></i>
        </div>
        
        <button type="submit" class="submit-btn" <?php echo $nivel > 2 ? 'disabled' : ''; ?>>
            <i class="fas fa-save"></i> Salvar Alterações
        </button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Lógica para mostrar/ocultar senha
    const togglePasswordIcons = document.querySelectorAll('.password-toggle');

    togglePasswordIcons.forEach(icon => {
        icon.addEventListener('click', function() {
            const input = this.parentNode.querySelector('input');
            if (input.disabled) return;
            
            const type = input.type === 'password' ? 'text' : 'password';
            input.type = type;
            
            // Alterna o ícone
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    });
});
</script>

<?php 
include "includes/footer.php"; 
?>