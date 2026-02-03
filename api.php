<?php
// arquivo scraper.php

function fazerScraping() {
    $url = 'https://api.hlserver.com.br/scraper.php';
    
    // Inicializa cURL
    $ch = curl_init();
    
    // Configura as opções do cURL
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Apenas para desenvolvimento, remova em produção
    
    // Executa a requisição
    $response = curl_exec($ch);
    
    // Verifica por erros
    if(curl_errno($ch)) {
        return ['erro' => 'Erro ao acessar a API: ' . curl_error($ch)];
    }
    
    // Fecha a sessão cURL
    curl_close($ch);
    
    // Decodifica a resposta JSON
    $dados = json_decode($response, true);
    
    if(json_last_error() !== JSON_ERROR_NONE) {
        return ['erro' => 'Resposta inválida da API'];
    }
    
    return $dados;
}