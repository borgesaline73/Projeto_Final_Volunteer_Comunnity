<?php
session_start();
require "banco.php";

// Só ONG pode confirmar recebimento
if (!isset($_SESSION["usuario_id"]) || ($_SESSION["usuario_tipo"] ?? "") !== "instituicao") {
    header("Location: login.php");
    exit;
}

$id_ong    = $_SESSION["usuario_id"];
$id_doacao = $_GET['id'] ?? null;

if (!$id_doacao) {
    header("Location: perfil-ong.php");
    exit;
}

// Buscar informações da doação (suporta PIX e ITENS)
try {
    $sql_doacao = "SELECT d.*, u.nome as nome_doador, u.email as email_doador,
                          c.data_agendada, c.endereco as local_coleta,
                          CASE 
                              WHEN d.tipo = 'ITEM'     THEN 'Doação de Itens'
                              WHEN d.tipo = 'DINHEIRO' THEN 'Doação em Dinheiro (PIX)'
                              ELSE d.tipo
                          END as tipo_formatado
                   FROM doacoes d 
                   JOIN usuarios u ON d.id_doador = u.id_usuario 
                   LEFT JOIN coletas c ON d.id_doacao = c.id_doacao
                   WHERE d.id_doacao = ? 
                   AND d.id_ong = ? 
                   AND d.status IN ('AGENDADA', 'PENDENTE_PIX')";

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

// Processar confirmação via POST
$erro       = null;
$confirmado = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Doações em dinheiro (PIX) também precisam ter o status_pagamento
        // atualizado para refletir que o valor já foi confirmado pela ONG.
        if ($doacao['tipo'] === 'DINHEIRO') {
            $stmt_update = $pdo->prepare("UPDATE doacoes 
                                          SET status = 'RECEBIDA', 
                                              status_pagamento = 'CONFIRMADO', 
                                              data_doacao = CURRENT_TIMESTAMP 
                                          WHERE id_doacao = ?");
        } else {
            $stmt_update = $pdo->prepare("UPDATE doacoes 
                                          SET status = 'RECEBIDA', 
                                              data_doacao = CURRENT_TIMESTAMP 
                                          WHERE id_doacao = ?");
        }
        $stmt_update->execute([$id_doacao]);

        $mensagem_notificacao = "Sua doação para " . ($_SESSION["usuario_nome"] ?? "a ONG") . " foi recebida e confirmada! 🎉";
        $stmt_notificacao = $pdo->prepare("INSERT INTO notificacoes (id_usuario, mensagem, tipo) VALUES (?, ?, 'DOACAO_RECEBIDA')");
        $stmt_notificacao->execute([$doacao['id_doador'], $mensagem_notificacao]);

        $pdo->commit();
        $confirmado = true;

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
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
  <title>Confirmar Recebimento - Volunteer Community</title>

  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/estilo_global.css">
  <link rel="stylesheet" href="css/estilo_confirmar_recebimento.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

  <style>
    /* Confinar SweetAlert dentro do .phone */
    .phone {
      position: relative;
      overflow: hidden;
    }

    .swal2-container.swal-inside-confirmar {
      position: absolute !important;
      top: 0 !important;
      left: 0 !important;
      width: 100% !important;
      height: 100% !important;
      z-index: 9999;
    }

    .swal2-container.swal-inside-confirmar .swal2-popup {
      width: 88% !important;
      max-width: 320px !important;
      border-radius: 20px !important;
      font-family: 'Poppins', sans-serif !important;
    }

    .swal2-confirm {
      background-color: #f4822f !important;
      border-radius: 50px !important;
      padding: 8px 20px !important;
      font-weight: 600 !important;
      font-size: 13px !important;
    }

    .swal2-cancel {
      border-radius: 50px !important;
      padding: 8px 20px !important;
      font-weight: 600 !important;
      font-size: 13px !important;
    }
  </style>
</head>
<body>

<div class="phone" id="phoneWrapper">

  <div class="header">
    <button class="back" onclick="history.back()">←</button>
  </div>

  <div class="content">

    <h1>Confirmar Recebimento</h1>

    <!-- CARD COM INFORMAÇÕES DA DOAÇÃO -->
    <div class="doacao-info">
      <div class="info-item">
        <span class="info-label">👤 Doador:</span>
        <?= htmlspecialchars($doacao['nome_doador']) ?>
      </div>
      <div class="info-item">
        <span class="info-label">📧 Email:</span>
        <?= htmlspecialchars($doacao['email_doador']) ?>
      </div>
      <div class="info-item">
        <span class="info-label">📦 Tipo:</span>
        <?= htmlspecialchars($doacao['tipo_formatado']) ?>
      </div>
      
      <!-- Exibir data apenas para doações de ITEM -->
      <?php if ($doacao['tipo'] == 'ITEM' && !empty($doacao['data_agendada'])): ?>
      <div class="info-item">
        <span class="info-label">📅 Data Agendada:</span>
        <?= date('d/m/Y H:i', strtotime($doacao['data_agendada'])) ?>
      </div>
      <?php endif; ?>
      
      <!-- Exibir local apenas para doações de ITEM -->
      <?php if ($doacao['tipo'] == 'ITEM' && !empty($doacao['local_coleta'])): ?>
      <div class="info-item">
        <span class="info-label">📍 Local:</span>
        <?= htmlspecialchars($doacao['local_coleta']) ?>
      </div>
      <?php endif; ?>
      
      <!-- Itens doados (apenas ITEM) -->
      <?php if ($doacao['tipo'] == 'ITEM' && !empty($doacao['descricao_item'])): ?>
      <div class="info-item">
        <span class="info-label">📝 Itens:</span>
        <?= htmlspecialchars($doacao['descricao_item']) ?>
      </div>
      <?php endif; ?>
      
      <!-- Valor da doação (apenas PIX) -->
      <?php if ($doacao['tipo'] == 'DINHEIRO' && !empty($doacao['valor'])): ?>
      <div class="info-item">
        <span class="info-label">💰 Valor:</span>
        R$ <?= number_format($doacao['valor'], 2, ',', '.') ?>
      </div>
      <?php endif; ?>
      
      <!-- Comprovante PIX (apenas PIX) -->
      <?php if ($doacao['tipo'] == 'DINHEIRO' && !empty($doacao['comprovante'])): ?>
      <div class="info-item">
        <span class="info-label">📎 Comprovante:</span>
        <a href="#" onclick="visualizarComprovante('<?= htmlspecialchars($doacao['comprovante']) ?>')" 
           style="color: #f4822f; text-decoration: none;">
          📄 Clique para visualizar
        </a>
      </div>
      <?php endif; ?>
    </div>

    <!-- CAIXA DE CONFIRMAÇÃO -->
    <div class="confirm-box">
      <div class="confirm-icon"><?= $doacao['tipo'] == 'DINHEIRO' ? '💰✅' : '📦✅' ?></div>
      <h3>A doação foi recebida?</h3>
      <p>Esta ação notificará o doador e mudará o status para "RECEBIDA".</p>
    </div>

    <!-- BOTÕES -->
    <button class="btn confirm" id="btnConfirmar" onclick="confirmarRecebimento()">
      ✅ Confirmar Recebimento
    </button>
    <button class="btn cancel" onclick="history.back()">
      ❌ Cancelar
    </button>

  </div><!-- /content -->

</div><!-- /phone -->

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
const phoneEl = document.getElementById('phoneWrapper');

const swalConfirmar = Swal.mixin({
    target: phoneEl,
    confirmButtonColor: '#f4822f',
    cancelButtonColor: '#aaa',
    customClass: {
        container: 'swal-inside-confirmar',
        popup:     'swal-popup-confirmar'
    }
});

// ===== EXIBIR SUCESSO OU ERRO VINDOS DO PHP =====
<?php if ($confirmado): ?>
document.addEventListener('DOMContentLoaded', function () {
    swalConfirmar.fire({
        title: '✅ Confirmado!',
        text: 'O doador foi notificado com sucesso.',
        icon: 'success',
        confirmButtonText: 'Voltar ao Perfil',
        allowOutsideClick: false,
        allowEscapeKey: false
    }).then(() => {
        window.location.href = 'perfil-ong.php';
    });
});
<?php elseif ($erro): ?>
document.addEventListener('DOMContentLoaded', function () {
    swalConfirmar.fire({
        title: '❌ Erro',
        text: '<?= htmlspecialchars($erro) ?>',
        icon: 'error',
        confirmButtonText: 'Tentar novamente'
    });
});
<?php endif; ?>

// ===== POPUP DE CONFIRMAÇÃO ANTES DE SUBMETER =====
async function confirmarRecebimento() {
    let detalhesDoacao = `
        <div style="text-align:left; font-size:13px; line-height:1.8;">
            <strong>👤 Doador:</strong> <?= htmlspecialchars(addslashes($doacao['nome_doador'])) ?><br>
            <strong>📦 Tipo:</strong> <?= htmlspecialchars(addslashes($doacao['tipo_formatado'])) ?><br>`;
    
    <?php if ($doacao['tipo'] == 'ITEM'): ?>
    detalhesDoacao += `
            <strong>📅 Data:</strong> <?= date('d/m/Y H:i', strtotime($doacao['data_agendada'])) ?><br>
            <strong>📍 Local:</strong> <?= htmlspecialchars(addslashes($doacao['local_coleta'])) ?><br>`;
    <?php else: ?>
    detalhesDoacao += `
            <strong>💰 Valor:</strong> R$ <?= number_format($doacao['valor'], 2, ',', '.') ?><br>`;
    <?php endif; ?>
    
    detalhesDoacao += `</div>`;
    
    const result = await swalConfirmar.fire({
        title: 'Confirmar recebimento?',
        html: detalhesDoacao,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '✅ Sim, confirmar',
        cancelButtonText: '❌ Cancelar'
    });

    if (result.isConfirmed) {
        document.getElementById('btnConfirmar').disabled = true;
        document.getElementById('btnConfirmar').textContent = '⏳ Confirmando...';

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        document.body.appendChild(form);
        form.submit();
    }
}

// Função para visualizar comprovante PIX
function visualizarComprovante(caminho) {
    swalConfirmar.fire({
        title: 'Comprovante de Pagamento',
        imageUrl: caminho,
        imageWidth: '100%',
        imageHeight: 'auto',
        imageAlt: 'Comprovante PIX',
        confirmButtonText: 'Fechar',
        confirmButtonColor: '#f4822f'
    });
}
</script>

</body>
</html>