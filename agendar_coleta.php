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
$titulo_ong = $_GET['titulo'] ?? '';

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
        ? "SELECT u.id_usuario, u.nome, u.email, u.cpf_cnpj, o.endereco, o.descricao, o.categoria, o.chave_pix 
           FROM usuarios u LEFT JOIN ongs o ON u.id_usuario = o.id_ong 
           WHERE u.tipo_usuario = 'instituicao' ORDER BY u.nome ASC"
        : "SELECT u.id_usuario, u.nome, u.email, u.cpf_cnpj, o.endereco, o.descricao, o.chave_pix 
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

// Buscar dados da ONG selecionada
$ong_selecionada = null;

function buscarOng($pdo, $id) {
    $stmt = $pdo->prepare("SELECT u.nome, u.email, u.cpf_cnpj, o.endereco, o.id_ong, o.descricao, o.chave_pix
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
    
    // Se não existir, criar automaticamente
    if (!$id_doador_table) {
        $stmt = $pdo->prepare("INSERT INTO doadores (id_doador, data_cadastro) VALUES (?, CURRENT_DATE)");
        $stmt->execute([$id_doador]);
        $id_doador_table = $id_doador;
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar/criar doador: " . $e->getMessage());
}


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
                $valor     = ($tipo_doacao === 'DINHEIRO' && !empty($valor_doacao)) ? floatval($valor_doacao) : null;
                $descricao = ($tipo_doacao === 'ITEM') ? $descricao_item : null;
                $metodo_pagamento = ($tipo_doacao === 'DINHEIRO') ? 'PIX' : 'DIRETO';
                $status_pagamento = ($tipo_doacao === 'DINHEIRO') ? 'PENDENTE' : null;

                $stmt_doacao = $pdo->prepare("INSERT INTO doacoes (id_doador, id_ong, tipo, status, descricao_item, valor, metodo_pagamento, status_pagamento, data_criacao) 
                                              VALUES (?, ?, ?, 'AGENDADA', ?, ?, ?, ?, CURRENT_TIMESTAMP)");
                $stmt_doacao->execute([$id_doador_table, $ong_escolhida, $tipo_doacao, $descricao, $valor, $metodo_pagamento, $status_pagamento]);
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

                // Redireciona para o feed com mensagem de sucesso
                $success_msg = urlencode("✅ Agendamento realizado com sucesso! A ONG foi notificada.");
                header("Location: feed.php?msg=$success_msg&tipo=success");
                exit;

            } catch (PDOException $e) {
                $pdo->rollBack();
                $mensagem = '<div class="error-message">❌ Erro ao agendar: ' . $e->getMessage() . '</div>';
                error_log("Erro no agendamento: " . $e->getMessage());
            }
        }
    }
}

// Buscar ONG via GET
if ($id_ong && $_SERVER['REQUEST_METHOD'] !== 'POST' && !$ong_selecionada) {
    try {
        $ong_selecionada = buscarOng($pdo, $id_ong);
    } catch (PDOException $e) {
        error_log("Erro ao buscar ONG específica: " . $e->getMessage());
    }
}

if (!$ong_selecionada && !empty($ong_escolhida)) {
    try {
        $ong_selecionada = buscarOng($pdo, $ong_escolhida);
    } catch (PDOException $e) {
        error_log("Erro ao buscar ONG: " . $e->getMessage());
    }
}

$mostrar_formulario = !empty($ong_escolhida);

// Gerar meses para o calendário
$meses = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
    5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
    9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
];

$ano_atual = date('Y');
$mes_atual = (int)date('m');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
<title>Agendar Coleta - Volunteer Community</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/estilo_global.css">
<link rel="stylesheet" href="css/estilo_agendar_coleta.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

