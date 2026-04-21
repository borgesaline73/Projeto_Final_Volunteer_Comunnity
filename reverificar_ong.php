<?php
session_start();
require "banco.php";

header('Content-Type: application/json');

if (!isset($_SESSION["usuario_id"])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Não autorizado.']);
    exit;
}

// Rate limiting básico
if (isset($_SESSION['ultima_consulta']) && (time() - $_SESSION['ultima_consulta']) < 5) {
    echo json_encode([
        'status' => 'pendente',
        'mensagem' => 'Aguarde 5 segundos entre as consultas para evitar bloqueio da API.'
    ]);
    exit;
}
$_SESSION['ultima_consulta'] = time();

$id_ong = (int)($_GET['id'] ?? 0);
$cnpj = trim($_GET['cnpj'] ?? '');

if ($id_ong <= 0 || empty($cnpj)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Parâmetros inválidos.']);
    exit;
}

$cnpj_limpo = preg_replace('/\D/', '', $cnpj);

if (strlen($cnpj_limpo) != 14) {
    $pdo->prepare("UPDATE usuarios SET verificada = false, verificacao_status = 'rejeitada' WHERE id_usuario = ?")
        ->execute([$id_ong]);
    echo json_encode(['status' => 'rejeitada', 'mensagem' => 'CNPJ inválido']);
    exit;
}

// ========== FUNÇÃO DE CONSULTA COM FALLBACK ==========
function consultarCNPJ($cnpj) {
    // Tentativa 1: BrasilAPI
    $ch = curl_init("https://brasilapi.com.br/api/cnpj/v1/{$cnpj}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Se BrasilAPI falhou por rate limit (429), tenta ReceitaWS
    if ($http_code == 429) {
        sleep(2); // Aguarda 2 segundos
        
        $ch2 = curl_init("https://receitaws.com.br/v1/cnpj/{$cnpj}");
        curl_setopt_array($ch2, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch2);
        $http_code = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        curl_close($ch2);
    }
    
    return ['http_code' => $http_code, 'data' => json_decode($response, true)];
}

$resultado = consultarCNPJ($cnpj_limpo);
$http_code = $resultado['http_code'];
$data = $resultado['data'];

// Erro 429 (muitas requisições)
if ($http_code == 429) {
    echo json_encode([
        'status' => 'pendente',
        'mensagem' => '⚠️ Muitas consultas no momento. A API bloqueou temporariamente. Aguarde 1 minuto e tente novamente.'
    ]);
    exit;
}

// Outros erros
if ($http_code != 200 || empty($data['razao_social'])) {
    $pdo->prepare("UPDATE usuarios SET verificada = false, verificacao_status = 'pendente' WHERE id_usuario = ?")
        ->execute([$id_ong]);
    echo json_encode([
        'status' => 'pendente',
        'mensagem' => 'Não foi possível verificar o CNPJ no momento. Tente novamente em alguns instantes.'
    ]);
    exit;
}

// Verifica situação cadastral
$situacao = $data['descricao_situacao_cadastral'] ?? $data['situacao'] ?? $data['situacao_cadastral'] ?? '';

if (strtoupper(trim($situacao)) == 'ATIVA') {
    $pdo->prepare("UPDATE usuarios SET verificada = true, verificacao_status = 'aprovada' WHERE id_usuario = ?")
        ->execute([$id_ong]);
    echo json_encode([
        'status' => 'aprovada',
        'mensagem' => '✅ CNPJ verificado! ONG aprovada.',
        'razao_social' => $data['razao_social']
    ]);
} else {
    $pdo->prepare("UPDATE usuarios SET verificada = false, verificacao_status = 'rejeitada' WHERE id_usuario = ?")
        ->execute([$id_ong]);
    echo json_encode([
        'status' => 'rejeitada',
        'mensagem' => '❌ CNPJ com situação: ' . ($situacao ?: 'não informada')
    ]);
}
?>