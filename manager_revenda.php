<?php
session_start();
require_once 'db/configdb.php';

// Função de depuração para registrar erros de sessão
function debugSession($message) {
    $logFile = 'session_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'IP desconhecido';
    $fullMessage = "$timestamp - IP: $ip - $message\n";
    error_log($fullMessage, 3, $logFile);
}

// Verificar se o usuário está logado e tem permissão
if (!isset($_SESSION["usuario"]) || !isset($_SESSION["user_id"]) || !isset($_SESSION["nivel"])) {
    $user_id = isset($_SESSION["user_id"]) ? $_SESSION["user_id"] : 'não definido';
    $nivel = isset($_SESSION["nivel"]) ? $_SESSION["nivel"] : 'não definido';
    debugSession("Tentativa de acesso sem sessão válida: user_id=$user_id, nivel=$nivel");
    header("Location: login.php?error=session_invalid");
    exit();
}

// Verificar nível de permissão
if ($_SESSION["nivel"] > 2) {
    debugSession("Acesso negado para user_id={$_SESSION["user_id"]}, nivel={$_SESSION["nivel"]}");
    header("Location: login.php?error=access_denied");
    exit();
}

// Conexão com o banco de dados com tratamento de erros aprimorado
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false
        ]
    );
} catch (PDOException $e) {
    debugSession("Erro crítico na conexão com o banco de dados: " . $e->getMessage());
    die("Erro no sistema. Por favor, tente novamente mais tarde.");
}

// Variáveis globais
$mensagem = "";
$acao = $_GET['acao'] ?? '';
$id = (int)($_GET['id'] ?? 0);

// Função para verificar permissão de edição
function verificarPermissaoEdicao($pdo, $id_usuario, $id_sessao, $nivel_sessao) {
    if ($nivel_sessao == 1) return true; // Admin pode editar todos
    
    // Revendedor só pode editar seus sub-revendedores
    try {
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE id = ? AND revenda_id = ? AND nivel = 3");
        $stmt->execute([$id_usuario, $id_sessao]);
        return (bool)$stmt->fetch();
    } catch (PDOException $e) {
        debugSession("Erro ao verificar permissão: " . $e->getMessage());
        return false;
    }
}

