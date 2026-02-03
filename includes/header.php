<?php
// Verificar se o usuário está logado e tem nivel definido
if (!isset($_SESSION["usuario"]) || !isset($_SESSION["user_id"]) || !isset($_SESSION["nivel"])) {
    $user_id = $_SESSION["user_id"] ?? 'não definido';
    $nivel = $_SESSION["nivel"] ?? 'não definido';
    error_log(date('Y-m-d H:i:s') . " - Sessão inválida no header: user_id=$user_id, nivel=$nivel\n", 3, 'session_debug.log');
    header("Location: login.php?error=session_invalid");
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1a1a2d">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') . ' - Painel' : 'Painel Administrativo'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        /* Reset e Variáveis CSS */
        :root {
            --sidebar-width: 260px;
            --sidebar-width-collapsed: 80px;
            --page-bg: #1a1a2d;
            --sidebar-bg: rgba(26, 26, 45, 0.9);
            --card-bg: rgba(44, 47, 74, 0.7);
            --text-color: #f0f0f0;
            --text-muted: #a0a0c0;
            --accent-color: #4e73df;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --transition-speed: 0.3s;
            --border-radius: 10px;
            --box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Estilos Base */
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: var(--page-bg);
            color: var(--text-color);
            background-image: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), 
                              url('https://images.unsplash.com/photo-1579952363873-27f3bade9f55');
            background-position: center;
            background-size: cover;
            background-attachment: fixed;
            background-repeat: no-repeat;
            min-height: 100vh;
            line-height: 1.6;
        }

        /* Layout Principal */
        .page-wrapper {
            display: flex;
            min-height: 100vh;
            position: relative;
        }

        /* Sidebar */
        #sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            transition: all var(--transition-speed) ease;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 1000;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: var(--accent-color) transparent;
        }

        #sidebar::-webkit-scrollbar {
            width: 6px;
        }

        #sidebar::-webkit-scrollbar-thumb {
            background-color: var(--accent-color);
            border-radius: 3px;
        }

        #sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            min-height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #sidebar-header h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #fff;
            white-space: nowrap;
            transition: opacity var(--transition-speed);
        }

        #sidebar-content {
            flex-grow: 1;
            padding: 15px 0;
            overflow-y: auto;
        }

        .sidebar-item {
            display: flex;
            align-items: center;
            padding: 14px 20px;
            color: var(--text-muted);
            text-decoration: none;
            transition: all var(--transition-speed);
            font-weight: 500;
            border-left: 4px solid transparent;
            margin: 0 10px;
            border-radius: var(--border-radius);
            white-space: nowrap;
        }

        .sidebar-item:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
            border-left-color: var(--accent-color);
        }

        .sidebar-item.active {
            background: rgba(78, 115, 223, 0.25);
            color: #fff;
            border-left-color: var(--accent-color);
            font-weight: 600;
        }

        .sidebar-item i {
            width: 24px;
            margin-right: 15px;
            font-size: 1.1rem;
            text-align: center;
            transition: margin var(--transition-speed);
        }

        .sidebar-item span {
            transition: opacity var(--transition-speed), transform var(--transition-speed);
        }

        #sidebar-footer {
            padding: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-item.logout:hover {
            border-left-color: var(--danger-color);
            background: rgba(220, 53, 69, 0.1);
        }

        /* Conteúdo Principal */
        #main-content {
            flex-grow: 1;
            margin-left: var(--sidebar-width);
            padding: 25px;
            transition: margin-left var(--transition-speed);
            min-height: 100vh;
        }

        /* Cabeçalho da Página */
        .page-header {
            margin-bottom: 30px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .page-header h1 {
            font-size: clamp(1.8rem, 4vw, 2.2rem);
            font-weight: 700;
            color: #fff;
        }

        /* Cards de Conteúdo */
        .content-card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--box-shadow);
        }

        /* Controles Mobile */
        #menu-toggle-button {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1001;
            background: var(--accent-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: var(--box-shadow);
            transition: all var(--transition-speed);
        }

        #menu-toggle-button:hover {
            transform: scale(1.05);
        }

        #menu-toggle-button:active {
            transform: scale(0.95);
        }

        #overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 999;
            backdrop-filter: blur(3px);
            -webkit-backdrop-filter: blur(3px);
        }

        /* Modo Sidebar Collapsed */
        body.sidebar-collapsed #sidebar {
            width: var(--sidebar-width-collapsed);
        }

        body.sidebar-collapsed #sidebar-header h2,
        body.sidebar-collapsed .sidebar-item span {
            opacity: 0;
            pointer-events: none;
            transform: translateX(-10px);
        }

        body.sidebar-collapsed .sidebar-item {
            justify-content: center;
            padding: 14px 0;
            margin: 0 10px;
        }

        body.sidebar-collapsed .sidebar-item i {
            margin-right: 0;
        }

        body.sidebar-collapsed #main-content {
            margin-left: var(--sidebar-width-collapsed);
        }

        /* Responsividade */
        @media (max-width: 1200px) {
            :root {
                --sidebar-width: 220px;
            }
        }

        @media (max-width: 992px) {
            #sidebar {
                transform: translateX(-100%);
                z-index: 1000;
            }

            body.sidebar-open #sidebar {
                transform: translateX(0);
            }

            body.sidebar-open #overlay {
                display: block;
            }

            #main-content {
                margin-left: 0;
            }

            #menu-toggle-button {
                display: flex;
            }
        }

        @media (max-width: 768px) {
            #main-content {
                padding: 20px 15px;
            }

            .content-card {
                padding: 20px;
            }

            .sidebar-item {
                padding: 12px 15px;
            }
        }

        @media (max-width: 576px) {
            #main-content {
                padding: 15px 10px;
            }

            .content-card {
                padding: 15px;
            }

            .page-header h1 {
                font-size: 1.6rem;
            }

            #menu-toggle-button {
                width: 40px;
                height: 40px;
                top: 10px;
                left: 10px;
            }
        }

        /* Animações */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .fade-in {
            animation: fadeIn 0.4s ease-out;
        }

        /* Melhorias para PWA */
        @media (display-mode: standalone) {
            #main-content {
                padding-top: 15px;
            }
        }
    </style>
