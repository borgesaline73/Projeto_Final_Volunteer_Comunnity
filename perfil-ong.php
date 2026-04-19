<?php
session_start();
require "banco.php";

// ===== CAPTURAR MENSAGEM DE SUCESSO DA URL =====
$mensagem_flash = '';
$tipo_flash = '';
if (isset($_GET['msg']) && isset($_GET['tipo'])) {
    $mensagem_flash = urldecode($_GET['msg']);
    $tipo_flash = $_GET['tipo'];
}

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

$tipo = $_SESSION["usuario_tipo"] ?? null;

if ($tipo !== "instituicao") {
    header("Location: perfil.php");
    exit;
}

$id_ong = $_SESSION["usuario_id"];

// Buscar chave PIX da ONG
$chave_pix = null;
try {
    $stmt = $pdo->prepare("SELECT chave_pix FROM ongs WHERE id_ong = ?");
    $stmt->execute([$id_ong]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $chave_pix = $result['chave_pix'] ?? null;
} catch (PDOException $e) {
    error_log("Erro ao buscar chave PIX: " . $e->getMessage());
}

try {
    $sql_ong = "SELECT nome, email, tipo_usuario FROM usuarios WHERE id_usuario = ?";
    $stmt_ong = $pdo->prepare($sql_ong);
    $stmt_ong->execute([$id_ong]);
    $ong = $stmt_ong->fetch(PDO::FETCH_ASSOC);

    if (!$ong) throw new Exception("ONG não encontrada");

    $nome        = $ong['nome']         ?? "Instituição";
    $email       = $ong['email']        ?? "email@ong.com";
    $tipo_usuario = $ong['tipo_usuario'] ?? "instituicao";

    $sql_posts = "SELECT p.*, u.nome, u.id_usuario as id_ong 
                  FROM posts p 
                  JOIN usuarios u ON p.id_usuario = u.id_usuario
                  WHERE p.id_usuario = ? 
                  ORDER BY p.data_post DESC";
    $stmt_posts = $pdo->prepare($sql_posts);
    $stmt_posts->execute([$id_ong]);
    $posts = $stmt_posts->fetchAll(PDO::FETCH_ASSOC);

    $sql_coletas = "SELECT d.*, u.nome as nome_doador, u.email as email_doador,
                           c.data_agendada, c.endereco as local_coleta,
                           CASE 
                               WHEN d.tipo = 'ITEM'     THEN 'Doação de Itens'
                               WHEN d.tipo = 'DINHEIRO' THEN 'Doação em Dinheiro'
                               ELSE d.tipo
                           END as tipo_formatado,
                           CASE 
                               WHEN d.status = 'AGENDADA' THEN 'Coleta Agendada'
                               WHEN d.status = 'RECEBIDA' THEN 'Coleta Recebida'
                               ELSE d.status
                           END as status_formatado
                    FROM doacoes d 
                    JOIN usuarios u ON d.id_doador = u.id_usuario 
                    JOIN coletas c ON d.id_doacao = c.id_doacao
                    WHERE d.id_ong = ? 
                    ORDER BY CASE WHEN d.status = 'AGENDADA' THEN 1 ELSE 2 END, c.data_agendada ASC";
    $stmt_coletas = $pdo->prepare($sql_coletas);
    $stmt_coletas->execute([$id_ong]);
    $coletas = $stmt_coletas->fetchAll(PDO::FETCH_ASSOC);

    $sql_itens = "SELECT * FROM itens_ong WHERE id_ong = ? ORDER BY tipo, nome ASC";
    $stmt_itens = $pdo->prepare($sql_itens);
    $stmt_itens->execute([$id_ong]);
    $itens = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);

    $itens_aceitos   = array_filter($itens, fn($i) => $i['tipo'] === 'ACEITO');
    $itens_recusados = array_filter($itens, fn($i) => $i['tipo'] === 'RECUSADO');

    $sql_destinos = "SELECT * FROM destino_doacoes WHERE id_ong = ? ORDER BY criado_em DESC";
    $stmt_destinos = $pdo->prepare($sql_destinos);
    $stmt_destinos->execute([$id_ong]);
    $destinos = $stmt_destinos->fetchAll(PDO::FETCH_ASSOC);

    $sql_notificacoes = "SELECT COUNT(*) as total FROM notificacoes WHERE id_usuario = ? AND lida = FALSE";
    $stmt_notificacoes = $pdo->prepare($sql_notificacoes);
    $stmt_notificacoes->execute([$id_ong]);
    $notif_result        = $stmt_notificacoes->fetch(PDO::FETCH_ASSOC);
    $total_notificacoes  = $notif_result['total'] ?? 0;

} catch (PDOException $e) {
    $posts = []; $coletas = []; $itens_aceitos = []; $itens_recusados = []; $destinos = [];
    $error_db = true;
    $nome = "Erro ao carregar"; $email = "Erro"; $tipo_usuario = "instituicao";
    $total_notificacoes = 0;
} catch (Exception $e) {
    $posts = []; $coletas = []; $itens_aceitos = []; $itens_recusados = []; $destinos = [];
    $error_db = true;
    $nome = "ONG não encontrada"; $email = "Erro"; $tipo_usuario = "instituicao";
    $total_notificacoes = 0;
}