// Processar ações POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_SESSION["nivel"])) {
        $user_id = $_SESSION["user_id"] ?? 'não definido';
        debugSession("Sessão inválida no POST: user_id=$user_id, nivel=não definido");
        $mensagem = '<div class="alert alert-danger">Erro: Sessão inválida. Faça login novamente.</div>';
    } elseif ($_SESSION["nivel"] > 2) {
        $mensagem = '<div class="alert alert-danger">Acesso negado!</div>';
    } else {
        // Cadastrar novo revendedor
        if (isset($_POST['cadastrar'])) {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $nivel = (int)($_POST['nivel'] ?? 3);
            $revenda_id = $_SESSION["nivel"] == 1 ? null : $_SESSION["user_id"];

            // Validações robustas
            if ($_SESSION["nivel"] == 2 && $nivel != 3) {
                $mensagem = '<div class="alert alert-danger">Você só pode criar sub-revendedores!</div>';
            } elseif (empty($username) || empty($email) || empty($password)) {
                $mensagem = '<div class="alert alert-danger">Todos os campos são obrigatórios!</div>';
            } elseif (strlen($password) < 8) {
                $mensagem = '<div class="alert alert-danger">A senha deve ter no mínimo 8 caracteres!</div>';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $mensagem = '<div class="alert alert-danger">E-mail inválido!</div>';
            } elseif (strlen($username) < 4 || strlen($username) > 30) {
                $mensagem = '<div class="alert alert-danger">O nome de usuário deve ter entre 4 e 30 caracteres!</div>';
            } else {
                try {
                    // Verificar se usuário já existe (com lock para evitar race condition)
                    $pdo->beginTransaction();
                    
                    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE username = ? OR email = ? FOR UPDATE");
                    $stmt->execute([$username, $email]);
                    
                    if ($stmt->fetch()) {
                        $mensagem = '<div class="alert alert-danger">Usuário ou e-mail já cadastrado!</div>';
                        $pdo->rollBack();
                    } else {
                        // Inserir novo revendedor com dados sanitizados
                        $stmt = $pdo->prepare("INSERT INTO usuarios (username, email, password, nivel, revenda_id) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([
                            htmlspecialchars($username, ENT_QUOTES, 'UTF-8'),
                            filter_var($email, FILTER_SANITIZE_EMAIL),
                            password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
                            $nivel,
                            $revenda_id
                        ]);
                        
                        $pdo->commit();
                        $mensagem = '<div class="alert alert-success">Revendedor cadastrado com sucesso!</div>';
                    }
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    debugSession("Erro ao cadastrar revendedor: " . $e->getMessage());
                    $mensagem = '<div class="alert alert-danger">Erro no sistema. Por favor, tente novamente.</div>';
                }
            }
        }
        
        // Editar revendedor
        elseif (isset($_POST['editar'])) {
            $id = (int)($_POST['id'] ?? 0);
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $nivel = (int)($_POST['nivel'] ?? 3);
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            
            // Verificações de segurança
            if ($id < 1) {
                $mensagem = '<div class="alert alert-danger">ID inválido!</div>';
            } elseif (!verificarPermissaoEdicao($pdo, $id, $_SESSION["user_id"], $_SESSION["nivel"])) {
                $mensagem = '<div class="alert alert-danger">Permissão negada para editar este revendedor!</div>';
            } elseif ($_SESSION["nivel"] == 2 && $nivel != 3) {
                $mensagem = '<div class="alert alert-danger">Você só pode editar sub-revendedores!</div>';
            } elseif (empty($username) || empty($email)) {
                $mensagem = '<div class="alert alert-danger">Todos os campos são obrigatórios!</div>';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $mensagem = '<div class="alert alert-danger">E-mail inválido!</div>';
            } elseif (strlen($username) < 4 || strlen($username) > 30) {
                $mensagem = '<div class="alert alert-danger">O nome de usuário deve ter entre 4 e 30 caracteres!</div>';
            } else {
                try {
                    // Atualizar dados com transação
                    $pdo->beginTransaction();
                    
                    // Verificar se email já existe em outro usuário
                    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ? FOR UPDATE");
                    $stmt->execute([$email, $id]);
                    
                    if ($stmt->fetch()) {
                        $mensagem = '<div class="alert alert-danger">Este e-mail já está em uso por outro usuário!</div>';
                        $pdo->rollBack();
                    } else {
                        // Atualizar dados
                        $stmt = $pdo->prepare("UPDATE usuarios SET username = ?, email = ?, nivel = ?, ativo = ? WHERE id = ?");
                        $stmt->execute([
                            htmlspecialchars($username, ENT_QUOTES, 'UTF-8'),
                            filter_var($email, FILTER_SANITIZE_EMAIL),
                            $nivel,
                            $ativo,
                            $id
                        ]);
                        
                        $pdo->commit();
                        $mensagem = '<div class="alert alert-success">Revendedor atualizado com sucesso!</div>';
                    }
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    debugSession("Erro ao atualizar revendedor ID $id: " . $e->getMessage());
                    $mensagem = '<div class="alert alert-danger">Erro ao atualizar. Por favor, tente novamente.</div>';
                }
            }
        }
    }
}

