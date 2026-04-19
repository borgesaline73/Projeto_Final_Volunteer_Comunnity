<?php
session_start();

// Verificar se está logado
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

require "banco.php";

$id_ong = $_SESSION["usuario_id"];
$mensagem = '';
$tipo_msg = '';

// ============================================
// BUSCA CORRIGIDA - Forçando conversão para INTEGER
// ============================================
try {
    // Converter para inteiro explicitamente
    $id_ong_int = intval($id_ong);
    
    // Buscar o usuário - usando CAST para garantir
    $sql = "SELECT id_usuario, nome, tipo_usuario FROM usuarios WHERE id_usuario = " . $id_ong_int;
    $result = $pdo->query($sql);
    $usuario = $result->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        // Tentar como string
        $sql = "SELECT id_usuario, nome, tipo_usuario FROM usuarios WHERE CAST(id_usuario AS TEXT) = '" . addslashes($id_ong) . "'";
        $result = $pdo->query($sql);
        $usuario = $result->fetch(PDO::FETCH_ASSOC);
    }
    
    if (!$usuario) {
        throw new Exception("Usuário ID $id_ong não encontrado. Verifique se o ID está correto.");
    }
    
    if ($usuario['tipo_usuario'] !== 'instituicao') {
        throw new Exception("Usuário não é instituição. Tipo: " . $usuario['tipo_usuario']);
    }
    
    // Buscar chave PIX
    $sql_ong = "SELECT id_ong, chave_pix FROM ongs WHERE id_ong = " . $id_ong_int;
    $result = $pdo->query($sql_ong);
    $ong_data = $result->fetch(PDO::FETCH_ASSOC);
    
    if (!$ong_data) {
        $pdo->exec("INSERT INTO ongs (id_ong) VALUES (" . $id_ong_int . ")");
        $result = $pdo->query($sql_ong);
        $ong_data = $result->fetch(PDO::FETCH_ASSOC);
        $mensagem = "ℹ️ Registro da ONG criado automaticamente.";
        $tipo_msg = "warning";
    }
    
    $ong = [
        'nome' => $usuario['nome'],
        'id_ong' => $ong_data['id_ong'],
        'chave_pix' => $ong_data['chave_pix'] ?? ''
    ];
    
} catch (Exception $e) {
    $mensagem = "❌ " . $e->getMessage();
    $tipo_msg = "error";
    error_log("ERRO: " . $e->getMessage());
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_pix']) && isset($ong)) {
    $nova_chave = trim($_POST['chave_pix'] ?? '');
    
    if (empty($nova_chave)) {
        $mensagem = "❌ Informe uma chave PIX";
        $tipo_msg = "error";
    } else {
        try {
            $id_int = intval($ong['id_ong']);
            $pdo->exec("UPDATE ongs SET chave_pix = '" . addslashes($nova_chave) . "' WHERE id_ong = " . $id_int);
            $mensagem = "✅ Chave PIX salva com sucesso!";
            $tipo_msg = "success";
            $ong['chave_pix'] = $nova_chave;
        } catch (Exception $e) {
            $mensagem = "❌ Erro ao salvar: " . $e->getMessage();
            $tipo_msg = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gerenciar PIX - <?= isset($ong) ? htmlspecialchars($ong['nome']) : 'ONG' ?></title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Poppins', sans-serif; background: #f5f5f5; }
    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
    .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 15px; margin-bottom: 20px; text-align: center; }
    .header h1 { font-size: 28px; margin-bottom: 10px; }
    .message { padding: 15px; border-radius: 10px; margin-bottom: 20px; font-weight: 500; }
    .message.success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
    .message.error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
    .message.warning { background: #fff3cd; color: #856404; border-left: 4px solid #ffc107; }
    .section { background: white; border-radius: 12px; padding: 25px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .section h2 { font-size: 18px; margin-bottom: 20px; color: #667eea; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; font-weight: 600; margin-bottom: 8px; color: #333; }
    .form-group input[type="text"] { width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; }
    .form-group input[type="text"]:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }
    .hint { font-size: 12px; color: #999; margin-top: 5px; }
    .btn { width: 100%; padding: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px; }
    .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(102,126,234,0.3); }
    .back-link { display: inline-block; margin-bottom: 20px; color: #667eea; text-decoration: none; font-weight: 600; }
    .back-link:hover { text-decoration: underline; }
    .info-box { background: #f0f4ff; border-left: 4px solid #667eea; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; }
    .info-box strong { color: #667eea; }
    .type-examples { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-top: 15px; }
    .type-example { background: #f9f9f9; padding: 12px; border-radius: 8px; border: 1px solid #e0e0e0; }
    .type-example .title { font-weight: 600; color: #667eea; font-size: 12px; }
    .type-example .example { font-size: 11px; color: #999; margin-top: 5px; font-family: monospace; word-break: break-all; }
</style>
</head>
<body>
<div class="container">
    <a href="perfil-ong.php" class="back-link">← Voltar ao Perfil</a>

    <div class="header">
        <h1>💜 Gerenciar PIX</h1>
        <p><?= isset($ong) ? htmlspecialchars($ong['nome']) : 'Sua ONG' ?></p>
    </div>

    <?php if (!empty($mensagem)): ?>
        <div class="message <?= $tipo_msg ?>"><?= $mensagem ?></div>
    <?php endif; ?>

    <?php if (isset($ong)): ?>
    <div class="section">
        <h2>🔐 Configurar Chave PIX</h2>

        <div class="info-box">
            <strong>💡 O que é chave PIX?</strong><br>
            A chave PIX é um identificador único para receber transferências instantâneas. Pode ser CPF, CNPJ, email, telefone ou uma chave aleatória gerada pelo seu banco.
        </div>

        <form method="POST" action="">
            <div class="form-group">
                <label for="chave_pix">Sua Chave PIX</label>
                <input type="text" id="chave_pix" name="chave_pix" 
                       value="<?= htmlspecialchars($ong['chave_pix'] ?? '') ?>"
                       placeholder="Digite sua chave PIX">
                <div class="hint">Exemplos: email@dominio.com, 12345678901, +5548999999999</div>
            </div>

            <div class="form-group">
                <label>Tipos de Chave Aceitos</label>
                <div class="type-examples">
                    <div class="type-example"><div class="title">📱 CPF</div><div class="example">12345678901</div></div>
                    <div class="type-example"><div class="title">🏢 CNPJ</div><div class="example">12345678000195</div></div>
                    <div class="type-example"><div class="title">📧 Email</div><div class="example">ong@example.com</div></div>
                    <div class="type-example"><div class="title">📞 Telefone</div><div class="example">+5548999999999</div></div>
                </div>
            </div>

            <button type="submit" name="atualizar_pix" class="btn">💾 Salvar Chave PIX</button>
        </form>
    </div>

    <div class="section">
        <h2>📖 Como Usar PIX</h2>
        <div class="info-box">
            <strong>Passo 1:</strong> Configure sua chave PIX acima<br>
            <strong>Passo 2:</strong> Doadores verão a chave ao escolher "Dinheiro"<br>
            <strong>Passo 3:</strong> Eles copiam a chave e fazem transferência<br>
            <strong>Passo 4:</strong> A doação é registrada no sistema<br><br>
            💡 <strong>Dica:</strong> Use email ou telefone para facilitar.
        </div>
    </div>
    
    <?php else: ?>
    <div class="section">
        <p style="color: red; text-align: center;">❌ Erro ao carregar dados da ONG.</p>
        <p style="text-align: center;">Tente <a href="logout.php">fazer logout</a> e <a href="login.php">login novamente</a>.</p>
    </div>
    <?php endif; ?>
</div>
</body>
</html>