<style>
    /* Estilo para confinar o SweetAlert dentro do telefone */
    .phone {
        position: relative;
        overflow: hidden;
    }

    .swal2-container.swal-inside-agendar {
        position: absolute !important;
        top: 0 !important;
        left: 0 !important;
        width: 100% !important;
        height: 100% !important;
        z-index: 9999;
    }

    .swal2-container.swal-inside-agendar .swal2-popup {
        width: 88% !important;
        max-width: 320px !important;
        border-radius: 20px !important;
        font-family: 'Poppins', sans-serif !important;
    }

    .swal2-confirm {
        background-color: #f4822f !important;
        border-radius: 50px !important;
        padding: 8px 20px !important;
        font-weight: 600 !important;
        font-size: 13px !important;
    }

    .swal2-cancel {
        border-radius: 50px !important;
        padding: 8px 20px !important;
        font-weight: 600 !important;
        font-size: 13px !important;
    }

    /* ESTILOS PIX */
    .pix-info-section {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 15px;
        padding: 20px;
        margin: 15px 0;
        color: white;
        text-align: center;
    }

    .pix-info-section h3 {
        margin: 0 0 10px 0;
        font-size: 16px;
    }

    .pix-key-box {
        background: rgba(255, 255, 255, 0.2);
        border-radius: 10px;
        padding: 15px;
        margin: 10px 0;
        word-break: break-all;
        font-weight: 600;
        font-size: 13px;
    }

    .pix-copy-btn {
        background: white;
        color: #667eea;
        border: none;
        border-radius: 8px;
        padding: 10px 15px;
        margin-top: 10px;
        cursor: pointer;
        font-weight: 600;
        font-size: 12px;
        transition: all 0.3s ease;
    }

    .pix-copy-btn:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    .pix-copy-btn.copied {
        background: #4CAF50;
        color: white;
    }

    .pix-instructions {
        background: rgba(255, 255, 255, 0.15);
        border-radius: 8px;
        padding: 12px;
        margin-top: 10px;
        font-size: 11px;
        text-align: left;
    }

    .pix-instructions ol {
        margin: 5px 0;
        padding-left: 20px;
    }
</style>
</head>
<body>

<div class="phone" id="phoneWrapper">

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
                     data-chavepix="<?= htmlspecialchars($ong['chave_pix'] ?? '') ?>"
                     onclick="selecionarOng(<?= $ong['id_usuario'] ?>, '<?= htmlspecialchars(addslashes($ong['nome'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($ong['chave_pix'] ?? '')) ?>')">
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
                  <?php if (!empty($ong['chave_pix'])): ?>
                    <div class="pix-badge" style="font-size: 10px; color: #667eea; margin-top: 5px;">💜 Aceita PIX</div>
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

    <form method="POST" action="" id="formAgendamento">
      <input type="hidden" name="ong_escolhida" value="<?= $ong_escolhida ?>">

      <div class="section">Tipo de Doação</div>
      <div class="tipo-doacao">
        <button type="button" class="tipo-btn <?= $tipo_doacao == 'ITEM' ? 'selected' : '' ?>" onclick="selectTipo('ITEM')">📦 Itens</button>
        <button type="button" class="tipo-btn <?= $tipo_doacao == 'DINHEIRO' ? 'selected' : '' ?>" onclick="selectTipo('DINHEIRO')">💰 Dinheiro</button>
      </div>
      <input type="hidden" name="tipo_doacao" id="tipo_doacao" value="<?= htmlspecialchars($tipo_doacao) ?>">

      <div class="descricao-item" id="descricaoItemContainer">
        <div class="section">Descrição dos Itens</div>
        <textarea name="descricao_item" id="descricao_item" placeholder="Descreva os itens que serão doados..."><?= htmlspecialchars($descricao_item) ?></textarea>
      </div>

      <div class="valor-doacao" id="valorDoacaoContainer">
        <div class="section">Valor da Doação (R$)</div>
        <input type="number" name="valor_doacao" id="valor_doacao" placeholder="0,00" step="0.01" min="0" value="<?= htmlspecialchars($valor_doacao) ?>">
        
        <!-- SEÇÃO PIX - EXIBIDA QUANDO SELECIONADO DINHEIRO E ONG TEM CHAVE PIX -->
        <?php if (!empty($ong_selecionada['chave_pix'])): ?>
        <div class="pix-info-section" id="pixInfoSection" style="display: none;">
          <h3>💜 PIX - Transferência Instantânea</h3>
          <p style="font-size: 12px; margin: 5px 0;">Copie a chave PIX e faça a transferência em seu banco:</p>
          
          <div class="pix-key-box" id="pixKeyBox">
            <?= htmlspecialchars($ong_selecionada['chave_pix']) ?>
          </div>
          
          <button type="button" class="pix-copy-btn" id="pixCopyBtn" onclick="copyPixKey()">
            📋 Copiar Chave PIX
          </button>
          
          <div class="pix-instructions">
            <strong>📌 Como fazer a transferência:</strong>
            <ol>
              <li>Copie a chave PIX acima</li>
              <li>Abra seu banco ou app de pagamentos</li>
              <li>Selecione "Transferência PIX"</li>
              <li>Cole a chave e informe o valor</li>
              <li>Confirme a transferência</li>
              <li>O sistema registrará sua doação como pendente</li>
            </ol>
          </div>
        </div>
        <?php endif; ?>
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

      <!-- CALENDÁRIO DINÂMICO -->
      <div class="calendar" id="calendar">
        <div class="month-selector">
          <button type="button" onclick="changeMonth(-1)">◀</button>
          <select id="monthSelect" onchange="updateCalendar()">
            <?php foreach ($meses as $num => $nome): ?>
              <option value="<?= $num ?>" <?= $num == $mes_atual ? 'selected' : '' ?>><?= $nome ?></option>
            <?php endforeach; ?>
          </select>
          <div class="year-display">
            <span id="yearDisplay"><?= $ano_atual ?></span>
            <button type="button" onclick="changeYear(1)" style="margin-left: 5px;">▲</button>
            <button type="button" onclick="changeYear(-1)">▼</button>
          </div>
          <button type="button" onclick="changeMonth(1)">▶</button>
        </div>
        <div class="weekdays">
          <span>Dom</span><span>Seg</span><span>Ter</span><span>Qua</span><span>Qui</span><span>Sex</span><span>Sáb</span>
        </div>
        <div class="days" id="daysContainer"></div>
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
        <select name="local_coleta" id="localColeta" required>
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
        <button type="button" class="btn-primary" id="btnAgendar" disabled onclick="confirmarAgendamento()">✅ Agendar</button>
        <button type="button" class="btn-secondary" onclick="window.location.href='agendar_coleta.php'">🔄 Trocar ONG</button>
      </div>
    </form>
    <?php endif; ?>

  </div>

  <!-- MENU INFERIOR -->
  <div class="bottom">
    <a href="feed.php" class="menu-item">🏠<span>Feed</span></a>
    <a href="campanhas.php" class="menu-item">📢<span>Campanhas</span></a>
    <button class="plus-btn" onclick="window.location.href='agendar_coleta.php'">+</button>
    <a href="notificacoes.php" class="menu-item">🔔<span>Notificações</span></a>
    <a href="perfil.php" class="menu-item">👤<span>Perfil</span></a>
  </div>

