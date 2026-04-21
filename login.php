<?php
require "banco.php";
session_start();

$mensagem_erro = '';
$tipo_erro = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST["email"] ?? "";
    $senha = $_POST["senha"] ?? "";

    // Primeiro verifica se o email existe
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = :email");
    $stmt->execute([":email" => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $mensagem_erro = "E-mail não encontrado. Verifique se o e-mail está correto ou <a href='cadastro.php'>crie uma conta</a>.";
        $tipo_erro = "error";
    } elseif ($user['ativo'] != 1) {
        $mensagem_erro = "Sua conta está desativada. Entre em contato com o suporte.";
        $tipo_erro = "error";
    } elseif (!password_verify($senha, $user["senha"])) {
        $mensagem_erro = "Senha incorreta. Tente novamente.";
        $tipo_erro = "error";
    } else {
        // Login bem sucedido
        $_SESSION["usuario_id"]   = $user["id_usuario"];
        $_SESSION["usuario_nome"] = $user["nome"];
        $_SESSION["usuario_tipo"] = $user["tipo_usuario"];

        header("Location: feed.php");
        exit();
    }
}
?>

<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Volunteer Community – Login</title>

  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/estilo_global.css">
  <link rel="stylesheet" href="css/estilo_login.css">
  
  <!-- SweetAlert2 CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  
  <style>
    /* ===== ESTILO DO TELEFONE PARA CONFINAR O MODAL ===== */
    .login-screen {
      position: relative;
      overflow: hidden;
    }

    /* Overlay do SweetAlert fica dentro do .login-screen */
    .swal2-container.swal-inside-login {
      position: absolute !important;
      top: 0 !important;
      left: 0 !important;
      width: 100% !important;
      height: 100% !important;
      z-index: 9999;
    }

    /* Popup centralizado dentro do container acima */
    .swal2-container.swal-inside-login .swal2-popup {
      width: 88% !important;
      max-width: 320px !important;
      border-radius: 20px !important;
      font-family: 'Nunito', sans-serif !important;
    }

    /* Toast também fica dentro do telefone */
    .swal2-container.swal-inside-login.swal2-top-end,
    .swal2-container.swal-inside-login.swal2-top-right {
      top: 8px !important;
      right: 8px !important;
      width: auto !important;
      height: auto !important;
    }

    .swal2-confirm {
      background-color: #f5920a !important;
      border-radius: 50px !important;
      padding: 10px 24px !important;
      font-weight: 600 !important;
    }

    .swal2-confirm:hover {
      background-color: #d97f07 !important;
    }

    .swal2-title {
      font-size: 20px !important;
      font-weight: 800 !important;
      color: #2d2418 !important;
    }

    .swal2-html-container {
      font-size: 14px !important;
      color: #6b6056 !important;
    }

    /* Botão de loading desabilitado */
    .btn.primary:disabled {
      opacity: 0.7;
      cursor: not-allowed;
    }
  </style>
</head>
<body>

<section class="login-screen" id="loginWrapper">

  <!-- ── Header laranja com curva ── -->
  <div class="header">
    <a href="index.php" class="btn-back" aria-label="Voltar">&#8592;</a>

    <div class="logo-wrapper">
      <img src="imagens/logo.png" alt="Volunteer Community" class="logo">
      <span class="logo-text">Volunteer</span>
      <span class="logo-sub">Community</span>
    </div>
  </div>

  <!-- ── Conteúdo principal ── -->
  <div class="content">

    <h2>Bem vindo de volta!</h2>

    <form class="form" action="login.php" method="post" id="formLogin">
      <input type="text" name="email" id="email" placeholder="E-mail / usuário" required autocomplete="email">
      <input type="password" name="senha" id="senha" placeholder="Senha" required autocomplete="current-password">
      <button type="submit" class="btn primary" id="btnLogin">Acessar</button>
    </form>

    <a href="recuperar_senha.php" class="forgot">Esqueceu sua senha?</a>

    <p class="signup">
      Não possui conta? <a href="cadastro.php">Crie agora</a>
    </p>

  </div>
  
</section>

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>

// ─── Referência ao elemento .login-screen para confinar os modais ───────────
const loginEl = document.getElementById('loginWrapper');

// Tema padrão para o SweetAlert2 - confinado dentro do telefone
const swalLogin = Swal.mixin({
  target: loginEl,
  confirmButtonColor: '#f5920a',
  cancelButtonColor: '#aaa',
  customClass: {
    container: 'swal-inside-login',
    popup: 'swal-popup-login'
  }
});

<?php if (!empty($mensagem_erro) && !empty($tipo_erro)): ?>
  
// Mostrar modal de erro dentro do telefone
document.addEventListener('DOMContentLoaded', function() {
    swalLogin.fire({
        title: '❌ Acesso negado',
        html: '<?= htmlspecialchars($mensagem_erro) ?>',
        icon: '<?= $tipo_erro ?>',
        confirmButtonText: 'Tentar novamente',
        allowOutsideClick: false,
        allowEscapeKey: false
    }).then(() => {
        document.getElementById('senha').value = '';
        document.getElementById('email').focus();
    });
});
<?php endif; ?>

// ─── Validação antes de enviar o formulário ────────────────────────────────
document.getElementById('formLogin').addEventListener('submit', function(e) {
    const email = document.getElementById('email').value.trim();
    const senha = document.getElementById('senha').value.trim();
    
    // Validação de email básica
    if (email && !email.includes('@')) {
        e.preventDefault();
        swalLogin.fire({
            title: 'E-mail inválido',
            text: 'Por favor, digite um e-mail válido (exemplo@dominio.com)',
            icon: 'warning',
            confirmButtonText: 'Ok'
        });
        return false;
    }
    
    if (!email || !senha) {
        e.preventDefault();
        swalLogin.fire({
            title: 'Campos vazios',
            text: 'Por favor, preencha todos os campos.',
            icon: 'warning',
            confirmButtonText: 'Ok'
        });
        return false;
    }
    
    // Mostrar loading ao enviar
    const btn = document.getElementById('btnLogin');
    btn.disabled = true;
    btn.innerHTML = '⏳ Entrando...';
    
    return true;
});

// ─── Limpar mensagem de erro ao digitar ─────────────────────────────────────
document.getElementById('email').addEventListener('input', function() {
    if (window.location.search) {
        window.history.replaceState({}, document.title, window.location.pathname);
    }
});

document.getElementById('senha').addEventListener('input', function() {
    if (window.location.search) {
        window.history.replaceState({}, document.title, window.location.pathname);
    }
});

// ─── Adicionar efeito de foco nos inputs ────────────────────────────────────
const inputs = document.querySelectorAll('.form input');
inputs.forEach(input => {
    input.addEventListener('focus', function() {
        this.parentElement.classList.add('focused');
    });
    input.addEventListener('blur', function() {
        this.parentElement.classList.remove('focused');
    });
});
</script>

</body>
</html>