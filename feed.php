<?php
session_start();
require "banco.php";

// Verificar se usuário está logado
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

// Define o comportamento do botão "+" e perfil
$tipoUsuario = $_SESSION["usuario_tipo"] ?? null;

// Definir rotas baseadas no tipo de usuário
if ($tipoUsuario === "instituicao") {
    $acaoPlus = "criar_post.php";
    $rotaPerfil = "perfil-ong.php";
} else {
    $acaoPlus = "agendar_coleta.php";
    $rotaPerfil = "perfil.php";
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Conexão Solidária - Volunteer Community</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/estilo_global.css">
<link rel="stylesheet" href="css/estilo_feed.css">
</head>

<body>

<div class="phone">

  <!-- HEADER -->
  <div class="header">
    <h1>Conexão Solidária</h1>
  </div>

  <div class="feed-container">
    <?php
    try {
        // Buscar todos os posts
        $query = $pdo->query("SELECT p.*, u.nome, u.id_usuario as id_ong FROM posts p 
                              JOIN usuarios u ON p.id_usuario = u.id_usuario
                              ORDER BY p.data_post DESC");

        $posts = $query->fetchAll(PDO::FETCH_ASSOC);

        if (!$posts || count($posts) === 0): ?>
            
            <div class="empty-feed">
              <div>
                <p style="font-size:16px; margin-bottom:8px;">📭</p>
                <p><strong>Nenhuma publicação encontrada</strong></p>
                <p style="font-size:12px; margin-top:8px;">No momento não há publicações</p>
              </div>
            </div>

        <?php else: ?>
        
          <?php foreach ($posts as $post): 
            $descricao = $post['descricao'];
            $textoCurto = strlen($descricao) > 200 ? substr($descricao, 0, 200) . '...' : $descricao;
            $temTextoLongo = strlen($descricao) > 200;
          ?>
            <div class="post-card-solidario">
              <div class="post-header">
                <div class="post-avatar">🤝</div>
                <div class="post-org-info">
                  <h3><?= htmlspecialchars($post['titulo']) ?></h3>
                  <div class="post-meta">
                    Publicado por <strong><?= htmlspecialchars($post['nome']) ?></strong> • 
                    <?= date("d/m/Y \à\s H:i", strtotime($post['data_post'])) ?>
                  </div>
                </div>
              </div>

              <?php if (!empty($post['categoria'])): ?>
                <div class="post-categories">
                  <span class="category-tag"><?= htmlspecialchars($post['categoria']) ?></span>
                </div>
              <?php endif; ?>

              <div class="post-content" id="content-<?= $post['id_post'] ?>">
                <?= nl2br(htmlspecialchars($textoCurto)) ?>
              </div>
              
              <?php if ($temTextoLongo): ?>
                <button class="read-more" onclick="toggleContent(<?= $post['id_post'] ?>, this)">
                  Ler mais
                </button>
              <?php endif; ?>

              <?php if (!empty($post['imagem'])): ?>
                <img src="uploads/<?= $post['imagem'] ?>" 
                     class="post-image" 
                     alt="<?= htmlspecialchars($post['titulo']) ?>"
                     onerror="this.style.display='none'">
              <?php endif; ?>

              <!-- BOTÃO EFETUAR DOAÇÃO - APENAS PARA DOADORES -->
              <?php if ($tipoUsuario === "doador"): ?>
                <button class="doacao-btn" onclick="efetuarDoacao(<?= $post['id_ong'] ?>, '<?= htmlspecialchars(addslashes($post['titulo'])) ?>')">
                  💝 Efetuar Doação para esta ONG
                </button>
              <?php elseif ($tipoUsuario === "instituicao"): ?>
                <button class="doacao-btn" disabled>
                  ⚠️ Apenas doadores podem efetuar doações
                </button>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>

        <?php endif; ?>

    <?php } catch (PDOException $e) { ?>
        <div class="empty-feed">
          <div>
            <p style="font-size:16px; margin-bottom:8px;">⚠️</p>
            <p><strong>Erro ao carregar publicações</strong></p>
            <p style="font-size:12px; margin-top:8px;">Tente recarregar a página</p>
          </div>
        </div>
    <?php } ?>
  </div>

  <!-- MENU INFERIOR FIXO -->
  <div class="bottom">
    <a href="feed.php" class="menu-item active">
      🏠
      <span>Feed</span>
    </a>
    
    <a href="campanhas.php" class="menu-item">
      📢
      <span>Campanhas</span>
    </a>

    <!-- BOTÃO + CENTRAL -->
    <button class="plus-btn" onclick="window.location.href='<?= $acaoPlus ?>'">+</button>

    <a href="notificacoes.php" class="menu-item">
      🔔
      <span>Notificações</span>
    </a>

    <!-- LINK DO PERFIL CORRETO -->
    <a href="<?= $rotaPerfil ?>" class="menu-item">
      👤
      <span>Perfil</span>
    </a>
  </div>
</div>

<script>
// Função para efetuar doação
function efetuarDoacao(idOng, tituloOng) {
    if (confirm(`Deseja efetuar uma doação para a ONG: "${tituloOng}"?`)) {
        window.location.href = `agendar_coleta.php?ong=${idOng}&titulo=${encodeURIComponent(tituloOng)}`;
    }
}

// Função para expandir/recolher conteúdo
function toggleContent(postId, button) {
    const content = document.getElementById('content-' + postId);
    const isExpanded = content.classList.contains('expanded');
    
    if (isExpanded) {
        content.classList.remove('expanded');
        button.textContent = 'Ler mais';
    } else {
        content.classList.add('expanded');
        button.textContent = 'Ler menos';
    }
}

document.body.style.overflow = 'hidden';
</script>

</body>
</html>