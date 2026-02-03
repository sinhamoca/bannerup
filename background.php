<?php
session_start();
if (!isset($_SESSION["usuario"]) || !isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION["user_id"]);
$background_types = [
    'fundo_banner_1' => ['name' => 'Fundo Banner 1', 'fixed_filename' => 'background_banner_1'],
    'fundo_banner_2' => ['name' => 'Fundo Banner 2', 'fixed_filename' => 'background_banner_2'],
    'fundo_banner_3' => ['name' => 'Fundo Banner 3', 'fixed_filename' => 'background_banner_3'],
    'fundo_banner_4' => ['name' => 'Fundo Banner 4', 'fixed_filename' => 'background_banner_4'],
    'fundo_banner_5' => ['name' => 'Fundo Banner 5', 'fixed_filename' => 'background_banner_5'],
];

$current_bg_key = $_GET['tipo'] ?? array_key_first($background_types);
if (!array_key_exists($current_bg_key, $background_types)) {
    header("Location: background.php");
    exit();
}

$current_bg_config = $background_types[$current_bg_key];
$user_json_path = "./api/fzstore/user_{$user_id}/{$current_bg_config['fixed_filename']}.json";
$global_json_path = "./api/fzstore/{$current_bg_config['fixed_filename']}.json";
$successMessage = '';
$errorMessage = '';
$redirect_bg_key = $current_bg_key;

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $posted_bg_type = $_POST['bg_type'] ?? null;
    if ($posted_bg_type && isset($background_types[$posted_bg_type])) {
        $redirect_bg_key = $posted_bg_type;
        $fixed_filename_base = $background_types[$posted_bg_type]['fixed_filename'];
        $user_json_path_to_update = "./api/fzstore/user_{$user_id}/{$fixed_filename_base}.json";
        $global_json_path_to_update = "./api/fzstore/{$fixed_filename_base}.json";
        $user_upload_dir = "./fzstore/Img/user_{$user_id}/";
        $global_upload_dir = "./fzstore/Img/";

        function update_background_json($path, $imageName, $uploadType) {
            $json_dir = dirname($path);
            if (!is_dir($json_dir)) mkdir($json_dir, 0755, true);
            $jsonData = json_encode([["ImageName" => $imageName, "Upload_type" => $uploadType]]);
            return file_put_contents($path, $jsonData) ? true : false;
        }

        if (isset($_POST['upload']) && isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $file = $_FILES['image'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (in_array($file['type'], $allowedTypes)) {
                if (!is_dir($user_upload_dir)) mkdir($user_upload_dir, 0755, true);
                if (!is_dir($global_upload_dir)) mkdir($global_upload_dir, 0755, true);
                
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $fileName = $fixed_filename_base . '_' . $user_id . '.' . $extension;
                $user_destination = $user_upload_dir . $fileName;
                $global_fileName = $fixed_filename_base . '.' . $extension;
                $global_destination = $global_upload_dir . $global_fileName;

                if (move_uploaded_file($file['tmp_name'], $user_destination)) {
                    // Copy to global directory for gerar_fut.php compatibility
                    copy($user_destination, $global_destination);
                    // Update both user-specific and global JSON
                    $user_success = update_background_json($user_json_path_to_update, "../fzstore/Img/user_{$user_id}/{$fileName}", "by_file");
                    $global_success = update_background_json($global_json_path_to_update, "../fzstore/Img/{$global_fileName}", "by_file");
                    if ($user_success && $global_success) {
                        $successMessage = "Plano de fundo atualizado com sucesso!";
                    } else {
                        $errorMessage = "Erro ao salvar as informações da imagem.";
                    }
                } else {
                    $errorMessage = 'Falha ao mover o arquivo enviado.';
                }
            } else {
                $errorMessage = 'Tipo de arquivo inválido.';
            }
        } elseif (isset($_POST['url-submit'])) {
            $imageUrl = filter_var($_POST['image-url'], FILTER_SANITIZE_URL);
            if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                $user_success = update_background_json($user_json_path_to_update, $imageUrl, "by_url");
                $global_success = update_background_json($global_json_path_to_update, $imageUrl, "by_url");
                if ($user_success && $global_success) {
                    $successMessage = "Plano de fundo atualizado com sucesso!";
                } else {
                    $errorMessage = "Erro ao salvar as informações da imagem.";
                }
            } else {
                $errorMessage = 'A URL fornecida não é válida.';
            }
        }
    } else {
        $errorMessage = "Tipo de plano de fundo inválido enviado.";
    }
}

$methord = "Não Definido";
$imageFilex = '';
$showPreview = false;
if (file_exists($user_json_path)) {
    $jsonDatax = json_decode(file_get_contents($user_json_path), true);
    if (isset($jsonDatax) && is_array($jsonDatax) && !empty($jsonDatax) && isset($jsonDatax[0])) {
        $filenamex = $jsonDatax[0]['ImageName'] ?? '';
        $uploadmethord = $jsonDatax[0]['Upload_type'] ?? 'default';
        if ($uploadmethord == "by_file" && !empty($filenamex)) {
            $imageFilex = str_replace('../', './', $filenamex);
            if (file_exists($imageFilex)) {
                $methord = "Arquivo Enviado";
                $showPreview = true;
            }
        } elseif ($uploadmethord == "by_url" && filter_var($filenamex, FILTER_VALIDATE_URL)) {
            $imageFilex = $filenamex;
            $methord = "URL Externa";
            $showPreview = true;
        }
    }
}

