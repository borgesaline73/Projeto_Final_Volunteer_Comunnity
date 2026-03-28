<?php
session_start();
require "banco.php";

// Bloqueia acesso sem login
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

// Apenas ONG pode acessar este perfil
$tipo = $_SESSION["usuario_tipo"] ?? null;

if ($tipo !== "instituicao") {
    header("Location: perfil.php");
    exit;
}

$id_ong = $_SESSION["usuario_id"];

// Buscar informações básicas da ONG do banco de dados
try {
    // Buscar apenas nome, email e tipo do usuário
    $sql_ong = "SELECT nome, email, tipo_usuario 
                FROM usuarios 
                WHERE id_usuario = ?";
    $stmt_ong = $pdo->prepare($sql_ong);
    $stmt_ong->execute([$id_ong]);
    $ong = $stmt_ong->fetch(PDO::FETCH_ASSOC);

    if (!$ong) {
        throw new Exception("ONG não encontrada");
    }

    $nome = $ong['nome'] ?? "Instituição";
    $email = $ong['email'] ?? "email@ong.com";
    $tipo_usuario = $ong['tipo_usuario'] ?? "instituicao";

    // Buscar posts da instituição
    $sql_posts = "SELECT p.*, u.nome, u.id_usuario as id_ong 
                  FROM posts p 
                  JOIN usuarios u ON p.id_usuario = u.id_usuario
                  WHERE p.id_usuario = ? 
                  ORDER BY p.data_post DESC";
    $stmt_posts = $pdo->prepare($sql_posts);
    $stmt_posts->execute([$id_ong]);
    $posts = $stmt_posts->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar coletas agendadas para a ONG
    $sql_coletas = "SELECT d.*, u.nome as nome_doador, u.email as email_doador,
                           c.data_agendada, c.endereco as local_coleta,
                           CASE 
                               WHEN d.tipo = 'ITEM' THEN 'Doação de Itens'
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
                    ORDER BY 
                        CASE WHEN d.status = 'AGENDADA' THEN 1 ELSE 2 END,
                        c.data_agendada ASC";
    
    $stmt_coletas = $pdo->prepare($sql_coletas);
    $stmt_coletas->execute([$id_ong]);
    $coletas = $stmt_coletas->fetchAll(PDO::FETCH_ASSOC);
    
    // Contar notificações não lidas
    $sql_notificacoes = "SELECT COUNT(*) as total 
                        FROM notificacoes 
                        WHERE id_usuario = ? AND lida = FALSE";
    $stmt_notificacoes = $pdo->prepare($sql_notificacoes);
    $stmt_notificacoes->execute([$id_ong]);
    $notif_result = $stmt_notificacoes->fetch(PDO::FETCH_ASSOC);
    $total_notificacoes = $notif_result['total'] ?? 0;
    
} catch (PDOException $e) {
    $posts = [];
    $coletas = [];
    $error_db = true;
    $nome = "Erro ao carregar";
    $email = "Erro";
    $tipo_usuario = "instituicao";
    $total_notificacoes = 0;
} catch (Exception $e) {
    $posts = [];
    $coletas = [];
    $error_db = true;
    $nome = "ONG não encontrada";
    $email = "Erro";
    $tipo_usuario = "instituicao";
    $total_notificacoes = 0;
}

// Define rotas
$rotaPlus = "criar_post.php";
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
</head>

<body>

<div class="phone">

  <!-- HEADER -->
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
    </div>

    <!-- MENU DE ABAS -->
    <div class="tab-menu">
      <div class="tab active" data-tab="posts">Conexão Solidária</div>
      <div class="tab" data-tab="coletas">Coletas Agendadas</div>
    </div>

    <!-- ABA POSTS -->
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

              <div class="post-content">
                <?= nl2br(htmlspecialchars($post['descricao'])) ?>
              </div>

              <?php if (!empty($post['imagem'])): ?>
                <img src="uploads/<?= $post['imagem'] ?>" 
                     class="post-image" 
                     alt="<?= htmlspecialchars($post['titulo']) ?>"
                     onerror="this.style.display='none'">
              <?php endif; ?>
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

    <!-- ABA COLETAS -->
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
                <div class="coleta-data">
                  <?= date('d/m H:i', strtotime($coleta['data_agendada'])) ?>
                </div>
              </div>
              
              <div class="coleta-doador">
                👤 Doador: <?= htmlspecialchars($coleta['nome_doador']) ?>
              </div>
              
              <div class="coleta-local">
                📍 Local: <?= htmlspecialchars($coleta['local_coleta']) ?>
              </div>
              
              <?php if (!empty($coleta['descricao_item'])): ?>
                <div class="coleta-descricao">
                  <strong>📦 Itens:</strong> <?= htmlspecialchars($coleta['descricao_item']) ?>
                </div>
              <?php endif; ?>
              
              <?php if (!empty($coleta['valor'])): ?>
                <div class="coleta-descricao">
                  <strong>💰 Valor:</strong> R$ <?= number_format($coleta['valor'], 2, ',', '.') ?>
                </div>
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

  <!-- MENU INFERIOR FIXO -->
  <div class="bottom">
    <a href="feed.php" class="menu-item">
      🏠
      <span>Feed</span>
    </a>
    
    <button class="plus-btn" onclick="window.location='<?= $rotaPlus ?>'">+</button>
    
    <a href="notificacoes.php" class="menu-item">
      🔔
      <span>Notificações</span>
      <?php if ($total_notificacoes > 0): ?>
        <span class="notification-badge" id="notificationBadge"><?= $total_notificacoes ?></span>
      <?php endif; ?>
    </a>
    
    <a href="<?= $rotaPerfil ?>" class="menu-item" style="color: var(--orange);">
      👤
      <span>Perfil</span>
    </a>
  </div>

</div>

<script>
function confirmarRecebimento(idDoacao) {
    if (confirm('Deseja confirmar o recebimento desta doação?\n\nEsta ação notificará o doador e mudará o status para "RECEBIDA".')) {
        window.location.href = 'confirmar_recebimento.php?id=' + idDoacao;
    }
}

// Sistema de abas
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.tab');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            const tabId = this.getAttribute('data-tab');
            document.getElementById(`${tabId}-tab`).classList.add('active');
        });
    });
});

// Função para buscar notificações não lidas
async function atualizarNotificacoes() {
    try {
        const response = await fetch('contar_notificacoes.php');
        const data = await response.json();
        
        const badge = document.getElementById('notificationBadge');
        
        if (data.total > 0) {
            if (badge) {
                badge.textContent = data.total;
            }
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
</script>

</body>
</html>