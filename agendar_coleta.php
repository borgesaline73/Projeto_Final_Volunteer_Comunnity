<?php
session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

if (($_SESSION["usuario_tipo"] ?? "") !== "doador") {
    header("Location: feed.php");
    exit;
}

require "banco.php";

$nome      = $_SESSION["usuario_nome"] ?? "Usuário";
$id_doador = $_SESSION["usuario_id"];

// Parâmetros vindos da URL
$id_ong    = $_GET['ong']   ?? null;
$titulo_ong = $_GET['titulo'] ?? ''; // vazio quando vem do perfil público

// Mensagem de sucesso
$mensagem_sucesso = '';
if (isset($_SESSION['agendamento_sucesso'])) {
    $mensagem_sucesso = $_SESSION['agendamento_sucesso'];
    unset($_SESSION['agendamento_sucesso']);
}

// Buscar todas as ONGs
$ongs = [];
try {
    $stmt_check = $pdo->query("SELECT column_name FROM information_schema.columns 
                               WHERE table_name = 'ongs' AND column_name = 'categoria'");
    $categoria_exists = $stmt_check->fetch(PDO::FETCH_ASSOC);

    $sql_ongs = $categoria_exists
        ? "SELECT u.id_usuario, u.nome, u.email, u.cpf_cnpj, o.endereco, o.descricao, o.categoria 
           FROM usuarios u LEFT JOIN ongs o ON u.id_usuario = o.id_ong 
           WHERE u.tipo_usuario = 'instituicao' ORDER BY u.nome ASC"
        : "SELECT u.id_usuario, u.nome, u.email, u.cpf_cnpj, o.endereco, o.descricao 
           FROM usuarios u LEFT JOIN ongs o ON u.id_usuario = o.id_ong 
           WHERE u.tipo_usuario = 'instituicao' ORDER BY u.nome ASC";

    $stmt = $pdo->prepare($sql_ongs);
    $stmt->execute();
    $ongs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("ERRO ao buscar ONGs: " . $e->getMessage());
    try {
        $stmt_simple = $pdo->prepare("SELECT u.id_usuario, u.nome, u.email, u.cpf_cnpj 
                                      FROM usuarios u WHERE u.tipo_usuario = 'instituicao' ORDER BY u.nome ASC");
        $stmt_simple->execute();
        $ongs = $stmt_simple->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {
        error_log("ERRO na consulta simples: " . $e2->getMessage());
    }
}

// Buscar dados da ONG selecionada (via GET ou POST)
$ong_selecionada = null;

function buscarOng($pdo, $id) {
    $stmt = $pdo->prepare("SELECT u.nome, u.email, u.cpf_cnpj, o.endereco, o.id_ong, o.descricao
                           FROM usuarios u LEFT JOIN ongs o ON u.id_usuario = o.id_ong 
                           WHERE u.id_usuario = ? AND u.tipo_usuario = 'instituicao'");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Buscar ID do doador
$id_doador_table = null;
try {
    $stmt = $pdo->prepare("SELECT id_doador FROM doadores WHERE id_doador = ?");
    $stmt->execute([$id_doador]);
    $doador_info     = $stmt->fetch(PDO::FETCH_ASSOC);
    $id_doador_table = $doador_info['id_doador'] ?? null;
} catch (PDOException $e) {
    error_log("Erro ao buscar doador: " . $e->getMessage());
}

// Variáveis do formulário
$data_selecionada    = $_POST['data_coleta']    ?? '';
$horario_selecionado = $_POST['horario']         ?? '';
$local_selecionado   = $_POST['local_coleta']    ?? '';
$tipo_doacao         = $_POST['tipo_doacao']     ?? 'ITEM';
$descricao_item      = $_POST['descricao_item']  ?? '';
$valor_doacao        = $_POST['valor_doacao']    ?? '';
$ong_escolhida       = $_POST['ong_escolhida']   ?? $id_ong ?? '';
$buscar_ong          = $_POST['buscar_ong']      ?? '';
$mensagem            = '';

// Processar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['escolher_ong'])) {
        $ong_escolhida = $_POST['ong_escolhida'] ?? '';
        if ($ong_escolhida) {
            try {
                $ong_selecionada = buscarOng($pdo, $ong_escolhida);
            } catch (PDOException $e) {
                $mensagem = '<div class="error-message">❌ Erro ao buscar informações da ONG.</div>';
            }
        }

    } elseif (isset($_POST['agendar'])) {
        if (empty($ong_escolhida)) {
            $mensagem = '<div class="error-message">Selecione uma ONG para doação!</div>';
        } elseif (empty($data_selecionada) || empty($horario_selecionado) || empty($local_selecionado)) {
            $mensagem = '<div class="error-message">Preencha todos os campos!</div>';
        } elseif (!$id_doador_table) {
            $mensagem = '<div class="error-message">❌ Erro: Doador não encontrado.</div>';
        } else {
            try {
                $pdo->beginTransaction();

                $data_hora_agendada = $data_selecionada . ' ' . $horario_selecionado . ':00';
                $valor     = ($tipo_doacao === 'DINHEIRO' && !empty($valor_doacao)) ? $valor_doacao : null;
                $descricao = ($tipo_doacao === 'ITEM') ? $descricao_item : null;

                $stmt_doacao = $pdo->prepare("INSERT INTO doacoes (id_doador, id_ong, tipo, status, descricao_item, valor) 
                                              VALUES (?, ?, ?, 'AGENDADA', ?, ?)");
                $stmt_doacao->execute([$id_doador_table, $ong_escolhida, $tipo_doacao, $descricao, $valor]);
                $id_doacao = $pdo->lastInsertId();

                $stmt_coleta = $pdo->prepare("INSERT INTO coletas (id_doacao, tipo, endereco, data_agendada) 
                                              VALUES (?, 'COLETA', ?, ?)");
                $stmt_coleta->execute([$id_doacao, $local_selecionado, $data_hora_agendada]);

                $nome_doador         = $_SESSION["usuario_nome"] ?? 'Doador';
                $mensagem_notificacao = "{$nome_doador} agendou uma coleta de {$tipo_doacao} para " .
                                        date('d/m/Y H:i', strtotime($data_hora_agendada)) .
                                        " no local: {$local_selecionado}";

                $stmt_notif = $pdo->prepare("INSERT INTO notificacoes (id_usuario, mensagem, tipo) 
                                             VALUES (?, ?, 'COLETA_AGENDADA')");
                $stmt_notif->execute([$ong_escolhida, $mensagem_notificacao]);

                $pdo->commit();

                $_SESSION['agendamento_sucesso'] = "✅ Agendamento realizado com sucesso! A ONG foi notificada.";
                header("Location: agendar_coleta.php?sucesso=1&ong=" . urlencode($ong_escolhida));
                exit;

            } catch (PDOException $e) {
                $pdo->rollBack();
                $mensagem = '<div class="error-message">❌ Erro ao agendar: ' . $e->getMessage() . '</div>';
                error_log("Erro no agendamento: " . $e->getMessage());
            }
        }
    }
}

// Buscar ONG via GET (vindo do feed ou perfil público) — só se não veio de POST
if ($id_ong && $_SERVER['REQUEST_METHOD'] !== 'POST' && !$ong_selecionada) {
    try {
        $ong_selecionada = buscarOng($pdo, $id_ong);
    } catch (PDOException $e) {
        error_log("Erro ao buscar ONG específica: " . $e->getMessage());
    }
}

// Se veio de POST de escolher_ong e ainda não buscou
if (!$ong_selecionada && !empty($ong_escolhida)) {
    try {
        $ong_selecionada = buscarOng($pdo, $ong_escolhida);
    } catch (PDOException $e) {
        error_log("Erro ao buscar ONG: " . $e->getMessage());
    }
}

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

  <div class="main-content">

    <?php if (!empty($mensagem_sucesso)): ?>
      <div class="success-message" id="successMessage"><?= $mensagem_sucesso ?></div>
    <?php endif; ?>

    <?= $mensagem ?>

    <?php if (!$mostrar_formulario): ?>
    <!-- ===== TELA DE ESCOLHA DE ONG ===== -->
    <div class="busca-ong">
      <div class="search-box">
        <form method="POST" action="" id="searchForm">
          <input type="text" name="buscar_ong" id="searchInput"
                 placeholder="Buscar ONG por nome ou categoria..."
                 value="<?= htmlspecialchars($buscar_ong) ?>" onkeyup="filterOngs()">
          <button type="button" onclick="filterOngs()">🔍</button>
        </form>
      </div>

      <div class="section">Selecione uma ONG para doação</div>

      <?php if (empty($ongs)): ?>
        <div class="no-ongs">
          <p>⚠️ Nenhuma ONG cadastrada no momento.</p>
        </div>
      <?php else: ?>
        <form method="POST" action="" id="formEscolherOng">
          <div class="ongs-list" id="ongsList">
            <?php
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
              </div>
            <?php else: ?>
              <?php foreach ($ongs_filtradas as $ong): ?>
                <div class="ong-card"
                     data-id="<?= $ong['id_usuario'] ?>"
                     data-nome="<?= htmlspecialchars($ong['nome'] ?? '') ?>"
                     data-descricao="<?= htmlspecialchars($ong['descricao'] ?? '') ?>"
                     onclick="selecionarOng(<?= $ong['id_usuario'] ?>, '<?= htmlspecialchars(addslashes($ong['nome'] ?? '')) ?>')">
                  <h3><?= htmlspecialchars($ong['nome'] ?? 'Instituição sem nome') ?></h3>
                  <?php if (!empty($ong['email'])): ?>
                    <p>📧 <?= htmlspecialchars($ong['email']) ?></p>
                  <?php endif; ?>
                  <?php if (!empty($ong['descricao'])): ?>
                    <p><?= htmlspecialchars(mb_strlen($ong['descricao']) > 100 ? mb_substr($ong['descricao'], 0, 100) . '...' : $ong['descricao']) ?></p>
                  <?php endif; ?>
                  <?php if (!empty($ong['endereco'])): ?>
                    <div class="endereco">📍 <?= htmlspecialchars(mb_strlen($ong['endereco']) > 60 ? mb_substr($ong['endereco'], 0, 60) . '...' : $ong['endereco']) ?></div>
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

          <div class="action-buttons-vertical">
            <button type="button" class="btn-secondary" onclick="window.location.href='feed.php'">Cancelar</button>
            <button type="submit" name="escolher_ong" class="btn-primary" id="btnEscolherOng" disabled>Continuar</button>
          </div>
        </form>
      <?php endif; ?>
    </div>

    <?php else: ?>
    <!-- ===== TELA DE AGENDAMENTO ===== -->

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

      <div class="section">Tipo de Doação</div>
      <div class="tipo-doacao">
        <button type="button" class="tipo-btn <?= $tipo_doacao == 'ITEM' ? 'selected' : '' ?>" onclick="selectTipo('ITEM')">📦 Itens</button>
        <button type="button" class="tipo-btn <?= $tipo_doacao == 'DINHEIRO' ? 'selected' : '' ?>" onclick="selectTipo('DINHEIRO')">💰 Dinheiro</button>
      </div>
      <input type="hidden" name="tipo_doacao" id="tipo_doacao" value="<?= htmlspecialchars($tipo_doacao) ?>">

      <div class="descricao-item" id="descricaoItemContainer">
        <div class="section">Descrição dos Itens</div>
        <textarea name="descricao_item" placeholder="Descreva os itens que serão doados..."><?= htmlspecialchars($descricao_item) ?></textarea>
      </div>

      <div class="valor-doacao" id="valorDoacaoContainer">
        <div class="section">Valor da Doação (R$)</div>
        <input type="number" name="valor_doacao" placeholder="0,00" step="0.01" min="0" value="<?= htmlspecialchars($valor_doacao) ?>">
      </div>

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

      <div class="form-buttons-container">
        <button type="submit" name="agendar" class="btn-primary" id="btnAgendar" disabled>✅ Agendar</button>
        <button type="button" class="btn-secondary" onclick="window.location.href='agendar_coleta.php'">🔄 Trocar ONG</button>
      </div>
    </form>
    <?php endif; ?>

  </div><!-- fim main-content -->

  <!-- MENU INFERIOR -->
  <div class="bottom">
    <a href="feed.php" class="menu-item">🏠<span>Feed</span></a>
    <a href="campanhas.php" class="menu-item">📢<span>Campanhas</span></a>
    <button class="plus-btn" onclick="window.location.href='agendar_coleta.php'">+</button>
    <a href="notificacoes.php" class="menu-item">🔔<span>Notificações</span></a>
    <a href="perfil.php" class="menu-item">👤<span>Perfil</span></a>
  </div>

</div>

<script>
let selectedDate = '';
let selectedTime = '';
let selectedTipo = '<?= $tipo_doacao ?>';

function filterOngs() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase().trim();
    document.querySelectorAll('.ong-card').forEach(card => {
        const nome = card.getAttribute('data-nome').toLowerCase();
        const desc = card.getAttribute('data-descricao').toLowerCase();
        card.style.display = (searchTerm === '' || nome.includes(searchTerm) || desc.includes(searchTerm)) ? 'block' : 'none';
    });
    checkOngSelection();
}

function selecionarOng(idOng, nomeOng) {
    document.querySelectorAll('.ong-card.selected').forEach(el => el.classList.remove('selected'));
    const card = event.currentTarget;
    card.classList.add('selected');
    const radio = card.querySelector('input[type="radio"]');
    if (radio) radio.checked = true;
    checkOngSelection();
}

function checkOngSelection() {
    const btn = document.getElementById('btnEscolherOng');
    if (!btn) return;
    const checked = document.querySelector('input[name="ong_escolhida"]:checked');
    btn.disabled = !checked;
}

function updateTipoDisplay() {
    const descricaoContainer = document.getElementById('descricaoItemContainer');
    const valorContainer     = document.getElementById('valorDoacaoContainer');
    if (descricaoContainer) descricaoContainer.style.display = selectedTipo === 'ITEM'     ? 'block' : 'none';
    if (valorContainer)     valorContainer.style.display     = selectedTipo === 'DINHEIRO' ? 'block' : 'none';
    checkFormCompletion();
}

function selectTipo(tipo) {
    selectedTipo = tipo;
    document.getElementById('tipo_doacao').value = tipo;
    document.querySelectorAll('.tipo-btn.selected').forEach(el => el.classList.remove('selected'));
    event.target.classList.add('selected');
    updateTipoDisplay();
}

function selectDate(date) {
    selectedDate = date;
    document.getElementById('data_coleta').value = date;
    document.querySelectorAll('.day.selected').forEach(el => el.classList.remove('selected'));
    event.target.classList.add('selected');
    checkFormCompletion();
}

function selectTime(time) {
    selectedTime = time;
    document.getElementById('horario').value = time;
    document.querySelectorAll('.time-btn.selected').forEach(el => el.classList.remove('selected'));
    event.target.classList.add('selected');
    checkFormCompletion();
}

function checkFormCompletion() {
    const data    = document.getElementById('data_coleta');
    const horario = document.getElementById('horario');
    const local   = document.querySelector('select[name="local_coleta"]');
    const btn     = document.getElementById('btnAgendar');
    if (!btn) return;

    let isValid = data?.value && horario?.value && local?.value;

    if (selectedTipo === 'ITEM') {
        const desc = document.querySelector('textarea[name="descricao_item"]');
        isValid = isValid && desc?.value.trim() !== '';
    } else if (selectedTipo === 'DINHEIRO') {
        const val = document.querySelector('input[name="valor_doacao"]');
        isValid = isValid && val?.value.trim() !== '' && parseFloat(val.value) > 0;
    }

    btn.disabled = !isValid;
}

document.addEventListener('DOMContentLoaded', function () {
    checkOngSelection();
    updateTipoDisplay();

    // Clique nos cards de ONG
    document.querySelectorAll('.ong-card').forEach(card => {
        card.addEventListener('click', function (e) {
            e.stopPropagation();
            document.querySelectorAll('.ong-card').forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');
            const radio = this.querySelector('input[type="radio"]');
            if (radio) radio.checked = true;
            const btn = document.getElementById('btnEscolherOng');
            if (btn) btn.disabled = false;
        });
    });

    // Verificar radio pré-marcado
    document.querySelectorAll('input[name="ong_escolhida"]').forEach(radio => {
        if (radio.checked) {
            const parentCard = radio.closest('.ong-card');
            if (parentCard) {
                parentCard.classList.add('selected');
                const btn = document.getElementById('btnEscolherOng');
                if (btn) btn.disabled = false;
            }
        }
    });

    // Eventos de input
    document.querySelector('select[name="local_coleta"]')?.addEventListener('change', checkFormCompletion);
    document.querySelector('textarea[name="descricao_item"]')?.addEventListener('input', checkFormCompletion);
    document.querySelector('input[name="valor_doacao"]')?.addEventListener('input', checkFormCompletion);
    document.getElementById('searchInput')?.addEventListener('input', filterOngs);

    // Auto-fechar sucesso
    const successMsg = document.getElementById('successMessage');
    if (successMsg) {
        setTimeout(() => {
            successMsg.style.transition = 'opacity 0.5s ease';
            successMsg.style.opacity = '0';
            setTimeout(() => successMsg.style.display = 'none', 500);
        }, 5000);
    }
});

document.body.style.overflow = 'hidden';
</script>
</body>
</html>