// Ação de deletar (apenas GET com confirmação via JavaScript)
if ($acao == 'deletar' && $id > 0) {
    if (!isset($_SESSION["nivel"])) {
        $user_id = $_SESSION["user_id"] ?? 'não definido';
        debugSession("Sessão inválida ao deletar: user_id=$user_id, nivel=não definido");
        $mensagem = '<div class="alert alert-danger">Erro: Sessão inválida. Faça login novamente.</div>';
    } elseif (!verificarPermissaoEdicao($pdo, $id, $_SESSION["user_id"], $_SESSION["nivel"])) {
        $mensagem = '<div class="alert alert-danger">Você não tem permissão para remover este revendedor!</div>';
    } else {
        try {
            // Verificar se o revendedor tem sub-revendedores ou registros associados
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE revenda_id = ?");
            $stmt->execute([$id]);
            $hasDependents = $stmt->fetchColumn() > 0;
            
            if ($hasDependents) {
                $mensagem = '<div class="alert alert-danger">Este revendedor possui sub-revendedores associados e não pode ser removido!</div>';
            } else {
                $pdo->beginTransaction();
                
                // Remover o revendedor
                $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
                $stmt->execute([$id]);
                
                $pdo->commit();
                $mensagem = '<div class="alert alert-success">Revendedor removido com sucesso!</div>';
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            debugSession("Erro ao remover revendedor ID $id: " . $e->getMessage());
            $mensagem = '<div class="alert alert-danger">Erro ao remover. Por favor, tente novamente.</div>';
        }
    }
}

// Obter lista de revendedores com paginação
function getRevendedores($pdo, $user_id, $user_level) {
    try {
        if ($user_level == 1) {
            // Admin vê todos os revendedores e sub-revendedores com paginação
            $stmt = $pdo->query("SELECT * FROM usuarios WHERE nivel IN (2,3) ORDER BY nivel, username");
        } else {
            // Revendedor vê apenas seus sub-revendedores
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE revenda_id = ? AND nivel = 3 ORDER BY username");
            $stmt->execute([$user_id]);
        }
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        debugSession("Erro ao obter lista de revendedores: " . $e->getMessage());
        return [];
    }
}

$revendedores = getRevendedores($pdo, $_SESSION["user_id"], $_SESSION["nivel"]);

// Definir título da página
$pageTitle = "Gerenciar Revendedores";

include "includes/header.php";
?>

<style>
    :root {
        --primary-color: #4361ee;
        --secondary-color: #3f37c9;
        --success-color: #4cc9f0;
        --danger-color: #f72585;
        --warning-color: #f8961e;
        --info-color: #4895ef;
        --dark-color: #212529;
        --light-color: #f8f9fa;
        --gray-color: #6c757d;
        --border-radius: 0.375rem;
        --box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        --transition: all 0.3s ease;
    }

    /* Layout Base */
    body {
        font-family: 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        background-color: #f5f7fa;
        color: #333;
        line-height: 1.6;
    }

    .content-card {
        max-width: 1200px;
        margin: 2rem auto;
        padding: 2rem;
        background-color: white;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .page-title {
        font-size: 1.75rem;
        font-weight: 600;
        color: var(--dark-color);
        margin: 0;
    }

    /* Alertas */
    .alert {
        padding: 1rem;
        margin-bottom: 1.5rem;
        border-radius: var(--border-radius);
        border: 1px solid transparent;
    }

    .alert-danger {
        color: #721c24;
        background-color: #f8d7da;
        border-color: #f5c6cb;
    }

    .alert-success {
        color: #155724;
        background-color: #d4edda;
        border-color: #c3e6cb;
    }

    /* Tabela */
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        margin-bottom: 1.5rem;
        border-radius: var(--border-radius);
        box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.05);
    }

    .table {
        width: 100%;
        border-collapse: collapse;
        background-color: white;
    }

    .table th,
    .table td {
        padding: 1rem;
        text-align: left;
        vertical-align: middle;
        border-top: 1px solid #dee2e6;
    }

    .table thead th {
        vertical-align: bottom;
        border-bottom: 2px solid #dee2e6;
        background-color: #f8f9fa;
        font-weight: 600;
        color: #495057;
    }

    .table tbody tr:hover {
        background-color: rgba(0, 0, 0, 0.02);
    }

    .table tbody tr:nth-of-type(even) {
        background-color: rgba(0, 0, 0, 0.01);
    }

    /* Badges */
    .badge {
        display: inline-block;
        padding: 0.35em 0.65em;
        font-size: 0.75em;
        font-weight: 600;
        line-height: 1;
        text-align: center;
        white-space: nowrap;
        vertical-align: baseline;
        border-radius: 50rem;
    }

    .badge-admin {
        color: white;
        background-color: var(--danger-color);
    }

    .badge-revenda {
        color: white;
        background-color: var(--secondary-color);
    }

    .badge-subrevenda {
        color: white;
        background-color: var(--info-color);
    }

    .badge-success {
        color: white;
        background-color: #28a745;
    }

    .badge-warning {
        color: #212529;
        background-color: #ffc107;
    }

    /* Botões */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 500;
        text-align: center;
        white-space: nowrap;
        vertical-align: middle;
        user-select: none;
        border: 1px solid transparent;
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
        line-height: 1.5;
        border-radius: var(--border-radius);
        transition: var(--transition);
        cursor: pointer;
        gap: 0.5rem;
    }

    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
    }

    .btn-primary {
        color: white;
        background-color: var(--primary-color);
        border-color: var(--primary-color);
    }

    .btn-primary:hover {
        background-color: var(--secondary-color);
        border-color: var(--secondary-color);
    }

    .btn-danger {
        color: white;
        background-color: var(--danger-color);
        border-color: var(--danger-color);
    }

    .btn-danger:hover {
        background-color: #d1144a;
        border-color: #c51243;
    }

    .btn-group {
        display: flex;
        gap: 0.5rem;
    }

    /* Formulários */
    .form-group {
        margin-bottom: 1.25rem;
    }

    .form-label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
    }

    .form-control {
        display: block;
        width: 100%;
        padding: 0.5rem 0.75rem;
        font-size: 0.875rem;
        line-height: 1.5;
        color: #495057;
        background-color: white;
        background-clip: padding-box;
        border: 1px solid #ced4da;
        border-radius: var(--border-radius);
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }

    .form-control:focus {
        color: #495057;
        background-color: white;
        border-color: #80bdff;
        outline: 0;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    }

    .form-check {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .form-check-input {
        width: 1em;
        height: 1em;
        margin-top: 0;
    }

    /* Modal */
    .modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1050;
        opacity: 0;
        visibility: hidden;
        transition: var(--transition);
    }

    .modal.show {
        opacity: 1;
        visibility: visible;
    }

    .modal-content {
        position: relative;
        width: 100%;
        max-width: 500px;
        background-color: white;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        padding: 1.5rem;
        margin: 0.5rem;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.25rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #dee2e6;
    }

    .modal-title {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 600;
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        line-height: 1;
        color: #6c757d;
        cursor: pointer;
        padding: 0;
    }

    .modal-close:hover {
        color: #495057;
    }

    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
        margin-top: 1.5rem;
        padding-top: 1rem;
        border-top: 1px solid #dee2e6;
    }

    /* Ícones */
    .icon {
        width: 1em;
        height: 1em;
        vertical-align: middle;
        fill: currentColor;
    }

    /* Utilitários */
    .text-center {
        text-align: center;
    }

    .text-right {
        text-align: right;
    }

    .mb-3 {
        margin-bottom: 1rem;
    }

    .mt-3 {
        margin-top: 1rem;
    }

    /* Responsividade */
    @media (max-width: 992px) {
        .content-card {
            padding: 1.5rem;
        }
    }

    @media (max-width: 768px) {
        .content-card {
            padding: 1rem;
            margin: 1rem;
        }

        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .table th,
        .table td {
            padding: 0.75rem;
        }

        .btn-group {
            flex-direction: column;
            gap: 0.25rem;
        }

        .btn {
            width: 100%;
        }
    }

    @media (max-width: 576px) {
        .modal-content {
            padding: 1rem;
        }

        .table th,
        .table td {
            padding: 0.5rem;
            font-size: 0.85rem;
        }

        .badge {
            font-size: 0.65rem;
            padding: 0.25em 0.5em;
        }
    }

     /* Animações */
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    .fade-in {
        animation: fadeIn 0.3s ease-in-out;
    }

    /* Dark Mode (opcional) */
    @media (prefers-color-scheme: dark) {
        body {
            background-color: #1a1a1a;
            color: #f0f0f0;
        }

        .content-card,
        .table,
        .modal-content {
            background-color: #2d2d2d;
            color: #f0f0f0;
        }

        .table th,
        .table td {
            border-color: #444;
        }

        .table thead th {
            background-color: #333;
            color: #f0f0f0;
            border-color: #444;
        }

        .table tbody tr:hover {
            background-color: rgba(255, 255, 255, 0.05);
        }

        .form-control {
            background-color: #333;
            border-color: #555;
            color: #f0f0f0;
        }

        .form-control:focus {
            background-color: #333;
            color: #f0f0f0;
            border-color: #4361ee;
        }

        .alert-danger {
            color: #f8d7da;
            background-color: #842029;
            border-color: #842029;
        }

        .alert-success {
            color: #d4edda;
            background-color: #0f5132;
            border-color: #0f5132;
        }
    }
