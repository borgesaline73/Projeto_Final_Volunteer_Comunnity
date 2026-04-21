<?php
session_start();

// Verificar se está logado
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

require "banco.php";

$id_ong = $_SESSION["usuario_id"];
$mensagem = '';
$tipo_msg = '';

// ============================================
// BUSCA CORRIGIDA - Forçando conversão para INTEGER
// ============================================
try {
    $id_ong_int = intval($id_ong);

    $sql = "SELECT id_usuario, nome, tipo_usuario FROM usuarios WHERE id_usuario = " . $id_ong_int;
    $result = $pdo->query($sql);
    $usuario = $result->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        $sql = "SELECT id_usuario, nome, tipo_usuario FROM usuarios WHERE CAST(id_usuario AS TEXT) = '" . addslashes($id_ong) . "'";
        $result = $pdo->query($sql);
        $usuario = $result->fetch(PDO::FETCH_ASSOC);
    }

    if (!$usuario) {
        throw new Exception("Usuário ID $id_ong não encontrado.");
    }

    if ($usuario['tipo_usuario'] !== 'instituicao') {
        throw new Exception("Usuário não é instituição. Tipo: " . $usuario['tipo_usuario']);
    }

    $sql_ong = "SELECT id_ong, chave_pix FROM ongs WHERE id_ong = " . $id_ong_int;
    $result = $pdo->query($sql_ong);
    $ong_data = $result->fetch(PDO::FETCH_ASSOC);

    if (!$ong_data) {
        $pdo->exec("INSERT INTO ongs (id_ong) VALUES (" . $id_ong_int . ")");
        $result = $pdo->query($sql_ong);
        $ong_data = $result->fetch(PDO::FETCH_ASSOC);
        $mensagem = "Registro da ONG criado automaticamente.";
        $tipo_msg = "warning";
    }

    $ong = [
        'nome'     => $usuario['nome'],
        'id_ong'   => $ong_data['id_ong'],
        'chave_pix' => $ong_data['chave_pix'] ?? ''
    ];

} catch (Exception $e) {
    $mensagem = $e->getMessage();
    $tipo_msg = "error";
    error_log("ERRO: " . $e->getMessage());
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_pix']) && isset($ong)) {
    $nova_chave = trim($_POST['chave_pix'] ?? '');

    if (empty($nova_chave)) {
        $mensagem = "Informe uma chave PIX válida.";
        $tipo_msg = "error";
    } else {
        try {
            $id_int = intval($ong['id_ong']);
            $pdo->exec("UPDATE ongs SET chave_pix = '" . addslashes($nova_chave) . "' WHERE id_ong = " . $id_int);
            $mensagem = "Chave PIX salva com sucesso!";
            $tipo_msg = "success";
            $ong['chave_pix'] = $nova_chave;
        } catch (Exception $e) {
            $mensagem = "Erro ao salvar: " . $e->getMessage();
            $tipo_msg = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
  <title>Gerenciar PIX - <?= isset($ong) ? htmlspecialchars($ong['nome']) : 'ONG' ?></title>

  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/estilo_global.css">
  <link rel="stylesheet" href="css/estilo_pix.css">
</head>
<body>

<div class="phone">

  <!-- HEADER -->
  <div class="header">
    <a href="perfil-ong.php" class="back-btn">←</a>
    <div class="header-info">
      <h1>💜 Gerenciar PIX</h1>
      <p><?= isset($ong) ? htmlspecialchars($ong['nome']) : 'Sua ONG' ?></p>
    </div>
  </div>

  <!-- CONTEÚDO PRINCIPAL COM SCROLL -->
  <div class="main-content">

    <?php if (!empty($mensagem)): ?>
      <div class="message <?= $tipo_msg ?>">
        <?php
          $icone = match($tipo_msg) {
              'success' => '✅',
              'error'   => '❌',
              'warning' => 'ℹ️',
              default   => ''
          };
          echo $icone . ' ' . htmlspecialchars($mensagem);
        ?>
      </div>
    <?php endif; ?>

    <?php if (isset($ong)): ?>

      <!-- SEÇÃO: CONFIGURAR CHAVE PIX -->
      <div class="section">
        <div class="section-title">🔐 Configurar Chave PIX</div>

        <div class="info-box">
          <strong>💡 O que é chave PIX?</strong><br>
          Identificador único para receber transferências instantâneas. Pode ser CPF, CNPJ, e-mail, telefone ou chave aleatória gerada pelo seu banco.
        </div>

        <form method="POST" action="">
          <div class="form-group">
            <label for="chave_pix">Sua Chave PIX</label>
            <input
              type="text"
              id="chave_pix"
              name="chave_pix"
              value="<?= htmlspecialchars($ong['chave_pix'] ?? '') ?>"
              placeholder="Ex: email@dominio.com"
            >
            <div class="hint">CPF, CNPJ, e-mail, telefone ou chave aleatória</div>
          </div>

          <div class="form-group">
            <label>Tipos de Chave Aceitos</label>
            <div class="type-examples">
              <div class="type-example">
                <div class="title">📱 CPF</div>
                <div class="example">12345678901</div>
              </div>
              <div class="type-example">
                <div class="title">🏢 CNPJ</div>
                <div class="example">12345678000195</div>
              </div>
              <div class="type-example">
                <div class="title">📧 E-mail</div>
                <div class="example">ong@example.com</div>
              </div>
              <div class="type-example">
                <div class="title">📞 Telefone</div>
                <div class="example">+5548999999999</div>
              </div>
            </div>
          </div>

          <button type="submit" name="atualizar_pix" class="btn-save">
            💾 Salvar Chave PIX
          </button>
        </form>
      </div>

      <!-- SEÇÃO: COMO USAR -->
      <div class="section">
        <div class="section-title">📖 Como Funciona</div>
        <div class="step-list">
          <div class="step-item">
            <div class="step-num">1</div>
            <div><strong>Configure</strong> sua chave PIX acima</div>
          </div>
          <div class="step-item">
            <div class="step-num">2</div>
            <div><strong>Doadores</strong> verão a chave ao escolher "Dinheiro"</div>
          </div>
          <div class="step-item">
            <div class="step-num">3</div>
            <div><strong>Eles copiam</strong> a chave e fazem a transferência</div>
          </div>
          <div class="step-item">
            <div class="step-num">4</div>
            <div><strong>A doação</strong> é registrada automaticamente no sistema</div>
          </div>
        </div>
      </div>

    <?php else: ?>

      <div class="section">
        <div class="error-state">
          <p>❌ Erro ao carregar dados da ONG.</p>
          <p>Tente <a href="logout.php">fazer logout</a> e <a href="login.php">entrar novamente</a>.</p>
        </div>
      </div>

    <?php endif; ?>

  </div><!-- /main-content -->

  <!-- MENU INFERIOR FIXO -->
  <div class="bottom">
    <a href="feed.php" class="menu-item">
      🏠
      <span>Feed</span>
    </a>

    <a href="campanhas.php" class="menu-item">
      📢
      <span>Campanhas</span>
    </a>

    <button class="plus-btn" onclick="window.location.href='criar_post.php'">+</button>

    <a href="notificacoes.php" class="menu-item">
      🔔
      <span>Notificações</span>
    </a>

    <a href="perfil-ong.php" class="menu-item active">
      👤
      <span>Perfil</span>
    </a>
  </div>

</div><!-- /phone -->

</body>
</html>