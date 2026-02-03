<?php
session_start();
if (!isset($_SESSION["usuario"])) {
    header("Location: login.php");
    exit();
}

// Configurações da API
$apiKey = 'ec8237f367023fbadd38ab6a1596b40c';
$language = 'pt-BR';

// Verifica se o nome do filme/série foi especificado
if (!isset($_GET['name'])) {
    echo "Nome do filme ou série não especificado.";
    exit;
}

// Parâmetros da requisição
$name = urlencode($_GET['name']);
$type = isset($_GET['type']) && $_GET['type'] == 'serie' ? 'tv' : 'movie';
$tipoTexto = $type === 'tv' ? 'SÉRIE' : 'FILME';
$year = isset($_GET['year']) ? $_GET['year'] : '';
$searchUrl = "https://api.themoviedb.org/3/search/$type?api_key=$apiKey&language=$language&query=$name" . ($year ? "&year=$year" : '');

// Busca os dados da API
$searchData = json_decode(file_get_contents($searchUrl), true);
if (empty($searchData['results'])) {
    echo "Nenhum filme ou série encontrado com o nome '$name'.";
    exit;
}

// Obtém o ID do primeiro resultado
$id = $searchData['results'][0]['id'];
$mediaUrl = "https://api.themoviedb.org/3/$type/$id?api_key=$apiKey&language=$language";
$elencoUrl = "https://api.themoviedb.org/3/$type/$id/credits?api_key=$apiKey&language=$language";

// Obtém os detalhes do filme/série e elenco
$mediaData = json_decode(file_get_contents($mediaUrl), true);
$elencoData = json_decode(file_get_contents($elencoUrl), true);

// Extrai informações
$nome = isset($mediaData['title']) ? $mediaData['title'] : $mediaData['name'];
$data = date('d/m/Y', strtotime($mediaData['release_date'] ?? $mediaData['first_air_date']));
$categoria = implode(" • ", array_slice(array_column($mediaData['genres'], 'name'), 0, 3));
$sinopse = $mediaData['overview'];
$poster = "https://image.tmdb.org/t/p/w500" . $mediaData['poster_path'];
$backdropUrl = "https://image.tmdb.org/t/p/w1280" . $mediaData['backdrop_path'];
$atores = array_slice($elencoData['cast'], 0, 5);

// Limita o tamanho da sinopse
$maxSinopseLength = 300;
if (strlen($sinopse) > $maxSinopseLength) {
    $sinopse = substr($sinopse, 0, $maxSinopseLength) . '...';
}

// Cria a imagem
$imageWidth = 1280;
$imageHeight = 853;
$image = imagecreatetruecolor($imageWidth, $imageHeight);
$backgroundImage = imagecreatefromjpeg($backdropUrl);
imagecopyresampled($image, $backgroundImage, 0, 0, 0, 0, $imageWidth, $imageHeight, imagesx($backgroundImage), imagesy($backgroundImage));

// Aplica efeito de sombra desfocada
$corPretaDesfocada = imagecolorallocate($image, 0, 0, 0);
$intensidadeBase = 70;
$numPassos = 5;
$deslocamento = 1;
for ($i = 0; $i < $numPassos; $i++) {
    $alfa = min(127, $intensidadeBase + ($i * ((127 - $intensidadeBase) / ($numPassos - 1))));
    $corSombraDesfocada = imagecolorallocatealpha($image, 0, 0, 0, $alfa);
    imagefilledrectangle(
        $image,
        -$i * $deslocamento,
        -$i * $deslocamento,
        $imageWidth + $i * $deslocamento,
        $imageHeight + $i * $deslocamento,
        $corSombraDesfocada
    );
}
$sombraCentralAlfa = min(127, $intensidadeBase + 10);
$corSombraCentral = imagecolorallocatealpha($image, 0, 0, 0, $sombraCentralAlfa);
imagefilledrectangle($image, 0, 0, $imageWidth, $imageHeight, $corSombraCentral);

// Define cores e fonte
$whiteColor = imagecolorallocate($image, 255, 255, 255);
$yellowColor = imagecolorallocate($image, 255, 215, 0);
$fontPath = __DIR__ . '/fonts/dejavu-sans-bold.ttf';
$fontSize = 20;

// Função para quebrar texto
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