</div>

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// ─── Referência ao elemento .phone para confinar os modais ───────────────────
const phoneEl = document.getElementById('phoneWrapper');

const swalAgendar = Swal.mixin({
    target: phoneEl,
    confirmButtonColor: '#f4822f',
    cancelButtonColor: '#aaa',
    customClass: {
        container: 'swal-inside-agendar',
        popup: 'swal-popup-agendar'
    }
});

// ===== VARIÁVEIS DO CALENDÁRIO =====
let currentYear = <?= $ano_atual ?>;
let currentMonth = <?= $mes_atual ?> - 1;
let selectedDate = '<?= $data_selecionada ?>';
let selectedTime = '<?= $horario_selecionado ?>';
let selectedTipo = '<?= $tipo_doacao ?>';

// ===== FUNÇÃO PARA COPIAR CHAVE PIX =====
function copyPixKey() {
    const pixKeyBox = document.getElementById('pixKeyBox');
    if (!pixKeyBox) return;
    
    const pixKey = pixKeyBox.textContent.trim();
    const btn = document.getElementById('pixCopyBtn');
    
    navigator.clipboard.writeText(pixKey).then(() => {
        btn.textContent = '✅ Copiado!';
        btn.classList.add('copied');
        
        setTimeout(() => {
            btn.textContent = '📋 Copiar Chave PIX';
            btn.classList.remove('copied');
        }, 2000);
    }).catch(err => {
        alert('Erro ao copiar: ' + err);
    });
}

