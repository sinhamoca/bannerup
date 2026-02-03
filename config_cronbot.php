<?php
session_start();

require_once './db/configdb.php';

if (!isset($_SESSION["usuario"]) || !isset($_SESSION["user_id"]) || !isset($_SESSION["nivel"])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION["nivel"] != ADMIN_LEVEL) {
    die('<div style="padding:20px;background:#ffdddd;border:1px solid red;border-radius:8px;">
        <strong>Erro:</strong> Acesso negado! Esta página é restrita a administradores.
    </div>');
}
// config_editor.php - Editor seguro do configbot.php
$pageTitle = "Editor de Configurações";
include "./includes/header.php";

$config_file = __DIR__ . '/cronjobs/configbot.php';

// Verifica se o arquivo existe
if (!file_exists($config_file)) {
    die('<div class="alert alert-danger">Arquivo configbot.php não encontrado!</div>');
}

// Carrega os valores atuais
require_once($config_file);

// Processa o formulário de atualização
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar'])) {
    $novo_token = $_POST['token'];
    $novo_chat_id = $_POST['chat_id'];
    $nova_senha = $_POST['senha_cron'];
    
    // Validações básicas
    if (empty($novo_token) || empty($novo_chat_id) || empty($nova_senha)) {
        $mensagem = '<div class="alert alert-danger">Todos os campos são obrigatórios!</div>';
    } else {
        // Prepara o conteúdo do arquivo
        $conteudo = "<?php
// configbot.php - Configurações do bot e segurança

\$token = '{$novo_token}'; // Token do seu bot
\$chat_id = '{$novo_chat_id}'; // Chat ID do Telegram
\$senha_cron = '{$nova_senha}'; // Senha para acessar a cron (altere para uma mais segura)
?>";

        // Tenta salvar o arquivo
        if (file_put_contents($config_file, $conteudo)) {
            $mensagem = '<div class="alert alert-success">Configurações atualizadas com sucesso!</div>';
            // Recarrega os valores
            require_once($config_file);
        } else {
            $mensagem = '<div class="alert alert-danger">Erro ao salvar as configurações. Verifique as permissões do arquivo.</div>';
        }
    }
}
?>

<style>
/* Estilos específicos para o editor */
.editor-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.config-form {
    background: rgba(44, 47, 74, 0.7);
    border-radius: 10px;
    padding: 25px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    margin-bottom: 8px;
    color: #a0a0c0;
    font-weight: 500;
}

.form-control {
    width: 100%;
    padding: 12px 15px;
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    color: #f0f0f0;
    font-size: 1rem;
    transition: all 0.3s;
}

.form-control:focus {
    border-color: #4e73df;
    box-shadow: 0 0 0 3px rgba(78, 115, 223, 0.2);
    outline: none;
}

.btn-save {
    background: #4e73df;
    color: white;
    border: none;
    padding: 12px 25px;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-save:hover {
    background: #3a56b4;
    transform: translateY(-2px);
}

.btn-save:active {
    transform: translateY(0);
}

.config-info {
    margin-top: 30px;
    padding: 15px;
    background: rgba(26, 26, 45, 0.5);
    border-radius: 8px;
    border-left: 4px solid #4e73df;
}

.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-success {
    background: rgba(28, 200, 138, 0.15);
    border: 1px solid rgba(28, 200, 138, 0.3);
    color: #d4edda;
}

.alert-danger {
    background: rgba(231, 74, 59, 0.15);
    border: 1px solid rgba(231, 74, 59, 0.3);
    color: #f8d7da;
}
</style>

<div class="editor-container">
    <h1><i class="fas fa-cog"></i> Editor de Configurações</h1>
    <p class="text-muted">Edite as configurações básicas do seu bot</p>
    
    <?php if (isset($mensagem)) echo $mensagem; ?>
    
    <div class="config-form">
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Token do Bot</label>
                <input type="text" class="form-control" name="token" value="<?php echo htmlspecialchars($token); ?>" required>
                <small class="text-muted">Token de autenticação do seu bot no Telegram</small>
            </div>
            
            <div class="form-group">
                <label class="form-label">Chat ID</label>
                <input type="text" class="form-control" name="chat_id" value="<?php echo htmlspecialchars($chat_id); ?>" required>
                <small class="text-muted">ID do chat/grupo onde o bot enviará mensagens</small>
            </div>
            
            <div class="form-group">
                <label class="form-label">Senha Cron</label>
                <input type="text" class="form-control" name="senha_cron" value="<?php echo htmlspecialchars($senha_cron); ?>" required>
                <small class="text-muted">Senha para acessar as tarefas agendadas (cron)</small>
            </div>
            
            <button type="submit" name="salvar" class="btn-save">
                <i class="fas fa-save"></i> Salvar Configurações
            </button>
        </form>
    </div>
    
    <div class="config-info">
        <h4><i class="fas fa-info-circle"></i> Informações Importantes</h4>
        <p>Este editor modifica apenas o arquivo <code>configbot.php</code>. Todas as alterações são salvas diretamente no arquivo.</p>
        <p class="text-danger">Certifique-se de que as informações estão corretas antes de salvar.</p>
    </div>
</div>

<?php 
include "./includes/footer.php"; 
?>