$rotaPlus   = "criar_post.php";
$rotaPerfil = "perfil-ong.php";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Perfil da ONG - Volunteer Community</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/estilo_global.css">
<link rel="stylesheet" href="css/estilo_perfil_ong.css">
<!-- SweetAlert2 -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

<style>
  /*
   * Confina o overlay e o popup do SweetAlert2 dentro do .phone.
   * O .phone precisa ter position: relative (ou absolute/fixed) para
   * que o overlay com position: absolute fique preso nele.
   */
  .phone {
    position: relative; 
    overflow: hidden;   
  }

  /* Overlay fica sobre o .phone, não sobre a página inteira */
  .swal2-container.swal-inside-phone {
    position: absolute !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    height: 100% !important;
    z-index: 9999;
  }

  /* Popup centralizado dentro do container acima */
  .swal2-container.swal-inside-phone .swal2-popup {
    width: 88% !important;
    max-width: 340px !important;
    border-radius: 16px !important;
    font-family: 'Poppins', sans-serif !important;
  }

  /* Toast também fica dentro do .phone */
  .swal2-container.swal-inside-phone.swal2-top-end,
  .swal2-container.swal-inside-phone.swal2-top-right {
    top: 8px !important;
    right: 8px !important;
    width: auto !important;
    height: auto !important;
  }

  /* Estilo do botão PIX */
  .btn-pix {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 12px;
    padding: 12px 20px;
    margin-top: 15px;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    width: 100%;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
  }

  .btn-pix:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
  }

  .pix-status {
    font-size: 12px;
    margin-top: 10px;
    padding: 8px;
    border-radius: 8px;
    text-align: center;
  }

  .pix-status.cadastrada {
    background: #d4edda;
    color: #155724;
  }

  .pix-status.nao-cadastrada {
    background: #fff3cd;
    color: #856404;
  }

  .pix-key-preview {
    font-family: monospace;
    word-break: break-all;
    font-size: 11px;
    margin-top: 5px;
    color: #666;
  }
</style>
</head>
<body>