// Carrega o logo
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
    $iconWidth = 240;
    $iconHeight = 240;
    $iconX = 1000;
    $iconY = 600;
    imagecopyresampled($image, $icon, $iconX, $iconY, 0, 0, $iconWidth, $iconHeight, imagesx($icon), imagesy($icon));
    imagedestroy($icon);
} catch (Exception $e) {
    error_log("Erro ao carregar logo: " . $e->getMessage());
}

// Carrega imagem de dispositivos
$urlDispositivos = 'https://i.ibb.co/qLZQSbBp/Design-sem-nome-9.png';
$imgDispositivosResource = imagecreatefrompng($urlDispositivos);
if ($imgDispositivosResource !== false) {
    $imgDisX = 445;
    $imgDisY = 685;
    $imgDisWidth = 416;
    $imgDisHeight = 96;
    imagecopyresampled(
        $image,
        $imgDispositivosResource,
        $imgDisX,
        $imgDisY,
        0,
        0,
        $imgDisWidth,
        $imgDisHeight,
        imagesx($imgDispositivosResource),
        imagesy($imgDispositivosResource)
    );
    imagedestroy($imgDispositivosResource);
}

// Adiciona texto "SÉRIE" ou "FILME"
$tipoFontSize = 24;
$fundoVermelho = imagecolorallocate($image, 255, 0, 0);
$margemVerticalTipo = 10;
$margemHorizontalTipo = 20;
$tipoBox = imagettfbbox($tipoFontSize, 0, $fontPath, $tipoTexto);
$tipoLargura = abs($tipoBox[2] - $tipoBox[0]);
$tipoAltura = abs($tipoBox[7] - $tipoBox[1]);
$boxXTipo = 445;
$boxYTipo = 295;
$boxLarguraTipo = $tipoLargura + ($margemHorizontalTipo * 2);
$boxAlturaTipo = $tipoAltura + ($margemVerticalTipo * 2);
imagefilledrectangle($image, $boxXTipo, $boxYTipo, $boxXTipo + $boxLarguraTipo, $boxYTipo + $boxAlturaTipo, $fundoVermelho);
$textoXTipo = $boxXTipo + $margemHorizontalTipo;
$textoYTipo = $boxYTipo + $margemVerticalTipo + $tipoAltura;
imagettftext($image, $tipoFontSize, 0, $textoXTipo, $textoYTipo, $whiteColor, $fontPath, $tipoTexto);

// Adiciona texto "JÁ DISPONÍVEL"
$textoFixo = "JÁ DISPONÍVEL";
$fontSizeFixo = 38;
$posicaoX = 455;
$posicaoY = 165;
$textBoxFixo = imagettfbbox($fontSizeFixo, 0, $fontPath, $textoFixo);
$textoLargura = $textBoxFixo[2] - $textBoxFixo[0];
$fundoAmarelo = imagecolorallocate($image, 255, 215, 0);
$corPreta = imagecolorallocate($image, 0, 0, 0);
$margemVertical = -6;
$margemHorizontal = 10;
imagefilledrectangle(
    $image,
    $posicaoX - $margemHorizontal,
    $posicaoY + $textBoxFixo[1] - $margemVertical,
    $posicaoX + $textoLargura + $margemHorizontal,
    $posicaoY + $textBoxFixo[7] + $margemVertical,
    $fundoAmarelo
);
imagettftext($image, $fontSizeFixo, 0, $posicaoX, $posicaoY, $corPreta, $fontPath, $textoFixo);

// Adiciona título
$tamanhoMaximoFonte = 33;
$tamanhoMinimoFonte = 7;
$larguraMaximaTitulo = $imageWidth - 460;
$posicaoX_titulo = 445;
$posicaoY_titulo = $posterY + 165;
$tamanhoFonteFinal = $tamanhoMaximoFonte;
while ($tamanhoFonteFinal > $tamanhoMinimoFonte) {
    $textBox = imagettfbbox($tamanhoFonteFinal, 0, $fontPath, $nome);
    $larguraTexto = abs($textBox[2] - $textBox[0]);
    if ($larguraTexto <= $larguraMaximaTitulo) {
        break;
    }
    $tamanhoFonteFinal--;
}
imagettftext(
    $image,
    $tamanhoFonteFinal,
    0,
    $posicaoX_titulo,
    $posicaoY_titulo,
    $whiteColor,
    $fontPath,
    $nome
);