$pageTitle = "Gerenciar Fundos";
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
        height: 200px;
        margin-top: 15px;
        background-color: var(--page-bg);
        border: 2px dashed rgba(255,255,255,0.2);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        position: relative;
    }
    .preview-area img { 
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center;
    }
    .preview-area .no-image {
        color: var(--text-muted);
        font-size: 0.9rem;
    }
    .current-method-info { text-align: center; color: var(--text-muted); font-size: 0.9rem; margin-top: 15px; }
    .current-method-info strong { color: var(--accent-color); }
    
    .method-switcher { display: flex; border-radius: 8px; overflow: hidden; }
    .method-switcher input[type="radio"] { display: none; }
    .method-switcher label {
        flex: 1; text-align: center; padding: 12px; cursor: pointer;
        background-color: rgba(0,0,0,0.2); color: var(--text-muted);
        transition: all 0.3s; font-weight: 500;
    }
    .method-switcher input[type="radio"]:checked + label { background-color: var(--accent-color); color: #fff; }

    .submit-btn {
        width: 100%; padding: 12px; font-size: 1rem; font-weight: 600; color: #fff;
        border: none; border-radius: 8px; cursor: pointer; transition: all 0.3s;
        background-color: var(--success-color);
    }
    .submit-btn:hover { background-color: #218838; transform: translateY(-2px); }
</style>

<div class="page-header">
    <h1><i class="fas fa-photo-video" style="color: var(--accent-color);"></i> Gerenciar Planos de Fundo</h1>
</div>

<div class="content-card">
    <div class="two-column-grid">
        <div class="column-box">
            <h3>1. Selecione e Visualize</h3>
            <div class="form-group">
                <label for="bg-selector">Plano de Fundo para Editar:</label>
                <select id="bg-selector" class="form-select">
                    <?php foreach ($background_types as $key => $details): ?>
                        <option value="<?= $key ?>" <?= ($key == $current_bg_key) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($details['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <label>Prévia Atual:</label>
            <div class="preview-area">
                <?php if ($showPreview): ?>
                    <img src="<?= $imageFilex ?>?v=<?= time() ?>" alt="Preview do Fundo">
                <?php else: ?>
                    <span class="no-image">Nenhum fundo definido ou imagem não encontrada.</span>
                <?php endif; ?>
            </div>
            <p class="current-method-info">Método Atual: <strong><?= $methord ?></strong></p>
            <?php if ($showPreview && $methord == "Arquivo Enviado"): ?>
                <p class="current-method-info">Caminho: <strong><?= htmlspecialchars($imageFilex) ?></strong></p>
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
                <form method="post" enctype="multipart/form-data" id="upload-form" class="method-form" action="background.php?tipo=<?= $current_bg_key ?>">
                    <input type="hidden" name="bg_type" value="<?= $current_bg_key ?>">
                    <div class="form-group">
                        <label for="image">Selecione uma imagem (PNG, JPG, GIF, WebP):</label>
                        <input class="form-control" type="file" name="image" id="image" accept="image/*">
                    </div>
                    <button class="submit-btn" type="submit" name="upload"><i class="fas fa-paper-plane"></i> Enviar</button>
                </form>

                <form method="post" id="url-form" class="method-form" style="display: none;" action="background.php?tipo=<?= $current_bg_key ?>">
                    <input type="hidden" name="bg_type" value="<?= $current_bg_key ?>">
                    <div class="form-group">
                        <label for="image-url">Insira a URL da imagem:</label>
                        <input class="form-control" type="text" name="image-url" id="image-url" placeholder="https://...">
                    </div>
                    <button class="submit-btn" type="submit" name="url-submit"><i class="fas fa-save"></i> Salvar URL</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const bgSelector = document.getElementById('bg-selector');
    bgSelector.addEventListener('change', function() {
        window.location.href = 'background.php?tipo=' + this.value;
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
    Swal.fire({
        title: 'Sucesso!', text: '<?= addslashes($successMessage) ?>', icon: 'success',
        background: '#2c2f4a', color: '#f1f1f1', confirmButtonColor: 'var(--success-color)'
    }).then(() => {
        window.location.href = window.location.pathname + '?tipo=<?= $redirect_bg_key ?>';
    });
    <?php elseif (!empty($errorMessage)): ?>
    Swal.fire({
        title: 'Erro!', text: '<?= addslashes($errorMessage) ?>', icon: 'error',
        background: '#2c2f4a', color: '#f1f1f1', confirmButtonColor: 'var(--danger-color)'
    });
    <?php endif; ?>
});
</script>

<?php 
include "includes/footer.php"; 
?>