</style>

<div class="content-card fade-in">
    <div class="page-header">
        <h1 class="page-title">
            <svg class="icon" viewBox="0 0 16 16" width="16" height="16">
                <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4zm-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10c-2.29 0-3.516.68-4.168 1.332-.678.678-.83 1.418-.832 1.664h10z"/>
            </svg>
            Gerenciar Revendedores
        </h1>
        
        <?php if (isset($_SESSION["nivel"]) && $_SESSION["nivel"] <= 2): ?>
            <button class="btn btn-primary" onclick="abrirModalCadastro()">
                <svg class="icon" viewBox="0 0 16 16" width="16" height="16">
                    <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                </svg>
                Novo Revendedor
            </button>
        <?php endif; ?>
    </div>
    
    <?php if ($mensagem): ?>
        <div class="fade-in"><?php echo $mensagem; ?></div>
    <?php endif; ?>
    
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Usuário</th>
                    <th>E-mail</th>
                    <th>Tipo</th>
                    <th>Status</th>
                    <th>Data Cadastro</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($revendedores)): ?>
                    <tr>
                        <td colspan="7" class="text-center">Nenhum revendedor encontrado</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($revendedores as $revendedor): ?>
                        <tr>
                            <td><?php echo $revendedor['id']; ?></td>
                            <td><?php echo htmlspecialchars($revendedor['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($revendedor['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php if ($revendedor['nivel'] == 2): ?>
                                    <span class="badge badge-revenda">Revenda</span>
                                <?php else: ?>
                                    <span class="badge badge-subrevenda">Sub-Revenda</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo $revendedor['ativo'] ? 
                                    '<span class="badge badge-success">Ativo</span>' : 
                                    '<span class="badge badge-warning">Inativo</span>'; ?>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($revendedor['data_criacao'])); ?></td>
                            <td>
                                <?php if (verificarPermissaoEdicao($pdo, $revendedor['id'], $_SESSION["user_id"], $_SESSION["nivel"])): ?>
                                    <div class="btn-group">
                                        <button class="btn btn-primary btn-sm" 
                                                onclick="abrirModalEdicao(
                                                    <?php echo $revendedor['id']; ?>,
                                                    '<?php echo htmlspecialchars($revendedor['username'], ENT_QUOTES, 'UTF-8'); ?>',
                                                    '<?php echo htmlspecialchars($revendedor['email'], ENT_QUOTES, 'UTF-8'); ?>',
                                                    <?php echo $revendedor['nivel']; ?>,
                                                    <?php echo $revendedor['ativo']; ?>
                                                )">
                                            <svg class="icon" viewBox="0 0 16 16" width="12" height="12">
                                                <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293l6.5-6.5zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                                            </svg>
                                            Editar
                                        </button>
                                        <a href="manager_revenda.php?acao=deletar&id=<?php echo $revendedor['id']; ?>" 
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirmarExclusao(event)">
                                            <svg class="icon" viewBox="0 0 16 16" width="12" height="12">
                                                <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>
                                                <path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/>
                                            </svg>
                                            Excluir
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal de Cadastro -->
<div id="modalCadastro" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">
                <svg class="icon" viewBox="0 0 16 16" width="16" height="16">
                    <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                </svg>
                Cadastrar Novo Revendedor
            </h2>
            <button type="button" class="modal-close" onclick="fecharModalCadastro()">&times;</button>
        </div>
        
        <form method="POST" action="manager_revenda.php" onsubmit="return validarFormCadastro()">
            <div class="form-group">
                <label for="username" class="form-label">Nome de Usuário</label>
                <input type="text" id="username" name="username" class="form-control" required minlength="4" maxlength="30">
                <small class="text-muted">Entre 4 e 30 caracteres</small>
            </div>
            
            <div class="form-group">
                <label for="email" class="form-label">E-mail</label>
                <input type="email" id="email" name="email" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">Senha</label>
                <input type="password" id="password" name="password" class="form-control" required minlength="8">
                <small class="text-muted">Mínimo 8 caracteres</small>
            </div>
            
            <div class="form-group">
                <label for="nivel" class="form-label">Tipo de Revendedor</label>
                <select id="nivel" name="nivel" class="form-control" required>
                    <?php if (isset($_SESSION["nivel"]) && $_SESSION["nivel"] == 1): ?>
                        <option value="2">Revendedor (Level 2)</option>
                        <option value="3">Sub-Revendedor (Level 3)</option>
                    <?php elseif (isset($_SESSION["nivel"]) && $_SESSION["nivel"] == 2): ?>
                        <option value="3">Sub-Revendedor (Level 3)</option>
                    <?php endif; ?>
                </select>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" onclick="fecharModalCadastro()">Cancelar</button>
                <button type="submit" name="cadastrar" class="btn btn-primary">Cadastrar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de Edição -->
