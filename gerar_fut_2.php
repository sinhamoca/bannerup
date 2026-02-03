<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');
define('CLOUDINARY_CLOUD_NAME', 'dvi4kawwq');
define('LOGO_OVERRIDES', [
    'St. Louis City' => 'https://a.espncdn.com/combiner/i?img=/i/teamlogos/soccer/500/21812.png',
    'Guarulhos' => 'https://upload.wikimedia.org/wikipedia/pt/d/d5/GuarulhosGRU.png',
    'Estados Unidos' => 'https://a.espncdn.com/combiner/i?img=/i/teamlogos/countries/500/usa.png',
    'Tupa' => 'https://static.flashscore.com/res/image/data/8SqNKfdM-27lsDqoa.png',
    'Tanabi' => 'https://ssl.gstatic.com/onebox/media/sports/logos/_0PCb1YBKcxp8eXBCCtZpg_96x96.png',
]);

function carregarEscudo(string $nomeTime, ?string $url, int $maxSize = 60)
{
    if (!empty($url)) {
        $imagem = _processarImagemDeUrl($url, $maxSize);
        if ($imagem) { return $imagem; }
    }
    return _criarPlaceholderComNome($nomeTime, $maxSize);
}

function _processarImagemDeUrl(string $url, int $maxSize)
{
    $urlParaCarregar = $url;
    $extensao = strtolower(pathinfo($url, PATHINFO_EXTENSION));
    if ($extensao === 'svg') {
        $cloudinaryCloudName = CLOUDINARY_CLOUD_NAME;
        if (empty($cloudinaryCloudName) || $cloudinaryCloudName === 'SEU_CLOUD_NAME_AQUI') {
            error_log("Cloud Name do Cloudinary não configurado. Pulando SVG: $url");
            return false;
        }
        $urlParaCarregar = "https://res.cloudinary.com/{$cloudinaryCloudName}/image/fetch/f_png/" . urlencode($url);
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $urlParaCarregar);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $imageContent = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($imageContent === false || $httpCode >= 400) { return false; }
    $img = @imagecreatefromstring($imageContent);
    if (!$img) { return false; }
    $w = imagesx($img); $h = imagesy($img);
    if ($w == 0 || $h == 0) { imagedestroy($img); return false; }
    $scale = min($maxSize / $w, $maxSize / $h, 1.0);
    $newW = (int)($w * $scale); $newH = (int)($h * $scale);
    $imgResized = imagecreatetruecolor($newW, $newH);
    imagealphablending($imgResized, false); imagesavealpha($imgResized, true);
    imagecopyresampled($imgResized, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);
    imagedestroy($img);
    return $imgResized;
}

function _criarPlaceholderComNome(string $nomeTime, int $size = 68)
{
    $img = imagecreatetruecolor($size, $size);
    if (!$img) return false;
    imagealphablending($img, false); imagesavealpha($img, true);
    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $transparent);
    $textColor = imagecolorallocate($img, 80, 80, 80);
    $fontePath = __DIR__ . '/fonts/RobotoCondensed-Bold.ttf';
    $fontSize = 10.5; $padding = 4;
    if (!file_exists($fontePath)) { imagestring($img, 2, 2, $size/2 - 5, "No Logo", $textColor); return $img; }
    $nomeLimpo = trim(preg_replace('/\s*\([^)]*\)/', '', $nomeTime));
    $palavras = explode(' ', $nomeLimpo);
    $linhas = []; $linhaAtual = '';
    foreach ($palavras as $palavra) {
        $testeLinha = $linhaAtual . ($linhaAtual ? ' ' : '') . $palavra;
        $bbox = imagettfbbox($fontSize, 0, $fontePath, $testeLinha);
        $larguraTeste = $bbox[2] - $bbox[0];
        if ($larguraTeste > ($size - $padding * 2) && $linhaAtual !== '') {
            $linhas[] = $linhaAtual; $linhaAtual = $palavra;
        } else { $linhaAtual = $testeLinha; }
    }
    $linhas[] = $linhaAtual;
    $bbox = imagettfbbox($fontSize, 0, $fontePath, "A");
    $alturaLinha = abs($bbox[7] - $bbox[1]);
    $espacoEntreLinhas = 2;
    $alturaTotalTexto = (count($linhas) * $alturaLinha) + ((count($linhas) - 1) * $espacoEntreLinhas);
    $y = ($size - $alturaTotalTexto) / 2 + $alturaLinha;
    foreach ($linhas as $linha) {
        $bboxLinha = imagettfbbox($fontSize, 0, $fontePath, $linha);
        $larguraLinha = $bboxLinha[2] - $bboxLinha[0];
        $x = ($size - $larguraLinha) / 2;
        imagettftext($img, $fontSize, 0, (int)$x, (int)$y, $textColor, $fontePath, $linha);
        $y += $alturaLinha + $espacoEntreLinhas;
    }
    return $img;
}

