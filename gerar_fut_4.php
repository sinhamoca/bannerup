<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');

define('CLOUDINARY_CLOUD_NAME', 'dwrikepvg');
define('LOGO_OVERRIDES', [
    //TIMES
    'St. Louis City' => 'https://a.espncdn.com/combiner/i?img=/i/teamlogos/soccer/500/21812.png',
    'Guarulhos' => 'https://upload.wikimedia.org/wikipedia/pt/d/d5/GuarulhosGRU.png',
    'Estados Unidos' => 'https://a.espncdn.com/combiner/i?img=/i/teamlogos/countries/500/usa.png',
    'Tupa' => 'https://static.flashscore.com/res/image/data/8SqNKfdM-27lsDqoa.png',
    'Guadeloupe' => 'https://static.flashscore.com/res/image/data/z7uwX5e5-Qw31eZbP.png',
    'Tanabi' => 'https://ssl.gstatic.com/onebox/media/sports/logos/_0PCb1YBKcxp8eXBCCtZpg_96x96.png',
    
    //LIGAS
    'Mundial de Clubes FIFA' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ad/2025_FIFA_Club_World_Cup.svg/1200px-2025_FIFA_Club_World_Cup.svg.png',
]);

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
    
    return implode(' • ', $canaisFiltrados);
}

