<?php
session_start();
require "banco.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

try {
    $stmt = $pdo->query("SELECT id_usuario, nome, email, cpf_cnpj, verificada, verificacao_status, data_cadastro
                         FROM usuarios
                         WHERE tipo_usuario = 'instituicao'
                         ORDER BY verificacao_status ASC, nome ASC");
    $ongs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $ongs = [];
}

$total      = count($ongs);
$pendentes  = count(array_filter($ongs, fn($o) => ($o['verificacao_status'] ?? 'pendente') === 'pendente'));
$aprovadas  = count(array_filter($ongs, fn($o) => $o['verificacao_status'] === 'aprovada'));
$rejeitadas = count(array_filter($ongs, fn($o) => $o['verificacao_status'] === 'rejeitada'));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
  <title>Admin – Verificar ONGs</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <link rel="stylesheet" href="css/estilo_global.css">
  <link rel="stylesheet" href="css/estilo_admin.css">
</head>

<body>
<div class="phone" id="phoneWrapper">

  <!-- HEADER -->
  <div class="header">
    <a href="feed.php" class="header-back">←</a>
    <span class="header-title">🛡️ ONGs Cadastradas</span>
    <?php if ($pendentes > 0): ?>
      <span class="header-badge"><?= $pendentes ?> pendente<?= $pendentes > 1 ? 's' : '' ?></span>
    <?php endif; ?>
  </div>

  <!-- FILTROS -->
  <div class="filtros-wrap">
    <div class="filtros">
      <button class="filtro-btn active" onclick="filtrar('todos', this)">Todas (<?= $total ?>)</button>
      <button class="filtro-btn" onclick="filtrar('pendente', this)">⏳ Pendentes (<?= $pendentes ?>)</button>
      <button class="filtro-btn" onclick="filtrar('aprovada', this)">✅ Aprovadas (<?= $aprovadas ?>)</button>
      <button class="filtro-btn" onclick="filtrar('rejeitada', this)">❌ Rejeitadas (<?= $rejeitadas ?>)</button>
    </div>
  </div>

  <!-- CONTEÚDO PRINCIPAL -->
  <div class="main-content">

    <!-- AVISO: verificação automática -->
    <div class="info-banner">
      🤖 <strong>Verificação automática ativa.</strong>
      O CNPJ é consultado na Receita Federal no momento do cadastro.
      ONGs pendentes tiveram falha de conexão com a API — use "Re-verificar" para tentar novamente.
    </div>

    <!-- STATS -->
    <div class="stats-row">
      <div class="stat-card pendente">
        <div class="stat-num"><?= $pendentes ?></div>
        <div class="stat-label">Pendentes</div>
      </div>
      <div class="stat-card aprovada">
        <div class="stat-num"><?= $aprovadas ?></div>
        <div class="stat-label">Aprovadas</div>
      </div>
      <div class="stat-card rejeitada">
        <div class="stat-num"><?= $rejeitadas ?></div>
        <div class="stat-label">Rejeitadas</div>
      </div>
    </div>

    <!-- CARDS DAS ONGs -->
    <?php if (empty($ongs)): ?>
      <div class="empty-message">
        <p style="font-size:24px; margin-bottom:8px;">🏢</p>
        <p><strong>Nenhuma ONG cadastrada</strong></p>
        <p style="font-size:12px; margin-top:6px;">As instituições aparecerão aqui quando se cadastrarem.</p>
      </div>
    <?php else: ?>
      <?php foreach ($ongs as $ong):
        $status = $ong['verificacao_status'] ?? 'pendente';
        $cnpj   = preg_replace('/\D/', '', $ong['cpf_cnpj'] ?? '');
      ?>
        <div class="ong-card <?= $status ?>" data-status="<?= $status ?>">

          <div class="ong-card-header">
            <div class="ong-avatar">🏢</div>
            <div class="ong-info">
              <div class="ong-nome"><?= htmlspecialchars($ong['nome']) ?></div>
              <div class="ong-email">📧 <?= htmlspecialchars($ong['email']) ?></div>
            </div>
            <span class="status-badge <?= $status ?>">
              <?= match($status) {
                'aprovada'  => '✅ Verificada',
                'rejeitada' => '❌ Rejeitada',
                default     => '⏳ Pendente'
              } ?>
            </span>
          </div>

          <div class="ong-cnpj">
            🪪 CNPJ: <?= !empty($cnpj) ? htmlspecialchars($ong['cpf_cnpj']) : '<em style="color:#aaa">Não informado</em>' ?>
          </div>

          <!-- Resultado da consulta CNPJ -->
          <div class="cnpj-result" id="cnpj-<?= $ong['id_usuario'] ?>"></div>

          <div class="btn-group">
            <!-- Consulta manual (sempre disponível) -->
            <?php if (!empty($cnpj) && strlen($cnpj) === 14): ?>
              <button class="btn btn-consultar" onclick="consultarCNPJ(<?= $ong['id_usuario'] ?>, '<?= $cnpj ?>')">
                🔍 Consultar CNPJ
              </button>
            <?php endif; ?>

            <!-- Re-verificar: só aparece para pendentes (falha de conexão no cadastro) -->
            <?php if ($status === 'pendente' && !empty($cnpj) && strlen($cnpj) === 14): ?>
              <button class="btn btn-reverificar" onclick="reverificar(<?= $ong['id_usuario'] ?>, '<?= $cnpj ?>', '<?= htmlspecialchars(addslashes($ong['nome'])) ?>')">
                🔄 Re-verificar
              </button>
            <?php endif; ?>
          </div>

        </div>
      <?php endforeach; ?>
    <?php endif; ?>

  </div>

  <!-- MENU INFERIOR FIXO -->
  <div class="bottom">
    <a href="feed.php" class="menu-item">
      🏠<span>Feed</span>
    </a>
    <a href="campanhas.php" class="menu-item">
      📢<span>Campanhas</span>
    </a>
    <a href="admin_verificar_ongs.php" class="menu-item active">
      🛡️<span>Admin</span>
    </a>
    <a href="notificacoes.php" class="menu-item">
      🔔<span>Notificações</span>
    </a>
    <a href="perfil.php" class="menu-item">
      👤<span>Perfil</span>
    </a>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const phoneEl = document.getElementById('phoneWrapper');

const swalAdmin = Swal.mixin({
    target: phoneEl,
    confirmButtonColor: '#f4822f',
    cancelButtonColor: '#aaa',
    customClass: { container: 'swal-inside' }
});

// ── Consultar CNPJ (só visualização) ─────────────────────────────────────────
async function consultarCNPJ(idOng, cnpj) {
    const box = document.getElementById('cnpj-' + idOng);
    box.className = 'cnpj-result show';
    box.innerHTML = '⏳ Consultando Receita Federal...';

    try {
        const res  = await fetch(`https://brasilapi.com.br/api/cnpj/v1/${cnpj}`);
        const data = await res.json();

        if (res.ok && data.razao_social) {
            box.className = 'cnpj-result show ok';
            box.innerHTML = `
                <strong>✅ CNPJ válido e ativo</strong><br>
                <strong>Razão Social:</strong> ${data.razao_social}<br>
                <strong>Situação:</strong> ${data.descricao_situacao_cadastral}<br>
                <strong>Natureza Jurídica:</strong> ${data.natureza_juridica}<br>
                <strong>Porte:</strong> ${data.porte || 'Não informado'}
            `;
        } else {
            box.className = 'cnpj-result show erro';
            box.innerHTML = `❌ CNPJ não encontrado ou inativo.<br><small>${data.message || ''}</small>`;
        }
    } catch (e) {
        box.className = 'cnpj-result show erro';
        box.innerHTML = '❌ Erro ao consultar. Verifique sua conexão.';
    }
}

// ── Re-verificar ONG pendente (chama PHP via fetch e recarrega) ───────────────
async function reverificar(idOng, cnpj, nomeOng) {
    const result = await swalAdmin.fire({
        title: '🔄 Re-verificar CNPJ?',
        html: `<p>Tentar novamente a consulta à Receita Federal para:</p><p><strong>${nomeOng}</strong></p>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '🔄 Sim, re-verificar',
        cancelButtonText: 'Cancelar',
    });

    if (!result.isConfirmed) return;

    swalAdmin.fire({
        title: '🔍 Consultando...',
        html: 'Aguarde enquanto verificamos o CNPJ na Receita Federal.',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
    });

    try {
        const res  = await fetch(`reverificar_ong.php?id=${idOng}&cnpj=${cnpj}`);
        const data = await res.json();

        await swalAdmin.fire({
            title: data.status === 'aprovada' ? '✅ ONG Aprovada!' : '❌ CNPJ não verificado',
            text: data.mensagem,
            icon: data.status === 'aprovada' ? 'success' : 'error',
            confirmButtonText: 'Ok'
        });

        // Recarrega a página para refletir o novo status
        if (data.status !== 'pendente') window.location.reload();

    } catch (e) {
        swalAdmin.fire({ title: 'Erro', text: 'Falha na requisição. Tente novamente.', icon: 'error' });
    }
}

// ── Filtro de cards ───────────────────────────────────────────────────────────
function filtrar(status, btn) {
    document.querySelectorAll('.filtro-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.ong-card').forEach(card => {
        card.style.display = (status === 'todos' || card.dataset.status === status) ? 'block' : 'none';
    });
}

document.body.style.overflow = 'hidden';
</script>
</body>
</html>