<div id="modalEdicao" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">
                <svg class="icon" viewBox="0 0 16 16" width="16" height="16">
                    <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293l6.5-6.5zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                </svg>
                Editar Revendedor
            </h2>
            <button type="button" class="modal-close" onclick="fecharModalEdicao()">&times;</button>
        </div>
        
        <form method="POST" action="manager_revenda.php" onsubmit="return validarFormEdicao()">
            <input type="hidden" id="edit_id" name="id">
            
            <div class="form-group">
                <label for="edit_username" class="form-label">Nome de Usuário</label>
                <input type="text" id="edit_username" name="username" class="form-control" required minlength="4" maxlength="30">
            </div>
            
            <div class="form-group">
                <label for="edit_email" class="form-label">E-mail</label>
                <input type="email" id="edit_email" name="email" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="edit_nivel" class="form-label">Tipo de Revendedor</label>
                <select id="edit_nivel" name="nivel" class="form-control" required>
                    <?php if (isset($_SESSION["nivel"]) && $_SESSION["nivel"] == 1): ?>
                        <option value="2">Revendedor (Level 2)</option>
                        <option value="3">Sub-Revendedor (Level 3)</option>
                    <?php elseif (isset($_SESSION["nivel"]) && $_SESSION["nivel"] == 2): ?>
                        <option value="3">Sub-Revendedor (Level 3)</option>
                    <?php endif; ?>
                </select>
            </div>
            
            <div class="form-group">
                <div class="form-check">
                    <input type="checkbox" id="edit_ativo" name="ativo" value="1" class="form-check-input">
                    <label for="edit_ativo" class="form-check-label">Ativo</label>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" onclick="fecharModalEdicao()">Cancelar</button>
                <button type="submit" name="editar" class="btn btn-primary">Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>

