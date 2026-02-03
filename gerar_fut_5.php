<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');

// Definir constantes e configurações como no código 1
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

// Funções auxiliares do código 1
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

// Função para processar canais (nova)
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

// Função para formatar os canais de transmissão (modificada)
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
    
    // Limitar a 3 canais e juntar com vírgula
    return implode(', ', array_slice($canaisFiltrados, 0, 3));
}

// Função para limpar termos da competição
function limparTermosCompeticao($texto) {
    // Termos a serem removidos (case insensitive)
    $padroes = [
        // Fases de competição
        '/\b(?:oitavas|quartas|semi|final|terceiro lugar|fases?|pré[- ]?eliminatória)\b(?: de final)?/iu',
        '/\bfase (?:de |)grupos?\b/iu',
        '/\b(?:apertura|clausura|clausura)\b/iu',
        // Rodadas e números
        '/\brodada\s*\d+\b/iu',
        '/\b\d+ª?\s*(?:rodada|fase|etapa)\b/iu',
        '/\b(?:jogo|partida)\s*(?:de|\d+)\b/iu',
        // Grupos e classificações
        '/\bgrupo\s*[a-z]\b/iu',
        '/\b\d+\s*(?:ª|º|°)\b/iu',
        // Temporadas
        '/\b(?:20|19)\d{2}(?:\/?(?:20|19)\d{2})?\b/iu',
        // Outros termos
        '/\b(?:turno|returno)\b/iu',
        // Termo "DE" isolado
        '/\bde\b/iu',
        // Caracteres especiais
        '/[|\-:;]+$/u'
    ];
    
    // Remover os padrões
    $textoLimpo = preg_replace($padroes, '', $texto);
    
    // Remover parênteses vazios e espaços extras
    $textoLimpo = str_replace(['()', '[]'], '', $textoLimpo);
    $textoLimpo = trim(preg_replace('/\s+/', ' ', $textoLimpo));
    
    // Remover "DE" no final (que possa ter sobrado)
    $textoLimpo = preg_replace('/\s+de$/i', '', $textoLimpo);
    
    return $textoLimpo;
}

