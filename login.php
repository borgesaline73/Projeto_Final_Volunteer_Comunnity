<?php
require "banco.php";
session_start();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST["email"] ?? "";
    $senha = $_POST["senha"] ?? "";

    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = :email AND ativo = true");
    $stmt->execute([":email" => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($senha, $user["senha"])) {
        $_SESSION["usuario_id"]   = $user["id_usuario"];
        $_SESSION["usuario_nome"] = $user["nome"];
        $_SESSION["usuario_tipo"] = $user["tipo_usuario"];

        header("Location: feed.php");
        exit();
    } else {
        echo "<script>alert('❌ Email ou senha incorretos.'); history.back();</script>";
    }
}
?>

<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Volunteer Community – Login</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/estilo_global.css">
  <link rel="stylesheet" href="css/estilo_login.css">
</head>
<body>

  <section class="login-screen">
    <div class="header">
      <img src="imagens/logo.png" alt="Volunteer" class="logo">
    </div>
    <h2>Bem vindo de volta!</h2>

    <form class="form" action="login.php" method="post">
      <input type="text" name="email" placeholder="E-mail / usuário" required>
      <input type="password" name="senha" placeholder="Senha" required>
      <button type="submit" class="btn primary">Acessar</button>
    </form>

    <a href="#" class="forgot">Esqueceu sua senha?</a>
    <div class="divider">ou</div>
    <button class="btn facebook" onclick="mostrarAlerta('Facebook')">Log in with Facebook</button>
    <button class="btn google" onclick="mostrarAlerta('Google')">Log in with Google</button>
    <p class="signup">Não possui conta? <a href="cadastro.php">Crie agora</a></p>
  </section>

  <script>
    function mostrarAlerta(provedor) {
      alert(`⚠️ A API de login com ${provedor} está temporariamente fora do ar.\n\nPor favor, utilize o login tradicional.`);
    }
  </script>

</body>
</html>