function limparNomeCompeticao($nome) {
    $padroesParaRemover = [
        '/\s*-\s*APERTURA\s*/i',
        '/\s*-\s*\d+\|\s*/',
        '/\s*-\s*\d+ª\s*RODADA\s*DE\s*PRÉ-ELIMINATÓRIA\s*/i',
        '/\s*-\s*\d+ª\s*FASE\s*-\s*RODADA\s*\d+\s*/i',
        '/\s*-\s*\d+ª\s*RODADA\s*/i',
        '/\s*-\s*OITAVAS\s*DE\s*FINAL\s*/i',
        '/\s*-\s*RODADA\s*\d+\s*/i',
        '/\s*-\s*FASE\s*DE\s*GRUPOS\s*-\s*RODADA\s*\d+\s*/i',
        '/\s*-\s*AMISTOSO\s*/i',
        '/\s*-\s*AMISTOSOS\s*/i'
    ];
    
    $nomeLimpo = preg_replace($padroesParaRemover, '', $nome);
    return trim($nomeLimpo);
}

function desenharTexto($im, $texto, $x, $y, $cor, $tamanho=12, $angulo=0, $fonteCustom = null) {
    $fontPath = __DIR__ . '/fonts/CalSans-Regular.ttf';
    $fonteUsada = $fonteCustom ?? $fontPath;
    if (file_exists($fonteUsada)) {
        $bbox = imagettfbbox($tamanho, $angulo, $fonteUsada, $texto);
        $alturaTexto = abs($bbox[7] - $bbox[1]);
        imagettftext($im, $tamanho, $angulo, $x, $y + $alturaTexto, $cor, $fonteUsada, $texto);
    } else {
        imagestring($im, 5, $x, $y, $texto, $cor);
    }
}

function getImageFromJson($jsonPath) {
    $jsonContent = @file_get_contents($jsonPath);
    if ($jsonContent === false) { return null; }
    $data = json_decode($jsonContent, true);
    if (empty($data) || !isset($data[0]['ImageName'])) { return null; }
    $imagePath = str_replace('../', '', $data[0]['ImageName']);
    return @file_get_contents($imagePath);
}

function processarCanais($canais) {
    if (empty($canais)) {
        return [];
    }
    
    if (is_array($canais)) {
        return array_filter(array_map('trim', $canais));
    }
    
    if (is_string($canais)) {
        // Remove espaços extras após vírgulas e divide
        $canais = preg_replace('/,\s+/', ',', $canais);
        return array_filter(array_map('trim', explode(',', $canais)));
    }
    
    return [];
}

function formatarCanais($canaisArray) {
    $canaisArray = processarCanais($canaisArray);
    
    if (empty($canaisArray)) {
        return 'Ao vivo';
    }
    
    // Remove entradas vazias e duplicadas
    $canaisFiltrados = array_unique(array_filter($canaisArray));
    
    if (empty($canaisFiltrados)) {
        return 'Ao vivo';
    }
    
    return implode(' / ', $canaisFiltrados);
}