<div class="phone" id="phoneWrapper">

  <div class="header">
    <span onclick="history.back()" style="cursor:pointer;">⬅</span>
    <div class="header-title">Perfil da ONG</div>
    <span style="cursor:pointer;" onclick="window.location='logout.php'">🚪</span>
  </div>

  <!-- CONTEÚDO PRINCIPAL COM SCROLL -->
  <div class="main-content">

    <!-- CARD PERFIL -->
    <div class="profile-card">
      <div class="avatar">🏢</div>
      <div class="name"><?= htmlspecialchars($nome) ?></div>
      <div class="info-item"><strong>Email:</strong> <?= htmlspecialchars($email) ?></div>
      <div class="info-item"><strong>Tipo de Conta:</strong>
        <?= $tipo_usuario === 'instituicao' ? 'Instituição' : htmlspecialchars($tipo_usuario) ?>
      </div>
      
      <!-- Status da Chave PIX -->
      <div class="pix-status <?= !empty($chave_pix) ? 'cadastrada' : 'nao-cadastrada' ?>">
        <?php if (!empty($chave_pix)): ?>
          💜 Chave PIX cadastrada
          <div class="pix-key-preview"><?= htmlspecialchars(substr($chave_pix, 0, 20)) . (strlen($chave_pix) > 20 ? '...' : '') ?></div>
        <?php else: ?>
          ⚠️ Nenhuma chave PIX cadastrada
        <?php endif; ?>
      </div>
      
      <!-- Botão para Gerenciar PIX -->
      <button class="btn-pix" onclick="window.location.href='gerenciar_pix.php'">
        💜 Gerenciar Chave PIX
      </button>
    </div>

    <!-- MENU DE ABAS -->
    <div class="tab-menu">
      <div class="tab active" data-tab="posts">Conexão Solidária</div>
      <div class="tab" data-tab="itens">Itens Aceitos e Não aceitos</div>
      <div class="tab" data-tab="destino">Sua Doação Importa!</div>
      <div class="tab" data-tab="coletas">Coletas Agendadas</div>
    </div>

    <!-- ========== ABA POSTS ========== -->
    <div class="tab-content active" id="posts-tab">
      <div class="section">
        <span>Meus Posts</span>
        <span class="section-count blue"><?= count($posts) ?></span>
      </div>

      <?php if (!empty($posts)): ?>
        <div class="coletas-list">
          <?php foreach ($posts as $post): ?>
            <div class="post-card">
              <h3><?= htmlspecialchars($post['titulo']) ?></h3>
              <div class="post-meta">
                Publicado por <strong><?= htmlspecialchars($post['nome']) ?></strong> •
                <?= date("d/m/Y \à\s H:i", strtotime($post['data_post'])) ?>
              </div>
              <?php if (!empty($post['categoria'])): ?>
                <span class="categoria-badge"><?= htmlspecialchars($post['categoria']) ?></span>
              <?php endif; ?>
              <div class="post-content"><?= nl2br(htmlspecialchars($post['descricao'])) ?></div>
              <?php if (!empty($post['imagem'])): ?>
                <img src="uploads/<?= $post['imagem'] ?>" class="post-image"
                     alt="<?= htmlspecialchars($post['titulo']) ?>" onerror="this.style.display='none'">
              <?php endif; ?>
              <div class="post-acoes">
                <a href="criar_post.php?id=<?= $post['id_post'] ?>" class="btn-editar-post">✏️ Editar</a>
                <button class="btn-excluir-post" onclick="excluirPost(<?= $post['id_post'] ?>)">🗑️ Excluir</button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty">
          <strong>📝 Nenhum post ainda</strong>
          <small>Use o botão "+" para criar seu primeiro post</small>
        </div>
      <?php endif; ?>
    </div>

    <!-- ========== ABA ITENS ========== -->
    <div class="tab-content" id="itens-tab">

      <div class="section">
        <span>✅ Itens Aceitos</span>
        <button class="btn-add-item" onclick="abrirModal('ACEITO')">+ Adicionar</button>
      </div>
      <div class="itens-grid" id="lista-aceitos">
        <?php if (empty($itens_aceitos)): ?>
          <p class="empty-itens" id="empty-aceitos">Nenhum item cadastrado ainda.</p>
        <?php else: ?>
          <?php foreach ($itens_aceitos as $item): ?>
            <div class="item-tag aceito" id="item-<?= $item['id_item'] ?>">
              <?= htmlspecialchars($item['nome']) ?>
              <span class="remove-item" onclick="removerItem(<?= $item['id_item'] ?>, 'ACEITO')">✕</span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div class="section" style="margin-top:16px;">
        <span>❌ Itens Não Aceitos</span>
        <button class="btn-add-item recusado" onclick="abrirModal('RECUSADO')">+ Adicionar</button>
      </div>
      <div class="itens-grid" id="lista-recusados">
        <?php if (empty($itens_recusados)): ?>
          <p class="empty-itens" id="empty-recusados">Nenhum item cadastrado ainda.</p>
        <?php else: ?>
          <?php foreach ($itens_recusados as $item): ?>
            <div class="item-tag recusado" id="item-<?= $item['id_item'] ?>">
              <?= htmlspecialchars($item['nome']) ?>
              <span class="remove-item" onclick="removerItem(<?= $item['id_item'] ?>, 'RECUSADO')">✕</span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

    </div>

    <!-- ========== ABA SUA DOAÇÃO IMPORTA ========== -->
    <div class="tab-content" id="destino-tab">

      <div class="section">
        <span>📢 Para onde vai sua doação</span>
        <button class="btn-add-item" onclick="window.location='criar_destino.php'">+ Publicar</button>
      </div>

      <?php if (empty($destinos)): ?>
        <div class="empty">
          <strong>💛 Nenhuma publicação ainda</strong>
          <small>Conte aos doadores o impacto das contribuições deles!</small>
        </div>
      <?php else: ?>
        <div class="destino-wrapper">
          <button class="destino-nav" id="btnPrev" onclick="navegarDestino(-1)">&#8592;</button>

          <div class="destino-carousel" id="destinoCarousel">
            <?php foreach ($destinos as $i => $d): ?>
              <div class="destino-slide <?= $i === 0 ? 'active' : '' ?>" data-index="<?= $i ?>">
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

        <div class="destino-dots" id="destinoDots">
          <?php foreach ($destinos as $i => $d): ?>
            <span class="dot <?= $i === 0 ? 'active' : '' ?>" onclick="irParaDestino(<?= $i ?>)"></span>
          <?php endforeach; ?>
        </div>

        <div class="destino-counter">
          <span id="destinoAtual">1</span> / <span id="destinoTotal"><?= count($destinos) ?></span>
        </div>
      <?php endif; ?>

    </div>

    <!-- ========== ABA COLETAS ========== -->
    <div class="tab-content" id="coletas-tab">
      <div class="section">
        <span>Coletas Agendadas</span>
        <span class="section-count"><?= count($coletas) ?></span>
      </div>

      <?php if (empty($coletas)): ?>
        <div class="empty">
          <strong>📭 Nenhuma coleta agendada</strong>
          <small>As coletas agendadas pelos doadores aparecerão aqui</small>
        </div>
      <?php else: ?>
        <div class="coletas-list">
          <?php foreach ($coletas as $coleta): ?>
            <div class="coleta-card <?= $coleta['status'] === 'RECEBIDA' ? 'recebida' : '' ?>">
              <div class="coleta-header">
                <div class="coleta-tipo"><?= htmlspecialchars($coleta['tipo_formatado']) ?></div>
                <div class="coleta-data"><?= date('d/m H:i', strtotime($coleta['data_agendada'])) ?></div>
              </div>
              <div class="coleta-doador">👤 Doador: <?= htmlspecialchars($coleta['nome_doador']) ?></div>
              <div class="coleta-local">📍 Local: <?= htmlspecialchars($coleta['local_coleta']) ?></div>
              <?php if (!empty($coleta['descricao_item'])): ?>
                <div class="coleta-descricao"><strong>📦 Itens:</strong> <?= htmlspecialchars($coleta['descricao_item']) ?></div>
              <?php endif; ?>
              <?php if (!empty($coleta['valor'])): ?>
                <div class="coleta-descricao"><strong>💰 Valor:</strong> R$ <?= number_format($coleta['valor'], 2, ',', '.') ?></div>
              <?php endif; ?>
              <div class="coleta-status <?= $coleta['status'] === 'RECEBIDA' ? 'status-recebida' : 'status-agendada' ?>">
                <?= $coleta['status'] === 'RECEBIDA' ? '✅ ' : '📅 ' ?>
                <?= htmlspecialchars($coleta['status_formatado']) ?>
              </div>
              <?php if ($coleta['status'] === 'AGENDADA'): ?>
                <button class="btn-confirmar" onclick="confirmarRecebimento(<?= $coleta['id_doacao'] ?>)">
                  ✅ Confirmar Recebimento
                </button>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  </div>

  <!-- MODAL ADICIONAR ITEM -->
  <div class="modal-overlay" id="modalItem" style="display:none;">
    <div class="modal-box">
      <h3 id="modal-titulo">Adicionar Item</h3>
      <input type="hidden" id="modal-tipo">
      <input type="text" id="modal-input" placeholder="Ex: Roupas limpas" maxlength="100" autocomplete="off">
      <div class="modal-actions">
        <button class="btn-cancelar" onclick="fecharModal()">Cancelar</button>
        <button class="btn-salvar" id="btn-salvar-item" onclick="salvarItem()">Salvar</button>
      </div>
    </div>
  </div>

  <!-- MENU INFERIOR FIXO -->
  <div class="bottom">
    <a href="feed.php" class="menu-item">
      🏠<span>Feed</span>
    </a>
    <a href="campanhas.php" class="menu-item">
      📢
      <span>Campanhas</span>
    </a>
    <button class="plus-btn" onclick="window.location='<?= $rotaPlus ?>'">+</button>
    <a href="notificacoes.php" class="menu-item">
      🔔<span>Notificações</span>
      <?php if ($total_notificacoes > 0): ?>
        <span class="notification-badge" id="notificationBadge"><?= $total_notificacoes ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= $rotaPerfil ?>" class="menu-item" style="color: var(--orange);">
      👤<span>Perfil</span>
    </a>
  </div>

