<?php
session_start();

// Só deixa entrar se estiver logado
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

// Só DOADOR pode agendar coleta
if (($_SESSION["usuario_tipo"] ?? "") !== "doador") {
    header("Location: feed.php");
    exit;
}

require "banco.php";

$nome = $_SESSION["usuario_nome"] ?? "Usuário";
$id_doador = $_SESSION["usuario_id"];

// Verificar se veio de um post específico (com ID da ONG)
$id_ong = $_GET['ong'] ?? null;
$titulo_ong = $_GET['titulo'] ?? '';

// Mostrar mensagem de sucesso se existir
$mensagem_sucesso = '';
if (isset($_SESSION['agendamento_sucesso'])) {
    $mensagem_sucesso = $_SESSION['agendamento_sucesso'];
    unset($_SESSION['agendamento_sucesso']);
}

// Buscar todas as ONGs cadastradas 
$ongs = [];
try {
    // Verificar primeiro se a coluna categoria existe
    $stmt_check = $pdo->query("SELECT column_name FROM information_schema.columns 
                               WHERE table_name = 'ongs' AND column_name = 'categoria'");
    $categoria_exists = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if ($categoria_exists) {
        // Se a coluna categoria existe, usar a consulta completa
        $stmt = $pdo->prepare("SELECT 
                u.id_usuario, 
                u.nome, 
                u.email, 
                u.cpf_cnpj, 
                o.endereco, 
                o.descricao, 
                o.categoria 
            FROM usuarios u 
            LEFT JOIN ongs o ON u.id_usuario = o.id_ong 
            WHERE u.tipo_usuario = 'instituicao' 
            ORDER BY u.nome ASC");
    } else {
        // Se não existe, buscar sem a coluna categoria
        $stmt = $pdo->prepare("SELECT 
                u.id_usuario, 
                u.nome, 
                u.email, 
                u.cpf_cnpj, 
                o.endereco, 
                o.descricao
            FROM usuarios u 
            LEFT JOIN ongs o ON u.id_usuario = o.id_ong 
            WHERE u.tipo_usuario = 'instituicao' 
            ORDER BY u.nome ASC");
    }
    
    $stmt->execute();
    $ongs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("ERRO ao buscar ONGs: " . $e->getMessage());
    // consulta mais simples em caso de erro
    try {
        $stmt_simple = $pdo->prepare("SELECT 
                u.id_usuario, 
                u.nome, 
                u.email, 
                u.cpf_cnpj
            FROM usuarios u 
            WHERE u.tipo_usuario = 'instituicao' 
            ORDER BY u.nome ASC");
        $stmt_simple->execute();
        $ongs = $stmt_simple->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {
        error_log("ERRO na consulta simples: " . $e2->getMessage());
    }
}

// Buscar informações da ONG específica se foi informada
$ong_selecionada = null;
if ($id_ong) {
    try {
        $stmt = $pdo->prepare("SELECT u.nome, u.email, u.cpf_cnpj, 
                                      o.endereco, o.id_ong, o.descricao
                              FROM usuarios u 
                              LEFT JOIN ongs o ON u.id_usuario = o.id_ong 
                              WHERE u.id_usuario = ? AND u.tipo_usuario = 'instituicao'");
        $stmt->execute([$id_ong]);
        $ong_selecionada = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao buscar ONG específica: " . $e->getMessage());
    }
}

// Buscar ID do doador na tabela doadores
$id_doador_table = null;
try {
    $stmt = $pdo->prepare("SELECT id_doador FROM doadores WHERE id_doador = ?");
    $stmt->execute([$id_doador]);
    $doador_info = $stmt->fetch(PDO::FETCH_ASSOC);
    $id_doador_table = $doador_info['id_doador'] ?? null;
} catch (PDOException $e) {
    error_log("Erro ao buscar doador: " . $e->getMessage());
}

// Variáveis para o formulário
$data_selecionada = $_POST['data_coleta'] ?? '';
$horario_selecionado = $_POST['horario'] ?? '';
$local_selecionado = $_POST['local_coleta'] ?? '';
$tipo_doacao = $_POST['tipo_doacao'] ?? 'ITEM';
$descricao_item = $_POST['descricao_item'] ?? '';
$valor_doacao = $_POST['valor_doacao'] ?? '';
$ong_escolhida = $_POST['ong_escolhida'] ?? $id_ong ?? '';
$buscar_ong = $_POST['buscar_ong'] ?? '';
$mensagem = '';

// Processar o agendamento
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['escolher_ong'])) {
        // Usuário está escolhendo uma ONG
        $ong_escolhida = $_POST['ong_escolhida'] ?? '';
        if ($ong_escolhida) {
            // Buscar informações da ONG escolhida
            try {
                $stmt = $pdo->prepare("SELECT u.nome, u.email, u.cpf_cnpj, 
                                              o.endereco, o.id_ong, o.descricao
                                      FROM usuarios u 
                                      LEFT JOIN ongs o ON u.id_usuario = o.id_ong 
                                      WHERE u.id_usuario = ?");
                $stmt->execute([$ong_escolhida]);
                $ong_selecionada = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $mensagem = '<div class="error-message">❌ Erro ao buscar informações da ONG.</div>';
            }
        }
    } elseif (isset($_POST['agendar'])) {
        // Validar dados do agendamento
        if (empty($ong_escolhida)) {
            $mensagem = '<div class="error-message">Selecione uma ONG para doação!</div>';
        } elseif (empty($data_selecionada) || empty($horario_selecionado) || empty($local_selecionado)) {
            $mensagem = '<div class="error-message">Preencha todos os campos!</div>';
        } else if (!$id_doador_table) {
            $mensagem = '<div class="error-message">❌ Erro: Doador não encontrado.</div>';
        } else {
            try {
                // Iniciar transação
                $pdo->beginTransaction();
                
                // Combinar data e horário
                $data_hora_agendada = $data_selecionada . ' ' . $horario_selecionado . ':00';
                
                // Preparar valores para inserção
                $valor = ($tipo_doacao === 'DINHEIRO' && !empty($valor_doacao)) ? $valor_doacao : null;
                $descricao = ($tipo_doacao === 'ITEM') ? $descricao_item : null;
                
                // 1. Inserir na tabela doacoes
                $sql_doacao = "INSERT INTO doacoes (id_doador, id_ong, tipo, status, descricao_item, valor) 
                              VALUES (?, ?, ?, 'AGENDADA', ?, ?)";
                $stmt_doacao = $pdo->prepare($sql_doacao);
                $stmt_doacao->execute([
                    $id_doador_table, 
                    $ong_escolhida,
                    $tipo_doacao, 
                    $descricao,
                    $valor
                ]);
                
                // Pegar o ID da doação inserida
                $id_doacao = $pdo->lastInsertId();
                
                // 2. Inserir na tabela coletas
                $sql_coleta = "INSERT INTO coletas (id_doacao, tipo, endereco, data_agendada) 
                              VALUES (?, 'COLETA', ?, ?)";
                $stmt_coleta = $pdo->prepare($sql_coleta);
                $stmt_coleta->execute([$id_doacao, $local_selecionado, $data_hora_agendada]);
                
                // 3. Criar notificação para a ONG
                $nome_doador = $_SESSION["usuario_nome"] ?? 'Doador';
                $mensagem_notificacao = "{$nome_doador} agendou uma coleta de {$tipo_doacao} para " . 
                                       date('d/m/Y H:i', strtotime($data_hora_agendada)) . 
                                       " no local: {$local_selecionado}";
                
                $sql_notificacao = "INSERT INTO notificacoes (id_usuario, mensagem, tipo) 
                                   VALUES (?, ?, 'COLETA_AGENDADA')";
                $stmt_notificacao = $pdo->prepare($sql_notificacao);
                $stmt_notificacao->execute([$ong_escolhida, $mensagem_notificacao]);

                $pdo->commit();
                
                // Armazenar a mensagem de sucesso para mostrar nesta página
                $_SESSION['agendamento_sucesso'] = "✅ Agendamento realizado com sucesso! A ONG foi notificada.";
                
                // Redirecionar para a mesma página para limpar os dados do POST
                header("Location: agendar_coleta.php?sucesso=1&ong=" . urlencode($ong_escolhida));
                exit;
                
            } catch (PDOException $e) {
                // Rollback em caso de erro
                $pdo->rollBack();
                $mensagem = '<div class="error-message">❌ Erro ao agendar: ' . $e->getMessage() . '</div>';
                error_log("Erro no agendamento: " . $e->getMessage());
            }
        }
    }
}

// Verificar se há ONG selecionada para mostrar o formulário completo
$mostrar_formulario = !empty($ong_escolhida);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Agendar Coleta - Volunteer Community</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/estilo_global.css">
<link rel="stylesheet" href="css/estilo_agendar_coleta.css">

<style>
.error-message {
  background: #ffe6e6;
  color: #d63031;
  text-align: center;
  padding: 10px;
  margin: 10px 20px;
  border-radius: 12px;
  border-left: 4px solid #d63031;
}
</style>
</head>

<body>

<div class="phone">

  <!-- HEADER -->
  <div class="header">
    <span onclick="history.back()" style="cursor:pointer;">⬅</span>
    <div class="header-title"><?= $mostrar_formulario ? 'Agendar Coleta' : 'Escolher ONG' ?></div>
    <span style="visibility:hidden;">⚙️</span>
  </div>

  <!-- CONTEÚDO PRINCIPAL COM SCROLL -->
  <div class="main-content">
    <?php 
    // Mostrar mensagem de sucesso se existir
    if (!empty($mensagem_sucesso)) {
        echo '<div class="success-message" id="successMessage">' . $mensagem_sucesso . '</div>';
    }
    
    echo $mensagem; 
    ?>

    <?php if (!$mostrar_formulario): ?>
    <!-- TELA DE ESCOLHA DE ONG -->
    <div class="busca-ong">
      <div class="search-box">
        <form method="POST" action="" id="searchForm">
          <input type="text" name="buscar_ong" id="searchInput" placeholder="Buscar ONG por nome ou categoria..." 
                 value="<?= htmlspecialchars($buscar_ong) ?>" onkeyup="filterOngs()">
          <button type="button" onclick="filterOngs()">🔍</button>
        </form>
      </div>
      
      <div class="section">Selecione uma ONG para doação</div>
      
      <?php if (empty($ongs)): ?>
        <div class="no-ongs">
          <p>⚠️ Nenhuma ONG cadastrada no momento.</p>
          <p><small>Verifique se existem instituições cadastradas no sistema.</small></p>
        </div>
      <?php else: ?>
        <form method="POST" action="" id="formEscolherOng">
          <div class="ongs-list" id="ongsList">
            <?php 
            // Filtrar ONGs baseado na busca
            $ongs_filtradas = $ongs;
            if (!empty($buscar_ong)) {
                $busca = strtolower(trim($buscar_ong));
                $ongs_filtradas = array_filter($ongs, function($ong) use ($busca) {
                    return stripos(strtolower($ong['nome'] ?? ''), $busca) !== false ||
                           stripos(strtolower($ong['descricao'] ?? ''), $busca) !== false ||
                           stripos(strtolower($ong['email'] ?? ''), $busca) !== false;
                });
            }
            
            if (empty($ongs_filtradas)): ?>
              <div class="no-ongs">
                <p>Nenhuma ONG encontrada para "<?= htmlspecialchars($buscar_ong) ?>"</p>
                <p><small>Tente buscar por outro termo</small></p>
              </div>
            <?php else: ?>
              <?php foreach ($ongs_filtradas as $ong): ?>
                <div class="ong-card" 
                     data-id="<?= $ong['id_usuario'] ?>"
                     data-nome="<?= htmlspecialchars($ong['nome'] ?? 'Sem nome') ?>"
                     data-descricao="<?= htmlspecialchars($ong['descricao'] ?? 'Sem descrição') ?>"
                     onclick="selecionarOng(<?= $ong['id_usuario'] ?>, '<?= htmlspecialchars(addslashes($ong['nome'] ?? '')) ?>')">
                  <h3><?= htmlspecialchars($ong['nome'] ?? 'Instituição sem nome') ?></h3>
                  <?php if (!empty($ong['email'])): ?>
                    <p>📧 <?= htmlspecialchars($ong['email']) ?></p>
                  <?php endif; ?>
                  <?php if (!empty($ong['descricao'])): ?>
                    <p><?= htmlspecialchars(mb_strlen($ong['descricao']) > 100 ? mb_substr($ong['descricao'], 0, 100) . '...' : $ong['descricao']) ?></p>
                  <?php endif; ?>
                  <?php if (!empty($ong['endereco'])): ?>
                    <div class="endereco">
                      📍 <?= htmlspecialchars(mb_strlen($ong['endereco']) > 60 ? mb_substr($ong['endereco'], 0, 60) . '...' : $ong['endereco']) ?>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($ong['cpf_cnpj'])): ?>
                    <div class="cnpj">CNPJ: <?= htmlspecialchars($ong['cpf_cnpj']) ?></div>
                  <?php endif; ?>
                  <input type="radio" name="ong_escolhida" value="<?= $ong['id_usuario'] ?>" 
                         <?= $ong_escolhida == $ong['id_usuario'] ? 'checked' : '' ?> 
                         style="display:none;">
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
          
          <!-- BOTÕES CENTRALIZADOS E VERTICALMENTE -->
          <div class="action-buttons-vertical">
            <button type="button" class="btn-secondary" onclick="window.location.href='feed.php'">
              Cancelar
            </button>
            <button type="submit" name="escolher_ong" class="btn-primary" id="btnEscolherOng" disabled>
              Continuar
            </button>
          </div>
        </form>
      <?php endif; ?>
    </div>
    
    <?php else: ?>
    <!-- TELA DE AGENDAMENTO -->
    
    <!-- INFO DA ONG SELECIONADA -->
    <?php if ($ong_selecionada): ?>
    <div class="ong-info">
      <div><strong>ONG selecionada:</strong> <?= htmlspecialchars($ong_selecionada['nome']) ?></div>
      <?php if (!empty($ong_selecionada['email'])): ?>
        <div><strong>Email:</strong> <?= htmlspecialchars($ong_selecionada['email']) ?></div>
      <?php endif; ?>
      <?php if (!empty($ong_selecionada['cpf_cnpj'])): ?>
        <div><strong>CNPJ:</strong> <?= htmlspecialchars($ong_selecionada['cpf_cnpj']) ?></div>
      <?php endif; ?>
      <?php if (!empty($ong_selecionada['descricao'])): ?>
        <div><strong>Descrição:</strong> <?= htmlspecialchars($ong_selecionada['descricao']) ?></div>
      <?php endif; ?>
      <?php if (!empty($ong_selecionada['endereco'])): ?>
        <div><strong>Endereço:</strong> <?= htmlspecialchars($ong_selecionada['endereco']) ?></div>
      <?php endif; ?>
      <?php if (!empty($titulo_ong)): ?>
        <div><strong>Campanha:</strong> <?= htmlspecialchars($titulo_ong) ?></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="">
      <input type="hidden" name="ong_escolhida" value="<?= $ong_escolhida ?>">

      <!-- TIPO DE DOAÇÃO -->
      <div class="section">Tipo de Doação</div>
      <div class="tipo-doacao">
        <button type="button" class="tipo-btn <?= $tipo_doacao == 'ITEM' ? 'selected' : '' ?>" onclick="selectTipo('ITEM')">
          📦 Itens
        </button>
        <button type="button" class="tipo-btn <?= $tipo_doacao == 'DINHEIRO' ? 'selected' : '' ?>" onclick="selectTipo('DINHEIRO')">
          💰 Dinheiro
        </button>
      </div>
      <input type="hidden" name="tipo_doacao" id="tipo_doacao" value="<?= htmlspecialchars($tipo_doacao) ?>">

      <!-- DESCRIÇÃO DOS ITENS (aparece apenas para doação de itens) -->
      <div class="descricao-item" id="descricaoItemContainer">
        <div class="section">Descrição dos Itens</div>
        <textarea name="descricao_item" placeholder="Descreva os itens que serão doados (roupas, calçados, alimentos, brinquedos, etc.)"><?= htmlspecialchars($descricao_item) ?></textarea>
      </div>

      <!-- VALOR DA DOAÇÃO (aparece apenas para doação em dinheiro) -->
      <div class="valor-doacao" id="valorDoacaoContainer">
        <div class="section">Valor da Doação (R$)</div>
        <input type="number" name="valor_doacao" placeholder="0,00" step="0.01" min="0" value="<?= htmlspecialchars($valor_doacao) ?>">
      </div>

      <!-- Data Selecionada -->
      <?php if ($data_selecionada): ?>
      <div class="selected-info">
        <div><strong>Doação:</strong> <?= $tipo_doacao == 'ITEM' ? 'Itens' : 'Dinheiro' ?></div>
        <?php if ($tipo_doacao == 'ITEM' && $descricao_item): ?>
          <div><strong>Itens:</strong> <?= htmlspecialchars($descricao_item) ?></div>
        <?php endif; ?>
        <?php if ($tipo_doacao == 'DINHEIRO' && $valor_doacao): ?>
          <div><strong>Valor:</strong> R$ <?= number_format($valor_doacao, 2, ',', '.') ?></div>
        <?php endif; ?>
        <div><strong>Data:</strong> <?= date('d/m/Y', strtotime($data_selecionada)) ?></div>
        <div><strong>Horário:</strong> <?= $horario_selecionado ?></div>
        <div><strong>Local:</strong> <?= htmlspecialchars($local_selecionado) ?></div>
        <?php if ($ong_selecionada): ?>
          <div><strong>ONG:</strong> <?= htmlspecialchars($ong_selecionada['nome']) ?></div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- CALENDÁRIO INTERATIVO -->
      <div class="calendar">
        <div class="month">Abril 2025</div>

        <div class="weekdays">
          <span>Dom</span><span>Seg</span><span>Ter</span><span>Qua</span><span>Qui</span><span>Sex</span><span>Sáb</span>
        </div>

        <div class="days">
          <span></span><span></span>
          <span class="day" onclick="selectDate('2025-04-01')">1</span>
          <span class="day" onclick="selectDate('2025-04-02')">2</span>
          <span class="day" onclick="selectDate('2025-04-03')">3</span>
          <span class="day" onclick="selectDate('2025-04-04')">4</span>
          <span class="day" onclick="selectDate('2025-04-05')">5</span>
          <span class="day" onclick="selectDate('2025-04-06')">6</span>
          <span class="day" onclick="selectDate('2025-04-07')">7</span>
          <span class="day highlight" onclick="selectDate('2025-04-08')">8</span>
          <span class="day highlight" onclick="selectDate('2025-04-09')">9</span>
          <span class="day highlight" onclick="selectDate('2025-04-10')">10</span>
          <span class="day" onclick="selectDate('2025-04-11')">11</span>
          <span class="day" onclick="selectDate('2025-04-12')">12</span>
          <span class="day" onclick="selectDate('2025-04-13')">13</span>
          <span class="day" onclick="selectDate('2025-04-14')">14</span>
          <span class="day" onclick="selectDate('2025-04-15')">15</span>
          <span class="day" onclick="selectDate('2025-04-16')">16</span>
          <span class="day" onclick="selectDate('2025-04-17')">17</span>
          <span class="day highlight" onclick="selectDate('2025-04-18')">18</span>
          <span class="day" onclick="selectDate('2025-04-19')">19</span>
          <span class="day" onclick="selectDate('2025-04-20')">20</span>
          <span class="day" onclick="selectDate('2025-04-21')">21</span>
          <span class="day" onclick="selectDate('2025-04-22')">22</span>
          <span class="day" onclick="selectDate('2025-04-23')">23</span>
          <span class="day" onclick="selectDate('2025-04-24')">24</span>
          <span class="day" onclick="selectDate('2025-04-25')">25</span>
          <span class="day" onclick="selectDate('2025-04-26')">26</span>
          <span class="day" onclick="selectDate('2025-04-27')">27</span>
          <span class="day" onclick="selectDate('2025-04-28')">28</span>
          <span class="day" onclick="selectDate('2025-04-29')">29</span>
          <span class="day" onclick="selectDate('2025-04-30')">30</span>
          <span></span><span></span><span></span>
        </div>
      </div>

      <input type="hidden" name="data_coleta" id="data_coleta" value="<?= htmlspecialchars($data_selecionada) ?>">

      <!-- HORÁRIOS -->
      <div class="section">Horários disponíveis</div>
      <div class="times">
        <button type="button" class="time-btn" onclick="selectTime('08:00')">8:00</button>
        <button type="button" class="time-btn" onclick="selectTime('09:00')">9:00</button>
        <button type="button" class="time-btn" onclick="selectTime('10:00')">10:00</button>
        <button type="button" class="time-btn" onclick="selectTime('11:00')">11:00</button>
        <button type="button" class="time-btn" onclick="selectTime('12:00')">12:00</button>
        <button type="button" class="time-btn" onclick="selectTime('14:00')">14:00</button>
        <button type="button" class="time-btn" onclick="selectTime('15:00')">15:00</button>
        <button type="button" class="time-btn" onclick="selectTime('16:00')">16:00</button>
      </div>

      <input type="hidden" name="horario" id="horario" value="<?= htmlspecialchars($horario_selecionado) ?>">

      <!-- LOCAL -->
      <div class="section">Local de coleta</div>
      <div class="select-wrapper">
        <select name="local_coleta" required>
          <option value="">Selecione um local</option>
          <option value="Fort Atacadista" <?= $local_selecionado == 'Fort Atacadista' ? 'selected' : '' ?>>Fort Atacadista</option>
          <option value="ONG Reviver - Centro" <?= $local_selecionado == 'ONG Reviver - Centro' ? 'selected' : '' ?>>ONG Reviver - Centro</option>
          <option value="Ponto de coleta Bairro A" <?= $local_selecionado == 'Ponto de coleta Bairro A' ? 'selected' : '' ?>>Ponto de coleta Bairro A</option>
          <option value="Ponto de coleta Bairro B" <?= $local_selecionado == 'Ponto de coleta Bairro B' ? 'selected' : '' ?>>Ponto de coleta Bairro B</option>
          <?php if ($ong_selecionada && !empty($ong_selecionada['endereco'])): ?>
            <option value="<?= htmlspecialchars($ong_selecionada['endereco']) ?>" <?= $local_selecionado == $ong_selecionada['endereco'] ? 'selected' : '' ?>>
              Endereço da ONG: <?= htmlspecialchars($ong_selecionada['endereco']) ?>
            </option>
          <?php endif; ?>
        </select>
      </div>

      <!-- BOTÕES AÇÃO - CENTRALIZADOS E VERTICALMENTE -->
      <div class="form-buttons-container">
        <button type="submit" name="agendar" class="btn-primary" id="btnAgendar" disabled>
          ✅ Agendar
        </button>
        <button type="button" class="btn-secondary" onclick="window.location.href='agendar_coleta.php?cancelar_ong=1'">
          🔄 Trocar ONG
        </button>
      </div>
    </form>
    <?php endif; ?>
  </div>

  <!-- MENU INFERIOR FIXO -->
  <div class="bottom">
    <a href="feed.php" class="menu-item">
      🏠
      <span>Feed</span>
    </a>
    
    <!-- BOTÃO + CENTRAL -->
    <button class="plus-btn" onclick="window.location.href='agendar_coleta.php'">+</button>
    
    <a href="notificacoes.php" class="menu-item">
      🔔
      <span>Notificações</span>
    </a>
    
    <a href="perfil.php" class="menu-item">
      👤
      <span>Perfil</span>
    </a>
  </div>

</div>

<script>
let selectedDate = '';
let selectedTime = '';
let selectedTipo = '<?= $tipo_doacao ?>';
let selectedOng = '';

// Função para filtrar ONGs em tempo real
function filterOngs() {
    const searchInput = document.getElementById('searchInput');
    const searchTerm = searchInput.value.toLowerCase().trim();
    const ongCards = document.querySelectorAll('.ong-card');
    let hasVisibleCards = false;
    
    ongCards.forEach(card => {
        const nome = card.getAttribute('data-nome').toLowerCase();
        const descricao = card.getAttribute('data-descricao').toLowerCase();
        
        if (searchTerm === '' || 
            nome.includes(searchTerm) || 
            descricao.includes(searchTerm)) {
            card.style.display = 'block';
            hasVisibleCards = true;
        } else {
            card.style.display = 'none';
        }
    });
    
    // Atualizar botão de continuar
    checkOngSelection();
}

// Função para selecionar ONG
function selecionarOng(idOng, nomeOng) {
    selectedOng = idOng;
    
    // Remover seleção anterior
    document.querySelectorAll('.ong-card.selected').forEach(el => {
        el.classList.remove('selected');
    });
    
    // Adicionar seleção atual
    const card = event.currentTarget;
    card.classList.add('selected');
    
    // Marcar o radio button
    const radio = card.querySelector('input[type="radio"]');
    if (radio) {
        radio.checked = true;
    }
    
    // Atualizar botão de continuar
    checkOngSelection();
    
    console.log('ONG selecionada:', idOng, nomeOng); // Debug
}

// Verificar se uma ONG foi selecionada
function checkOngSelection() {
    const btnEscolherOng = document.getElementById('btnEscolherOng');
    if (!btnEscolherOng) return;
    
    // Verificar se algum radio está marcado
    const radios = document.querySelectorAll('input[name="ong_escolhida"]');
    let isSelected = false;
    
    radios.forEach(radio => {
        if (radio.checked) {
            isSelected = true;
        }
    });
    
    // Verificar se há pelo menos um card visível
    const visibleCards = document.querySelectorAll('.ong-card');
    const hasVisibleOngs = visibleCards.length > 0;
    
    // Habilitar/desabilitar botão
    if (isSelected && hasVisibleOngs) {
        btnEscolherOng.disabled = false;
        console.log('Botão habilitado'); // Debug
    } else {
        btnEscolherOng.disabled = true;
        console.log('Botão desabilitado'); // Debug
    }
}

// Mostrar/ocultar campos baseado no tipo selecionado
function updateTipoDisplay() {
    const descricaoContainer = document.getElementById('descricaoItemContainer');
    const valorContainer = document.getElementById('valorDoacaoContainer');
    
    if (selectedTipo === 'ITEM') {
        if (descricaoContainer) descricaoContainer.style.display = 'block';
        if (valorContainer) valorContainer.style.display = 'none';
    } else if (selectedTipo === 'DINHEIRO') {
        if (descricaoContainer) descricaoContainer.style.display = 'none';
        if (valorContainer) valorContainer.style.display = 'block';
    } else {
        if (descricaoContainer) descricaoContainer.style.display = 'none';
        if (valorContainer) valorContainer.style.display = 'none';
    }
    checkFormCompletion();
}

function selectTipo(tipo) {
    selectedTipo = tipo;
    document.getElementById('tipo_doacao').value = tipo;
    
    // Remover seleção anterior
    document.querySelectorAll('.tipo-btn.selected').forEach(el => {
        el.classList.remove('selected');
    });
    
    // Adicionar seleção atual
    event.target.classList.add('selected');
    updateTipoDisplay();
}

function selectDate(date) {
    selectedDate = date;
    document.getElementById('data_coleta').value = date;
    
    // Remover seleção anterior
    document.querySelectorAll('.day.selected').forEach(el => {
        el.classList.remove('selected');
    });
    
    // Adicionar seleção atual
    event.target.classList.add('selected');
    checkFormCompletion();
}

function selectTime(time) {
    selectedTime = time;
    document.getElementById('horario').value = time;
    
    // Remover seleção anterior
    document.querySelectorAll('.time-btn.selected').forEach(el => {
        el.classList.remove('selected');
    });
    
    // Adicionar seleção atual
    event.target.classList.add('selected');
    checkFormCompletion();
}

function checkFormCompletion() {
    const data = document.getElementById('data_coleta');
    const horario = document.getElementById('horario');
    const local = document.querySelector('select[name="local_coleta"]');
    const descricaoItem = document.querySelector('textarea[name="descricao_item"]');
    const valorDoacao = document.querySelector('input[name="valor_doacao"]');
    const btnAgendar = document.getElementById('btnAgendar');
    
    if (!btnAgendar) return;
    
    let isValid = data && data.value && horario && horario.value && local && local.value;
    
    // Validações específicas por tipo
    if (selectedTipo === 'ITEM' && descricaoItem) {
        isValid = isValid && descricaoItem.value.trim() !== '';
    } else if (selectedTipo === 'DINHEIRO' && valorDoacao) {
        isValid = isValid && valorDoacao.value.trim() !== '' && parseFloat(valorDoacao.value) > 0;
    }
    
    if (isValid) {
        btnAgendar.disabled = false;
    } else {
        btnAgendar.disabled = true;
    }
}

// Verificar quando o select muda
const localSelect = document.querySelector('select[name="local_coleta"]');
if (localSelect) {
    localSelect.addEventListener('change', checkFormCompletion);
}

// Verificar quando a descrição muda
const descricaoItem = document.querySelector('textarea[name="descricao_item"]');
if (descricaoItem) {
    descricaoItem.addEventListener('input', checkFormCompletion);
}

// Verificar quando o valor muda
const valorDoacaoInput = document.querySelector('input[name="valor_doacao"]');
if (valorDoacaoInput) {
    valorDoacaoInput.addEventListener('input', checkFormCompletion);
}

// Verificar seleção de ONG ao carregar
document.addEventListener('DOMContentLoaded', function() {
    console.log('Página carregada');
    
    // Forçar a verificação inicial
    checkOngSelection();
    updateTipoDisplay();
    
    // Adicionar evento de clique para cada card de ONG
    const cards = document.querySelectorAll('.ong-card');
    cards.forEach(card => {
        card.addEventListener('click', function(e) {
            // Evitar que o clique se propague
            e.stopPropagation();
            
            // Remover seleção de todos os cards
            cards.forEach(c => c.classList.remove('selected'));
            
            // Adicionar seleção ao card clicado
            this.classList.add('selected');
            
            // Marcar o radio button correspondente
            const radio = this.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = true;
                console.log('Radio marcado:', radio.value);
            }
            
            // Habilitar botão
            const btn = document.getElementById('btnEscolherOng');
            if (btn) {
                btn.disabled = false;
                console.log('Botão habilitado');
            }
        });
    });
    
    // Verificar se algum radio já está marcado (para ONGs pré-selecionadas)
    const radios = document.querySelectorAll('input[name="ong_escolhida"]');
    radios.forEach(radio => {
        if (radio.checked) {
            const parentCard = radio.closest('.ong-card');
            if (parentCard) {
                parentCard.classList.add('selected');
                const btn = document.getElementById('btnEscolherOng');
                if (btn) btn.disabled = false;
                console.log('Radio pré-marcado:', radio.value);
            }
        }
    });
    
    // Configurar busca em tempo real
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', filterOngs);
    }
    
    // Auto-fechar mensagem de sucesso após 5 segundos
    const successMessage = document.getElementById('successMessage');
    if (successMessage) {
        setTimeout(() => {
            successMessage.style.opacity = '0';
            successMessage.style.transition = 'opacity 0.5s ease';
            setTimeout(() => {
                successMessage.style.display = 'none';
            }, 500);
        }, 5000);
    }
});

// Prevenir scroll do body
document.body.style.overflow = 'hidden';
</script>
</body>
</html>