function gerarBanner($im, $jogos, $grupoJogos, $padding, $heightPorJogo, $width, $preto, $branco, $fontLiga) {
    $fundoJogoPath = __DIR__ . '/fzstore/card/card_banner_2.png';
    $fundoJogo = file_exists($fundoJogoPath) ? imagecreatefrompng($fundoJogoPath) : null;
    $yAtual = $padding + 150;
    $offsetEsquerda = 50;
    $posX = 15;
    
    foreach ($grupoJogos as $idx) {
        if (!isset($jogos[$idx])) continue;
        
        if ($fundoJogo) {
            $alturaCard = $heightPorJogo - 8;
            $larguraCard = $width - $padding * 2;
            $cardResized = imagecreatetruecolor($larguraCard, $alturaCard);
            imagealphablending($cardResized, false); imagesavealpha($cardResized, true);
            imagecopyresampled($cardResized, $fundoJogo, 0, 0, 0, 0, $larguraCard, $alturaCard, imagesx($fundoJogo), imagesy($fundoJogo));
            imagecopy($im, $cardResized, $posX, $yAtual, 0, 0, $larguraCard, $alturaCard);
            imagedestroy($cardResized);
        }
        
        $jogo = $jogos[$idx];
        $time1 = $jogo['time1'] ?? 'Time 1';
        $time2 = $jogo['time2'] ?? 'Time 2';
        $liga = limparNomeCompeticao($jogo['liga'] ?? 'Liga');
        $hora = $jogo['hora'] ?? '';
        $canais = formatarCanais($jogo['canais'] ?? []);
        
        $escudo1_url = LOGO_OVERRIDES[$time1] ?? $jogo['logo1'] ?? '';
        $escudo2_url = LOGO_OVERRIDES[$time2] ?? $jogo['logo2'] ?? '';
        
        $tamEscudo = 78;
        $imgEscudo1 = carregarEscudo($time1, $escudo1_url, $tamEscudo);
        $imgEscudo2 = carregarEscudo($time2, $escudo2_url, $tamEscudo);
        
        $vsOriginal = @imagecreatefrompng(__DIR__ . '/imgelementos/vs.png');
        if ($vsOriginal) {
            $xBase = $offsetEsquerda;
            $yTop = $yAtual + 20;
            $fontSizeLiga = 12;
            
            // Centralizar texto da liga
            $bboxLiga = imagettfbbox($fontSizeLiga, 0, $fontLiga, $liga);
            $textWidthLiga = $bboxLiga[2] - $bboxLiga[0];
            $centerX_Liga = ($width / 2) - ($textWidthLiga / 2);
            desenharTexto($im, $liga, $centerX_Liga, $yTop + 21, $branco, $fontSizeLiga, 0, $fontLiga);
            
            $yEscudos = $yTop - 35;
            imagecopy($im, $imgEscudo1, $xBase + 70, $yEscudos, 0, 0, imagesx($imgEscudo1), imagesy($imgEscudo1));
            imagecopy($im, $imgEscudo2, $xBase + 470, $yEscudos, 0, 0, imagesx($imgEscudo2), imagesy($imgEscudo2));
            
            desenharTexto($im, "$time1", $xBase + 70, $yTop + 50, $branco, 14);
            desenharTexto($im, "$time2", $xBase + 450, $yTop + 50, $branco, 14);
            desenharTexto($im, $hora, 345, $yTop + 0, $branco, 12);
            
            // Centralizar canais
            $fontSize = 12;
            $bbox = imagettfbbox($fontSize, 0, $fontLiga, $canais);
            $textWidth = $bbox[2] - $bbox[0];
            $centerX = ($width / 2) - ($textWidth / 2);
            desenharTexto($im, $canais, $centerX, $yTop + 90, $branco, $fontSize, 0, $fontLiga);
            
            imagedestroy($vsOriginal);
        }
        
        if ($imgEscudo1) imagedestroy($imgEscudo1);
        if ($imgEscudo2) imagedestroy($imgEscudo2);
        
        $yAtual += $heightPorJogo;
    }
    
    // Rodapé com logos das ligas
    $ligas_url = 'https://i.ibb.co/ycxpN2rc/Rodape-liga-720.png';
    $logo_liga_img = @imagecreatefrompng($ligas_url);
    if ($logo_liga_img) {
        $logo_largura = imagesx($logo_liga_img);
        $logo_altura = imagesy($logo_liga_img);
        $posicaoX = 40;
        $posicaoY = 870; 
        imagecopy($im, $logo_liga_img, $posicaoX, $posicaoY, 0, 0, $logo_largura, $logo_altura);
        imagedestroy($logo_liga_img);
    }
    
    if ($fundoJogo) imagedestroy($fundoJogo);
    
    // Cabeçalho com título and data
    $fonteTitulo = __DIR__ . '/fonts/BebasNeue-Regular.ttf';
    $fonteData = __DIR__ . '/fonts/RobotoCondensed-VariableFont_wght.ttf';
    $corBranco = imagecolorallocate($im, 255, 255, 255);
    $titulo1 = "DESTAQUES DE HOJE";
    $titulo2 = "";
    
    setlocale(LC_TIME, 'pt_BR.utf8', 'pt_BR.UTF-8', 'pt_BR', 'portuguese');
    $dataTexto = mb_strtoupper(strftime('%A - %d de %B'), 'UTF-8');
    
    // Centralizar títulos
    $xTitulo1 = centralizarTexto($width, 36, $fonteTitulo, $titulo1);
    $xTitulo2 = centralizarTexto($width, 36, $fonteTitulo, $titulo2);
    $xData = centralizarTexto($width, 17, $fonteData, $dataTexto);
    
    imagettftext($im, 36, 0, $xTitulo1, 65, $corBranco, $fonteTitulo, $titulo1);
    imagettftext($im, 36, 0, $xTitulo2, 110, $corBranco, $fonteTitulo, $titulo2);
    imagettftext($im, 17, 0, $xData, 90, $corBranco, $fonteData, $dataTexto);
    
    // Logo personalizada
    $logoContent = getImageFromJson('api/fzstore/logo_banner_2.json');
    if ($logoContent !== false) {
        $logo = @imagecreatefromstring($logoContent);
        if ($logo !== false) {
            $logoLarguraDesejada = 150;
            $logoPosX = 6; $logoPosY = 10;
            $logoWidthOriginal = imagesx($logo);
            $logoHeightOriginal = imagesy($logo);
            $logoHeight = (int)($logoHeightOriginal * ($logoLarguraDesejada / $logoWidthOriginal));
            $logoRedimensionada = imagecreatetruecolor($logoLarguraDesejada, $logoHeight);
            imagealphablending($logoRedimensionada, false); imagesavealpha($logoRedimensionada, true);
            imagecopyresampled($logoRedimensionada, $logo, 0, 0, 0, 0, $logoLarguraDesejada, $logoHeight, $logoWidthOriginal, $logoHeightOriginal);
            imagecopy($im, $logoRedimensionada, $logoPosX, $logoPosY, 0, 0, $logoLarguraDesejada, $logoHeight);
            imagedestroy($logo); 
            imagedestroy($logoRedimensionada);
        }
    }
}