// ===== FUNÇÃO PARA GERAR O CALENDÁRIO =====
function updateCalendar() {
    const monthSelect = document.getElementById('monthSelect');
    currentMonth = parseInt(monthSelect.value) - 1;
    const yearDisplay = document.getElementById('yearDisplay');
    yearDisplay.textContent = currentYear;
    
    const firstDay = new Date(currentYear, currentMonth, 1);
    const lastDay = new Date(currentYear, currentMonth + 1, 0);
    const startDayOfWeek = firstDay.getDay();
    const daysInMonth = lastDay.getDate();
    
    const daysContainer = document.getElementById('daysContainer');
    daysContainer.innerHTML = '';
    
    for (let i = 0; i < startDayOfWeek; i++) {
        const emptyDay = document.createElement('span');
        emptyDay.className = 'day empty';
        emptyDay.innerHTML = '';
        daysContainer.appendChild(emptyDay);
    }
    
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    for (let day = 1; day <= daysInMonth; day++) {
        const dayElement = document.createElement('span');
        dayElement.className = 'day';
        dayElement.textContent = day;
        
        const dateStr = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        dayElement.onclick = () => selectDate(dateStr);
        
        const currentDate = new Date(currentYear, currentMonth, day);
        if (currentDate.toDateString() === today.toDateString()) {
            dayElement.classList.add('highlight');
        }
        
        if (selectedDate === dateStr) {
            dayElement.classList.add('selected');
        }
        
        daysContainer.appendChild(dayElement);
    }
}

function changeMonth(delta) {
    let newMonth = currentMonth + delta;
    let newYear = currentYear;
    
    if (newMonth < 0) {
        newMonth = 11;
        newYear--;
    } else if (newMonth > 11) {
        newMonth = 0;
        newYear++;
    }
    
    currentYear = newYear;
    currentMonth = newMonth;
    
    document.getElementById('monthSelect').value = currentMonth + 1;
    document.getElementById('yearDisplay').textContent = currentYear;
    updateCalendar();
}

function changeYear(delta) {
    currentYear += delta;
    document.getElementById('yearDisplay').textContent = currentYear;
    updateCalendar();
}

// ===== FUNÇÕES DE FILTRO E SELEÇÃO =====
function filterOngs() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase().trim();
    document.querySelectorAll('.ong-card').forEach(card => {
        const nome = card.getAttribute('data-nome').toLowerCase();
        const desc = card.getAttribute('data-descricao').toLowerCase();
        card.style.display = (searchTerm === '' || nome.includes(searchTerm) || desc.includes(searchTerm)) ? 'block' : 'none';
    });
    checkOngSelection();
}

let selectedOngPixKey = '';