</head>
<body>
<div class="page-wrapper">
    <button id="menu-toggle-button" aria-label="Alternar menu">
        <i class="fas fa-bars"></i>
    </button>
    <div id="overlay" onclick="toggleSidebar()"></div>
    
    <aside id="sidebar">
        <div id="sidebar-header">
            <h2>FutBanner</h2>
        </div>
        
        <nav id="sidebar-content" aria-label="Menu principal">
            <a href="index.php" class="sidebar-item" aria-current="page">
                <i class="fa-solid fa-house"></i>
                <span>Dashboard</span>
            </a>
            <a href="futbanner.php" class="sidebar-item">
                <i class="fas fa-futbol"></i>
                <span>Banner Fut</span>
            </a>
            <a href="logo.php" class="sidebar-item">
                <i class="fas fa-image"></i>
                <span>Logo</span>
            </a>
            <a href="background.php" class="sidebar-item">
                <i class="fas fa-photo-video"></i>
                <span>Fundo</span>
            </a>
            
            <?php if (isset($_SESSION["nivel"]) && $_SESSION["nivel"] == 1): ?>
                <a href="card.php" class="sidebar-item">
                    <i class="fas fa-th-large"></i>
                    <span>Card Jogos</span>
                </a>
                <a href="aprov_redefinirsenha.php" class="sidebar-item">  <!-- Corrigido o href e ícone -->
                    <i class="fa-solid fa-fingerprint"></i>
                    <span>Aprov-Redefinir Pass</span>
                </a>
                
                <a href="config_cronbot.php" class="sidebar-item">
                    <i class="fa-solid fa-robot"></i>
                    <span>Config Bot</span>
                </a>
            <?php endif; ?>
            
            <?php if (isset($_SESSION["nivel"]) && $_SESSION["nivel"] <= 2): ?>
                <a href="manager_revenda.php" class="sidebar-item">
                    <i class="fas fa-users-cog"></i>
                    <span>Revendedores</span>
                </a>
                <a href="setting.php" class="sidebar-item">
                    <i class="fas fa-cog"></i>
                    <span>Credenciais</span>
                </a>
            <?php endif; ?>
        </nav>
        
        <div id="sidebar-footer">
            <a href="logout.php" class="sidebar-item logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Sair</span>
            </a>
        </div>
    </aside>
    
    <main id="main-content">