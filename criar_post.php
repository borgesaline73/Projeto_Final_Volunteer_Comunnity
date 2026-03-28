<?php
session_start();

// Bloqueia acesso se não estiver logado
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

// Só instituição pode acessar
$tipo = $_SESSION["usuario_tipo"] ?? "";
if ($tipo !== "instituicao") {
    header("Location: feed.php");
    exit;
}

// Define rota do botão + e perfil
$rotaPlus = "criar_post.php";
$rotaPerfil = "perfil-ong.php";
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Criar Post - Volunteer Community</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/estilo_global.css">
<link rel="stylesheet" href="css/estilo_criar_post.css">
</head>

<body>

<div class="phone">

  <div class="header">
    <button class="back" onclick="history.back()">←</button>
  </div>

  <h1>Criar publicação</h1>

  <!-- FORMULÁRIO -->
  <form action="salvar_post.php" method="POST" enctype="multipart/form-data">

    <input type="text" name="titulo" placeholder="Título da publicação" required>

    <select name="categoria" required>
      <option value="">Selecione uma categoria</option>
      <option>Educação</option>
      <option>Saúde</option>
      <option>Alimentos</option>
      <option>Campanhas</option>
    </select>

    <textarea name="descricao" placeholder="Descreva a necessidade..." required></textarea>

    <input type="file" name="imagem" accept="image/*">

    <button type="submit">Publicar</button>

  </form>

  <!-- MENU INFERIOR FIXO (opcional) -->
  <div class="bottom">
    <a href="feed.php" class="menu-item">
      🏠
      <span>Feed</span>
    </a>
    
    <!-- BOTÃO + CENTRAL -->
    <button class="plus-btn" onclick="window.location.href='<?= $rotaPlus ?>'">+</button>
    
    <a href="notificacoes.php" class="menu-item">
      🔔
      <span>Notificações</span>
    </a>
    
    <a href="<?= $rotaPerfil ?>" class="menu-item">
      👤
      <span>Perfil</span>
    </a>
  </div>

</div>

<script>
// Prevenir scroll do body
document.body.style.overflow = 'hidden';
</script>

</body>
</html>