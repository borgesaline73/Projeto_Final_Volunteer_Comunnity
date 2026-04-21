<?php
session_start();
require "banco.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

$id_visitante   = $_SESSION["usuario_id"];
$tipo_visitante = $_SESSION["usuario_tipo"] ?? "doador";
$id_ong         = (int)($_GET["id"] ?? 0);

if ($id_ong <= 0) {
    header("Location: feed.php");
    exit;
}

$rotaPerfil = $tipo_visitante === "instituicao" ? "perfil-ong.php" : "perfil.php";
$rotaPlus   = $tipo_visitante === "instituicao" ? "criar_post.php" : "agendar_coleta.php";

try {
    $stmt = $pdo->prepare("SELECT nome, email, cpf_cnpj, verificada, verificacao_status
                           FROM usuarios
                           WHERE id_usuario = ? AND tipo_usuario = 'instituicao'");
    $stmt->execute([$id_ong]);
    $ong = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ong) {
        header("Location: feed.php");
        exit;
    }

    $nome_ong    = $ong['nome'];
    $verificada  = $ong['verificada'] ?? false;
    $status_ver  = $ong['verificacao_status'] ?? 'pendente';

    $stmt_posts = $pdo->prepare("SELECT * FROM posts WHERE id_usuario = ? ORDER BY data_post DESC");
    $stmt_posts->execute([$id_ong]);
    $posts = $stmt_posts->fetchAll(PDO::FETCH_ASSOC);

    $stmt_itens = $pdo->prepare("SELECT * FROM itens_ong WHERE id_ong = ? ORDER BY tipo, nome ASC");
    $stmt_itens->execute([$id_ong]);
    $itens           = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);
    $itens_aceitos   = array_filter($itens, fn($i) => $i['tipo'] === 'ACEITO');
    $itens_recusados = array_filter($itens, fn($i) => $i['tipo'] === 'RECUSADO');

    $stmt_destinos = $pdo->prepare("SELECT * FROM destino_doacoes WHERE id_ong = ? ORDER BY criado_em DESC");
    $stmt_destinos->execute([$id_ong]);
    $destinos = $stmt_destinos->fetchAll(PDO::FETCH_ASSOC);

    $stmt_notif = $pdo->prepare("SELECT COUNT(*) as total FROM notificacoes WHERE id_usuario = ? AND lida = FALSE");
    $stmt_notif->execute([$id_visitante]);
    $total_notificacoes = $stmt_notif->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

} catch (PDOException $e) {
    header("Location: feed.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($nome_ong) ?> - Volunteer Community</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/estilo_global.css">
<link rel="stylesheet" href="css/estilo_perfil_ong_publico.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

<style>
  .phone { position: relative; overflow: hidden; }

  .swal2-container.swal-inside-ong {
    position: absolute !important; top: 0 !important; left: 0 !important;
    width: 100% !important; height: 100% !important; z-index: 9999;
  }
  .swal2-container.swal-inside-ong .swal2-popup {
    width: 88% !important; max-width: 320px !important;
    border-radius: 20px !important; font-family: 'Poppins', sans-serif !important;
  }
  .swal2-confirm {
    background-color: #f4822f !important; border-radius: 50px !important;
    padding: 8px 20px !important; font-weight: 600 !important; font-size: 13px !important;
  }
  .swal2-cancel {
    border-radius: 50px !important; padding: 8px 20px !important;
    font-weight: 600 !important; font-size: 13px !important;
  }

  /* ── Selos de verificação ── */
  .verified-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 600;
    padding: 5px 14px;
    border-radius: 20px;
    margin-bottom: 14px;
    cursor: pointer;
    transition: transform 0.2s;
  }
  .verified-badge:hover { transform: scale(1.03); }

  .verified-badge.aprovada {
    background: linear-gradient(135deg, #d4edda, #b8e0c4);
    color: #155724;
    border: 1.5px solid #a8d5b0;
  }
  .verified-badge.pendente {
    background: #fff3cd;
    color: #856404;
    border: 1.5px solid #ffd877;
  }
  .verified-badge.rejeitada {
    background: #f8d7da;
    color: #721c24;
    border: 1.5px solid #f5b8b3;
  }

  /* Ícone de verificação animado */
  .check-icon {
    width: 18px; height: 18px;
    background: #28a745;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 11px;
    font-weight: 700;
    animation: popIn 0.4s ease;
  }
  @keyframes popIn {
    0%   { transform: scale(0); }
    70%  { transform: scale(1.2); }
    100% { transform: scale(1); }
  }

  /* Faixa de destaque no card quando verificada */
  .profile-card.verificada {
    border: 2px solid #a8d5b0;
    background: linear-gradient(180deg, #f0fbf4 0%, #fff 60%);
  }

  /* CNPJ no perfil */
  .ong-cnpj-info {
    font-size: 12px;
    color: #888;
    margin-bottom: 10px;
  }
</style>
</head>
<body>

<div class="phone" id="phoneWrapper">

  <div class="header">
    <span onclick="history.back()" style="cursor:pointer;">⬅</span>
    <div class="header-title"><?= htmlspecialchars($nome_ong) ?></div>
    <span></span>
  </div>

  <div class="main-content">

    <!-- CARD PERFIL -->
    <div class="profile-card <?= $verificada ? 'verificada' : '' ?>">
      <div class="avatar">🏢</div>
      <div class="name"><?= htmlspecialchars($nome_ong) ?></div>

      <?php if (!empty($ong['cpf_cnpj'])): ?>
        <div class="ong-cnpj-info">🪪 CNPJ: <?= htmlspecialchars($ong['cpf_cnpj']) ?></div>
      <?php endif; ?>

      <!-- Selo dinâmico conforme status -->
      <?php if ($verificada && $status_ver === 'aprovada'): ?>
        <div class="verified-badge aprovada" onclick="mostrarInfoVerificacao()">
          <span class="check-icon">✓</span>
          ONG Verificada pelo Volunteer Community
        </div>
      <?php elseif ($status_ver === 'rejeitada'): ?>
        <div class="verified-badge rejeitada">
          ❌ Verificação não aprovada
        </div>
      <?php else: ?>
        <div class="verified-badge pendente">
          ⏳ Verificação em análise
        </div>
      <?php endif; ?>

      <?php if ($tipo_visitante === 'doador'): ?>
        <button class="btn-doar" onclick="confirmarDoacao(<?= $id_ong ?>, '<?= htmlspecialchars(addslashes($nome_ong)) ?>')">
          💛 Quero Doar
        </button>
      <?php endif; ?>
    </div>

    <!-- MENU DE ABAS -->
    <div class="tab-menu">
      <div class="tab active" data-tab="posts">Conexão Solidária</div>
      <div class="tab" data-tab="itens">Itens Aceitos</div>
      <div class="tab" data-tab="destino">Sua Doação Importa!</div>
    </div>

    <!-- ========== ABA POSTS ========== -->
    <div class="tab-content active" id="posts-tab">
      <div class="section">
        <span>Publicações</span>
        <span class="section-count blue"><?= count($posts) ?></span>
      </div>

      <?php if (!empty($posts)): ?>
        <?php foreach ($posts as $post): ?>
          <div class="post-card">
            <h3><?= htmlspecialchars($post['titulo']) ?></h3>
            <div class="post-meta">
              <?= date("d/m/Y \à\s H:i", strtotime($post['data_post'])) ?>
            </div>
            <?php if (!empty($post['categoria'])): ?>
              <span class="categoria-badge"><?= htmlspecialchars($post['categoria']) ?></span>
            <?php endif; ?>
            <div class="post-content"><?= nl2br(htmlspecialchars($post['descricao'])) ?></div>
            <?php if (!empty($post['imagem'])): ?>
              <img src="uploads/<?= htmlspecialchars($post['imagem']) ?>" class="post-image"
                   alt="<?= htmlspecialchars($post['titulo']) ?>" onerror="this.style.display='none'">
            <?php endif; ?>
            <?php if ($tipo_visitante === 'doador'): ?>
              <button class="btn-doar-post" onclick="confirmarDoacao(<?= $id_ong ?>, '<?= htmlspecialchars(addslashes($nome_ong)) ?>')">
                💝 Efetuar Doação
              </button>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="empty"><strong>📝 Nenhuma publicação ainda</strong></div>
      <?php endif; ?>
    </div>

    <!-- ========== ABA ITENS ========== -->
    <div class="tab-content" id="itens-tab">
      <div class="section"><span>✅ Itens Aceitos</span></div>
      <div class="itens-grid">
        <?php if (empty($itens_aceitos)): ?>
          <p class="empty-itens">Nenhum item cadastrado ainda.</p>
        <?php else: ?>
          <?php foreach ($itens_aceitos as $item): ?>
            <div class="item-tag aceito"><?= htmlspecialchars($item['nome']) ?></div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div class="section" style="margin-top:16px;"><span>❌ Itens Não Aceitos</span></div>
      <div class="itens-grid">
        <?php if (empty($itens_recusados)): ?>
          <p class="empty-itens">Nenhum item cadastrado ainda.</p>
        <?php else: ?>
          <?php foreach ($itens_recusados as $item): ?>
            <div class="item-tag recusado"><?= htmlspecialchars($item['nome']) ?></div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- ========== ABA SUA DOAÇÃO IMPORTA ========== -->
    <div class="tab-content" id="destino-tab">
      <div class="section"><span>📢 Para onde vai sua doação</span></div>
      <?php if (empty($destinos)): ?>
        <div class="empty"><strong>💛 Nenhuma publicação ainda</strong></div>
      <?php else: ?>
        <div class="destino-wrapper">
          <button class="destino-nav" id="btnPrev" onclick="navegarDestino(-1)">&#8592;</button>
          <div class="destino-carousel">
            <?php foreach ($destinos as $i => $d): ?>
              <div class="destino-slide <?= $i === 0 ? 'active' : '' ?>">
                <?php if (!empty($d['imagem'])): ?>
                  <img src="uploads/<?= htmlspecialchars($d['imagem']) ?>" class="destino-img"
                       alt="<?= htmlspecialchars($d['titulo']) ?>" onerror="this.style.display='none'">
                <?php endif; ?>
                <div class="destino-body">
                  <div class="destino-titulo"><?= htmlspecialchars($d['titulo']) ?></div>
                  <div class="destino-data"><?= date('d/m/Y', strtotime($d['criado_em'])) ?></div>
                  <div class="destino-descricao"><?= nl2br(htmlspecialchars($d['descricao'])) ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <button class="destino-nav" id="btnNext" onclick="navegarDestino(1)">&#8594;</button>
        </div>
        <div class="destino-dots">
          <?php foreach ($destinos as $i => $d): ?>
            <span class="dot <?= $i === 0 ? 'active' : '' ?>" onclick="irParaDestino(<?= $i ?>)"></span>
          <?php endforeach; ?>
        </div>
        <div class="destino-counter">
          <span id="destinoAtual">1</span> / <?= count($destinos) ?>
        </div>
      <?php endif; ?>
    </div>

  </div>

  <!-- MENU INFERIOR -->
  <div class="bottom">
    <a href="feed.php" class="menu-item">🏠<span>Feed</span></a>
    <a href="campanhas.php" class="menu-item">📢<span>Campanhas</span></a>
    <button class="plus-btn" onclick="window.location='<?= $rotaPlus ?>'">+</button>
    <a href="notificacoes.php" class="menu-item">
      🔔<span>Notificações</span>
      <?php if ($total_notificacoes > 0): ?>
        <span class="notification-badge"><?= $total_notificacoes ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= $rotaPerfil ?>" class="menu-item">👤<span>Perfil</span></a>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.body.style.overflow = 'hidden';

const phoneEl = document.getElementById('phoneWrapper');
const swalOng = Swal.mixin({
    target: phoneEl,
    confirmButtonColor: '#f4822f',
    cancelButtonColor: '#aaa',
    customClass: { container: 'swal-inside-ong' }
});

// ── Info sobre verificação ────────────────────────────────────────────────
function mostrarInfoVerificacao() {
    swalOng.fire({
        title: '✅ ONG Verificada',
        html: `
            <div style="text-align:left; font-size:13px; line-height:1.8;">
                <p>Esta ONG passou pelo processo de verificação do <strong>Volunteer Community</strong>:</p>
                <br>
                <p>🪪 <strong>CNPJ validado</strong> na Receita Federal</p>
                <p>🛡️ <strong>Dados conferidos</strong> pela equipe</p>
                <p>✅ <strong>Aprovada</strong> para receber doações</p>
                <br>
                <p style="font-size:11px; color:#aaa;">
                    A verificação garante que esta é uma instituição legítima.
                </p>
            </div>
        `,
        icon: 'success',
        confirmButtonText: 'Entendi',
    });
}

// ── Confirmação de doação ─────────────────────────────────────────────────
async function confirmarDoacao(idOng, nomeOng) {
    const result = await swalOng.fire({
        title: '💛 Fazer uma doação?',
        html: `
            <p style="font-size:14px; color:#555; margin:0;">
                Você será direcionado para agendar<br>
                uma doação para <strong>${nomeOng}</strong>.
            </p>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '💝 Sim, quero doar!',
        cancelButtonText: 'Cancelar',
    });
    if (result.isConfirmed) {
        window.location.href = `agendar_coleta.php?ong=${idOng}&titulo=${encodeURIComponent(nomeOng)}`;
    }
}

// ── Abas ──────────────────────────────────────────────────────────────────
document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', function () {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        this.classList.add('active');
        this.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        const target = document.getElementById(this.getAttribute('data-tab') + '-tab');
        if (target) target.classList.add('active');
    });
});

// ── Carrossel ─────────────────────────────────────────────────────────────
let destinoAtual = 0;

function navegarDestino(dir) {
    const slides = document.querySelectorAll('.destino-slide');
    if (!slides.length) return;
    slides[destinoAtual].classList.remove('active');
    document.querySelectorAll('.dot')[destinoAtual]?.classList.remove('active');
    destinoAtual = Math.max(0, Math.min(destinoAtual + dir, slides.length - 1));
    slides[destinoAtual].classList.add('active');
    document.querySelectorAll('.dot')[destinoAtual]?.classList.add('active');
    document.getElementById('destinoAtual').textContent = destinoAtual + 1;
    document.getElementById('btnPrev').disabled = destinoAtual === 0;
    document.getElementById('btnNext').disabled = destinoAtual === slides.length - 1;
}

function irParaDestino(i) { navegarDestino(i - destinoAtual); }

document.addEventListener('DOMContentLoaded', () => {
    const slides = document.querySelectorAll('.destino-slide');
    if (slides.length) {
        document.getElementById('btnPrev').disabled = true;
        document.getElementById('btnNext').disabled = slides.length <= 1;
    }
});
</script>
</body>
</html>