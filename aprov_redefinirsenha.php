<?php
require_once 'db/configdb.php';
session_start();

// Configurações de debug (desative em produção)
define('DEBUG_MODE', 0);

if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

if (!isset($_SESSION["usuario"]) || !isset($_SESSION["user_id"]) || !isset($_SESSION["nivel"])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION["nivel"] != ADMIN_LEVEL) {
    header("Location: login.php?error=access_denied");
    exit();
}

$message = '';
$error = '';
$solicitacoes_ativas = true;
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

    // Processa ativação/desativação de solicitações
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['toggle_solicitacoes'])) {
        $novo_estado = $solicitacoes_ativas ? 0 : 1;
        try {
            $stmt = $pdo->prepare("UPDATE configuracoes_sistema SET solicitacoes_ativas = ? WHERE id = 1");
            if ($stmt->execute([$novo_estado])) {
                $solicitacoes_ativas = $novo_estado;
                $message = $novo_estado ? "Solicitações de recuperação de senha ativadas." : "Solicitações de recuperação de senha desativadas.";
            } else {
                $error = "Falha ao atualizar o estado das solicitações.";
            }
        } catch (PDOException $e) {
            $error = handleDatabaseError($e, "atualização do estado das solicitações");
        }
    }

    // Processa ativação/desativação de aprovação automática
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['toggle_aprovacao_automatica'])) {
        $novo_estado = $aprovacao_automatica ? 0 : 1;
        try {
            $stmt = $pdo->prepare("UPDATE configuracoes_sistema SET aprovacao_automatica = ? WHERE id = 1");
            if ($stmt->execute([$novo_estado])) {
                $aprovacao_automatica = $novo_estado;
                $message = $novo_estado ? "Aprovação automática de solicitações ativada." : "Aprovação automática de solicitações desativada.";
            } else {
                $error = "Falha ao atualizar o estado da aprovação automática.";
            }
        } catch (PDOException $e) {
            $error = handleDatabaseError($e, "atualização da aprovação automática");
        }
    }

    // Processa aprovação/recusa individual (apenas se aprovação automática estiver desativada)
    if (!$aprovacao_automatica && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && isset($_POST['id'])) {
        $action = $_POST['action'];
        $solicitacao_id = $_POST['id'];
        
        try {
            $stmt = $pdo->prepare("SELECT email, nova_senha, status, data_expiracao FROM solicitacoes_redefinicao WHERE id = ?");
            $stmt->execute([$solicitacao_id]);
            $solicitacao = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($solicitacao && $solicitacao['data_expiracao'] > date("Y-m-d H:i:s")) {
                if ($solicitacao['status'] !== 'pendente') {
                    $error = "Esta solicitação já foi processada.";
                } else {
                    if ($action === 'approve') {
                        // Atualiza a senha na tabela usuarios
                        $stmt = $pdo->prepare("UPDATE usuarios SET password = ? WHERE email = ?");
                        if ($stmt->execute([$solicitacao['nova_senha'], $solicitacao['email']])) {
                            // Atualiza o status da solicitação
                            $stmt = $pdo->prepare("UPDATE solicitacoes_redefinicao SET status = 'aprovado' WHERE id = ?");
                            $stmt->execute([$solicitacao_id]);
                            $message = "Solicitação aprovada. A nova senha foi aplicada.";
                        } else {
                            $error = "Falha ao atualizar a senha.";
                        }
                    } else {
                        // Recusa a solicitação
                        $stmt = $pdo->prepare("UPDATE solicitacoes_redefinicao SET status = 'recusado' WHERE id = ?");
                        $stmt->execute([$solicitacao_id]);
                        $message = "Solicitação recusada.";
                    }
                }
            } else {
                $error = "Solicitação inválida ou expirada.";
            }
        } catch (PDOException $e) {
            $error = handleDatabaseError($e, "processamento da solicitação");
        }
    }

    // Lista solicitações pendentes (apenas se aprovação automática estiver desativada)
    $stmt = $pdo->prepare("SELECT id, email, data_solicitacao FROM solicitacoes_redefinicao WHERE status = 'pendente' AND data_expiracao > NOW()");
    $stmt->execute();
    $solicitacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Erro na conexão com o banco de dados. Por favor, tente novamente mais tarde.";
    if (DEBUG_MODE) {
        $error .= "<br>Detalhes: " . $e->getMessage();
    }
}

$pageTitle = "Gerenciamento Staff";
include "includes/header.php";
?>