function selecionarOng(idOng, nomeOng, chavePix) {
    selectedOngPixKey = chavePix;
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
    const pixSection        = document.getElementById('pixInfoSection');
    
    if (descricaoContainer) descricaoContainer.style.display = selectedTipo === 'ITEM' ? 'block' : 'none';
    if (valorContainer) valorContainer.style.display = selectedTipo === 'DINHEIRO' ? 'block' : 'none';
    if (pixSection) pixSection.style.display = selectedTipo === 'DINHEIRO' ? 'block' : 'none';
    
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
    const days = document.querySelectorAll('.day');
    days.forEach(day => {
        if (day.textContent && !day.classList.contains('empty')) {
            const dayNum = parseInt(day.textContent);
            const dateStr = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(dayNum).padStart(2, '0')}`;
            if (dateStr === date) {
                day.classList.add('selected');
            }
        }
    });
    
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

// ===== FUNÇÃO DE CONFIRMAÇÃO DE AGENDAMENTO =====
async function confirmarAgendamento() {
    const tipoDoacao = document.getElementById('tipo_doacao').value;
    const descricaoItem = document.getElementById('descricao_item')?.value.trim() || '';
    const valorDoacao = document.getElementById('valor_doacao')?.value.trim() || '';
    const data = document.getElementById('data_coleta').value;
    const horario = document.getElementById('horario').value;
    const local = document.getElementById('localColeta').value;
    const nomeOng = '<?= addslashes($ong_selecionada['nome'] ?? '') ?>';
    const pixKey = '<?= addslashes($ong_selecionada['chave_pix'] ?? '') ?>';
    
    if (!data || !horario || !local) {
        await swalAgendar.fire({
            title: 'Campos incompletos',
            text: 'Preencha todos os campos antes de agendar!',
            icon: 'warning',
            confirmButtonText: 'Ok'
        });
        return;
    }
    
    let resumo = '';
    if (tipoDoacao === 'ITEM') {
        if (!descricaoItem) {
            await swalAgendar.fire({
                title: 'Descrição necessária',
                text: 'Por favor, descreva os itens que serão doados!',
                icon: 'warning',
                confirmButtonText: 'Ok'
            });
            return;
        }
        resumo = `<strong>📦 Itens:</strong> ${descricaoItem.substring(0, 100)}${descricaoItem.length > 100 ? '...' : ''}<br>`;
    } else {
        if (!valorDoacao || parseFloat(valorDoacao) <= 0) {
            await swalAgendar.fire({
                title: 'Valor inválido',
                text: 'Por favor, informe um valor válido para doação!',
                icon: 'warning',
                confirmButtonText: 'Ok'
            });
            return;
        }
        
        let pixHtml = '';
        if (pixKey) {
            pixHtml = `<div style="background: #f0f4ff; border-radius: 8px; padding: 10px; margin: 10px 0; font-size: 11px;">
                <strong style="color: #667eea;">💜 Chave PIX da ONG:</strong><br>
                <code style="word-break: break-all;">${pixKey}</code>
            </div>`;
        }
        
        resumo = `<strong>💰 Valor:</strong> R$ ${parseFloat(valorDoacao).toFixed(2).replace('.', ',')}<br>${pixHtml}`;
    }
    
    const mensagem = `
        <div style="text-align: left;">
            ${resumo}
            <strong>🏢 ONG:</strong> ${nomeOng}<br>
            <strong>📅 Data:</strong> ${data}<br>
            <strong>⏰ Horário:</strong> ${horario}<br>
            <strong>📍 Local:</strong> ${local}<br>
            <hr style="margin: 10px 0;">
            <p style="color: #f4822f; font-weight: bold;">Deseja confirmar este agendamento?</p>
        </div>
    `;
    
    const result = await swalAgendar.fire({
        title: 'Confirmar Agendamento',
        html: mensagem,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '✅ Sim, agendar',
        cancelButtonText: '❌ Cancelar'
    });
    
    if (result.isConfirmed) {
        const form = document.getElementById('formAgendamento');
        const btnAgendar = document.getElementById('btnAgendar');
        btnAgendar.disabled = true;
        btnAgendar.textContent = '⏳ Agendando...';
        
        const inputData = document.createElement('input');
        inputData.type = 'hidden';
        inputData.name = 'data_coleta';
        inputData.value = data;
        form.appendChild(inputData);
        
        const inputHorario = document.createElement('input');
        inputHorario.type = 'hidden';
        inputHorario.name = 'horario';
        inputHorario.value = horario;
        form.appendChild(inputHorario);
        
        const inputLocal = document.createElement('input');
        inputLocal.type = 'hidden';
        inputLocal.name = 'local_coleta';
        inputLocal.value = local;
        form.appendChild(inputLocal);
        
        const inputTipo = document.createElement('input');
        inputTipo.type = 'hidden';
        inputTipo.name = 'tipo_doacao';
        inputTipo.value = tipoDoacao;
        form.appendChild(inputTipo);
        
        if (tipoDoacao === 'ITEM') {
            const inputDesc = document.createElement('input');
            inputDesc.type = 'hidden';
            inputDesc.name = 'descricao_item';
            inputDesc.value = descricaoItem;
            form.appendChild(inputDesc);
        } else {
            const inputValor = document.createElement('input');
            inputValor.type = 'hidden';
            inputValor.name = 'valor_doacao';
            inputValor.value = valorDoacao;
            form.appendChild(inputValor);
        }
        
        const submitBtn = document.createElement('input');
        submitBtn.type = 'submit';
        submitBtn.name = 'agendar';
        submitBtn.value = 'agendar';
        submitBtn.style.display = 'none';
        form.appendChild(submitBtn);
        
        submitBtn.click();
    }
}

// ===== INICIALIZAÇÃO =====
document.addEventListener('DOMContentLoaded', function () {
    checkOngSelection();
    updateTipoDisplay();
    updateCalendar();
    
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
    
    document.querySelector('select[name="local_coleta"]')?.addEventListener('change', checkFormCompletion);
    document.querySelector('textarea[name="descricao_item"]')?.addEventListener('input', checkFormCompletion);
    document.querySelector('input[name="valor_doacao"]')?.addEventListener('input', checkFormCompletion);
    document.getElementById('searchInput')?.addEventListener('input', filterOngs);
    
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