<?php
session_start();
if (!isset($_SESSION["usuario"]) || !isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION["user_id"]);
$pageTitle = isset($_GET['banner']) ? "Gerador de Banner" : "Selecionar Modelo de Banner";
include "includes/header.php";

// Obter dados dos jogos da APIHL
$api_url = 'https://api.hlserver.com.br/api.php';
$jogos = [];

$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
curl_close($ch);

if ($response !== false) {
    $todos_jogos = json_decode($response, true);
    if (is_array($todos_jogos)) {
        // Filtrar apenas jogos de hoje
        $hoje = date('d/m/Y');
        foreach ($todos_jogos as $jogo) {
            if (isset($jogo['data']) && $jogo['data'] === $hoje) {
                // Padronizar a estrutura para compatibilidade com os geradores existentes
                $jogos[] = [
                    'campeonato' => $jogo['liga'],
                    'data_jogo' => 'hoje', // Mantém como 'hoje' para compatibilidade
                    'hora_jogo' => $jogo['hora'],
                    'time_casa' => $jogo['time1'],
                    'time_fora' => $jogo['time2'],
                    'placar_casa' => isset($jogo['placar1']) ? $jogo['placar1'] : '',
                    'placar_fora' => isset($jogo['placar2']) ? $jogo['placar2'] : '',
                    'status' => $jogo['status'],
                    'canais' => $jogo['canais'],
                    'escudo_casa' => $jogo['logo1'],
                    'escudo_fora' => $jogo['logo2']
                ];
            }
        }
    }
}

$jogosPorBanner = 5;
$gruposDeJogos = array_chunk($jogos, $jogosPorBanner);