<style>
    /* Estilos complementares para integrar com o header */
    .admin-content {
        padding: 2rem;
        width: 100%;
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .admin-card {
        background: rgba(44, 47, 74, 0.7);
        backdrop-filter: blur(10px);
        border-radius: 12px;
        padding: 2rem;
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.1);
        margin-bottom: 2rem;
    }
    
    .admin-title {
        font-size: 1.8rem;
        margin-bottom: 1.5rem;
        color: #4e73df;
        text-align: center;
    }
    
    .alert {
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
    }
    
    .alert i {
        margin-right: 0.75rem;
    }
    
    .alert-danger {
        background: rgba(247, 37, 133, 0.2);
        border-left: 4px solid #f72585;
        color: #f8f9fa;
    }
    
    .alert-success {
        background: rgba(76, 201, 240, 0.2);
        border-left: 4px solid #4cc9f0;
        color: #f8f9fa;
    }
    
    .config-container {
        background: rgba(0, 0, 0, 0.2);
        padding: 1.5rem;
        border-radius: 8px;
        margin-bottom: 2rem;
        border-left: 4px solid rgba(78, 115, 223, 0.5);
    }
    
    .config-title {
        font-size: 1.3rem;
        margin-bottom: 1rem;
        color: #4e73df;
    }
    
    .config-item {
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .config-item:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }
    
    .config-label {
        font-weight: 600;
        margin-right: 0.5rem;
    }
    
    .config-value {
        color: #adb5bd;
    }
    
    .btn {
        padding: 0.5rem 1rem;
        border: none;
        border-radius: 6px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .btn-toggle {
        background: linear-gradient(to right, #4361ee, #3f37c9);
        color: white;
        width: 100%;
        margin-top: 0.5rem;
    }
    
    .btn-toggle:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }
    
    .solicitacao-list {
        margin-top: 2rem;
    }
    
    .solicitacao-item {
        background: rgba(0, 0, 0, 0.2);
        padding: 1.25rem;
        margin-bottom: 1rem;
        border-radius: 8px;
        border-left: 4px solid rgba(78, 115, 223, 0.5);
    }
    
    .solicitacao-item:hover {
        background: rgba(0, 0, 0, 0.3);
    }
    
    .solicitacao-info {
        margin-bottom: 1rem;
    }
    
    .solicitacao-info p {
        margin-bottom: 0.5rem;
    }
    
    .solicitacao-email {
        font-weight: 600;
        color: #4e73df;
    }
    
    .solicitacao-date {
        color: #adb5bd;
        font-size: 0.9rem;
    }
    
    .btn-group {
        display: flex;
        gap: 0.75rem;
    }
    
    .btn-approve {
        background: #4cc9f0;
        color: white;
    }
    
    .btn-approve:hover {
        background: #3ab5d9;
    }
    
    .btn-reject {
        background: #f72585;
        color: white;
    }
    
    .btn-reject:hover {
        background: #e01a5e;
    }
    
    .empty-state {
        text-align: center;
        padding: 2rem;
        background: rgba(44, 47, 74, 0.3);
        border-radius: 8px;
        color: #adb5bd;
    }
    
    .auto-approval-message {
        text-align: center;
        padding: 1.5rem;
        background: rgba(76, 201, 240, 0.1);
        border-radius: 8px;
        border-left: 4px solid #4cc9f0;
        margin-bottom: 2rem;
    }
    
    @media (max-width: 768px) {
        .admin-content {
            padding: 1rem;
        }
        
        .btn-group {
            flex-direction: column;
        }
        
        .btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="admin-content">
    <div class="admin-card">
        <h1 class="admin-title">
            <i class="fas fa-shield-alt"></i> Gerenciamento Staff
        </h1>
        
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
        
        <div class="config-container">
            <h3 class="config-title">Configurações do Sistema</h3>
            
            <div class="config-item">
                <p>
                    <span class="config-label">Status das Solicitações:</span>
                    <span class="config-value"><?php echo $solicitacoes_ativas ? 'Ativadas' : 'Desativadas'; ?></span>
                </p>
                <form method="POST" action="aprov_redefinirsenha.php">
                    <button type="submit" name="toggle_solicitacoes" class="btn btn-toggle">
                        <?php echo $solicitacoes_ativas ? 'Desativar Solicitações' : 'Ativar Solicitações'; ?>
                    </button>
                </form>
            </div>
            
            <div class="config-item">
                <p>
                    <span class="config-label">Aprovação Automática:</span>
                    <span class="config-value"><?php echo $aprovacao_automatica ? 'Ativada' : 'Desativada'; ?></span>
                </p>
                <form method="POST" action="aprov_redefinirsenha.php">
                    <button type="submit" name="toggle_aprovacao_automatica" class="btn btn-toggle">
                        <?php echo $aprovacao_automatica ? 'Desativar Aprovação Automática' : 'Ativar Aprovação Automática'; ?>
                    </button>
                </form>
            </div>
        </div>
        
        <?php if (!$aprovacao_automatica): ?>
            <div class="solicitacao-list">
                <h3 class="config-title">Solicitações Pendentes</h3>
                
                <?php if (empty($solicitacoes)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                        <p>Nenhuma solicitação pendente no momento.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($solicitacoes as $solicitacao): ?>
                        <div class="solicitacao-item">
                            <div class="solicitacao-info">
                                <p class="solicitacao-email">
                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($solicitacao['email']); ?>
                                </p>
                                <p class="solicitacao-date">
                                    <i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($solicitacao['data_solicitacao']); ?>
                                </p>
                            </div>
                            <form method="POST" action="aprov_redefinirsenha.php">
                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($solicitacao['id']); ?>">
                                <div class="btn-group">
                                    <button type="submit" name="action" value="approve" class="btn btn-approve">
                                        <i class="fas fa-check"></i> Aprovar
                                    </button>
                                    <button type="submit" name="action" value="reject" class="btn btn-reject">
                                        <i class="fas fa-times"></i> Recusar
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="auto-approval-message">
                <i class="fas fa-robot" style="font-size: 1.5rem; margin-bottom: 1rem;"></i>
                <p>Aprovação automática ativada. As solicitações estão sendo processadas automaticamente.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include "includes/footer.php"; ?>