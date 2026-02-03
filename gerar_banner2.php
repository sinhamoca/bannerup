<?php
session_start();
if (!isset($_SESSION["usuario"])) {
    header("Location: login.php");
    exit();
}

// Limpeza de arquivos temporários antigos
$tempDir = sys_get_temp_dir();
$files = glob($tempDir . '/banner2_*.png');
$currentTime = time();
$expireTime = 600; // 10 minutos em segundos
foreach ($files as $file) {
    if (filemtime($file) < $currentTime - $expireTime) {
        unlink($file);
    }
}

// Configurações da API
$apiKey = 'ec8237f367023fbadd38ab6a1596b40c';
$language = 'pt-BR';

// Verifica se o nome foi especificado
if (!isset($_GET['name'])) {
    echo "Nome do filme ou série não especificado.";
    exit;
}

// Obter nome e tipo (filme ou série)
$name = urlencode($_GET['name']);
$type = isset($_GET['type']) && $_GET['type'] == 'serie' ? 'tv' : 'movie';
$tipoTexto = $type === 'tv' ? 'SÉRIE' : 'FILME';
$year = isset($_GET['year']) ? $_GET['year'] : '';
$searchUrl = "https://api.themoviedb.org/3/search/$type?api_key=$apiKey&language=$language&query=$name" . ($year ? "&year=$year" : '');

// Buscar dados do filme ou série
$searchData = json_decode(file_get_contents($searchUrl), true);
if (empty($searchData['results'])) {
    echo "Nenhum filme ou série encontrado com o nome '$name'.";
    exit;
}

// Obtém o ID do primeiro resultado
$id = $searchData['results'][0]['id'];
$mediaUrl = "https://api.themoviedb.org/3/$type/$id?api_key=$apiKey&language=$language";
$elencoUrl = "https://api.themoviedb.org/3/$type/$id/credits?api_key=$apiKey&language=$language";

$mediaData = json_decode(file_get_contents($mediaUrl), true);
$elencoData = json_decode(file_get_contents($elencoUrl), true);

$nome = isset($mediaData['title']) ? $mediaData['title'] : $mediaData['name'];
$data = date('d/m/Y', strtotime($mediaData['release_date'] ?? $mediaData['first_air_date']));
$categoria = implode(", ", array_column($mediaData['genres'], 'name'));
$sinopse = $mediaData['overview'];
$poster = "https://image.tmdb.org/t/p/w500" . $mediaData['poster_path'];
$atores = array_slice($elencoData['cast'], 0, 5);

// Limitar a sinopse para 349 caracteres
$maxSinopseLength = 349;
if (strlen($sinopse) > $maxSinopseLength) {
    $sinopse = substr($sinopse, 0, $maxSinopseLength) . '...';
}

// Criar imagem
$imageWidth = 720;
$imageHeight = 1100;
$image = imagecreatetruecolor($imageWidth, $imageHeight);

// Fundo
$backgroundImage = imagecreatefromjpeg($poster);
$backgroundWidth = imagesx($backgroundImage);
$backgroundHeight = imagesy($backgroundImage);
$imageAspect = $backgroundWidth / $backgroundHeight;
$imageHeight = $imageWidth / $imageAspect;
$image = imagecreatetruecolor($imageWidth, $imageHeight);
imagecopyresampled($image, $backgroundImage, 0, 0, 0, 0, $imageWidth, $imageHeight, $backgroundWidth, $backgroundHeight);

// Sombra
$shadowColor = imagecolorallocatealpha($image, 0, 0, 0, 70);
imagefilledrectangle($image, 0, 0, $imageWidth, $imageHeight, $shadowColor);

// Fontes
$whiteColor = imagecolorallocate($image, 255, 255, 255);
$yellowColor = imagecolorallocate($image, 255, 215, 0);
$fontPath = __DIR__ . '/fonts/arial.ttf';
$fontSize = 20;

// Função quebra de linha
function wrapText($text, $font, $fontSize, $maxWidth) {
    $wrappedText = '';
    $words = explode(' ', $text);
    $line = '';
    foreach ($words as $word) {
        $testLine = $line . ' ' . $word;
        $testBox = imagettfbbox($fontSize, 0, $font, $testLine);
        $testWidth = $testBox[2] - $testBox[0];
        if ($testWidth <= $maxWidth) {
            $line = $testLine;
        } else {
            $wrappedText .= trim($line) . "\n";
            $line = $word;
        }
    }
    $wrappedText .= trim($line);
    return $wrappedText;
}

// Ícone - leitura do JSON com tratamento de erros
$logoJsonPath = 'api/fzstore/logo_banner_1.json';
try {
    $logoJson = file_get_contents($logoJsonPath);
    if ($logoJson === false) {
        throw new Exception("Não foi possível ler o arquivo JSON");
    }
    $logoData = json_decode($logoJson, true);
    if (json_last_error() !== JSON_ERROR_NONE || empty($logoData)) {
        throw new Exception("JSON inválido ou vazio");
    }
    $iconPath = str_replace('../', '', $logoData[0]['ImageName']);
    if (!file_exists($iconPath)) {
        throw new Exception("Arquivo de logo não encontrado: " . $iconPath);
    }
    $posterY = 80;
    $icon = imagecreatefrompng($iconPath);
    if ($icon === false) {
        throw new Exception("Não foi possível carregar a imagem da logo");
    }
    $iconWidth = 100;
    $iconHeight = 100;
    $iconX = 20;
    $iconY = $posterY - 40;
    imagecopyresampled($image, $icon, $iconX, $iconY, 0, 0, $iconWidth, $iconHeight, imagesx($icon), imagesy($icon));
    imagedestroy($icon);
} catch (Exception $e) {
    error_log("Erro ao carregar logo: " . $e->getMessage());
}

