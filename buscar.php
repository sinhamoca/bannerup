<?php
session_start();
if (!isset($_SESSION["usuario"]) || !isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION["user_id"]);

// Limpar diretório img/ (exceto logo.png)
$imgDir = __DIR__ . '/img/'; // Caminho absoluto
if (is_dir($imgDir)) {
    $files = scandir($imgDir);
    if ($files !== false) {
        foreach ($files as $file) {
            $filePath = $imgDir . $file;
            if ($file !== '.' && $file !== '..' && $file !== 'logo.png' && is_file($filePath)) {
                @unlink($filePath);
            }
        }
    }
}

$apiKey = 'ec8237f367023fbadd38ab6a1596b40c';
$language = 'pt-BR';

$pageTitle = "Resultados da Busca";
include "includes/header.php";

// Processar geração com logo do usuário
if (isset($_POST['generate_with_my_logo']) && isset($_POST['banner_type']) && isset($_POST['name']) && isset($_POST['type']) && isset($_POST['year'])) {
    $banner_type = $_POST['banner_type'];
    $logo_types = [
        'horizontal' => 'logo_banner_1', // Para gerar_banner.php
        'vertical' => 'logo_banner_2',   // Para gerar_banner2.php
    ];
    
    if (isset($logo_types[$banner_type])) {
        $logo_key = $logo_types[$banner_type];
        $user_logos_json_path = "./api/fzstore/user_{$user_id}/user_{$user_id}_logos.json";
        $global_logo_json_path = "./api/fzstore/{$logo_key}.json";
        $global_logo_dir = "./fzstore/logo/";
        $success = true;
        $error_msg = '';

        // Processar Logo
        if (file_exists($user_logos_json_path)) {
            $user_logos_data = json_decode(file_get_contents($user_logos_json_path), true);
            if (isset($user_logos_data[$logo_key]) && isset($user_logos_data[$logo_key]['ImageName']) && isset($user_logos_data[$logo_key]['Upload_type'])) {
                $logo_imageName = $user_logos_data[$logo_key]['ImageName'];
                $logo_uploadType = $user_logos_data[$logo_key]['Upload_type'];

                if ($logo_uploadType == "by_file") {
                    $user_file_path = str_replace('../', './', $logo_imageName);
                    $extension = pathinfo($user_file_path, PATHINFO_EXTENSION);
                    $global_fileName = $logo_key . '.' . $extension;
                    $global_destination = $global_logo_dir . $global_fileName;
                    if (!is_dir($global_logo_dir)) mkdir($global_logo_dir, 0755, true);
                    if (file_exists($user_file_path)) {
                        copy($user_file_path, $global_destination);
                        $logo_imageName = "../fzstore/logo/{$global_fileName}";
                    } else {
                        $success = false;
                        $error_msg = "Arquivo de logo não encontrado.";
                    }
                }

                $logo_jsonData = json_encode([["ImageName" => $logo_imageName, "Upload_type" => $logo_uploadType]]);
                if (!file_put_contents($global_logo_json_path, $logo_jsonData)) {
                    $success = false;
                    $error_msg = "Erro ao configurar o logo para geração.";
                }
            } else {
                $success = false;
                $error_msg = "Nenhum logo configurado para este modelo.";
            }
        } else {
            $success = false;
            $error_msg = "Nenhum logo encontrado para o usuário.";
        }

        if ($success) {
            $mensagem = "<div class='alert alert-success' style='margin: 20px auto; max-width: 800px;'>Logo configurado para geração! Gerando banner...</div>";
            // Redirecionar para o script de geração apropriado
            $script = $banner_type == 'horizontal' ? 'gerar_banner.php' : 'gerar_banner2.php';
            $query_params = http_build_query([
                'name' => $_POST['name'],
                'type' => $_POST['type'],
                'year' => $_POST['year']
            ]);
            header("Location: $script?$query_params");
            exit();
        } else {
            $mensagem = "<div class='alert alert-danger' style='margin: 20px auto; max-width: 800px;'>{$error_msg}</div>";
        }
    }
}
?>