// Função principal para gerar o banner
function gerarBanner($im, $jogos, $grupoJogos, $padding, $heightPorJogo, $width, $preto, $branco, $fontLiga) {
    $fundoJogoPath = __DIR__ . '/fzstore/card/card_banner_5.png';
    $fundoJogo = file_exists($fundoJogoPath) ? imagecreatefrompng($fundoJogoPath) : null;
    
    // CONFIGURAÇÕES AJUSTÁVEIS
    $config = [
        'espacamento_vertical' => 10,
        'altura_jogo' => 150,
        'espaco_cabecalho' => 200,
        'posicoes' => [
            'liga' => ['x' => 130, 'y' => 17],
            'escudo1' => ['x' => 130, 'y' => 55],
            'escudo2' => ['x' => 625, 'y' => 55],
            'nome_time1' => ['x' => 188, 'y' => 40],
            'nome_time2' => ['x' => 608, 'y' => 40],
            'vs' => ['x' => 370, 'y' => 45],
            'data' => ['x' => 530, 'y' => 348],
            'horario' => ['x' => 365, 'y' => 8],
            'canais' => ['x' => 290, 'y' => 110],
            'logo' => ['x' => 20, 'y' => 1, 'largura' => 180],
            'titulo1' => ['x' => 500, 'y' => 70],
            'titulo2' => ['x' => 500, 'y' => 110],
            'data_cabecalho' => ['x' => 300, 'y' => 180]
        ]
    ];

    // FONTE ESPECIAL PARA OS TIMES
    $fonteTimes = __DIR__ . '/fonts/AvilockBold.ttf';
    $tamanhoFonteTimes = 16;

    $heightPorJogo = $config['altura_jogo'];
    $espacamentoVertical = $config['espacamento_vertical'];
    $espacoCabecalho = $config['espaco_cabecalho'];
    $posicoes = $config['posicoes'];

    // CABEÇALHO
    $fonteTitulo = __DIR__ . '/fonts/AvilockBold.ttf';
    $fonteData = __DIR__ . '/fonts/AvilockBold.ttf';
    $corBranco = imagecolorallocate($im, 255, 255, 255);

    // Logo
    $logoContent = getImageFromJson('api/fzstore/logo_banner_5.json');
    if ($logoContent !== false) {
        $logo = @imagecreatefromstring($logoContent);
        if ($logo !== false) {
            $logoLarguraDesejada = $posicoes['logo']['largura'];
            $logoPosX = $posicoes['logo']['x'];
            $logoPosY = $posicoes['logo']['y'];
            
            $logoWidthOriginal = imagesx($logo);
            $logoHeightOriginal = imagesy($logo);
            $logoHeight = (int)($logoHeightOriginal * ($logoLarguraDesejada / $logoWidthOriginal));
            
            $logoRedimensionada = imagecreatetruecolor($logoLarguraDesejada, $logoHeight);
            imagealphablending($logoRedimensionada, false);
            imagesavealpha($logoRedimensionada, true);
            imagecopyresampled($logoRedimensionada, $logo, 0, 0, 0, 0, 
                             $logoLarguraDesejada, $logoHeight, 
                             $logoWidthOriginal, $logoHeightOriginal);
            
            imagecopy($im, $logoRedimensionada, $logoPosX, $logoPosY, 
                     0, 0, $logoLarguraDesejada, $logoHeight);
            
            imagedestroy($logo);
            imagedestroy($logoRedimensionada);
        }
    }

    // Título
    imagettftext($im, 51, 0, $posicoes['titulo1']['x'], $posicoes['titulo1']['y'], 
                $corBranco, $fonteTitulo, "AGENDA ");
    imagettftext($im, 36, 0, $posicoes['titulo2']['x'], $posicoes['titulo2']['y'], 
                $corBranco, $fonteTitulo, "ESPORTIVA");
    
    // Data
    setlocale(LC_TIME, 'pt_BR.utf8', 'portuguese');
    $dataHoje = date('Y-m-d');
    $timestamp = strtotime($dataHoje);
    $diaSemana = strftime('%A', $timestamp);
    $linhaData = strtoupper($diaSemana) . ' - ' . strtoupper(strftime('%d/%B', $timestamp));
    imagettftext($im, 47, 0, $posicoes['data_cabecalho']['x'], $posicoes['data_cabecalho']['y'], 
                $corBranco, $fonteData, $linhaData);

    // JOGOS
    $yAtual = $espacoCabecalho;
    
    foreach ($grupoJogos as $idx) {
        if (!isset($jogos[$idx])) continue;

        if ($fundoJogo) {
            $alturaCard = $heightPorJogo - 10;
            $larguraCard = $width - $padding * 2;
            $cardResized = imagecreatetruecolor($larguraCard, $alturaCard);
            imagealphablending($cardResized, false);
            imagesavealpha($cardResized, true);
            imagecopyresampled($cardResized, $fundoJogo, 0, 0, 0, 0, 
                              $larguraCard, $alturaCard, 
                              imagesx($fundoJogo), imagesy($fundoJogo));
            imagecopy($im, $cardResized, $padding, $yAtual, 
                     0, 0, $larguraCard, $alturaCard);
            imagedestroy($cardResized);
        }

        $jogo = $jogos[$idx];
        $time1 = $jogo['time1'] ?? 'Time 1';
        $time2 = $jogo['time2'] ?? 'Time 2';
        $liga = limparTermosCompeticao($jogo['liga'] ?? '');
        $hora = $jogo['hora'] ?? '';
        $canaisTexto = formatarCanais($jogo['canais'] ?? '');
        $escudo1_url = isset(LOGO_OVERRIDES[$time1]) ? LOGO_OVERRIDES[$time1] : ($jogo['logo1'] ?? '');
        $escudo2_url = isset(LOGO_OVERRIDES[$time2]) ? LOGO_OVERRIDES[$time2] : ($jogo['logo2'] ?? '');

        // Remover termos como sub-20, sub17, u17
        $time1 = preg_replace('/\b(sub[\s-]?20|sub[\s-]?17|u17)\b/i', '', $time1);
        $time2 = preg_replace('/\b(sub[\s-]?20|sub[\s-]?17|u17)\b/i', '', $time2);
        $time1 = trim(preg_replace('/\s+/', ' ', $time1));
        $time2 = trim(preg_replace('/\s+/', ' ', $time2));

        $tamEscudo = 45;
        $tamVS = 50;

        // Carregar imagens
        $imgEscudo1 = carregarEscudo($time1, $escudo1_url, $tamEscudo);
        $imgEscudo2 = carregarEscudo($time2, $escudo2_url, $tamEscudo);
        
        $vsOriginal = imagecreatefrompng(__DIR__ . '/imgelementos/vs.png');
        $vsImg = imagecreatetruecolor($tamVS, $tamVS);
        imagealphablending($vsImg, false);
        imagesavealpha($vsImg, true);
        imagecopyresampled($vsImg, $vsOriginal, 0, 0, 0, 0, $tamVS, $tamVS, imagesx($vsOriginal), imagesy($vsOriginal));
        imagedestroy($vsOriginal);

        $yTop = $yAtual + ($espacamentoVertical / 2);

        // Elementos do jogo
        desenharTexto($im, $liga, $posicoes['liga']['x'], $yTop + $posicoes['liga']['y'], 
                     $branco, 17, 0, $fontLiga);

        // Escudos
        imagecopy($im, $imgEscudo1, $posicoes['escudo1']['x'], $yTop + $posicoes['escudo1']['y'], 
                 0, 0, $tamEscudo, $tamEscudo);
        imagecopy($im, $vsImg, $posicoes['vs']['x'], $yTop + $posicoes['vs']['y'], 
                 0, 0, $tamVS, $tamVS);
        imagecopy($im, $imgEscudo2, $posicoes['escudo2']['x'], $yTop + $posicoes['escudo2']['y'], 
                 0, 0, $tamEscudo, $tamEscudo);

        // Nomes dos times centralizados
        $nome_time1_y = $yTop + $posicoes['nome_time1']['y'] + ($tamEscudo / 2) + 8;
        $nome_time2_y = $yTop + $posicoes['nome_time2']['y'] + ($tamEscudo / 2) + 8;
        desenharTexto($im, $time1, $posicoes['nome_time1']['x'], $nome_time1_y, 
                     $branco, $tamanhoFonteTimes, 0, $forteTimes);
        
        $bbox2 = imagettfbbox($tamanhoFonteTimes, 0, $fonteTimes, $time2);
        $larguraTexto2 = $bbox2[2] - $bbox2[0];
        $posX_nome_time2 = $posicoes['nome_time2']['x'] - $larguraTexto2;

        desenharTexto($im, $time2, $posX_nome_time2, $nome_time2_y, 
                     $branco, $tamanhoFonteTimes, 0, $fonteTimes);

        // Outros elementos
        desenharTexto($im, date('d/m'), $posicoes['data']['x'], $yTop + $posicoes['data']['y'], 
                     $branco, 12, 0, $fontLiga);
        desenharTexto($im, $hora, $posicoes['horario']['x'], $yTop + $posicoes['horario']['y'], 
                     $branco, 22, 0, $fontLiga);
        desenharTexto($im, $canaisTexto, $posicoes['canais']['x'], $yTop + $posicoes['canais']['y'], 
                     $branco, 16, 0, $fontLiga);

        imagedestroy($imgEscudo1);
        imagedestroy($imgEscudo2);
        imagedestroy($vsImg);

        $yAtual += $heightPorJogo + $espacamentoVertical;
    }

    if ($fundoJogo) imagedestroy($fundoJogo);
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
$espacoExtra = 400;
$fontLiga = __DIR__ . '/fonts/BebasNeue-Regular.ttf';
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
            $height = $jogosPorBanner * $heightPorJogo + $padding * 2 + $espacoExtra;
            $im = imagecreatetruecolor($width, $height);
            $preto = imagecolorallocate($im, 0, 0, 0);
            $branco = imagecolorallocate($im, 255, 255, 255);
            
            $fundoContent = getImageFromJson('api/fzstore/background_banner_5.json');
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
$height = $jogosPorBanner * $heightPorJogo + $padding * 2 + $espacoExtra;
$im = imagecreatetruecolor($width, $height);
$preto = imagecolorallocate($im, 0, 0, 0);
$branco = imagecolorallocate($im, 255, 255, 255);

$fundoContent = getImageFromJson('api/fzstore/background_banner_5.json');
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