// Processar geração com logo e fundo do usuário
if (isset($_POST['generate_with_my_logo']) && isset($_GET['banner'])) {
    $tipo_banner = $_GET['banner'];
    $logo_types = [
        '1' => 'logo_banner_1',
        '2' => 'logo_banner_2',
        '3' => 'logo_banner_3',
        '4' => 'logo_banner_4',
        '5' => 'logo_banner_5',
    ];
    $bg_types = [
        '1' => 'background_banner_1',
        '2' => 'background_banner_2',
        '3' => 'background_banner_3',
        '4' => 'background_banner_4',
        '5' => 'background_banner_5',
    ];
    
    if (isset($logo_types[$tipo_banner]) && isset($bg_types[$tipo_banner])) {
        $logo_key = $logo_types[$tipo_banner];
        $bg_key = $bg_types[$tipo_banner];
        $user_logo_json_path = "./api/fzstore/user_{$user_id}/{$logo_key}.json";
        $global_logo_json_path = "./api/fzstore/{$logo_key}.json";
        $user_bg_json_path = "./api/fzstore/user_{$user_id}/{$bg_key}.json";
        $global_bg_json_path = "./api/fzstore/{$bg_key}.json";
        $global_logo_dir = "./fzstore/logo/";
        $global_bg_dir = "./fzstore/Img/";
        $success = true;
        $error_msg = '';

        // Default background fallback
        $default_bg_path = "./fzstore/Img/default_background.png";

        // Processar Logo
        if (file_exists($user_logo_json_path)) {
            $user_logo_data = json_decode(file_get_contents($user_logo_json_path), true);
            if (is_array($user_logo_data) && isset($user_logo_data[0]['ImageName']) && isset($user_logo_data[0]['Upload_type'])) {
                $logo_imageName = $user_logo_data[0]['ImageName'];
                $logo_uploadType = $user_logo_data[0]['Upload_type'];

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
                        $error_msg = "Arquivo de logo não encontrado em: $user_file_path";
                    }
                }

                if ($success) {
                    $logo_jsonData = json_encode([["ImageName" => $logo_imageName, "Upload_type" => $logo_uploadType]]);
                    if (!file_put_contents($global_logo_json_path, $logo_jsonData)) {
                        $success = false;
                        $error_msg = "Erro ao salvar o arquivo JSON do logo em: $global_logo_json_path";
                    }
                }
            } else {
                $success = false;
                $error_msg = "Dados de logo inválidos ou ausentes no JSON: $user_logo_json_path";
            }
        } else {
            $success = false;
            $error_msg = "Arquivo JSON de logo não encontrado para o usuário: $user_logo_json_path";
        }

        // Processar Fundo
        if (file_exists($user_bg_json_path)) {
            $user_bg_data = json_decode(file_get_contents($user_bg_json_path), true);
            if (is_array($user_bg_data) && isset($user_bg_data[0]['ImageName']) && isset($user_bg_data[0]['Upload_type'])) {
                $bg_imageName = $user_bg_data[0]['ImageName'];
                $bg_uploadType = $user_bg_data[0]['Upload_type'];

                if ($bg_uploadType == "by_file") {
                    $user_file_path = str_replace('../', './', $bg_imageName);
                    $extension = pathinfo($user_file_path, PATHINFO_EXTENSION);
                    $global_fileName = $bg_key . '.' . $extension;
                    $global_destination = $global_bg_dir . $global_fileName;
                    if (!is_dir($global_bg_dir)) mkdir($global_bg_dir, 0755, true);
                    if (file_exists($user_file_path)) {
                        copy($user_file_path, $global_destination);
                        $bg_imageName = "../fzstore/Img/{$global_fileName}";
                    } else {
                        if (file_exists($default_bg_path)) {
                            $bg_imageName = "../fzstore/Img/default_background.png";
                            $bg_uploadType = "by_file";
                        } else {
                            $success = false;
                            $error_msg = "Arquivo de fundo não encontrado em: $user_file_path e nenhum fundo padrão disponível em: $default_bg_path";
                        }
                    }
                }

                if ($success) {
                    $bg_jsonData = json_encode([["ImageName" => $bg_imageName, "Upload_type" => $bg_uploadType]]);
                    if (!file_put_contents($global_bg_json_path, $bg_jsonData)) {
                        $success = false;
                        $error_msg = "Erro ao salvar o arquivo JSON do fundo em: $global_bg_json_path";
                    }
                }
            } else {
                if (file_exists($default_bg_path)) {
                    $bg_imageName = "../fzstore/Img/default_background.png";
                    $bg_uploadType = "by_file";
                    $bg_jsonData = json_encode([["ImageName" => $bg_imageName, "Upload_type" => $bg_uploadType]]);
                    if (!file_put_contents($global_bg_json_path, $bg_jsonData)) {
                        $success = false;
                        $error_msg = "Erro ao salvar o arquivo JSON do fundo padrão em: $global_bg_json_path";
                    }
                } else {
                    $success = false;
                    $error_msg = "Dados de fundo inválidos ou ausentes no JSON: $user_bg_json_path e nenhum fundo padrão disponível em: $default_bg_path";
                }
            }
        } else {
            if (file_exists($default_bg_path)) {
                $bg_imageName = "../fzstore/Img/default_background.png";
                $bg_uploadType = "by_file";
                $bg_jsonData = json_encode([["ImageName" => $bg_imageName, "Upload_type" => $bg_uploadType]]);
                if (!file_put_contents($global_bg_json_path, $bg_jsonData)) {
                    $success = false;
                    $error_msg = "Erro ao salvar o arquivo JSON do fundo padrão em: $global_bg_json_path";
                }
            } else {
                $success = false;
                $error_msg = "Arquivo JSON de fundo não encontrado: $user_bg_json_path e nenhum fundo padrão disponível em: $default_bg_path";
            }
        }

        if ($success) {
            $mensagem = "<div class='alert alert-success text-center'>Logo e fundo configurados para geração! Gerando banners...</div>";
        } else {
            $mensagem = "<div class='alert alert-danger text-center'>{$error_msg}</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: #111827;
            color: #e5e7eb;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
        }
        .container {
            max-width: 1200px;
            padding: 2rem 1rem;
        }
        .card {
            background: #1f2937;
            border: none;
            border-radius: 16px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.4);
        }
        .card-img-top {
            width: 100%;
            object-fit: contain;
            border-bottom: 2px solid #2563eb;
            display: block;
            max-width: 100%;
        }
        .card-body {
            padding: 1.5rem;
        }
        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #60a5fa;
            margin-bottom: 1rem;
            text-align: center;
        }
        .btn {
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-primary {
            background: #2563eb;
            border: none;
        }
        .btn-primary:hover {
            background: #1e40af;
            transform: translateY(-2px);
        }
        .btn-success {
            background: #10b981;
            border: none;
        }
        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
        }
        .btn-warning {
            background: #f59e0b;
            border: none;
        }
        .btn-warning:hover {
            background: #d97706;
            transform: translateY(-2px);
        }
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        .alert {
            border-radius: 8px;
            margin-bottom: 2rem;
            padding: 1rem;
            text-align: center;
        }
        h1 {
            font-size: 2.5rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 2.5rem;
            color: #ffffff;
        }
        .banner-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        .no-games {
            background: #1f2937;
            padding: 2rem;
            border-radius: 16px;
            text-align: center;
            font-size: 1.1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (isset($_GET['banner'])) : 
            $tipo_banner = $_GET['banner'];
            $geradorScript = '';
            switch ($tipo_banner) {
                case '1': $geradorScript = 'gerar_fut.php'; break;
                case '2': $geradorScript = 'gerar_fut_2.php'; break;
                case '3': $geradorScript = 'gerar_fut_3.php'; break;
                case '4': $geradorScript = 'gerar_fut_4.php'; break;
                case '5': $geradorScript = 'gerar_fut_5.php'; break;
                default:
                    echo "<div class='alert alert-danger'>Tipo de banner inválido!</div>";
                    include "includes/footer.php";
                    exit();
            }
        ?>
            <h1>Banners de Jogos de Hoje (Modelo <?php echo htmlspecialchars($tipo_banner); ?>)</h1>
            <div class="action-buttons">
                <a href="<?php echo basename(__FILE__); ?>" class="btn btn-warning"><i class="fas fa-arrow-left"></i> Voltar</a>
                <?php if (!empty($jogos)) : ?>
                    <a href="<?php echo $geradorScript; ?>?download_all=1" class="btn btn-success"><i class="fas fa-file-archive"></i> Baixar Todos (ZIP)</a>
                    <form method="post">
                        <button type="submit" name="generate_with_my_logo" class="btn btn-primary"><i class="fas fa-image"></i> Gerar com Minha Logo</button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if (isset($mensagem)) echo $mensagem; ?>

            <?php if (empty($jogos)) : ?>
                <div class="no-games"><p>Nenhum jogo disponível no momento.</p></div>
            <?php else : ?>
                <div class="banner-grid">
                    <?php foreach ($gruposDeJogos as $index => $grupo): ?>
                        <div class="card">
                            <img src="<?php echo $geradorScript; ?>?grupo=<?php echo $index; ?>" alt="Banner Parte <?php echo $index + 1; ?>" class="card-img-top" loading="lazy">
                            <div class="card-body text-center">
                                <h5 class="card-title">Banner Parte <?php echo $index + 1; ?></h5>
                                <a href="<?php echo $geradorScript; ?>?grupo=<?php echo $index; ?>&download=1" class="btn btn-primary"><i class="fas fa-download"></i> Baixar</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php else : ?>
            <h1>Escolha o Modelo de Banner</h1>
            <?php if (empty($jogos)): ?>
                <div class="no-games"><p>Nenhum jogo disponível no momento para gerar banners.</p></div>
            <?php else: ?>
                <div class="banner-grid">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <div class="card">
                            <img src="fzstore/themasJG/thema<?php echo $i; ?>.png" alt="Prévia do Banner <?php echo $i; ?>" class="card-img-top" loading="lazy">
                            <div class="card-body text-center">
                                <h5 class="card-title">Banner <?php echo $i; ?></h5>
                                <a href="?banner=<?php echo $i; ?>" class="btn btn-success">Selecionar Modelo</a>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php include "includes/footer.php"; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>