function desenhar_retangulo_arredondado($image, $x, $y, $width, $height, $radius, $color) {
    $x1 = $x;
    $y1 = $y;
    $x2 = $x + $width;
    $y2 = $y + $height;
    if ($radius > $width / 2) $radius = $width / 2;
    if ($radius > $height / 2) $radius = $height / 2;

    imagefilledrectangle($image, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
    imagefilledrectangle($image, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);

    imagefilledarc($image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, 180, 270, $color, IMG_ARC_PIE);
    imagefilledarc($image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, 270, 360, $color, IMG_ARC_PIE);
    imagefilledarc($image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, 90, 180, $color, IMG_ARC_PIE);
    imagefilledarc($image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, 0, 90, $color, IMG_ARC_PIE);
}

function carregarImagemDeUrl(string $url, int $maxSize) {
    $urlParaCarregar = $url;
    $extensao = strtolower(pathinfo($url, PATHINFO_EXTENSION));

    if ($extensao === 'svg') {
        $cloudinaryCloudName = CLOUDINARY_CLOUD_NAME;
        if (empty($cloudinaryCloudName) || $cloudinaryCloudName === 'SEU_CLOUD_NAME_AQUI') {
            return false;
        }
        $urlParaCarregar = "https://res.cloudinary.com/{$cloudinaryCloudName}/image/fetch/f_png/" . urlencode($url);
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [ 
        CURLOPT_URL => $urlParaCarregar, 
        CURLOPT_RETURNTRANSFER => 1, 
        CURLOPT_SSL_VERIFYPEER => false, 
        CURLOPT_FOLLOWLOCATION => true, 
        CURLOPT_CONNECTTIMEOUT => 5, 
        CURLOPT_TIMEOUT => 15 
    ]);
    $imageContent = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($imageContent === false || $httpCode >= 400) return false;
    $img = @imagecreatefromstring($imageContent);
    if (!$img) return false;

    $w = imagesx($img); 
    $h = imagesy($img);
    if ($w == 0 || $h == 0) { imagedestroy($img); return false; }
    
    $scale = min($maxSize / $w, $maxSize / $h, 1.0);
    $newW = (int)($w * $scale); 
    $newH = (int)($h * $scale);
    $imgResized = imagecreatetruecolor($newW, $newH);
    imagealphablending($imgResized, false); 
    imagesavealpha($imgResized, true);
    imagecopyresampled($imgResized, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);
    imagedestroy($img);
    return $imgResized;
}

function carregarLogoCanalComAlturaFixa(string $url, int $alturaFixa = 50) {
    if (empty($url)) return false;
    
    $ch = curl_init();
    curl_setopt_array($ch, [ 
        CURLOPT_URL => $url, 
        CURLOPT_RETURNTRANSFER => 1, 
        CURLOPT_SSL_VERIFYPEER => false, 
        CURLOPT_FOLLOWLOCATION => true, 
        CURLOPT_CONNECTTIMEOUT => 5, 
        CURLOPT_TIMEOUT => 15 
    ]);
    $imageContent = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($imageContent === false || $httpCode >= 400) return false;
    $img = @imagecreatefromstring($imageContent);
    if (!$img) return false;
    
    $origW = imagesx($img); 
    $origH = imagesy($img);
    if ($origH == 0) { imagedestroy($img); return false; }
    
    $ratio = $origW / $origH;
    $newW = (int)($alturaFixa * $ratio);
    $newH = $alturaFixa;
    $imgResized = imagecreatetruecolor($newW, $newH);
    imagealphablending($imgResized, false); 
    imagesavealpha($imgResized, true);
    $transparent = imagecolorallocatealpha($imgResized, 0, 0, 0, 127);
    imagefill($imgResized, 0, 0, $transparent);
    imagecopyresampled($imgResized, $img, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
    imagedestroy($img);
    return $imgResized;
}

function criarPlaceholderComNome(string $nomeTime, int $size = 68) {
    $img = imagecreatetruecolor($size, $size);
    imagealphablending($img, false); 
    imagesavealpha($img, true);
    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $transparent);
    $textColor = imagecolorallocate($img, 80, 80, 80);
    $fontePath = __DIR__ . '/fonts/RobotoCondensed-Bold.ttf';
    
    if (!file_exists($fontePath)) { 
        imagestring($img, 2, 2, $size/2 - 5, "No Logo", $textColor); 
        return $img; 
    }
    
    $nomeLimpo = trim(preg_replace('/\s*\([^)]*\)/', '', $nomeTime));
    $palavras = explode(' ', $nomeLimpo);
    $linhas = []; 
    $linhaAtual = '';
    
    foreach ($palavras as $palavra) {
        $testeLinha = $linhaAtual . ($linhaAtual ? ' ' : '') . $palavra;
        $bbox = imagettfbbox(10.5, 0, $fontePath, $testeLinha);
        if (($bbox[2] - $bbox[0]) > ($size - 8) && $linhaAtual !== '') { 
            $linhas[] = $linhaAtual; 
            $linhaAtual = $palavra; 
        } else { 
            $linhaAtual = $testeLinha; 
        }
    }
    $linhas[] = $linhaAtual;
    
    $bbox = imagettfbbox(10.5, 0, $fontePath, "A");
    $alturaLinha = abs($bbox[7] - $bbox[1]);
    $alturaTotalTexto = (count($linhas) * $alturaLinha) + ((count($linhas) - 1) * 2);
    $y = ($size - $alturaTotalTexto) / 2 + $alturaLinha;
    
    foreach ($linhas as $linha) {
        $bboxLinha = imagettfbbox(10.5, 0, $fontePath, $linha);
        $x = ($size - ($bboxLinha[2] - $bboxLinha[0])) / 2;
        imagettftext($img, 10.5, 0, (int)$x, (int)$y, $textColor, $fontePath, $linha);
        $y += $alturaLinha + 2;
    }
    return $img;
}

function carregarEscudo(string $nomeTime, ?string $url, int $maxSize = 60) {
    if (!empty($url)) {
        $imagem = carregarImagemDeUrl($url, $maxSize);
        if ($imagem) return $imagem;
    }
    return criarPlaceholderComNome($nomeTime, $maxSize);
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
    if ($jsonContent === false) return null;
    $data = json_decode($jsonContent, true);
    if (empty($data) || !isset($data[0]['ImageName'])) return null;
    $imagePath = str_replace('../', '', $data[0]['ImageName']);
    if (!file_exists($imagePath)) return null;
    return @file_get_contents($imagePath);
}

function centralizarTextoX($larguraImagem, $tamanhoFonte, $fonte, $texto) { 
    if (!file_exists($fonte)) return $larguraImagem / 2;
    $caixa = imagettfbbox($tamanhoFonte, 0, $fonte, $texto); 
    return ($larguraImagem - ($caixa[2] - $caixa[0])) / 2; 
}

function gerarBanner($im, $jogos, $grupoJogos, $padding, $heightPorJogo, $width, $preto, $branco, $fontLiga) {
    $fundoJogoPath = __DIR__ . '/fzstore/card/card_banner_4.png';
    $fundoJogo = file_exists($fundoJogoPath) ? imagecreatefrompng($fundoJogoPath) : null;
    $yAtual = $padding;
    $offsetEsquerda = 290;

    foreach ($grupoJogos as $idx) {
        if (!isset($jogos[$idx])) continue;

        if ($fundoJogo) {
            $alturaCard = $heightPorJogo - 10;
            $larguraCard = $width - $padding * 2;
            $cardResized = imagecreatetruecolor($larguraCard, $alturaCard);
            imagealphablending($cardResized, false);
            imagesavealpha($cardResized, true);
            imagecopyresampled($cardResized, $fundoJogo, 0, 0, 0, 0, $larguraCard, $alturaCard, imagesx($fundoJogo), imagesy($fundoJogo));
            imagecopy($im, $cardResized, $padding, $yAtual, 0, 0, $larguraCard, $alturaCard);
            imagedestroy($cardResized);
        }

        $jogo = $jogos[$idx];
        $time1 = $jogo['time1'] ?? 'Time 1';
        $time2 = $jogo['time2'] ?? 'Time 2';
        $liga = limparNomeCompeticao($jogo['liga'] ?? 'Liga');
        $hora = $jogo['hora'] ?? '';
        $canaisArray = $jogo['canais'] ?? [];
        $canaisTexto = formatarCanais($canaisArray);
        $escudo1_url = isset(LOGO_OVERRIDES[$time1]) ? LOGO_OVERRIDES[$time1] : ($jogo['logo1'] ?? '');
        $escudo2_url = isset(LOGO_OVERRIDES[$time2]) ? LOGO_OVERRIDES[$time2] : ($jogo['logo2'] ?? '');

        $tamEscudo = 68;
        $tamVS = 50;

        $imgEscudo1 = carregarEscudo($time1, $escudo1_url, $tamEscudo);
        $imgEscudo2 = carregarEscudo($time2, $escudo2_url, $tamEscudo);

        $vsOriginal = imagecreatefrompng(__DIR__ . '/imgelementos/vs.png');
        $vsImg = imagecreatetruecolor($tamVS, $tamVS);
        imagealphablending($vsImg, false);
        imagesavealpha($vsImg, true);
        imagecopyresampled($vsImg, $vsOriginal, 0, 0, 0, 0, $tamVS, $tamVS, imagesx($vsOriginal), imagesy($vsOriginal));
        imagedestroy($vsOriginal);

        $xBase = $offsetEsquerda;
        $yTop = $yAtual + 20;

        desenharTexto($im, $liga, $xBase, $yTop - 17, $branco, 12, 0, $fontLiga);
        $yEscudos = $yTop + 15;
        imagecopy($im, $imgEscudo1, $xBase, $yEscudos, 0, 0, imagesx($imgEscudo1), imagesy($imgEscudo1));
        imagecopy($im, $imgEscudo2, $xBase + $tamEscudo + $tamVS + 10, $yEscudos, 0, 0, imagesx($imgEscudo2), imagesy($imgEscudo2));
        $xVS = $xBase + $tamEscudo + 5;
        imagecopy($im, $vsImg, $xVS, $yEscudos + ($tamEscudo / 2) - ($tamVS / 2), 0, 0, $tamVS, $tamVS);
        desenharTexto($im, "$time1 X $time2", $xBase + 20, $yTop + 91, $branco, 14);
        desenharTexto($im, date('d/m'), 530, $yTop + 8, $preto, 12);
        desenharTexto($im, $hora, 220, $yTop + 40, $preto, 12);
        
        // Exibir canais formatados
        desenharTexto($im, $canaisTexto, 530, $yTop + 50, $branco, 12);

        // Opcional: exibir logos dos canais
        if (!empty($jogo['logo_canais']) && is_array($jogo['logo_canais'])) {
            $xLogos = 530;
            foreach ($jogo['logo_canais'] as $logoUrl) {
                if (!empty($logoUrl)) {
                    $logoCanal = carregarLogoCanalComAlturaFixa($logoUrl, 30);
                    if ($logoCanal) {
                        imagecopy($im, $logoCanal, $xLogos, $yTop + 3000, 0, 0, imagesx($logoCanal), imagesy($logoCanal));
                        $xLogos += imagesx($logoCanal) + 10;
                        imagedestroy($logoCanal);
                    }
                }
            }
        }

        imagedestroy($imgEscudo1);
        imagedestroy($imgEscudo2);
        imagedestroy($vsImg);

        $yAtual += $heightPorJogo;
    }

    if ($fundoJogo) imagedestroy($fundoJogo);

    // Cabeçalho
    $fonteTitulo = __DIR__ . '/fonts/BebasNeue-Regular.ttf';
    $fonteData = __DIR__ . '/fonts/RobotoCondensed-VariableFont_wght.ttf';
    $corBranco = imagecolorallocate($im, 255, 255, 255);
    imagettftext($im, 36, 0, 20, 70, $corBranco, $fonteTitulo, "JOGOS DE ");
    imagettftext($im, 36, 0, 20, 110, $corBranco, $fonteTitulo, "HOJE");
    setlocale(LC_TIME, 'pt_BR.utf8', 'pt_BR.UTF-8', 'pt_BR', 'portuguese');
    $dataTexto = mb_strtoupper(strftime('%A - %d/%B'));
    imagettftext($im, 17, 0, 20, 131, $corBranco, $fonteData, $dataTexto);

    // Logo - obtido do JSON
    $logoContent = getImageFromJson('api/fzstore/logo_banner_4.json');
    if ($logoContent && ($logo = @imagecreatefromstring($logoContent))) {
        $logoLarguraDesejada = 200;
        $logoPosX = 6;
        $logoPosY = 135;
        
        $logoWidthOriginal = imagesx($logo);
        $logoHeightOriginal = imagesy($logo);
        $logoHeight = (int)($logoHeightOriginal * ($logoLarguraDesejada / $logoWidthOriginal));
        $logoRedimensionada = imagecreatetruecolor($logoLarguraDesejada, $logoHeight);
        imagealphablending($logoRedimensionada, false);
        imagesavealpha($logoRedimensionada, true);
        imagecopyresampled($logoRedimensionada, $logo, 0, 0, 0, 0, $logoLarguraDesejada, $logoHeight, $logoWidthOriginal, $logoHeightOriginal);
        imagecopy($im, $logoRedimensionada, $logoPosX, $logoPosY, 0, 0, $logoLarguraDesejada, $logoHeight);
        imagedestroy($logo);
        imagedestroy($logoRedimensionada);
    }
}

// Obter dados da API
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
                    'logo_canais' => $jogo['logo_canais'] ?? [],
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
    imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
    imagestring($im, 5, 10, 40, "Nenhum jogo disponivel.", imagecolorallocate($im, 0, 0, 0));
    imagepng($im);
    imagedestroy($im);
    exit;
}

