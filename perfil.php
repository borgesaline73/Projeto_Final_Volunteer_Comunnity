<?php
session_start();
require "banco.php";

// Só deixa entrar se estiver logado
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

// Se for instituição, redireciona para perfil-ong.php
$tipo = $_SESSION["usuario_tipo"] ?? null;

if ($tipo === "instituicao") {
    header("Location: perfil-ong.php");
    exit;
}

// Se chegou aqui, é DOADOR
$id_doador = $_SESSION["usuario_id"];

// Buscar informações básicas do doador do banco de dados
try {
    // Buscar apenas nome, email e tipo do usuário
    $sql_doador = "SELECT nome, email, tipo_usuario 
                   FROM usuarios 
                   WHERE id_usuario = ?";
    $stmt_doador = $pdo->prepare($sql_doador);
    $stmt_doador->execute([$id_doador]);
    $doador = $stmt_doador->fetch(PDO::FETCH_ASSOC);

    if (!$doador) {
        throw new Exception("Doador não encontrado");
    }

    $nome = $doador['nome'] ?? "Usuário";
    $email = $doador['email'] ?? "email@exemplo.com";
    $tipo_usuario = $doador['tipo_usuario'] ?? "doador";

    // Buscar ID do doador na tabela doadores
    $stmt_doador_id = $pdo->prepare("SELECT id_doador FROM doadores WHERE id_doador = ?");
    $stmt_doador_id->execute([$id_doador]);
    $doador_info = $stmt_doador_id->fetch(PDO::FETCH_ASSOC);
    $id_doador_table = $doador_info['id_doador'] ?? null;

    // Buscar coletas do doador
    $coletas_agendadas = [];
    $coletas_recebidas = [];

    if ($id_doador_table) {
        // Buscar todas as coletas do doador
        $sql_coletas = "SELECT d.*, c.data_agendada, c.endereco as local_coleta, 
                               u.nome as nome_ong, u.email as email_ong,
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
                        LEFT JOIN coletas c ON d.id_doacao = c.id_doacao
                        LEFT JOIN usuarios u ON d.id_ong = u.id_usuario
                        WHERE d.id_doador = ? 
                        ORDER BY 
                            CASE WHEN d.status = 'AGENDADA' THEN 1 ELSE 2 END,
                            c.data_agendada DESC";
        
        $stmt_coletas = $pdo->prepare($sql_coletas);
        $stmt_coletas->execute([$id_doador_table]);
        $todas_coletas = $stmt_coletas->fetchAll(PDO::FETCH_ASSOC);

        // Separar por status
        $coletas_agendadas = array_filter($todas_coletas, function($coleta) {
            return $coleta['status'] === 'AGENDADA';
        });
        
        $coletas_recebidas = array_filter($todas_coletas, function($coleta) {
            return $coleta['status'] === 'RECEBIDA';
        });
    }

    // Contar notificações não lidas
    $sql_notificacoes = "SELECT COUNT(*) as total 
                        FROM notificacoes 
                        WHERE id_usuario = ? AND lida = FALSE";
    $stmt_notificacoes = $pdo->prepare($sql_notificacoes);
    $stmt_notificacoes->execute([$id_doador]);
    $notif_result = $stmt_notificacoes->fetch(PDO::FETCH_ASSOC);
    $total_notificacoes = $notif_result['total'] ?? 0;

} catch (PDOException $e) {
    $coletas_agendadas = [];
    $coletas_recebidas = [];
    $total_notificacoes = 0;
    $nome = "Erro ao carregar";
    $email = "Erro";
    $tipo_usuario = "doador";
    error_log("Erro ao buscar coletas: " . $e->getMessage());
} catch (Exception $e) {
    $coletas_agendadas = [];
    $coletas_recebidas = [];
    $total_notificacoes = 0;
    $nome = "Doador não encontrado";
    $email = "Erro";
    $tipo_usuario = "doador";
}

// Define rota do botão + para doador
$rotaPlus = "agendar_coleta.php";
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Perfil - Volunteer Community</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/estilo_global.css">
<link rel="stylesheet" href="css/estilo_perfil_doador.css">
</head>

<body>