<script>
// Funções para controle dos modais
function abrirModalCadastro() {
    document.getElementById('modalCadastro').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function fecharModalCadastro() {
    document.getElementById('modalCadastro').classList.remove('show');
    document.body.style.overflow = '';
}

function abrirModalEdicao(id, username, email, nivel, ativo) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_nivel').value = nivel;
    document.getElementById('edit_ativo').checked = ativo == 1;
    
    document.getElementById('modalEdicao').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function fecharModalEdicao() {
    document.getElementById('modalEdicao').classList.remove('show');
    document.body.style.overflow = '';
}

// Fechar modal ao clicar fora
window.addEventListener('click', function(event) {
    if (event.target.classList.contains('modal')) {
        fecharModalCadastro();
        fecharModalEdicao();
    }
});

// Validação de formulários
function validarFormCadastro() {
    const username = document.getElementById('username').value.trim();
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    
    if (username.length < 4 || username.length > 30) {
        alert('O nome de usuário deve ter entre 4 e 30 caracteres!');
        return false;
    }
    
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        alert('Por favor, insira um e-mail válido!');
        return false;
    }
    
    if (password.length < 8) {
        alert('A senha deve ter no mínimo 8 caracteres!');
        return false;
    }
    
    return true;
}

function validarFormEdicao() {
    const username = document.getElementById('edit_username').value.trim();
    const email = document.getElementById('edit_email').value.trim();
    
    if (username.length < 4 || username.length > 30) {
        alert('O nome de usuário deve ter entre 4 e 30 caracteres!');
        return false;
    }
    
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        alert('Por favor, insira um e-mail válido!');
        return false;
    }
    
    return true;
}

// Confirmação de exclusão
function confirmarExclusao(event) {
    if (!confirm('Tem certeza que deseja excluir este revendedor?\nEsta ação não pode ser desfeita!')) {
        event.preventDefault();
        return false;
    }
    return true;
}

// Fechar modais com ESC
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        fecharModalCadastro();
        fecharModalEdicao();
    }
});
</script>

<?php 
include "includes/footer.php"; 
?>