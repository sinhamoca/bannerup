<?php
session_start();
if (!isset($_SESSION["usuario"])) {
    header("Location: login.php");
    exit();
}

$pageTitle = "Banner Filmes e Séries";
include "includes/header.php"; 
?>

<style>
    /* Estilos de formulário padrão (reutilizados) */
    .form-group { position: relative; margin-bottom: 20px; }
    .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-muted); }
    .input-field {
        width: 100%; padding: 12px 15px; background: rgba(0, 0, 0, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 8px;
        color: #fff; font-size: 1em; transition: all 0.3s ease;
    }
    .input-field:focus { outline: none; border-color: var(--accent-color); }

    /* --- NOVOS ESTILOS PARA O SELETOR CUSTOMIZADO --- */
    .custom-select-wrapper {
        position: relative;
        width: 100%;
    }
    .custom-select-trigger {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 15px;
        background: rgba(0, 0, 0, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 8px;
        color: #fff;
        font-size: 1em;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    .custom-select-wrapper.open .custom-select-trigger {
        border-color: var(--accent-color);
        border-bottom-left-radius: 0;
        border-bottom-right-radius: 0;
    }
    .custom-options {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: #2c2f4a; /* Cor de fundo sólida para as opções */
        border: 1px solid var(--accent-color);
        border-top: none;
        border-radius: 0 0 8px 8px;
        z-index: 10;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: all 0.3s ease;
    }
    .custom-select-wrapper.open .custom-options {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }
    .custom-option {
        padding: 12px 15px;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    .custom-option:hover {
        background-color: var(--accent-color);
    }
    .custom-option:not(:last-child) {
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    /* Fim dos novos estilos */

    .submit-btn {
        width: 100%; padding: 12px 30px; border: none; border-radius: 8px;
        background-color: var(--accent-color); color: #fff; font-size: 1.1em;
        font-weight: bold; cursor: pointer; transition: all 0.3s ease;
        display: flex; align-items: center; justify-content: center;
    }
    .submit-btn i { margin-right: 8px; }
    .submit-btn:hover { background-color: #3a5bbf; transform: translateY(-2px); }

    .form-logo { max-width: 100px; margin: 0 auto 20px auto; opacity: 0.8; }
</style>

<div class="page-header">
    <h1><i class="fas fa-film" style="color: var(--accent-color);"></i> Gerar Banner - Filme/Série</h1>
    <p style="color: var(--text-muted);">Busque por um título para gerar seu banner personalizado.</p>
</div>

<div class="content-card" style="max-width: 500px; margin: 0 auto;">
    <div style="text-align: center;">
        <img src="./img/logo.png" alt="Logo" class="form-logo">
    </div>

    <form action="buscar.php" method="GET" onsubmit="playClickSound()">
        <div class="form-group">
            <label for="query">Nome do Filme ou Série</label>
            <input type="text" id="query" name="query" class="input-field" placeholder="Ex: Interestelar" required>
        </div>
        
        <div class="form-group">
            <label>Tipo</label>
            <div class="custom-select-wrapper">
                <input type="hidden" name="type" id="selected-type" value="filme">
                
                <div class="custom-select-trigger">
                    <span id="selected-text">Filme</span>
                    <i class="fas fa-chevron-down"></i>
                </div>
                
                <div class="custom-options">
                    <div class="custom-option" data-value="filme">Filme</div>
                    <div class="custom-option" data-value="serie">Série</div>
                </div>
            </div>
        </div>

        <button type="submit" class="submit-btn">
            <i class="fas fa-search"></i> Buscar Agora
        </button>
    </form>
</div>

<audio id="clickSound" src="https://cdn.pixabay.com/audio/2022/03/15/audio_12d3b003b2.mp3" preload="auto"></audio>

<script>
    // Som do clique
    function playClickSound() {
        document.getElementById('clickSound').play();
    }

    // Lógica para o novo seletor customizado
    document.addEventListener('DOMContentLoaded', function() {
        const wrapper = document.querySelector('.custom-select-wrapper');
        const trigger = document.querySelector('.custom-select-trigger');
        const options = document.querySelectorAll('.custom-option');
        const hiddenInput = document.getElementById('selected-type');
        const selectedText = document.getElementById('selected-text');

        // Abre/fecha o dropdown
        trigger.addEventListener('click', () => {
            wrapper.classList.toggle('open');
        });

        // Lógica de seleção de opção
        options.forEach(option => {
            option.addEventListener('click', function() {
                // Atualiza o texto visível
                selectedText.textContent = this.textContent;
                
                // Atualiza o valor do input oculto que será enviado
                hiddenInput.value = this.getAttribute('data-value');
                
                // Fecha o dropdown
                wrapper.classList.remove('open');
            });
        });

        // Fecha o dropdown se clicar fora dele
        window.addEventListener('click', function(e) {
            if (!wrapper.contains(e.target)) {
                wrapper.classList.remove('open');
            }
        });
    });
</script>

<?php 
include "includes/footer.php"; 
?>