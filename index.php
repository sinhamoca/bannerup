<?php
// Inicia a sessão para que possamos usar a variável $_SESSION["usuario"]
session_start();

// Se o usuário não estiver logado, redireciona para a página de login
if (!isset($_SESSION["usuario"])) {
    header("Location: login.php");
    exit();
}

$pageTitle = "Página Inicial";
include "includes/header.php";
?>

<style>
    /* Reset e Estilos Globais */
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        /* Mantém o mesmo fundo da página de login para consistência */
        background-image: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), url('https://images.unsplash.com/photo-1579952363873-27f3bade9f55');
        background-position: center;
        background-size: cover;
        background-attachment: fixed;
        min-height: 100vh;
        /* Centraliza o container principal */
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 20px;
    }

    /* Container principal com efeito de vidro */
    .dashboard-container {
        width: 100%;
        max-width: 800px; /* Maior para acomodar o grid */
        padding: 40px;
        background: rgba(0, 0, 0, 0.4);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border-radius: 15px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
        text-align: center;
        color: #fff;
    }

    /* Cabeçalho de boas-vindas */
    .dashboard-header {
        margin-bottom: 40px;
    }

    .dashboard-header h1 {
        font-size: 2.5rem;
        font-weight: 600;
        margin-bottom: 5px;
    }

    .dashboard-header p {
        font-size: 1.1rem;
        color: #ccc;
    }
    
    /* Novo Layout em Grid para os botões */
    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr); /* 2 colunas por padrão */
        gap: 25px; /* Espaçamento entre os cards */
    }

    /* Estilo dos "Cards de Ação" */
    .card-link {
        display: block;
        padding: 30px 20px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 10px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        text-decoration: none;
        color: #fff;
        transition: all 0.3s ease;
    }

    .card-link:hover {
        transform: translateY(-5px);
        background: rgba(255, 255, 255, 0.2);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
    }

    .card-link i {
        font-size: 2.5rem; /* Ícones maiores */
        margin-bottom: 15px;
        display: block;
        color: #28a745; /* Cor de destaque verde */
    }

    .card-link span {
        font-size: 1.1rem;
        font-weight: 500;
    }
    
    /* Cor especial para o botão de Deslogar */
    .card-link.logout i {
        color: #e74c3c;
    }

    /* Layout responsivo para telas menores */
    @media (max-width: 768px) {
        .dashboard-container {
            padding: 30px;
        }
        .dashboard-header h1 {
            font-size: 2rem;
        }
        /* Muda o grid para 1 coluna em telas de celular */
        .dashboard-grid {
            grid-template-columns: 1fr;
            gap: 20px;
        }
    }

</style>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h1>Bem-vindo, <?php echo htmlspecialchars($_SESSION["usuario"]); ?>!</h1>
        <p>O que você gostaria de fazer hoje?</p>
    </div>

    <div class="dashboard-grid">
        <a href="painel.php" class="card-link">
            <i class="fas fa-film"></i>
            <span>Banner Filmes e Séries</span>
        </a>
        <a href="futbanner.php" class="card-link">
            <i class="fas fa-futbol"></i>
            <span>Banner Fut</span>
        </a>
        <a href="setting.php" class="card-link">
            <i class="fas fa-cog"></i>
            <span>Credenciais</span>
        </a>
        <a href="logout.php" class="card-link logout">
            <i class="fas fa-sign-out-alt"></i>
            <span>Sair</span>
        </a>
    </div>
</div>

<?php include "includes/footer.php"; ?>