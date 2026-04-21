<?php
/**
 * reverificar_ong.php
 * 
 * Chamado via GET pelo painel admin quando uma ONG ficou como
 * "pendente" (falha de conexão no momento do cadastro).
 * Retorna JSON com o resultado da nova tentativa.
 */

session_start();
require "banco.php";

header('Content-Type: application/json');

// Só admin logado pode chamar este endpoint
if (!isset($_SESSION["usuario_id"])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Não autorizado.']);
    exit;
}

$id_ong = (int)($_GET['id']   ?? 0);
$cnpj   = trim($_GET['cnpj']  ?? '');

if ($id_ong <= 0 || empty($cnpj)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Parâmetros inválidos.']);
    exit;
}

// ── Confirma que a ONG existe e está pendente ─────────────────────────────────
$stmt = $pdo->prepare("SELECT id_usuario, verificacao_status FROM usuarios WHERE id_usuario = ? AND tipo_usuario = 'instituicao'");
$stmt->execute([$id_ong]);
$ong = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ong) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'ONG não encontrada.']);
    exit;
}

// ── Consulta à BrasilAPI ──────────────────────────────────────────────────────
$cnpj_limpo = preg_replace('/\D/', '', $cnpj);

$ch = curl_init("https://brasilapi.com.br/api/cnpj/v1/{$cnpj_limpo}");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    CURLOPT_SSL_VERIFYPEER => true,
]);
$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_erro = curl_error($ch);
curl_close($ch);

// ── Falha de rede → mantém pendente ──────────────────────────────────────────
if ($curl_erro || $response === false) {
    echo json_encode([
        'status'   => 'pendente',
        'mensagem' => 'Não foi possível conectar à Receita Federal. Tente novamente mais tarde.'
    ]);
    exit;
}

$data = json_decode($response, true);

// ── CNPJ encontrado e API respondeu OK → verifica situação cadastral ─────────
if ($http_code === 200 && !empty($data['razao_social'])) {
    $situacao = strtoupper(trim($data['descricao_situacao_cadastral'] ?? ''));

    if ($situacao === 'ATIVA') {
        // ✅ Ativo → aprova
        $pdo->prepare("UPDATE usuarios SET verificada = true, verificacao_status = 'aprovada' WHERE id_usuario = ?")
            ->execute([$id_ong]);
        echo json_encode([
            'status'   => 'aprovada',
            'mensagem' => 'CNPJ verificado! ONG aprovada automaticamente.'
        ]);
    } else {
        // ❌ Encontrado mas situação não é ATIVA (BAIXADA, SUSPENSA, INAPTA etc.)
        $pdo->prepare("UPDATE usuarios SET verificada = false, verificacao_status = 'rejeitada' WHERE id_usuario = ?")
            ->execute([$id_ong]);
        echo json_encode([
            'status'   => 'rejeitada',
            'mensagem' => "CNPJ encontrado, mas com situação: {$situacao}. Apenas CNPJs com situação ATIVA são aceitos."
        ]);
    }
    exit;
}

// Rate limit (429), 5xx ou qualquer erro temporário → mantém pendente
if ($http_code === 429 || ($http_code >= 400 && $http_code !== 404) || $http_code >= 500) {
    echo json_encode([
        'status'   => 'pendente',
        'mensagem' => 'Serviço da Receita Federal indisponível no momento. Tente novamente mais tarde.'
    ]);
    exit;
}

// ── CNPJ não encontrado na Receita Federal (404) → rejeita ───────────────────
$pdo->prepare("UPDATE usuarios SET verificada = false, verificacao_status = 'rejeitada' WHERE id_usuario = ?")
    ->execute([$id_ong]);

echo json_encode([
    'status'   => 'rejeitada',
    'mensagem' => $data['message'] ?? 'CNPJ não encontrado na Receita Federal.'
]);