<div class="phone">

  <!-- HEADER -->
  <div class="header">
    <span>🔔</span>
    <span onclick="window.location='logout.php'">🚪</span>
  </div>

  <!-- ÁREA PRINCIPAL COM SCROLL -->
  <div class="main-content">
    <!-- CARD PERFIL DO DOADOR -->
    <div class="profile-card">
      <div class="avatar">👤</div>

      <div class="name"><?= htmlspecialchars($nome) ?></div>

      <div class="info-item"><strong>Email:</strong> <?= htmlspecialchars($email) ?></div>
      <div class="info-item"><strong>Tipo de Conta:</strong> 
        <?= $tipo_usuario === 'doador' ? 'Doador' : htmlspecialchars($tipo_usuario) ?>
      </div>
    </div>

    <!-- COLETAS AGENDADAS -->
    <?php if (!empty($coletas_agendadas)): ?>
    <div class="section">
      <span>📅 Coletas Agendadas</span>
      <span class="section-count"><?= count($coletas_agendadas) ?></span>
    </div>

    <div class="coletas-list">
      <?php foreach ($coletas_agendadas as $coleta): ?>
        <div class="coleta-card">
          <div class="coleta-header">
            <div class="coleta-tipo"><?= htmlspecialchars($coleta['tipo_formatado']) ?></div>
            <div class="coleta-data">
              <?= date('d/m H:i', strtotime($coleta['data_agendada'])) ?>
            </div>
          </div>
          
          <?php if (!empty($coleta['nome_ong'])): ?>
            <div class="coleta-ong">
              🏢 ONG: <?= htmlspecialchars($coleta['nome_ong']) ?>
            </div>
          <?php else: ?>
            <div class="coleta-ong">
              📍 Doação geral
            </div>
          <?php endif; ?>
          
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
          
          <div class="coleta-status status-agendada">
            📅 <?= htmlspecialchars($coleta['status_formatado']) ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- COLETAS RECEBIDAS -->
    <?php if (!empty($coletas_recebidas)): ?>
    <div class="section">
      <span>✅ Coletas Recebidas</span>
      <span class="section-count green"><?= count($coletas_recebidas) ?></span>
    </div>

    <div class="coletas-list">
      <?php foreach ($coletas_recebidas as $coleta): ?>
        <div class="coleta-card recebida">
          <div class="coleta-header">
            <div class="coleta-tipo"><?= htmlspecialchars($coleta['tipo_formatado']) ?></div>
            <div class="coleta-data">
              <?= date('d/m/Y', strtotime($coleta['data_doacao'])) ?>
            </div>
          </div>
          
          <?php if (!empty($coleta['nome_ong'])): ?>
            <div class="coleta-ong">
              ✅ ONG: <?= htmlspecialchars($coleta['nome_ong']) ?>
            </div>
          <?php else: ?>
            <div class="coleta-ong">
              ✅ Doação geral
            </div>
          <?php endif; ?>
          
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
          
          <div class="coleta-status status-recebida">
            ✅ <?= htmlspecialchars($coleta['status_formatado']) ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- MENSAGEM VAZIA -->
    <?php if (empty($coletas_agendadas) && empty($coletas_recebidas)): ?>
      <div class="empty">
        <strong>📭 Nenhuma coleta ainda</strong>
        <small>Agende sua primeira coleta usando o botão "+"</small>
      </div>
    <?php endif; ?>
  </div>

  <!-- MENU FIXO NO RODAPÉ COM BOTÃO + -->
  <div class="bottom">
    <a href="feed.php" class="menu-item">
      🏠
      <span>Feed</span>
    </a>
    
    <button class="plus-btn" onclick="window.location.href='<?= $rotaPlus ?>'">+</button>
    
    <a href="notificacoes.php" class="menu-item">
      🔔
      <span>Notificações</span>
      <?php if ($total_notificacoes > 0): ?>
        <span class="notification-badge" id="notificationBadge"><?= $total_notificacoes ?></span>
      <?php endif; ?>
    </a>
    
    <a href="perfil.php" class="menu-item active">
      👤
      <span>Perfil</span>
    </a>
  </div>

</div>

<script>
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

// Atualizar a cada 30 segundos
setInterval(atualizarNotificacoes, 30000);

// Atualizar imediatamente ao carregar a página
document.addEventListener('DOMContentLoaded', atualizarNotificacoes);

// Prevenir scroll do body
document.body.style.overflow = 'hidden';
</script>

</body>
</html>