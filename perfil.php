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
$rotaPerfil = "perfil.php";
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

<!-- SweetAlert2 -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

<style>
  /* ===== ESTILO PARA AS ABAS (MESMO DO PERFIL DA ONG) ===== */
  .phone {
    position: relative;
    overflow: hidden;
  }

  /* Menu de abas  */
  .tab-menu {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    padding: 12px 20px 4px;
    scrollbar-width: none;
    margin: 0;
    background: transparent;
  }

  .tab-menu::-webkit-scrollbar {
    display: none;
  }

  .tab {
    flex-shrink: 0;
    padding: 8px 20px;
    border-radius: 30px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    border: 1.5px solid #e0e0e0;
    background: #fff;
    color: #888;
    transition: all 0.2s ease;
    white-space: nowrap;
    font-family: 'Poppins', sans-serif;
  }

  .tab:hover:not(.active) {
    background: #f5f5f5;
    color: #666;
    border-color: #ccc;
  }

  .tab.active {
    background: var(--orange, #f4822f);
    border-color: var(--orange, #f4822f);
    color: #fff;
  }

  .tab-content {
    display: none;
    padding: 0 20px 20px;
  }

  .tab-content.active {
    display: block;
  }

  /* Overlay do SweetAlert fica dentro do .phone */
  .swal2-container.swal-inside-phone {
    position: absolute !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    height: 100% !important;
    z-index: 9999;
  }

  .swal2-container.swal-inside-phone .swal2-popup {
    width: 88% !important;
    max-width: 320px !important;
    border-radius: 20px !important;
    font-family: 'Poppins', sans-serif !important;
  }

  /* Estilo para o contador de itens na aba */
  .tab-count {
    display: inline-block;
    background: rgba(0,0,0,0.1);
    border-radius: 20px;
    padding: 2px 8px;
    font-size: 11px;
    margin-left: 8px;
    font-weight: 500;
  }

  .tab.active .tab-count {
    background: rgba(255,255,255,0.25);
    color: white;
  }
</style>
</head>

<body>

<div class="phone" id="phoneWrapper">

  
  <div class="header">
    <span onclick="history.back()" style="cursor:pointer;">⬅</span>
    <div class="header-title">Meu Perfil</div>
    <span style="cursor:pointer;" onclick="window.location='logout.php'">🚪</span>
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

    <!-- MENU DE ABAS (MESMO ESTILO DO PERFIL DA ONG) -->
    <div class="tab-menu">
      <div class="tab active" data-tab="agendadas">
        📅 Agendadas
        <span class="tab-count"><?= count($coletas_agendadas) ?></span>
      </div>
      <div class="tab" data-tab="recebidas">
        ✅ Recebidas
        <span class="tab-count"><?= count($coletas_recebidas) ?></span>
      </div>
    </div>

    <!-- ========== ABA COLETAS AGENDADAS ========== -->
    <div class="tab-content active" id="agendadas-tab">
      <?php if (!empty($coletas_agendadas)): ?>
        <div class="coletas-list">
          <?php foreach ($coletas_agendadas as $coleta): ?>
            <div class="coleta-card">
              <div class="coleta-header">
                <div class="coleta-tipo"><?= htmlspecialchars($coleta['tipo_formatado']) ?></div>
                <div class="coleta-data">
                  <?= date('d/m/Y H:i', strtotime($coleta['data_agendada'])) ?>
                </div>
              </div>
              
              <?php if (!empty($coleta['nome_ong'])): ?>
                <div class="coleta-ong">
                  🏢 <strong>ONG:</strong> <?= htmlspecialchars($coleta['nome_ong']) ?>
                </div>
              <?php else: ?>
                <div class="coleta-ong">
                  📍 Doação geral
                </div>
              <?php endif; ?>
              
              <div class="coleta-local">
                📍 <strong>Local:</strong> <?= htmlspecialchars($coleta['local_coleta']) ?>
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
      <?php else: ?>
        <div class="empty">
          <strong>📭 Nenhuma coleta agendada</strong>
          <small>Clique no botão "+" para agendar sua primeira coleta</small>
        </div>
      <?php endif; ?>
    </div>

    <!-- ========== ABA COLETAS RECEBIDAS ========== -->
    <div class="tab-content" id="recebidas-tab">
      <?php if (!empty($coletas_recebidas)): ?>
        <div class="coletas-list">
          <?php foreach ($coletas_recebidas as $coleta): ?>
            <div class="coleta-card recebida">
              <div class="coleta-header">
                <div class="coleta-tipo"><?= htmlspecialchars($coleta['tipo_formatado']) ?></div>
                <div class="coleta-data">
                  <?= date('d/m/Y', strtotime($coleta['data_doacao'] ?? $coleta['data_agendada'])) ?>
                </div>
              </div>
              
              <?php if (!empty($coleta['nome_ong'])): ?>
                <div class="coleta-ong">
                  ✅ <strong>ONG:</strong> <?= htmlspecialchars($coleta['nome_ong']) ?>
                </div>
              <?php else: ?>
                <div class="coleta-ong">
                  ✅ Doação geral
                </div>
              <?php endif; ?>
              
              <div class="coleta-local">
                📍 <strong>Local:</strong> <?= htmlspecialchars($coleta['local_coleta']) ?>
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
      <?php else: ?>
        <div class="empty">
          <strong>📭 Nenhuma coleta recebida ainda</strong>
          <small>Suas coletas aparecerão aqui após a confirmação da ONG</small>
        </div>
      <?php endif; ?>
    </div>

  </div>

  <!-- MENU FIXO NO RODAPÉ COM BOTÃO + -->
  <div class="bottom">
    <a href="feed.php" class="menu-item">
      🏠
      <span>Feed</span>
    </a>
    <a href="campanhas.php" class="menu-item">
      📢
      <span>Campanhas</span>
    </a>
    
    <button class="plus-btn" onclick="window.location.href='<?= $rotaPlus ?>'">+</button>
    
    <a href="notificacoes.php" class="menu-item">
      🔔
      <span>Notificações</span>
      <?php if ($total_notificacoes > 0): ?>
        <span class="notification-badge" id="notificationBadge"><?= $total_notificacoes ?></span>
      <?php endif; ?>
    </a>
    
    <a href="<?= $rotaPerfil ?>" class="menu-item active">
      👤
      <span>Perfil</span>
    </a>
  </div>

</div>

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// ─── Referência ao elemento .phone para confinar os modais ───────────────────
const phoneEl = document.getElementById('phoneWrapper');

// Tema padrão para o SweetAlert2
const swalDoador = Swal.mixin({
  target: phoneEl,
  confirmButtonColor: '#f4822f',
  cancelButtonColor: '#aaa',
  customClass: {
    container: 'swal-inside-phone',
    popup: 'swal-popup-doador'
  }
});

// ===== VERIFICAR MENSAGEM DE SUCESSO NA URL =====
(function verificarMensagemSucesso() {
    const urlParams = new URLSearchParams(window.location.search);
    const msg = urlParams.get('msg');
    const tipo = urlParams.get('tipo');
    
    if (msg && tipo) {
        setTimeout(() => {
            swalDoador.fire({
                title: tipo === 'success' ? 'Sucesso!' : 'Atenção',
                text: decodeURIComponent(msg),
                icon: tipo,
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: true,
                confirmButtonText: 'Ok'
            });
        }, 500);
        
        const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
        window.history.replaceState({}, document.title, newUrl);
    }
})();

// ===== SISTEMA DE ABAS (IGUAL DA ONG) =====
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
document.addEventListener('DOMContentLoaded', atualizarNotificacoes);

// Prevenir scroll do body
document.body.style.overflow = 'hidden';
</script>

</body>
</html>