<style>
    .results-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 25px;
    }
    .result-card {
        background: var(--card-bg);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 10px;
        text-align: center;
        transition: all 0.3s ease;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }
    .result-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
        border-color: var(--accent-color);
    }
    .poster-container {
        width: 100%;
        height: 300px;
        background-color: #1a1a2d;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .poster-container img { width: 100%; height: 100%; object-fit: cover; }
    .poster-container .no-poster-icon { font-size: 4rem; color: var(--text-muted); }

    .info-container {
        padding: 15px;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .info-container .text-content {
        flex-grow: 1;
    }
    .info-container h3 {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-color);
        margin-bottom: 5px;
    }
    .info-container small { font-size: 0.85rem; color: var(--text-muted); }
    .info-container .buttons-container { 
        margin-top: 15px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .generate-btn {
        width: 100%; 
        padding: 10px; 
        font-size: 0.9rem; 
        font-weight: bold;
        color: #fff; 
        border: none; 
        border-radius: 8px; 
        cursor: pointer;
        transition: all 0.3s;
    }
    .btn-vertical {
        background-color: var(--accent-color);
    }
    .btn-horizontal {
        background-color: #6c757d;
    }
    .btn-generate-logo {
        background-color: #e74a3b;
    }
    .generate-btn:hover { 
        opacity: 0.9;
        transform: translateY(-2px);
    }
    .no-results { text-align: center; padding: 40px; color: var(--text-muted); }
</style>

<?php
if (isset($_GET['query']) && !empty($_GET['query'])) {
    $query = urlencode($_GET['query']);
    $type = isset($_GET['type']) && $_GET['type'] == 'serie' ? 'serie' : 'filme';
    $ano = isset($_GET['ano_lancamento']) ? intval($_GET['ano_lancamento']) : null;
    
    if ($type == 'serie') {
        $api_type = 'tv';
        $url = "https://api.themoviedb.org/3/search/tv?api_key=$apiKey&language=$language&query=$query";
        if ($ano) { $url .= "&first_air_date_year=$ano"; }
    } else {
        $api_type = 'movie';
        $url = "https://api.themoviedb.org/3/search/movie?api_key=$apiKey&language=$language&query=$query";
        if ($ano) { $url .= "&primary_release_year=$ano"; }
    }

    @$response = file_get_contents($url);
    $data = $response ? json_decode($response, true) : null;
    
    echo '<div class="page-header">';
    echo '<h1>Resultados para: "' . htmlspecialchars(urldecode($_GET['query'])) . '"</h1>';
    echo '</div>';

    echo '<div class="content-card">';
    if (isset($mensagem)) echo $mensagem;
    
    if ($data && !empty($data['results'])) {
        echo "<div class='results-grid'>";

        foreach ($data['results'] as $item) {
            $id = $item['id'];
            $title = $item['title'] ?? $item['name'];
            $posterPath = $item['poster_path'] ? "https://image.tmdb.org/t/p/w500" . $item['poster_path'] : null;
            $releaseDate = $item['release_date'] ?? $item['first_air_date'] ?? '';
            $year = $releaseDate ? substr($releaseDate, 0, 4) : '';

            echo "<div class='result-card'>";
            echo "<div class='poster-container'>";
            if ($posterPath) {
                echo "<img src='{$posterPath}' alt='Poster de {$title}'>";
            } else {
                echo "<i class='fas fa-image no-poster-icon'></i>";
            }
            echo "</div>";

            echo "<div class='info-container'>";
            echo "<div class='text-content'>";
            echo "<h3>" . htmlspecialchars($title) . "</h3>";
            if ($releaseDate) {
                echo "<small>Lançamento: " . htmlspecialchars($releaseDate) . "</small>";
            }
            echo "</div>";
            
            // Botões de geração
            echo "<div class='buttons-container'>";
            
            // Botão para banner horizontal
            echo "<form method='GET' action='gerar_banner.php' onsubmit='showLoading(event, this, \"horizontal\")'>";
            echo "<input type='hidden' name='name' value='" . htmlspecialchars($title, ENT_QUOTES) . "'>";
            echo "<input type='hidden' name='type' value='" . ($type == 'serie' ? 'serie' : 'filme') . "'>";
            echo "<input type='hidden' name='year' value='" . htmlspecialchars($year, ENT_QUOTES) . "'>";
            echo "<button type='submit' class='generate-btn btn-horizontal'>";
            echo "<i class='fas fa-arrows-alt-h'></i> Banner Horizontal";
            echo "</button>";
            echo "</form>";
            
            // Botão para banner vertical
            echo "<form method='GET' action='gerar_banner2.php' onsubmit='showLoading(event, this, \"vertical\")'>";
            echo "<input type='hidden' name='name' value='" . htmlspecialchars($title, ENT_QUOTES) . "'>";
            echo "<input type='hidden' name='type' value='" . ($type == 'serie' ? 'serie' : 'filme') . "'>";
            echo "<input type='hidden' name='year' value='" . htmlspecialchars($year, ENT_QUOTES) . "'>";
            echo "<button type='submit' class='generate-btn btn-vertical'>";
            echo "<i class='fas fa-arrows-alt-v'></i> Banner Vertical";
            echo "</button>";
            echo "</form>";
            
            // Botão para gerar com minha logo
            echo "<form method='POST' action='' onsubmit='showLoading(event, this, \"logo\")'>";
            echo "<input type='hidden' name='name' value='" . htmlspecialchars($title, ENT_QUOTES) . "'>";
            echo "<input type='hidden' name='type' value='" . ($type == 'serie' ? 'serie' : 'filme') . "'>";
            echo "<input type='hidden' name='year' value='" . htmlspecialchars($year, ENT_QUOTES) . "'>";
            echo "<input type='hidden' name='banner_type' value='horizontal'>"; // Ou 'vertical' dependendo do contexto
            echo "<button type='submit' name='generate_with_my_logo' class='generate-btn btn-generate-logo'>";
            echo "<i class='fas fa-image'></i> Gerar com Minha Logo";
            echo "</button>";
            echo "</form>";
            
            echo "</div>"; // Fecha buttons-container
            echo "</div>"; // Fecha info-container
            echo "</div>"; // Fecha result-card
        }

        echo "</div>";
    } else {
        echo "<div class='no-results'><i class='fas fa-search' style='font-size: 3rem; margin-bottom: 15px;'></i><p>Nenhum resultado encontrado.</p></div>";
    }
    echo '</div>';

} else {
    echo '<div class="content-card no-results"><p>Por favor, realize uma busca na página anterior.</p></div>';
}
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function showLoading(event, form, actionType) {
        event.preventDefault();
        let titleText;
        if (actionType === 'horizontal') {
            titleText = 'Gerando Banner Horizontal...';
        } else if (actionType === 'vertical') {
            titleText = 'Gerando Banner Vertical...';
        } else {
            titleText = 'Configurando Logo e Gerando Banner...';
        }
        
        Swal.fire({
            title: titleText,
            text: 'Por favor, aguarde...',
            icon: 'info',
            allowOutsideClick: false,
            showConfirmButton: false,
            background: '#2c2f4a',
            color: '#f1f1f1',
            didOpen: () => {
                Swal.showLoading();
            }
        });
        setTimeout(() => {
            form.submit();
        }, 1000);
    }
</script>

<?php 
include "includes/footer.php"; 
?>