// Adiciona poster
$posterY = 80;
$posterImage = imagecreatefromjpeg($poster);
$posterWidth = 400;
$posterHeight = 650;
$posterX = 10;
imagecopyresampled($image, $posterImage, $posterX, $posterY, 0, 0, $posterWidth, $posterHeight, imagesx($posterImage), imagesy($posterImage));

// Adiciona categoria
$tamanhoMaximoFonteCat = 25;
$tamanhoMinimoFonteCat = 10;
$larguraMaximaCategoria = $imageWidth - 600;
$posicaoX_cat = 600;
$posicaoY_cat = 330;
$tamanhoFonteFinalCat = $tamanhoMaximoFonteCat;
while ($tamanhoFonteFinalCat > $tamanhoMinimoFonteCat) {
    $textBoxCat = imagettfbbox($tamanhoFonteFinalCat, 0, $fontPath, $categoria);
    $larguraTextoCat = abs($textBoxCat[2] - $textBoxCat[0]);
    if ($larguraTextoCat <= $larguraMaximaCategoria) {
        break;
    }
    $tamanhoFonteFinalCat--;
}
imagettftext(
    $image,
    $tamanhoFonteFinalCat,
    0,
    $posicaoX_cat,
    $posicaoY_cat,
    $whiteColor,
    $fontPath,
    $categoria
);

// Adiciona nota e estrelas
$nota = $mediaData['vote_average'];
$notaFormatada = number_format($nota, 1, ',', '');
$estrelaCheia = '★';
$estrelaVazia = '☆';
$totalEstrelas = 10;
$numEstrelasCheias = round($nota);
$numEstrelasVazias = $totalEstrelas - $numEstrelasCheias;
$textoEstrelas = str_repeat($estrelaCheia, $numEstrelasCheias) . str_repeat($estrelaVazia, $numEstrelasVazias);
$posicaoX_estrelas = 450;
$posicaoY_estrelas = 285;
$fontSizeEstrelas = 28;
$fontSizeNota = 24;
imagettftext(
    $image,
    $fontSizeEstrelas,
    0,
    $posicaoX_estrelas,
    $posicaoY_estrelas,
    $yellowColor,
    $fontPath,
    $textoEstrelas
);
$boxEstrelas = imagettfbbox($fontSizeEstrelas, 0, $fontPath, $textoEstrelas);
$larguraEstrelas = $boxEstrelas[2] - $boxEstrelas[0];
$posicaoX_nota = $posicaoX_estrelas + $larguraEstrelas + 15;
imagettftext(
    $image,
    $fontSizeNota,
    0,
    $posicaoX_nota,
    $posicaoY_estrelas,
    $whiteColor,
    $fontPath,
    $notaFormatada
);

// Adiciona sinopse
$maxWidth = $imageWidth - 460;
$wrappedSinopse = wrapText($sinopse, $fontPath, $fontSize, $maxWidth);
imagettftext($image, $fontSize, 0, 445, 390, $whiteColor, $fontPath, $wrappedSinopse);

// Adiciona textos fixos
imagettftext($image, 20, 0, 215, $imageHeight - 30, $whiteColor, $fontPath, "O MELHOR DO STREAMING VOCÊ SÓ ENCONTRA AQUI");
imagettftext($image, 16, 0, 445, $imageHeight - 180, $whiteColor, $fontPath, "DISPONÍVEL EM DIVERSOS APARELHOS");

// Gera um nome de arquivo temporário
$tempDir = sys_get_temp_dir();
$tempFileName = 'banner_' . uniqid() . '.png';
$outputPath = $tempDir . DIRECTORY_SEPARATOR . $tempFileName;

// Salva a imagem temporariamente
imagepng($image, $outputPath);

// Armazena o caminho do arquivo na sessão para uso posterior
$_SESSION['temp_banner'] = $outputPath;

// Libera memória
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
    header('Content-Disposition: attachment; filename="banner_' . $nome . '.png"');
    readfile($outputPath);
    unlink($outputPath); // Remove o arquivo após o download
    unset($_SESSION['temp_banner']); // Limpa a sessão
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