</div>

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// ─── Referência ao elemento .phone para confinar os modais ───────────────────
const phoneEl = document.getElementById('phoneWrapper');

// Tema padrão da ONG para o SweetAlert2
// target: phoneEl → o overlay e o popup ficam dentro do .phone
const swalONG = Swal.mixin({
  target: phoneEl,
  confirmButtonColor: '#f4822f',
  cancelButtonColor: '#aaa',
  customClass: {
    container: 'swal-inside-phone',   // classe para o CSS de confinamento
    popup:     'swal-popup-ong'
  }
});

// ===== VERIFICAR MENSAGEM DE SUCESSO NA URL =====
(function verificarMensagemSucesso() {
    const urlParams = new URLSearchParams(window.location.search);
    const msg = urlParams.get('msg');
    const tipo = urlParams.get('tipo');
    
    if (msg && tipo) {
        // Aguarda o DOM carregar
        setTimeout(() => {
            swalONG.fire({
                title: tipo === 'success' ? 'Sucesso!' : 'Atenção',
                text: decodeURIComponent(msg),
                icon: tipo,
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: true,
                confirmButtonText: 'Ok'
            });
        }, 500);
        
        // Remove os parâmetros da URL sem recarregar a página
        const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
        window.history.replaceState({}, document.title, newUrl);
    }
})();

