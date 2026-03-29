<?php
session_start();
require "banco.php";

if (!isset($_SESSION["usuario_id"]) || $_SESSION["usuario_tipo"] !== "instituicao") {
    header("Location: login.php"); exit;
}

$id_ong = $_SESSION["usuario_id"];
$erro = "";
$sucesso = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $titulo    = trim($_POST["titulo"] ?? "");
    $descricao = trim($_POST["descricao"] ?? "");
    $imagem    = null;

    if (empty($titulo) || empty($descricao)) {
        $erro = "Preencha todos os campos.";
    } else {
        // Upload de imagem
        if (!empty($_FILES["imagem"]["name"])) {
            $ext  = strtolower(pathinfo($_FILES["imagem"]["name"], PATHINFO_EXTENSION));
            $permitidos = ["jpg","jpeg","png","gif","webp"];
            if (!in_array($ext, $permitidos)) {
                $erro = "Formato de imagem inválido.";
            } else {
                $nome_arquivo = uniqid("destino_") . "." . $ext;
                move_uploaded_file($_FILES["imagem"]["tmp_name"], "uploads/" . $nome_arquivo);
                $imagem = $nome_arquivo;
            }
        }

        if (empty($erro)) {
            $stmt = $pdo->prepare("INSERT INTO destino_doacoes (id_ong, titulo, descricao, imagem) VALUES (?,?,?,?)");
            $stmt->execute([$id_ong, $titulo, $descricao, $imagem]);
            header("Location: perfil-ong.php"); exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Publicar Destino da Doação</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/estilo_global.css">
<style>
  body { background:#f6f4f2; display:flex; justify-content:center; align-items:center; min-height:100vh; padding:20px; font-family:'Poppins',sans-serif; }
  .card { background:#fff; border-radius:24px; padding:28px 24px; max-width:420px; width:100%; box-shadow:0 8px 30px rgba(0,0,0,0.08); }
  h2 { margin:0 0 20px; font-size:20px; color:#2b2b2b; }
  label { display:block; font-size:13px; font-weight:600; color:#555; margin-bottom:5px; }
  input[type=text], textarea {
    width:100%; box-sizing:border-box; border:1px solid #ddd;
    border-radius:12px; padding:10px 14px; font-size:14px;
    font-family:'Poppins',sans-serif; outline:none; transition:border 0.2s;
  }
  input[type=text]:focus, textarea:focus { border-color:#f4822f; }
  textarea { resize:vertical; min-height:120px; }
  .field { margin-bottom:16px; }
  .btn-salvar {
    background:#f4822f; color:#fff; border:none; border-radius:20px;
    padding:12px 24px; font-size:14px; font-weight:600;
    font-family:'Poppins',sans-serif; cursor:pointer; width:100%;
    transition:background 0.2s;
  }
  .btn-salvar:hover { background:#e67329; }
  .btn-voltar {
    display:block; text-align:center; margin-top:12px;
    color:#888; font-size:13px; cursor:pointer; text-decoration:none;
  }
  .erro { background:#fdecea; color:#c0392b; border-radius:10px; padding:10px 14px; font-size:13px; margin-bottom:16px; }
  .file-label {
    display:block; border:1px dashed #ddd; border-radius:12px;
    padding:12px 14px; font-size:13px; color:#888; cursor:pointer; text-align:center;
  }
  input[type=file] { display:none; }
  #nome-arquivo { font-size:12px; color:#f4822f; margin-top:4px; }
</style>
</head>
<body>
<div class="card">
  <h2>💛 Para onde vai a doação?</h2>

  <?php if ($erro): ?>
    <div class="erro"><?= htmlspecialchars($erro) ?></div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data">
    <div class="field">
      <label>Título</label>
      <input type="text" name="titulo" placeholder="Ex: Compra de cestas básicas" maxlength="150" required>
    </div>
    <div class="field">
      <label>Descrição</label>
      <textarea name="descricao" placeholder="Conte como a doação está sendo utilizada..." required></textarea>
    </div>
    <div class="field">
      <label>Imagem (opcional)</label>
      <label class="file-label" for="imagem-input">📷 Clique para adicionar uma foto</label>
      <input type="file" id="imagem-input" name="imagem" accept="image/*" onchange="mostrarNome(this)">
      <div id="nome-arquivo"></div>
    </div>
    <button type="submit" class="btn-salvar">✅ Publicar</button>
  </form>
  <a href="perfil-ong.php" class="btn-voltar">← Voltar ao perfil</a>
</div>
<script>
function mostrarNome(input) {
  document.getElementById('nome-arquivo').textContent = input.files[0]?.name || '';
}
</script>
</body>
</html>