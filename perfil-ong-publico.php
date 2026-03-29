<?php
session_start();
require "banco.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

$id_visitante     = $_SESSION["usuario_id"];
$tipo_visitante   = $_SESSION["usuario_tipo"] ?? "doador";
$id_ong           = (int)($_GET["id"] ?? 0);

if ($id_ong <= 0) {
    header("Location: feed.php");
    exit;
}

$rotaPerfil = $tipo_visitante === "instituicao" ? "perfil-ong.php" : "perfil.php";
$rotaPlus   = $tipo_visitante === "instituicao" ? "criar_post.php" : "agendar_coleta.php";

try {
    $stmt = $pdo->prepare("SELECT nome, email FROM usuarios WHERE id_usuario = ? AND tipo_usuario = 'instituicao'");
    $stmt->execute([$id_ong]);
    $ong = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ong) {
        header("Location: feed.php");
        exit;
    }

    $nome_ong = $ong['nome'];

    $stmt_posts = $pdo->prepare("SELECT * FROM posts WHERE id_usuario = ? ORDER BY data_post DESC");
    $stmt_posts->execute([$id_ong]);
    $posts = $stmt_posts->fetchAll(PDO::FETCH_ASSOC);

    $stmt_itens = $pdo->prepare("SELECT * FROM itens_ong WHERE id_ong = ? ORDER BY tipo, nome ASC");
    $stmt_itens->execute([$id_ong]);
    $itens = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);
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
</head>
<body>

<div class="phone">

  <!-- HEADER -->
  <div class="header">
    <span onclick="history.back()" style="cursor:pointer;">⬅</span>
    <div class="header-title"><?= htmlspecialchars($nome_ong) ?></div>
    <span></span>
  </div>

  <div class="main-content">

    <!-- CARD PERFIL -->
    <div class="profile-card">
      <div class="avatar">🏢</div>
      <div class="name"><?= htmlspecialchars($nome_ong) ?></div>
      <div class="verified-badge">✅ Usuário Verificado pela Comunidade</div>

      <?php if ($tipo_visitante === 'doador'): ?>
        <a href="agendar_coleta.php?ong=<?= $id_ong ?>" class="btn-doar">
          💛 Quero Doar
        </a>
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
              <button class="btn-doar-post" onclick="efetuarDoacao(<?= $id_ong ?>, '<?= htmlspecialchars(addslashes($nome_ong)) ?>')">
                💝 Efetuar Doação
              </button>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="empty">
          <strong>📝 Nenhuma publicação ainda</strong>
        </div>
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
        <div class="empty">
          <strong>💛 Nenhuma publicação ainda</strong>
        </div>
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

  </div><!-- fim main-content -->

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

<script>
document.body.style.overflow = 'hidden';

// Abas
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

// Doação — mesmo fluxo do feed
function efetuarDoacao(idOng, nomeOng) {
  if (confirm(`Deseja efetuar uma doação para "${nomeOng}"?`)) {
    window.location.href = `agendar_coleta.php?ong=${idOng}&titulo=${encodeURIComponent(nomeOng)}`;
  }
}

// Carrossel
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

function irParaDestino(i) {
  navegarDestino(i - destinoAtual);
}

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