// ===== SISTEMA DE ABAS =====
document.addEventListener('DOMContentLoaded', function () {
  const tabs = document.querySelectorAll('.tab');
  const tabMenu = document.querySelector('.tab-menu');
  if (tabMenu) tabMenu.scrollLeft = 0;

  tabs.forEach(tab => {
    tab.addEventListener('click', function () {
      tabs.forEach(t => t.classList.remove('active'));
      document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

      this.classList.add('active');
      this.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });

      const tabId = this.getAttribute('data-tab');
      const target = document.getElementById(tabId + '-tab');
      if (target) target.classList.add('active');
    });
  });
});

// ===== NOTIFICAÇÕES =====
async function atualizarNotificacoes() {
  try {
    const response = await fetch('contar_notificacoes.php');
    const data = await response.json();
    const badge = document.getElementById('notificationBadge');
    if (data.total > 0) {
      if (badge) badge.textContent = data.total;
    } else if (badge) {
      badge.remove();
    }
  } catch (error) {
    console.error('Erro ao atualizar notificações:', error);
  }
}
setInterval(atualizarNotificacoes, 30000);
document.addEventListener('DOMContentLoaded', atualizarNotificacoes);
document.body.style.overflow = 'hidden';

// ===== COLETAS =====
async function confirmarRecebimento(idDoacao) {
  const result = await swalONG.fire({
    title: 'Confirmar recebimento?',
    text: 'O doador será notificado e o status mudará para "RECEBIDA".',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: '✅ Confirmar',
    cancelButtonText: 'Cancelar',
  });

  if (result.isConfirmed) {
    window.location.href = 'confirmar_recebimento.php?id=' + idDoacao;
  }
}