// Tipo (Filme ou Série) no topo
$tipoFontSize = 24;
$tipoBox = imagettfbbox($tipoFontSize, 0, $fontPath, $tipoTexto);
$tipoWidth = $tipoBox[2] - $tipoBox[0];
$tipoX = ($imageWidth - $tipoWidth) / 2;
$tipoY = 35;
imagettftext($image, $tipoFontSize, 0, $tipoX, $tipoY, $whiteColor, $fontPath, $tipoTexto);

// Título
$textBox = imagettfbbox($fontSize, 0, $fontPath, $nome);
$textWidth = $textBox[2] - $textBox[0];
$textX = ($imageWidth - $textWidth) / 2;
imagettftext($image, 24, 0, $textX, $posterY - 15, $yellowColor, $fontPath, $nome);

// Poster centralizado
$posterImage = imagecreatefromjpeg($poster);
$posterWidth = 300;
$posterHeight = 450;
$posterX = ($imageWidth - $posterWidth) / 2;
imagecopyresampled($image, $posterImage, $posterX, $posterY, 0, 0, $posterWidth, $posterHeight, imagesx($posterImage), imagesy($posterImage));

// Borda branca
$borderThickness = 4;
for ($i = 0; $i < $borderThickness; $i++) {
    imagerectangle($image, $posterX - $i, $posterY - $i, $posterX + $posterWidth + $i, $posterY + $posterHeight + $i, $whiteColor);
}

// Info
$infoY = $posterY + $posterHeight + 30;
imagettftext($image, $fontSize, 0, 20, $infoY, $whiteColor, $fontPath, "Lançamento: $data");
imagettftext($image, $fontSize, 0, 20, $infoY + 40, $whiteColor, $fontPath, "Categoria: $categoria");

// Sinopse
$maxWidth = $imageWidth - 40;
$wrappedSinopse = wrapText($sinopse, $fontPath, $fontSize, $maxWidth);
imagettftext($image, $fontSize, 0, 20, $infoY + 90, $whiteColor, $fontPath, $wrappedSinopse);

// Atores
$actorYPosition = $imageHeight - 230;
$actorWidth = 100;
$actorHeight = 150;
$actorXPosition = 20;
foreach ($atores as $ator) {
    $actorImageUrl = "https://image.tmdb.org/t/p/w185" . $ator['profile_path'];
    if (@getimagesize($actorImageUrl)) {
        $actorImage = imagecreatefromjpeg($actorImageUrl);
        imagecopyresampled($image, $actorImage, $actorXPosition, $actorYPosition, 0, 0, $actorWidth, $actorHeight, imagesx($actorImage), imagesy($actorImage));
        $primeiroNome = explode(' ', $ator['name'])[0];
        imagettftext($image, 14, 0, $actorXPosition, $actorYPosition + $actorHeight + 20, $whiteColor, $fontPath, $primeiroNome);
        imagedestroy($actorImage);
        $actorXPosition += $actorWidth + 20;
    }
}

// Rodapé
imagettftext($image, 18, 0, 20, $imageHeight - 20, $whiteColor, $fontPath, "O MELHOR DO STREAMING E AQUI");

// Gera um nome de arquivo temporário
$tempFileName = 'banner2_' . uniqid() . '.png';
$outputPath = $tempDir . DIRECTORY_SEPARATOR . $tempFileName;

// Salva a imagem temporariamente
imagepng($image, $outputPath);

// Armazena o caminho do arquivo na sessão
$_SESSION['temp_banner2'] = $outputPath;

// Limpar
imagedestroy($image);
imagedestroy($posterImage);
imagedestroy($backgroundImage);

// Gera URL para exibição e download
$protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$baseURL = $protocolo . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
$relativePath = 'temp_serve.php?file=' . urlencode($tempFileName);
$urlCompleta = rtrim($baseURL, '/') . '/' . $relativePath;

// Verifica se é uma solicitação de download
if (isset($_GET['action']) && $_GET['action'] === 'download' && file_exists($outputPath)) {
    header('Content-Type: image/png');
    header('Content-Disposition: attachment; filename="banner2_' . $nome . '.png"');
    readfile($outputPath);
    unlink($outputPath); // Remove o arquivo após o download
    unset($_SESSION['temp_banner2']); // Limpa a sessão
    exit;
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Banner Gerado</title>
    <style>
        body { background: #111; color: #fff; text-align: center; font-family: Arial, sans-serif; padding: 40px; }
        .container { max-width: 800px; margin: auto; }
        img { max-width: 100%; height: auto; margin-bottom: 20px; border: 4px solid #00bcd4; }
        a.button, button.button {
            display: inline-block;
            padding: 10px 20px;
            margin: 10px;
            background-color: #00bcd4;
            color: #fff;
            text-decoration: none;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
        }
        a.button:hover, button.button:hover {
            background-color: #0097a7;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Banner Gerado com Sucesso!</h1>
        <img src="<?= $relativePath ?>" alt="Banner do Filme/Série">
        <br>
        <a class="button" href="<?= $relativePath ?>" target="_blank">Ver Imagem</a>
        <a class="button" href="?name=<?= urlencode($nome) ?>&type=<?= $type ?>&year=<?= $year ?>&action=download">Baixar Imagem</a>
        <button class="button" onclick="navigator.clipboard.writeText('<?= $urlCompleta ?>').then(() => alert('URL copiada!'));">Copiar URL</button>
        <a class="button" href="send_telegram.php?image=<?= urlencode($relativePath) ?>" target="_blank">Enviar para Telegram</a>
    </div>
</body>
</html>