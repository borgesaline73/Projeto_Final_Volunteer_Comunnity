<?php
session_start();
require "banco.php";

// Protege a página
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

// Verifica se é doador
if ($_SESSION["usuario_tipo"] === "instituicao") {
    header("Location: perfil-ong.php");
    exit;
}

$id_doador = $_SESSION["usuario_id"];
$nome = $_SESSION["usuario_nome"] ?? "Usuário";

// Buscar pontos do doador
try {
    // Primeiro, buscar ID do doador na tabela doadores
    $stmt_doador = $pdo->prepare("SELECT id_doador FROM doadores WHERE id_doador = ?");
    $stmt_doador->execute([$id_doador]);
    $doador_info = $stmt_doador->fetch(PDO::FETCH_ASSOC);
    
    $id_doador_table = $doador_info['id_doador'] ?? null;
    
    if ($id_doador_table) {
        // Contar doações recebidas para calcular pontos (10 pontos por doação)
        $sql_pontos = "SELECT COUNT(*) as total FROM doacoes WHERE id_doador = ? AND status = 'RECEBIDA'";
        $stmt_pontos = $pdo->prepare($sql_pontos);
        $stmt_pontos->execute([$id_doador_table]);
        $result = $stmt_pontos->fetch(PDO::FETCH_ASSOC);
        $total_doacoes = $result['total'];
        $pontos = $total_doacoes * 10;
    } else {
        $pontos = 0;
    }
    
    // Buscar recompensas disponíveis
    $sql_recompensas = "SELECT * FROM recompensas ORDER BY pontos_necessarios ASC";
    $stmt_recompensas = $pdo->query($sql_recompensas);
    $recompensas = $stmt_recompensas->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar trocas do usuário
    $trocas_usuario = [];
    if ($id_doador_table) {
        $sql_trocas = "SELECT t.*, r.descricao, r.pontos_necessarios 
                      FROM trocas_pontos t 
                      JOIN recompensas r ON t.id_recompensa = r.id_recompensa 
                      WHERE t.id_doador = ? 
                      ORDER BY t.data_troca DESC";
        $stmt_trocas = $pdo->prepare($sql_trocas);
        $stmt_trocas->execute([$id_doador_table]);
        $trocas_usuario = $stmt_trocas->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    $recompensas = [];
    $trocas_usuario = [];
    $pontos = 0;
    $error_db = true;
}

// Definir rota do botão + para doador
$rotaPlus = "agendar_coleta.php";
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Recompensas - Volunteer Community</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

<style>
:root{
  --orange:#f4822f;
  --green:#28a745;
  --blue:#3498db;
  --bg:#f6f4f2;
  --text:#2b2b2b;
  --muted:#8b8b8b;
}

*{
  font-family:"Poppins", sans-serif;
  box-sizing:border-box;
}

body{
  margin:0;
  background:var(--bg);
  display:flex;
  justify-content:center;
  align-items:center;
  min-height:100vh;
  padding:20px;
}

.phone{
  width:100%;
  max-width:430px;
  background:#fff;
  height:90vh;
  max-height:800px;
  border-radius:32px;
  box-shadow:0 10px 40px rgba(0,0,0,0.06);
  display:flex;
  flex-direction:column;
  position:relative;
  overflow:hidden;
}

/* HEADER */
.header{
  padding:20px;
  display:flex;
  justify-content:space-between;
  align-items:center;
  font-weight:700;
  color:var(--orange);
  flex-shrink:0;
}

.pontos-badge{
  background:var(--orange);
  color:white;
  padding:6px 12px;
  border-radius:20px;
  font-size:14px;
  display:flex;
  align-items:center;
  gap:5px;
}

/* MENU DE ABAS */
.tab-menu {
  display: flex;
  background-color: #34495e;
  margin:0 20px;
  border-radius: 12px 12px 0 0;
  overflow: hidden;
  flex-shrink:0;
}

.tab {
  padding: 15px 20px;
  color: white;
  cursor: pointer;
  transition: background-color 0.3s;
  flex: 1;
  text-align: center;
  font-weight: 500;
  font-size: 14px;
}

.tab:hover {
  background-color: #3d566e;
}

.tab.active {
  background-color: var(--orange);
  font-weight: 600;
}

.tab-content {
  display: none;
  padding: 0 20px;
  overflow-y:auto;
  flex:1;
}

.tab-content.active {
  display: block;
}

/* SEÇÕES */
.section{
  margin:20px 0 10px;
  font-weight:700;
  font-size:16px;
  color:#333;
  display:flex;
  justify-content:space-between;
  align-items:center;
}

.section-count{
  background:var(--orange);
  color:white;
  padding:2px 8px;
  border-radius:12px;
  font-size:12px;
}

/* LISTA DE RECOMPENSAS */
.recompensas-list{
  padding-bottom:20px;
}

.recompensa-card{
  background:#fff;
  border-radius:16px;
  padding:16px;
  margin-bottom:12px;
  box-shadow:0 4px 12px rgba(0,0,0,0.08);
  border-left:4px solid var(--orange);
  position:relative;
}

.recompensa-disabled{
  opacity:0.7;
  border-left-color:#ccc;
}

.recompensa-header{
  display:flex;
  justify-content:space-between;
  align-items:flex-start;
  margin-bottom:8px;
}

.recompensa-descricao{
  font-weight:600;
  color:#333;
  font-size:14px;
}

.recompensa-pontos{
  font-size:12px;
  color:#888;
  background:#f5f5f5;
  padding:4px 8px;
  border-radius:8px;
  font-weight:600;
}

.recompensa-info{
  font-size:13px;
  color:#666;
  margin:6px 0;
  line-height:1.4;
}

.btn-resgatar{
  background:var(--green);
  color:white;
  border:none;
  padding:8px 16px;
  border-radius:20px;
  font-size:12px;
  font-weight:600;
  cursor:pointer;
  margin-top:8px;
  transition:background 0.3s;
  width:100%;
}

.btn-resgatar:hover{
  background:#218838;
}

.btn-resgatar:disabled{
  background:#ccc;
  cursor:not-allowed;
}

.btn-resgatado{
  background:var(--blue);
  color:white;
  border:none;
  padding:8px 16px;
  border-radius:20px;
  font-size:12px;
  font-weight:600;
  width:100%;
  cursor:default;
}

/* MINHAS TROCAS */
.troca-card{
  background:#fff;
  border-radius:16px;
  padding:16px;
  margin-bottom:12px;
  box-shadow:0 4px 12px rgba(0,0,0,0.05);
  border-left:4px solid var(--blue);
}

.troca-header{
  display:flex;
  justify-content:space-between;
  align-items:flex-start;
  margin-bottom:8px;
}

.troca-descricao{
  font-weight:600;
  color:#333;
  font-size:14px;
}

.troca-data{
  font-size:11px;
  color:#888;
  background:#f5f5f5;
  padding:4px 8px;
  border-radius:8px;
}

.troca-pontos{
  font-size:13px;
  color:#666;
  margin-top:6px;
  font-weight:600;
}

.empty{
  background:linear-gradient(0deg,#f0f0f0,#fafafa);
  padding:50px 20px;
  border-radius:16px;
  text-align:center;
  color:#888;
  margin:20px 0;
  font-size:14px;
}

/* MENU FIXO NO RODAPÉ COM BOTÃO + */
.bottom{
  height:74px;
  border-top:1px solid #eee;
  display:flex;
  justify-content:space-around;
  align-items:center;
  background:#fff;
  position:absolute;
  bottom:0;
  left:0;
  right:0;
  z-index:100;
}

.menu-item{
  display:flex;
  flex-direction:column;
  align-items:center;
  text-decoration:none;
  font-size:11px;
  color:#aaa;
  text-align:center;
  transition:color 0.3s;
}

.menu-item:hover{
  color:var(--orange);
}

.menu-item.active{
  color:var(--orange);
  font-weight:600;
}

.plus-btn{
  width:52px;
  height:52px;
  border-radius:50%;
  background:var(--orange);
  border:none;
  color:#fff;
  font-size:28px;
  margin-top:-30px;
  box-shadow:0 6px 20px rgba(0,0,0,0.15);
  cursor:pointer;
  transition:transform 0.3s, background 0.3s;
}

.plus-btn:hover{
  background:#e67329;
  transform:scale(1.05);
}

/* ÁREA DE CONTEÚDO PRINCIPAL COM SCROLL */
.main-content {
  flex: 1;
  overflow-y: auto;
  padding-bottom: 80px; /* Espaço para o menu fixo */
}
</style>
</head>

<body>

<div class="phone">

  <!-- HEADER -->
  <div class="header">
    <span>Recompensas</span>
    <div class="pontos-badge">⭐ <?= $pontos ?> pontos</div>
  </div>

  <!-- MENU DE ABAS -->
  <div class="tab-menu">
    <div class="tab active" data-tab="disponiveis">Disponíveis</div>
    <div class="tab" data-tab="minhas">Minhas Trocas</div>
  </div>

  <!-- ÁREA PRINCIPAL COM SCROLL -->
  <div class="main-content">
    <!-- ABA RECOMPENSAS DISPONÍVEIS -->
    <div class="tab-content active" id="disponiveis-tab">
      <div class="section">
        <span>Recompensas Disponíveis</span>
        <span class="section-count"><?= count($recompensas) ?></span>
      </div>

      <?php if (!empty($recompensas)): ?>
        <div class="recompensas-list">
          <?php foreach ($recompensas as $recompensa): 
            $podeResgatar = $pontos >= $recompensa['pontos_necessarios'];
            $jaResgatada = false;
            
            // Verificar se já resgatou esta recompensa
            foreach ($trocas_usuario as $troca) {
              if ($troca['id_recompensa'] == $recompensa['id_recompensa']) {
                $jaResgatada = true;
                break;
              }
            }
          ?>
            <div class="recompensa-card <?= !$podeResgatar ? 'recompensa-disabled' : '' ?>">
              <div class="recompensa-header">
                <div class="recompensa-descricao"><?= htmlspecialchars($recompensa['descricao']) ?></div>
                <div class="recompensa-pontos"><?= $recompensa['pontos_necessarios'] ?> pontos</div>
              </div>
              
              <div class="recompensa-info">
                <?php if ($podeResgatar): ?>
                  <div style="color: var(--green); font-weight: 600;">✅ Você tem pontos suficientes!</div>
                <?php else: ?>
                  <div style="color: #f44336;">⚠️ Faltam <?= $recompensa['pontos_necessarios'] - $pontos ?> pontos</div>
                <?php endif; ?>
              </div>
              
              <?php if ($jaResgatada): ?>
                <button class="btn-resgatado">✅ Já resgatada</button>
              <?php elseif ($podeResgatar): ?>
                <button class="btn-resgatar" onclick="resgatarRecompensa(<?= $recompensa['id_recompensa'] ?>, <?= $recompensa['pontos_necessarios'] ?>, '<?= htmlspecialchars(addslashes($recompensa['descricao'])) ?>')">
                  🎁 Resgatar Recompensa
                </button>
              <?php else: ?>
                <button class="btn-resgatar" disabled>
                  🔒 Pontos insuficientes
                </button>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty">
          Nenhuma recompensa cadastrada no momento.<br>
          <small>Novas recompensas serão adicionadas em breve!</small>
        </div>
      <?php endif; ?>
    </div>

    <!-- ABA MINHAS TROCAS -->
    <div class="tab-content" id="minhas-tab">
      <div class="section">
        <span>Minhas Trocas</span>
        <span class="section-count"><?= count($trocas_usuario) ?></span>
      </div>

      <?php if (!empty($trocas_usuario)): ?>
        <div class="recompensas-list">
          <?php foreach ($trocas_usuario as $troca): ?>
            <div class="troca-card">
              <div class="troca-header">
                <div class="troca-descricao"><?= htmlspecialchars($troca['descricao']) ?></div>
                <div class="troca-data"><?= date('d/m/Y', strtotime($troca['data_troca'])) ?></div>
              </div>
              
              <div class="troca-pontos">
                ⭐ Custo: <?= $troca['pontos_necessarios'] ?> pontos
              </div>
              
              <div style="font-size: 12px; color: #888; margin-top: 6px;">
                ID da troca: #<?= str_pad($troca['id_troca'], 6, '0', STR_PAD_LEFT) ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty">
          Você ainda não resgatou nenhuma recompensa.<br>
          <small>Resgate sua primeira recompensa usando seus pontos!</small>
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
    
    <a href="ranking.php" class="menu-item">
      🏆
      <span>Ranking</span>
    </a>
    
    <!-- BOTÃO + CENTRAL -->
    <button class="plus-btn" onclick="window.location.href='<?= $rotaPlus ?>'">+</button>
    
    <a href="notificacoes.php" class="menu-item">
      🔔
      <span>Notificações</span>
    </a>
    
    <a href="recompensas.php" class="menu-item active">
      🎁
      <span>Recompensas</span>
    </a>
  </div>

</div>

<script>
function resgatarRecompensa(idRecompensa, pontos, descricao) {
    if (confirm(`Deseja resgatar esta recompensa?\n\n"${descricao}"\n\nCusto: ${pontos} pontos\n\nEsta ação não pode ser desfeita.`)) {
        // Enviar requisição AJAX para resgatar
        fetch('resgatar_recompensa.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id_recompensa=${idRecompensa}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ Recompensa resgatada com sucesso!');
                location.reload();
            } else {
                alert('❌ Erro ao resgatar: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('❌ Erro ao conectar com o servidor');
        });
    }
}

// Sistema de abas
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.tab');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            // Remove active class from all tabs
            tabs.forEach(t => t.classList.remove('active'));
            
            // Add active class to clicked tab
            this.classList.add('active');
            
            // Hide all tab content
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Show the corresponding tab content
            const tabId = this.getAttribute('data-tab');
            document.getElementById(`${tabId}-tab`).classList.add('active');
        });
    });
});

// Prevenir scroll do body
document.body.style.overflow = 'hidden';
</script>

</body>
</html>