// ===== EXCLUIR POST =====
async function excluirPost(idPost) {
  const result = await swalONG.fire({
    title: 'Excluir post?',
    text: 'Esta ação não pode ser desfeita.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: '🗑️ Excluir',
    cancelButtonText: 'Cancelar',
  });

  if (result.isConfirmed) {
    window.location.href = 'excluir_post.php?id=' + idPost;
  }
}

// ===== MODAL ITENS =====
function abrirModal(tipo) {
  document.getElementById('modal-tipo').value = tipo;
  document.getElementById('modal-titulo').textContent =
    tipo === 'ACEITO' ? '✅ Adicionar Item Aceito' : '❌ Adicionar Item Não Aceito';
  document.getElementById('modal-input').value = '';
  document.getElementById('modalItem').style.display = 'flex';
  setTimeout(() => document.getElementById('modal-input').focus(), 100);
}

function fecharModal() {
  document.getElementById('modalItem').style.display = 'none';
}

document.getElementById('modalItem').addEventListener('click', function (e) {
  if (e.target === this) fecharModal();
});

async function salvarItem() {
  const nome = document.getElementById('modal-input').value.trim();
  const tipo = document.getElementById('modal-tipo').value;
  const btn  = document.getElementById('btn-salvar-item');

  if (!nome) {
    document.getElementById('modal-input').focus();
    return;
  }

  btn.disabled = true;
  btn.textContent = 'Salvando...';

  const form = new FormData();
  form.append('acao', 'adicionar');
  form.append('nome', nome);
  form.append('tipo', tipo);

  try {
    const res  = await fetch('gerenciar_item_ong.php', { method: 'POST', body: form });
    const data = await res.json();

    if (data.sucesso) {
      const lista = document.getElementById(tipo === 'ACEITO' ? 'lista-aceitos' : 'lista-recusados');
      const empty = document.getElementById(tipo === 'ACEITO' ? 'empty-aceitos' : 'empty-recusados');
      if (empty) empty.remove();

      const tag = document.createElement('div');
      tag.className = `item-tag ${tipo === 'ACEITO' ? 'aceito' : 'recusado'}`;
      tag.id = `item-${data.id_item}`;
      tag.innerHTML = `${nome} <span class="remove-item" onclick="removerItem(${data.id_item},'${tipo}')">✕</span>`;
      lista.appendChild(tag);
      fecharModal();

      await swalONG.fire({
        title: 'Item adicionado!',
        icon: 'success',
        timer: 1500,
        showConfirmButton: false,
        toast: true,
        position: 'top-end',
      });
    } else {
      await swalONG.fire({
        title: 'Erro ao salvar',
        text: data.erro || 'Tente novamente.',
        icon: 'error',
        confirmButtonText: 'Ok',
      });
    }
  } catch (e) {
    await swalONG.fire({
      title: 'Erro de conexão',
      text: 'Verifique sua conexão e tente novamente.',
      icon: 'error',
      confirmButtonText: 'Ok',
    });
  }

  btn.disabled = false;
  btn.textContent = 'Salvar';
}

