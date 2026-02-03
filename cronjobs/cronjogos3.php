<?php
// sand_jogosAuto.php

// Inclui as configurações do bot
require 'configbot.php';

// ==================================================
// VERIFICAÇÃO DE ACESSO FLEXÍVEL (SESSÃO OU SENHA CRON)
session_start();

// Se NÃO estiver logado no painel E NÃO tiver a senha correta → BLOQUEIA
if (!isset($_SESSION["usuario"]) && (!isset($_GET['senha']) || $_GET['senha'] !== $senha_cron)) {
    if (isset($_SESSION["usuario"])) {
        // Se estiver no contexto do painel, redireciona para login
        header("Location: login.php");
    } else {
        // Se for acesso externo, mostra mensagem
        die("Acesso negado: Requer autenticação no painel OU senha cron válida.");
    }
    exit();
}
// ==================================================

// Pasta para salvar as imagens
$image_dir = 'jogosimgdodia';

// Criar a pasta se não existir
if (!file_exists($image_dir)) {
    mkdir($image_dir, 0777, true);
}

// ==================================================
// DETECÇÃO AUTOMÁTICA DO DOMÍNIO E CAMINHO BASE (CORRIGIDO)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
$dominio = $_SERVER['HTTP_HOST'];

// Calcula o caminho base corretamente (remove o nível cronjobs)
$caminho_base = dirname(dirname($_SERVER['SCRIPT_NAME'])); 

// Monta a URL base corretamente sem duplicar /admin
$url_base = $protocol . $dominio . $caminho_base . '/gerar_fut_3.php?grupo=';

// DEBUG: Mostra as URLs que serão usadas (pode remover depois de testar)
echo "URLs que serão acessadas:\n";
for ($i = 0; $i <= 5; $i++) {
    echo $url_base . $i . "\n";
}
// ==================================================

// URLs das imagens (agora montadas dinamicamente e corretamente)
$image_urls = [
    $url_base . '0',
    $url_base . '1',
    $url_base . '2',
    $url_base . '3',
    $url_base . '4',
    $url_base . '5',
    $url_base . '6'
];

// Função para verificar se a imagem é um banner de erro
function isErrorImage($image_path) {
    // Verifica se o arquivo existe
    if (!file_exists($image_path)) return true;
    
    // Pega as dimensões da imagem
    $image_info = getimagesize($image_path);
    if (!$image_info) return true;
    
    $width = $image_info[0];
    $height = $image_info[1];
    
    // Verifica se tem o tamanho do banner de erro (600x100)
    return ($width == 600 && $height == 100);
}

// Função para enviar imagem para o Telegram
function sendImageToTelegram($image_path, $token, $chat_id) {
    $url = "https://api.telegram.org/bot{$token}/sendPhoto";
    
    $post_fields = [
        'chat_id' => $chat_id,
        'photo' => new CURLFile(realpath($image_path))
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    
    return $response;
}

// Baixar e enviar imagens (com tratamento de erros melhorado)
$images_to_send = [];
foreach ($image_urls as $index => $image_url) {
    $image_name = "$image_dir/image_$index.jpg";
    
    // Baixar a imagem com tratamento de erros
    try {
        $image_data = @file_get_contents($image_url);
        
        if ($image_data === false) {
            throw new Exception("Erro ao baixar a imagem");
        }
        
        file_put_contents($image_name, $image_data);
        
        // Verificar se a imagem é válida e não é banner de erro
        if (file_exists($image_name) && filesize($image_name) > 0 && !isErrorImage($image_name)) {
            $images_to_send[] = $image_name;
            echo "Imagem $index válida e pronta para envio. URL: $image_url\n";
        } else {
            unlink($image_name); // Remove imagem inválida
            echo "Imagem $index ignorada (banner de erro ou inválida). URL: $image_url\n";
        }
    } catch (Exception $e) {
        echo "Erro ao processar imagem $index (URL: $image_url): " . $e->getMessage() . "\n";
    }
}

// Enviar imagens válidas para o Telegram
foreach ($images_to_send as $image_path) {
    $result = sendImageToTelegram($image_path, $token, $chat_id);
    $result_array = json_decode($result, true);
    
    if ($result_array['ok'] ?? false) {
        echo "Imagem enviada com sucesso: " . basename($image_path) . "\n";
    } else {
        echo "Erro ao enviar imagem: " . basename($image_path) . "\n";
        echo "Resposta do Telegram: " . $result . "\n";
    }
    
    sleep(2); // Delay entre envios
}

// Limpar a pasta após envio
array_map('unlink', glob("$image_dir/*"));
echo "Pasta limpa após envio.\n";

echo "Processo concluído!\n";
?>