// Configurações do banner
$width = 800;
$heightPorJogo = 140;
$padding = 15;
$espacoExtra = 80;
$fontLiga = __DIR__ . '/fonts/MANDATOR.ttf';
$jogosPorBanner = 5;
$gruposDeJogos = array_chunk(array_keys($jogos), $jogosPorBanner);

// Download de todos os banners em ZIP
if (isset($_GET['download_all']) && $_GET['download_all'] == 1) {
    $zip = new ZipArchive();
    $zipNome = "banners_jogos_" . date('Y-m-d') . ".zip";
    $caminhoTempZip = sys_get_temp_dir() . '/' . uniqid('banners_') . '.zip';
    $tempFiles = [];

    if ($zip->open($caminhoTempZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        foreach ($gruposDeJogos as $index => $grupoJogos) {
            $numJogos = count($grupoJogos);
            $height = $numJogos * $heightPorJogo + $padding * 2 + $espacoExtra;
            $im = imagecreatetruecolor($width, $height);
            $preto = imagecolorallocate($im, 0, 0, 0);
            $branco = imagecolorallocate($im, 255, 255, 255);
            
            $fundoContent = getImageFromJson('api/fzstore/background_banner_4.json');
            if ($fundoContent && ($fundo = @imagecreatefromstring($fundoContent))) {
                imagecopyresampled($im, $fundo, 0, 0, 0, 0, $width, $height, imagesx($fundo), imagesy($fundo));
                imagedestroy($fundo);
            } else {
                imagefill($im, 0, 0, $branco);
            }
            
            gerarBanner($im, $jogos, $grupoJogos, $padding, $heightPorJogo, $width, $preto, $branco, $fontLiga);
            
            $nomeArquivoTemp = sys_get_temp_dir() . '/banner_jogos_' . uniqid() . '.png';
            imagepng($im, $nomeArquivoTemp);
            
            $zip->addFile($nomeArquivoTemp, 'banner_jogos_' . ($index + 1) . '.png');
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

// Geração de banner individual
$grupoIndex = isset($_GET['grupo']) ? (int)$_GET['grupo'] : 0;
if (!isset($gruposDeJogos[$grupoIndex])) {
    header('Content-Type: image/png');
    $im = imagecreatetruecolor(600, 100);
    imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
    imagestring($im, 5, 10, 40, "Banner invalido.", imagecolorallocate($im, 0, 0, 0));
    imagepng($im);
    imagedestroy($im);
    exit;
}

$grupoJogos = $gruposDeJogos[$grupoIndex];
$numJogos = count($grupoJogos);
$height = $numJogos * $heightPorJogo + $padding * 2 + $espacoExtra;
$im = imagecreatetruecolor($width, $height);
$preto = imagecolorallocate($im, 0, 0, 0);
$branco = imagecolorallocate($im, 255, 255, 255);

$fundoContent = getImageFromJson('api/fzstore/background_banner_4.json');
if ($fundoContent !== false && ($fundo = @imagecreatefromstring($fundoContent))) {
    imagecopyresampled($im, $fundo, 0, 0, 0, 0, $width, $height, imagesx($fundo), imagesy($fundo));
    imagedestroy($fundo);
} else {
    imagefill($im, 0, 0, $branco);
}

gerarBanner($im, $jogos, $grupoJogos, $padding, $heightPorJogo, $width, $preto, $branco, $fontLiga);

if (isset($_GET['download']) && $_GET['download'] == 1) {
    $nomeArquivo = "banner_jogos_" . date('Y-m-d') . "_parte" . ($grupoIndex + 1) . ".png";
    header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
}

header('Content-Type: image/png');
imagepng($im);
imagedestroy($im);
exit;