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

$card_types = [
    'card_banner_1' => ['name' => 'Card Banner 1', 'fixed_filename' => 'card_banner_1'],
    'card_banner_2' => ['name' => 'Card Banner 2', 'fixed_filename' => 'card_banner_2'],
    'card_banner_3' => ['name' => 'Card Banner 3', 'fixed_filename' => 'card_banner_3'],
    'card_banner_4' => ['name' => 'Card Banner 4', 'fixed_filename' => 'card_banner_4'],
    'card_banner_5' => ['name' => 'Card Banner 5', 'fixed_filename' => 'card_banner_5'],
];

$current_card_key = $_GET['tipo'] ?? array_key_first($card_types);
if (!array_key_exists($current_card_key, $card_types)) {
    header("Location: card.php");
    exit();
}

$current_card_config = $card_types[$current_card_key];
$successMessage = '';
$errorMessage = '';
$redirect_card_key = $current_card_key;

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $posted_card_type = $_POST['card_type'] ?? null;
    if ($posted_card_type && isset($card_types[$posted_card_type])) {
        $redirect_card_key = $posted_card_type;
        $fixed_filename_base = $card_types[$posted_card_type]['fixed_filename'];

        if (isset($_POST['upload']) && isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $file = $_FILES['image'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (in_array($file['type'], $allowedTypes)) {
                $uploadPath = './fzstore/card/';
                if (!is_dir($uploadPath)) mkdir($uploadPath, 0755, true);
                
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $fileName = $fixed_filename_base . '.' . $extension;
                $destination = $uploadPath . $fileName;

                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    // Limpar cache
                    clearstatcache();
                    $successMessage = "Imagem enviada com sucesso!";
                } else {
                    $errorMessage = 'Falha ao mover o arquivo enviado.';
                }
            } else {
                $errorMessage = 'Tipo de arquivo inválido.';
            }
        } elseif (isset($_POST['url-submit'])) {
            $imageUrl = filter_var($_POST['image-url'], FILTER_SANITIZE_URL);
            if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                $successMessage = "URL salva com sucesso!";
            } else {
                $errorMessage = 'A URL fornecida não é válida.';
            }
        }
    } else {
        $errorMessage = "Tipo de card inválido enviado.";
    }
}

$methord = "Não Definido";
$imageFilex = '';
$showPreview = false;
// Simples verificação do arquivo físico ou URL
$uploadPath = './fzstore/card/';
$fileName = $current_card_config['fixed_filename'];
$possibleExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
foreach ($possibleExtensions as $ext) {
    $filePath = $uploadPath . $fileName . '.' . $ext;
    if (file_exists($filePath)) {
        $imageFilex = $filePath;
        $methord = "Arquivo Enviado";
        $showPreview = true;
        break;
    }
}

$pageTitle = "Gerenciar Cards";
include "includes/header.php"; 
?>

<style>
    .two-column-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 30px;
    }
    @media (min-width: 768px) {
        .two-column-grid {
            grid-template-columns: 1fr 1fr;
        }
    }
    .column-box {
        background: rgba(0,0,0,0.15);
        padding: 25px;
        border-radius: 10px;
    }
    .column-box h3 {
        color: var(--accent-color);
        margin-bottom: 20px;
        font-weight: 600;
        border-bottom: 1px solid var(--accent-color);
        padding-bottom: 10px;
    }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; color: var(--text-secondary); margin-bottom: 8px; font-weight: 500; }
    .form-select, .form-control {
        width: 100%; padding: 12px 15px; background-color: var(--page-bg);
        border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; color: var(--text-color);
    }
    .form-select:focus, .form-control:focus { outline: none; border-color: var(--accent-color); }
    
    .preview-area {
        width: 100%;
        height: 250px;
        margin-top: 15px;
        background-color: var(--page-bg);
        background-image: 
            linear-gradient(45deg, rgba(255,255,255,0.05) 25%, transparent 25%),
            linear-gradient(-45deg, rgba(255,255,255,0.05) 25%, transparent 25%),
            linear-gradient(45deg, transparent 75%, rgba(255,255,255,0.05) 75%),
            linear-gradient(-45deg, transparent 75%, rgba(255,255,255,0.05) 75%);
        background-size: 20px 20px;
        border: 2px dashed rgba(255,255,255,0.2);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    .preview-area img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }
    .preview-area .no-image {
        color: var(--text-muted);
        font-size: 0.9rem;
    }
    .current-method-info {
        text-align: center;
        color: var(--text-muted);
        font-size: 0.9rem;
        margin-top: 15px;
        word-break: break-all;
    }
    .current-method-info strong {
        color: var(--accent-color);
    }
    
    .method-switcher {
        display: flex;
        border-radius: 8px;
        overflow: hidden;
    }
    .method-switcher input[type="radio"] {
        display: none;
    }
    .method-switcher label {
        flex: 1;
        text-align: center;
        padding: 12px;
        cursor: pointer;
        background-color: rgba(0,0,0,0.2);
        color: var(--text-muted);
        transition: all 0.3s;
        font-weight: 500;
    }
    .method-switcher input[type="radio"]:checked + label {
        background-color: var(--accent-color);
        color: #fff;
    }

    .submit-btn {
        width: 100%;
        padding: 12px;
        font-size: 1rem;
        font-weight: 600;
        color: #fff;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s;
        background-color: var(--success-color);
    }
    .submit-btn:hover {
        background-color: #218838;
        transform: translateY(-2px);
    }
    /* Estilo para mensagem de erro */
    .error-message {
        text-align: center;
        color: var(--danger-color);
        font-size: 0.9rem;
        margin-top: 15px;
    }