function centralizarTexto($larguraImagem, $tamanhoFonte, $fonte, $texto) {
    if (!file_exists($fonte)) return 0;
    $caixa = imagettfbbox($tamanhoFonte, 0, $fonte, $texto);
    $larguraTexto = $caixa[2] - $caixa[0];
    return ($larguraImagem - $larguraTexto) / 2;
}

// Obter dados da APIHL
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
                // Converter string de canais para array se necessário
                $canais = processarCanais($jogo['canais'] ?? []);
                
                // Padronizar estrutura para compatibilidade
                $jogos[] = [
                    'time1' => $jogo['time1'],
                    'time2' => $jogo['time2'],
                    'liga' => $jogo['liga'],
                    'hora' => $jogo['hora'],
                    'canais' => $canais, // Agora sempre será array
                    'logo_canais' => $jogo['logo_canais'] ?? [], // Opcional para logos de canais
                    'logo1' => $jogo['logo1'],
                    'logo2' => $jogo['logo2'],
                    'placar1' => $jogo['placar1'] ?? '',
                    'placar2' => $jogo['placar2'] ?? '',
                    'status' => $jogo['status'] ?? ''
                ];
            }
        }
    }
}

if (empty($jogos)) {
    header('Content-Type: image/png'); 
    $im = imagecreatetruecolor(600, 100);
    $bg = imagecolorallocate($im, 255, 255, 255); 
    imagefill($im, 0, 0, $bg);
    $color = imagecolorallocate($im, 0, 0, 0);
    imagestring($im, 5, 10, 40, "Nenhum jogo disponível.", $color);
    imagepng($im); 
    imagedestroy($im); 
    exit;
}

$jogosPorBanner = 5;
$gruposDeJogos = array_chunk(array_keys($jogos), $jogosPorBanner);
$width = 720;
$heightPorJogo = 140;
$padding = 15;
$espacoExtra = 200;
$fontLiga = __DIR__ . '/fonts/MANDATOR.ttf';