async function removerItem(idItem, tipo) {
  const result = await swalONG.fire({
    title: 'Remover item?',
    text: 'Esta ação não pode ser desfeita.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Remover',
    cancelButtonText: 'Cancelar',
  });

  if (!result.isConfirmed) return;

  const form = new FormData();
  form.append('acao', 'remover');
  form.append('id_item', idItem);

  try {
    const res  = await fetch('gerenciar_item_ong.php', { method: 'POST', body: form });
    const data = await res.json();

    if (data.sucesso) {
      const el = document.getElementById(`item-${idItem}`);
      if (el) el.remove();

      const lista = document.getElementById(tipo === 'ACEITO' ? 'lista-aceitos' : 'lista-recusados');
      if (lista && lista.querySelectorAll('.item-tag').length === 0) {
        const p = document.createElement('p');
        p.className = 'empty-itens';
        p.id = tipo === 'ACEITO' ? 'empty-aceitos' : 'empty-recusados';
        p.textContent = 'Nenhum item cadastrado ainda.';
        lista.appendChild(p);
      }

      await swalONG.fire({
        title: 'Item removido!',
        icon: 'success',
        timer: 1500,
        showConfirmButton: false,
        toast: true,
        position: 'top-end',
      });
    } else {
      await swalONG.fire({
        title: 'Erro ao remover',
        text: data.erro || 'Tente novamente.',
        icon: 'error',
        confirmButtonText: 'Ok',
      });
    }
  } catch (e) {
    await swalONG.fire({
      title: 'Erro de conexão',
      text: 'Verifique sua conexão e tente novamente.',
      icon: 'error',
      confirmButtonText: 'Ok',
    });
  }
}

// ===== CARROSSEL SUA DOAÇÃO IMPORTA =====
let destinoAtual = 0;

function navegarDestino(direcao) {
  const slides = document.querySelectorAll('.destino-slide');
  if (!slides.length) return;

  slides[destinoAtual].classList.remove('active');
  document.querySelectorAll('.dot')[destinoAtual]?.classList.remove('active');

  destinoAtual = Math.max(0, Math.min(destinoAtual + direcao, slides.length - 1));

  slides[destinoAtual].classList.add('active');
  document.querySelectorAll('.dot')[destinoAtual]?.classList.add('active');
  document.getElementById('destinoAtual').textContent = destinoAtual + 1;

  document.getElementById('btnPrev').disabled = destinoAtual === 0;
  document.getElementById('btnNext').disabled = destinoAtual === slides.length - 1;
}

function irParaDestino(index) {
  const slides = document.querySelectorAll('.destino-slide');
  if (!slides.length) return;

  slides[destinoAtual].classList.remove('active');
  document.querySelectorAll('.dot')[destinoAtual]?.classList.remove('active');

  destinoAtual = index;

  slides[destinoAtual].classList.add('active');
  document.querySelectorAll('.dot')[destinoAtual]?.classList.add('active');
  document.getElementById('destinoAtual').textContent = destinoAtual + 1;

  document.getElementById('btnPrev').disabled = destinoAtual === 0;
  document.getElementById('btnNext').disabled = destinoAtual === slides.length - 1;
}

document.addEventListener('DOMContentLoaded', function () {
  const slides = document.querySelectorAll('.destino-slide');
  if (slides.length) {
    const prev = document.getElementById('btnPrev');
    const next = document.getElementById('btnNext');
    if (prev) prev.disabled = true;
    if (next) next.disabled = slides.length <= 1;
  }
});
</script>

</body>
</html>