</style>

<div class="page-header">
    <h1><i class="fas fa-th-large" style="color: var(--accent-color);"></i> Gerenciar Cards</h1>
</div>

<div class="content-card">
    <div class="two-column-grid">
        <div class="column-box">
            <h3>1. Selecione e Visualize</h3>
            <div class="form-group">
                <label for="card-selector">Card para Editar:</label>
                <select id="card-selector" class="form-select">
                    <?php foreach ($card_types as $key => $details): ?>
                        <option value="<?= $key ?>" <?= ($key == $current_card_key) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($details['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <label>Prévia Atual:</label>
            <div class="preview-area">
                <?php if ($showPreview): ?>
                    <img src="<?= $imageFilex ?>?v=<?= time() ?>" alt="Preview do Card" onerror="this.onerror=null;this.src='imgelementos/semimagem.png';">
                <?php else: ?>
                    <span class="no-image">Nenhum card definido ou imagem não encontrada.</span>
                <?php endif; ?>
            </div>
            <p class="current-method-info">Método Atual: <strong><?= $methord ?></strong></p>
            <?php if ($showPreview): ?>
                <p class="current-method-info">Origem: <strong><?= htmlspecialchars($imageFilex) ?></strong></p>
            <?php endif; ?>
        </div>

        <div class="column-box">
            <h3>2. Altere a Imagem</h3>
            <div class="method-switcher">
                <input type="radio" id="upload-radio" name="upload-type" value="file" checked>
                <label for="upload-radio"><i class="fas fa-upload"></i> Enviar Arquivo</label>
                
                <input type="radio" id="url-radio" name="upload-type" value="url">
                <label for="url-radio"><i class="fas fa-link"></i> Usar URL</label>
            </div>
            <div class="forms-container" style="margin-top: 20px;">
                <form method="post" enctype="multipart/form-data" id="upload-form" class="method-form" action="card.php?tipo=<?= $current_card_key ?>">
                    <input type="hidden" name="card_type" value="<?= $current_card_key ?>">
                    <div class="form-group">
                        <label for="image">Selecione uma imagem (PNG, JPG, GIF, WebP):</label>
                        <input class="form-control" type="file" name="image" id="image" accept="image/*">
                    </div>
                    <button class="submit-btn" type="submit" name="upload"><i class="fas fa-paper-plane"></i> Enviar</button>
                </form>

                <form method="post" id="url-form" class="method-form" style="display: none;" action="card.php?tipo=<?= $current_card_key ?>">
                    <input type="hidden" name="card_type" value="<?= $current_card_key ?>">
                    <div class="form-group">
                        <label for="image-url">Insira a URL da imagem:</label>
                        <input class="form-control" type="text" name="image-url" id="image-url" placeholder="https://...">
                    </div>
                    <button class="submit-btn" type="submit" name="url-submit"><i class="fas fa-save"></i> Salvar URL</button>
                </form>
            </div>
            <?php if (!empty($errorMessage)): ?>
                <p class="error-message"><?= htmlspecialchars($errorMessage) ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const cardSelector = document.getElementById('card-selector');
    cardSelector.addEventListener('change', function() {
        window.location.href = 'card.php?tipo=' + this.value;
    });

    const uploadRadio = document.getElementById('upload-radio');
    const urlRadio = document.getElementById('url-radio');
    const uploadForm = document.getElementById('upload-form');
    const urlForm = document.getElementById('url-form');
    
    function switchForms() {
        if (uploadRadio.checked) {
            uploadForm.style.display = 'block';
            urlForm.style.display = 'none';
        } else {
            uploadForm.style.display = 'none';
            urlForm.style.display = 'block';
        }
    }
    
    uploadRadio.addEventListener('change', switchForms);
    urlRadio.addEventListener('change', switchForms);
    switchForms();

    <?php if (!empty($successMessage)): ?>
        // Efeito de confete
        confetti({
            particleCount: 150,
            spread: 90,
            origin: { y: 0.5 },
            zIndex: 9999
        });
        setTimeout(() => {
            window.location.href = window.location.pathname + '?tipo=<?= $redirect_card_key ?>';
        }, 2000); // Aguarda 2 segundos para exibir o efeito completo antes de redirecionar
    <?php endif; ?>
});
</script>

<?php 
include "includes/footer.php"; 
?>