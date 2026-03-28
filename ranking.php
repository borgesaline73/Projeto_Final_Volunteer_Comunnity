<?php
session_start();
require "banco.php";

// Bloqueio para usuários não logados
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

// Pega o tipo do usuário
$tipo = $_SESSION["usuario_tipo"] ?? null;

// Define para onde o botão "+" vai levar
if ($tipo === "instituicao") {
    $rotaPlus = "criar_post.php";
    $rotaPerfil = "perfil-ong.php";
} else {
    $rotaPlus = "agendar_coleta.php";
    $rotaPerfil = "perfil.php";
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ranking - Volunteer Community</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

<style>
:root{
  --orange:#f4822f;
  --bg:#f6f4f2;
  --text:#2b2b2b;
  --muted:#9a9a9a;
}

*{
  box-sizing: border-box;
  font-family: "Poppins", sans-serif;
}

body{
  margin:0;
  background: var(--bg);
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
  overflow:hidden;
  box-shadow:0 10px 40px rgba(0,0,0,0.06);
  display:flex;
  flex-direction:column;
  position:relative;
}

/* HEADER */
.header{
  padding:24px 20px 10px;
  display:flex;
  align-items:center;
  justify-content:space-between;
  flex-shrink:0;
}

.header h1{
  font-size:24px;
  font-weight:700;
  color:var(--orange);
  margin:0;
}

.star{
  font-size:20px;
  color:var(--orange);
}

/* CONTEÚDO */
.content{
  flex:1;
  padding:20px;
  display:flex;
  align-items:center;
  justify-content:center;
  overflow-y:auto;
  padding-bottom:80px;
}

.empty{
  width:100%;
  border-radius:18px;
  background:linear-gradient(0deg, #f0f0f0, #fafafa);
  padding:40px 20px;
  text-align:center;
  color:var(--muted);
  font-size:14px;
}

/* MENU INFERIOR FIXO */
.bottom{
  height:74px;
  border-top:1px solid #eee;
  display:flex;
  align-items:center;
  justify-content:space-around;
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
  gap:2px;
  font-size:11px;
  color:#aaa;
  text-decoration:none;
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
</style>
</head>

<body>

<div class="phone">

  <!-- HEADER -->
  <div class="header">
    <h1>Ranking</h1>
    <div class="star">⭐</div>
  </div>

  <!-- CONTEÚDO VAZIO -->
  <div class="content">
    <div class="empty">
      Ainda não há nenhum participante no ranking.<br>
      Em breve o ranking será atualizado.
    </div>
  </div>

  <!-- MENU INFERIOR FIXO -->
  <div class="bottom">
    <a href="feed.php" class="menu-item">
      🏠
      <span>Feed</span>
    </a>

    <a href="ranking.php" class="menu-item active">
      🏆
      <span>Ranking</span>
    </a>

    <!-- BOTÃO + FUNCIONAL -->
    <button class="plus-btn" onclick="window.location.href='<?= $rotaPlus ?>'">+</button>

    <a href="notificacoes.php" class="menu-item">
      🔔
      <span>Notificações</span>
    </a>

    <!-- PERFIL CORRETO BASEADO NO TIPO DE USUÁRIO -->
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