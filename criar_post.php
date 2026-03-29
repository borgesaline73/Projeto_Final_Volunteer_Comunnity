<?php
session_start();
require "banco.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

$tipo = $_SESSION["usuario_tipo"] ?? "";
if ($tipo !== "instituicao") {
    header("Location: feed.php");
    exit;
}

$id_ong     = $_SESSION["usuario_id"];
$rotaPlus   = "criar_post.php";
$rotaPerfil = "perfil-ong.php";

// Modo edição
$modo_edicao = false;
$post        = null;
$id_post     = (int)($_GET["id"] ?? 0);

if ($id_post > 0) {
    $stmt = $pdo->prepare("SELECT * FROM posts WHERE id_post = ? AND id_usuario = ?");
    $stmt->execute([$id_post, $id_ong]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($post) {
        $modo_edicao = true;
    } else {
        header("Location: perfil-ong.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $modo_edicao ? 'Editar Post' : 'Criar Post' ?></title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/estilo_global.css">
<link rel="stylesheet" href="css/estilo_criar_post.css">
</head>

<body>

<div class="phone">

  <!-- ÁREA COM SCROLL (IMPORTANTE) -->
  <div class="form-content">

    <!-- HEADER -->
    <div class="header">
      <button class="back" onclick="history.back()">←</button>
    </div>

    <!-- TÍTULO -->
    <h1><?= $modo_edicao ? 'Editar publicação' : 'Criar publicação' ?></h1>

    <!-- FORM -->
    <form action="salvar_post.php" method="POST" enctype="multipart/form-data">

      <?php if ($modo_edicao): ?>
        <input type="hidden" name="id_post" value="<?= $post['id_post'] ?>">
        <input type="hidden" name="imagem_atual" value="<?= htmlspecialchars($post['imagem'] ?? '') ?>">
      <?php endif; ?>

      <!-- TÍTULO -->
      <input type="text" name="titulo"
        placeholder="Título da publicação"
        value="<?= $modo_edicao ? htmlspecialchars($post['titulo']) : '' ?>"
        required>

      <!-- CATEGORIA -->
      <select name="categoria" required>
        <option value="">Selecione uma categoria</option>
        <?php
        $categorias = ['Educação', 'Saúde', 'Alimentos', 'Campanhas'];
        foreach ($categorias as $cat):
          $selected = ($modo_edicao && $post['categoria'] === $cat) ? 'selected' : '';
        ?>
          <option value="<?= $cat ?>" <?= $selected ?>><?= $cat ?></option>
        <?php endforeach; ?>
      </select>

      <!-- DESCRIÇÃO -->
      <textarea name="descricao"
        placeholder="Descreva a necessidade..."
        required><?= $modo_edicao ? htmlspecialchars($post['descricao']) : '' ?></textarea>

      <!-- IMAGEM ATUAL -->
      <?php if ($modo_edicao && !empty($post['imagem'])): ?>
        <div class="imagem-atual">
          <p>Imagem atual:</p>
          <img src="uploads/<?= htmlspecialchars($post['imagem']) ?>" alt="Imagem atual"
               onerror="this.parentElement.style.display='none'">

          <label class="trocar-imagem">
            <input type="checkbox" name="remover_imagem" value="1">
            Remover imagem atual
          </label>
        </div>
      <?php endif; ?>

      <!-- INPUT FILE -->
      <label class="label-file">
        <?= ($modo_edicao && !empty($post['imagem'])) ? 'Trocar imagem (opcional)' : 'Adicionar imagem (opcional)' ?>
      </label>

      <input type="file" name="imagem" accept="image/*" id="inputImagem">
      <div class="preview-nome" id="previewNome"></div>

      <!-- BOTÃO -->
      <button type="submit">
        <?= $modo_edicao ? '💾 Salvar alterações' : '📢 Publicar' ?>
      </button>

    </form>

  </div>

  <!-- MENU FIXO (FORA DO SCROLL) -->
  <div class="bottom">
    <a href="feed.php" class="menu-item">🏠<span>Feed</span></a>
    <a href="campanhas.php" class="menu-item">
      📢
      <span>Campanhas</span>
    </a>
    <button class="plus-btn" onclick="window.location.href='<?= $rotaPlus ?>'">+</button>
    <a href="notificacoes.php" class="menu-item">🔔<span>Notificações</span></a>
    <a href="<?= $rotaPerfil ?>" class="menu-item">👤<span>Perfil</span></a>
  </div>

</div>

<script>
// Preview nome do arquivo
document.getElementById('inputImagem').addEventListener('change', function () {
  const nome = this.files[0]?.name || '';
  document.getElementById('previewNome').textContent = nome ? '📎 ' + nome : '';
});
</script>

</body>
</html>