if (isset($_GET['download_all']) && $_GET['download_all'] == 1) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);

    $zip = new ZipArchive();
    $zipNome = "banners_Top_V3_" . date('Y-m-d') . ".zip";
    $caminhoTempZip = sys_get_temp_dir() . '/' . uniqid('banners_V3_') . '.zip';
    $tempFiles = [];

    if ($zip->open($caminhoTempZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        foreach ($gruposDeJogos as $index => $grupoJogos) {
            $numJogosNesteBanner = count($grupoJogos);
            $height = $numJogosNesteBanner * $heightPorJogo + $padding * 2 + $espacoExtra;
            if ($height <= 1015) $height = 1015;

            $im = imagecreatetruecolor($width, $height);
            $preto = imagecolorallocate($im, 0, 0, 0);
            $branco = imagecolorallocate($im, 255, 255, 255);
            
            $fundoContent = getImageFromJson('api/fzstore/background_banner_2.json');
            if ($fundoContent && ($fundo = @imagecreatefromstring($fundoContent))) {
                imagecopyresampled($im, $fundo, 0, 0, 0, 0, $width, $height, imagesx($fundo), imagesy($fundo));
                imagedestroy($fundo);
            } else {
                imagefill($im, 0, 0, $branco);
            }

            gerarBanner($im, $jogos, $grupoJogos, $padding, $heightPorJogo, $width, $preto, $branco, $fontLiga);
            
            $nomeArquivoTemp = sys_get_temp_dir() . '/banner_V3_parte_' . uniqid() . '.png';
            imagepng($im, $nomeArquivoTemp);
            
            $zip->addFile($nomeArquivoTemp, 'banner_V3_parte_' . ($index + 1) . '.png');
            $tempFiles[] = $nomeArquivoTemp;
            imagedestroy($im);
        }
        $zip->close();

        if (ob_get_level()) ob_end_clean();
        
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipNome . '"');
        header('Content-Length: ' . filesize($caminhoTempZip));
        header('Pragma: no-cache');
        header('Expires: 0');

        if(readfile($caminhoTempZip)) {
            foreach ($tempFiles as $file) {
                if (file_exists($file)) unlink($file);
            }
            unlink($caminhoTempZip);
        }
        
        exit;
    } else {
        die("Erro: Não foi possível criar o arquivo ZIP.");
    }
}

$grupoIndex = isset($_GET['grupo']) ? (int)$_GET['grupo'] : 0;
if (!isset($gruposDeJogos[$grupoIndex])) {
    header('Content-Type: image/png'); 
    $im = imagecreatetruecolor(600, 100);
    $bg = imagecolorallocate($im, 255, 255, 255); 
    imagefill($im, 0, 0, $bg);
    $color = imagecolorallocate($im, 0, 0, 0);
    imagestring($im, 5, 10, 40, "Banner inválido.", $color);
    imagepng($im); 
    imagedestroy($im); 
    exit;
}

$grupoJogos = $gruposDeJogos[$grupoIndex];
$numJogosNesteBanner = count($grupoJogos);
$height = $numJogosNesteBanner * $heightPorJogo + $padding * 2 + $espacoExtra;
if ($height <= 1015) $height = 1015;

$im = imagecreatetruecolor($width, $height);
$preto = imagecolorallocate($im, 0, 0, 0);
$branco = imagecolorallocate($im, 255, 255, 255);

$fundoContent = getImageFromJson('api/fzstore/background_banner_2.json');
if ($fundoContent !== false) {
    $fundo = @imagecreatefromstring($fundoContent);
    if ($fundo !== false) {
        imagecopyresampled($im, $fundo, 0, 0, 0, 0, $width, $height, imagesx($fundo), imagesy($fundo));
        imagedestroy($fundo);
    } else { 
        imagefill($im, 0, 0, $branco); 
    }
} else { 
    imagefill($im, 0, 0, $branco); 
}

gerarBanner($im, $jogos, $grupoJogos, $padding, $heightPorJogo, $width, $preto, $branco, $fontLiga);

if (isset($_GET['download']) && $_GET['download'] == 1) {
    $nomeArquivo = "banner_V3_" . date('Y-m-d') . "_parte" . ($grupoIndex + 1) . ".png";
    header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
}

header('Content-Type: image/png');
imagepng($im);
imagedestroy($im);
exit;
?>