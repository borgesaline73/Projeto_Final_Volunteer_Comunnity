<?php
session_start();
require "banco.php";

// Só ONG pode confirmar recebimento
if (!isset($_SESSION["usuario_id"]) || ($_SESSION["usuario_tipo"] ?? "") !== "instituicao") {
    header("Location: login.php");
    exit;
}

$id_ong = $_SESSION["usuario_id"];
$id_doacao = $_GET['id'] ?? null;

if (!$id_doacao) {
    header("Location: perfil-ong.php");
    exit;
}

// Buscar informações da doação
try {
    $sql_doacao = "SELECT d.*, u.nome as nome_doador, u.email as email_doador,
                          c.data_agendada, c.endereco as local_coleta,
                          CASE 
                              WHEN d.tipo = 'ITEM' THEN 'Doação de Itens'
                              WHEN d.tipo = 'DINHEIRO' THEN 'Doação em Dinheiro'
                              ELSE d.tipo
                          END as tipo_formatado
                   FROM doacoes d 
                   JOIN usuarios u ON d.id_doador = u.id_usuario 
                   JOIN coletas c ON d.id_doacao = c.id_doacao
                   WHERE d.id_doacao = ? AND d.id_ong = ? AND d.status = 'AGENDADA'";
    
    $stmt = $pdo->prepare($sql_doacao);
    $stmt->execute([$id_doacao, $id_ong]);
    $doacao = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doacao) {
        header("Location: perfil-ong.php");
        exit;
    }

} catch (PDOException $e) {
    error_log("Erro ao buscar doação: " . $e->getMessage());
    header("Location: perfil-ong.php");
    exit;
}

// Processar confirmação
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // 1. Atualizar status da doação para RECEBIDA
        $sql_update = "UPDATE doacoes SET status = 'RECEBIDA', data_doacao = CURRENT_TIMESTAMP WHERE id_doacao = ?";
        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->execute([$id_doacao]);
        
        // 2. Criar notificação para o doador
        $mensagem_notificacao = "Sua doação para " . $_SESSION["usuario_nome"] . " foi recebida e confirmada! 🎉";
        $sql_notificacao = "INSERT INTO notificacoes (id_usuario, mensagem, tipo) VALUES (?, ?, 'DOACAO_RECEBIDA')";
        $stmt_notificacao = $pdo->prepare($sql_notificacao);
        $stmt_notificacao->execute([$doacao['id_doador'], $mensagem_notificacao]);
        
        $pdo->commit();
        
        echo "<script>
        alert('✅ Recebimento confirmado! O doador será notificado.');
        window.location='perfil-ong.php';
        </script>";
        exit;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $erro = "Erro ao confirmar recebimento: " . $e->getMessage();
        error_log($erro);
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Confirmar Recebimento - Volunteer Community</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/estilo_global.css">
<link rel="stylesheet" href="css/estilo_confirmar_recebimento.css">
</head>

<body>

<div class="phone">
  <div class="header">
    <button class="back" onclick="history.back()">←</button>
  </div>

  <div class="content">
    <h1>Confirmar Recebimento</h1>
    
    <?php if (isset($erro)): ?>
      <div class="error-message">
        <?= htmlspecialchars($erro) ?>
      </div>
    <?php endif; ?>

    <div class="doacao-info">
      <div class="info-item">
        <span class="info-label">👤 Doador:</span> <?= htmlspecialchars($doacao['nome_doador']) ?>
      </div>
      <div class="info-item">
        <span class="info-label">📧 Email:</span> <?= htmlspecialchars($doacao['email_doador']) ?>
      </div>
      <div class="info-item">
        <span class="info-label">📦 Tipo:</span> <?= htmlspecialchars($doacao['tipo_formatado']) ?>
      </div>
      <div class="info-item">
        <span class="info-label">📅 Data Agendada:</span> <?= date('d/m/Y H:i', strtotime($doacao['data_agendada'])) ?>
      </div>
      <div class="info-item">
        <span class="info-label">📍 Local:</span> <?= htmlspecialchars($doacao['local_coleta']) ?>
      </div>
      <?php if ($doacao['descricao_item']): ?>
        <div class="info-item">
          <span class="info-label">📝 Itens:</span> <?= htmlspecialchars($doacao['descricao_item']) ?>
        </div>
      <?php endif; ?>
      <?php if ($doacao['valor']): ?>
        <div class="info-item">
          <span class="info-label">💰 Valor:</span> R$ <?= number_format($doacao['valor'], 2, ',', '.') ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="confirm-box">
      <div class="confirm-icon">📦✅</div>
      <h3>Confirmar que a doação foi recebida?</h3>
      <p>
        Esta ação notificará o doador e mudará o status para "RECEBIDA".
      </p>
    </div>

    <form method="POST">
      <button type="submit" class="btn confirm">✅ Sim, Confirmar Recebimento</button>
    </form>
    
    <button class="btn cancel" onclick="history.back()">❌ Cancelar</button>
  </div>
</div>

</body>
</html>