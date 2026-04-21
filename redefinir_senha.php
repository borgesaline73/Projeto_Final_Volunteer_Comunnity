<?php
require "banco.php";
session_start();

$token    = trim($_GET["token"] ?? "");
$mensagem = "";
$tipo     = "";
$valido   = false;
$registro = null;

// Valida token
if ($token) {
    $stmt = $pdo->prepare("
        SELECT r.id, r.id_usuario, r.expira_em, r.usado, u.nome
        FROM recuperacao_senha r
        JOIN usuarios u ON u.id_usuario = r.id_usuario
        WHERE r.token = :token
        LIMIT 1
    ");
    $stmt->execute([":token" => $token]);
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($registro) {
        $expira_em = strtotime($registro["expira_em"]);
        $agora = time();

        // Debug (lembrar de remover depois do projeto pronto)
        error_log("Token encontrado: " . $token);
        error_log("Expira em: " . $registro["expira_em"]);
        error_log("Expira timestamp: " . $expira_em);
        error_log("Agora timestamp: " . $agora);
        error_log("Usado: " . ($registro["usado"] ? "SIM" : "NÃO"));

        if (!$registro["usado"] && $expira_em > $agora) {
            $valido = true;
        } else {
            if ($registro["usado"]) {
                $mensagem = "Este link já foi utilizado. Solicite um novo.";
            } elseif ($expira_em <= $agora) {
                $mensagem = "Este link expirou em " . date("d/m/Y H:i:s", $expira_em) . ". Solicite um novo.";
            }
            $tipo = "erro";
        }
    } else {
        $mensagem = "Link inválido. Solicite um novo.";
        $tipo = "erro";
    }
} else {
    $mensagem = "Nenhum token foi fornecido.";
    $tipo = "erro";
}

// Processa nova senha
if ($_SERVER["REQUEST_METHOD"] === "POST" && $valido && $registro) {
    $nova     = $_POST["nova_senha"] ?? "";
    $confirma = $_POST["confirma_senha"] ?? "";

    if (strlen($nova) < 8) {
        $mensagem = "A senha deve ter pelo menos 8 caracteres.";
        $tipo     = "erro_validacao";
    } elseif ($nova !== $confirma) {
        $mensagem = "As senhas não coincidem.";
        $tipo     = "erro_validacao";
    } else {
        try {
            $hash = password_hash($nova, PASSWORD_DEFAULT);

            // Inicia transação
            $pdo->beginTransaction();

            // Atualiza senha
            $upd = $pdo->prepare("UPDATE usuarios SET senha = :senha WHERE id_usuario = :id");
            $upd->execute([":senha" => $hash, ":id" => $registro["id_usuario"]]);

            // Marca token como usado
            $used = $pdo->prepare("UPDATE recuperacao_senha SET usado = true WHERE id = :id");
            $used->execute([":id" => $registro["id"]]);

            // Confirma transação
            $pdo->commit();

            $mensagem = "Senha redefinida com sucesso! Você já pode fazer login.";
            $tipo     = "ok";
            $valido   = false;

        } catch (Exception $e) {
            $pdo->rollBack();
            $mensagem = "Erro ao redefinir senha. Tente novamente.";
            $tipo = "erro_validacao";
            error_log("Erro ao redefinir senha: " . $e->getMessage());
        }
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Volunteer Community – Redefinir Senha</title>

  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/estilo_global.css">
  <link rel="stylesheet" href="css/estilo_login.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

  <style>
    /* Confinar SweetAlert dentro do .login-screen */
    .login-screen {
      position: relative;
      overflow: hidden;
    }
    .swal2-container.swal-inside-redefinir {
      position: absolute !important;
      top: 0 !important;
      left: 0 !important;
      width: 100% !important;
      height: 100% !important;
      z-index: 9999;
    }
    .swal2-container.swal-inside-redefinir .swal2-popup {
      width: 88% !important;
      max-width: 320px !important;
      border-radius: 20px !important;
      font-family: 'Nunito', sans-serif !important;
    }
    .swal2-confirm {
      background-color: #f5920a !important;
      border-radius: 50px !important;
      padding: 8px 20px !important;
      font-weight: 700 !important;
      font-size: 13px !important;
    }
    .swal2-cancel {
      border-radius: 50px !important;
      padding: 8px 20px !important;
      font-weight: 700 !important;
      font-size: 13px !important;
    }
    .desc {
      font-size: 14px;
      color: #888;
      font-weight: 600;
      text-align: center;
      margin-bottom: 20px;
      line-height: 1.55;
    }
    .strength-bar {
      width: 100%;
      height: 5px;
      border-radius: 99px;
      background: #eee;
      margin-top: -6px;
      overflow: hidden;
    }
    .strength-fill {
      height: 100%;
      border-radius: 99px;
      width: 0%;
      transition: width 0.3s, background 0.3s;
    }
    .strength-label {
      font-size: 12px;
      font-weight: 700;
      color: #aaa;
      text-align: right;
      margin-top: 2px;
    }
    .requisitos {
      font-size: 11px;
      margin-top: -5px;
      margin-bottom: 10px;
      text-align: left;
    }
    .requisitos span {
      display: inline-block;
      margin-right: 10px;
    }
    .valido   { color: #2a7d46; }
    .invalido { color: #b91c1c; }
  </style>
</head>
<body>

<section class="login-screen" id="loginWrapper">

  <div class="header">
    <a href="login.php" class="btn-back" aria-label="Voltar">&#8592;</a>
    <div class="logo-wrapper">
      <img src="imagens/logo.png" alt="Volunteer Community" class="logo">
      <span class="logo-text">Volunteer</span>
      <span class="logo-sub">Community</span>
    </div>
  </div>

  <div class="content">

    <h2>Nova senha</h2>

    <?php if ($valido && $registro): ?>
      <p class="desc">
        Olá, <strong><?= htmlspecialchars($registro['nome']) ?></strong>!<br>
        Escolha uma senha forte com pelo menos 8 caracteres.
      </p>

      <form class="form" action="redefinir_senha.php?token=<?= htmlspecialchars($token) ?>" method="post" onsubmit="return validarSenha()">
        <input type="password" name="nova_senha" id="nova_senha" placeholder="Nova senha" required>
        <div class="strength-bar"><div class="strength-fill" id="strength-fill"></div></div>
        <div class="strength-label" id="strength-label"></div>

        <div class="requisitos" id="requisitos">
          <span id="req-tamanho"   class="invalido">📏 8+ caracteres</span>
          <span id="req-maiuscula" class="invalido">🔠 Letra maiúscula</span>
          <span id="req-numero"    class="invalido">🔢 Número</span>
          <span id="req-especial"  class="invalido">✨ Caractere especial</span>
        </div>

        <input type="password" name="confirma_senha" id="confirma_senha" placeholder="Confirme a nova senha" required>
        <div id="msg-confirma" style="font-size:12px; margin-top:-5px; margin-bottom:10px;"></div>

        <button type="submit" class="btn primary" id="btn-submit" disabled>Salvar nova senha</button>
      </form>

    <?php else: ?>
      <!-- Token inválido/expirado: SweetAlert cuida do aviso, exibe apenas o link de fallback -->
      <a href="recuperar_senha.php" class="forgot">Recuperar senha</a>
    <?php endif; ?>

    <a href="login.php" class="forgot" style="margin-top:16px;">Voltar para o login</a>

  </div>

</section>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const loginEl = document.getElementById('loginWrapper');

const swalRedefinir = Swal.mixin({
    target: loginEl,
    customClass: {
        container: 'swal-inside-redefinir',
    }
});

// ── Feedbacks vindos do PHP ────────────────────────────────────────────────

<?php if ($tipo === "ok"): ?>
// Senha redefinida com sucesso
document.addEventListener('DOMContentLoaded', function () {
    swalRedefinir.fire({
        title: '✅ Senha redefinida!',
        text: 'Sua senha foi alterada com sucesso. Você já pode fazer login.',
        icon: 'success',
        confirmButtonText: '🔐 Ir para o login',
        confirmButtonColor: '#f5920a',
        allowOutsideClick: false,
        allowEscapeKey: false
    }).then(() => {
        window.location.href = 'login.php';
    });
});

<?php elseif ($tipo === "erro"): ?>
// Token inválido, expirado ou já usado
document.addEventListener('DOMContentLoaded', function () {
    swalRedefinir.fire({
        title: 'Link inválido',
        text: '<?= addslashes(htmlspecialchars($mensagem)) ?>',
        icon: 'error',
        confirmButtonText: 'Solicitar novo link',
        confirmButtonColor: '#f5920a',
        showCancelButton: true,
        cancelButtonText: 'Voltar ao login',
        cancelButtonColor: '#aaa',
        allowOutsideClick: false,
        allowEscapeKey: false
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'recuperar_senha.php';
        } else {
            window.location.href = 'login.php';
        }
    });
});

<?php elseif ($tipo === "erro_validacao"): ?>
// Erro de validação do formulário (senha curta, não coincidem, etc.)
document.addEventListener('DOMContentLoaded', function () {
    swalRedefinir.fire({
        title: 'Atenção',
        text: '<?= addslashes(htmlspecialchars($mensagem)) ?>',
        icon: 'warning',
        confirmButtonText: 'Tentar novamente',
        confirmButtonColor: '#f5920a'
    });
});
<?php endif; ?>

// ── Lógica do formulário (força de senha + confirmação) ────────────────────
const inputSenha  = document.getElementById("nova_senha");
const confirma    = document.getElementById("confirma_senha");
const fill        = document.getElementById("strength-fill");
const label       = document.getElementById("strength-label");
const btnSubmit   = document.getElementById("btn-submit");
const msgConfirma = document.getElementById("msg-confirma");

const reqTamanho   = document.getElementById("req-tamanho");
const reqMaiuscula = document.getElementById("req-maiuscula");
const reqNumero    = document.getElementById("req-numero");
const reqEspecial  = document.getElementById("req-especial");

function verificarSenha() {
    const v = inputSenha.value;
    const confirmValue = confirma.value;

    const temTamanho   = v.length >= 8;
    const temMaiuscula = /[A-Z]/.test(v);
    const temNumero    = /[0-9]/.test(v);
    const temEspecial  = /[^A-Za-z0-9]/.test(v);

    reqTamanho.className   = temTamanho   ? "valido" : "invalido";
    reqMaiuscula.className = temMaiuscula ? "valido" : "invalido";
    reqNumero.className    = temNumero    ? "valido" : "invalido";
    reqEspecial.className  = temEspecial  ? "valido" : "invalido";

    const score = [temTamanho, temMaiuscula, temNumero, temEspecial].filter(Boolean).length;

    const map = [
        { w: "0%",   c: "#eee",    t: "" },
        { w: "25%",  c: "#ef4444", t: "Fraca" },
        { w: "50%",  c: "#f59e0b", t: "Razoável" },
        { w: "75%",  c: "#3b82f6", t: "Boa" },
        { w: "100%", c: "#22c55e", t: "Forte" },
    ];

    fill.style.width      = map[score].w;
    fill.style.background = map[score].c;
    label.textContent     = map[score].t;
    label.style.color     = map[score].c;

    verificarConfirmacao();

    const senhasIguais = v === confirmValue && v !== "";
    const senhaValida  = temTamanho && (temMaiuscula || temNumero || temEspecial);
    btnSubmit.disabled = !(senhasIguais && senhaValida);
}

function verificarConfirmacao() {
    const v = inputSenha.value;
    const confirmValue = confirma.value;

    if (confirmValue === "") {
        msgConfirma.innerHTML = "";
    } else if (v === confirmValue) {
        msgConfirma.innerHTML   = "✅ Senhas coincidem";
        msgConfirma.style.color = "#2a7d46";
    } else {
        msgConfirma.innerHTML   = "❌ Senhas não coincidem";
        msgConfirma.style.color = "#b91c1c";
    }
}

function validarSenha() {
    const v = inputSenha.value;
    const confirmValue = confirma.value;

    if (v.length < 8) {
        swalRedefinir.fire({
            title: 'Atenção',
            text: 'A senha deve ter pelo menos 8 caracteres.',
            icon: 'warning',
            confirmButtonText: 'Ok',
            confirmButtonColor: '#f5920a'
        });
        return false;
    }

    if (v !== confirmValue) {
        swalRedefinir.fire({
            title: 'Atenção',
            text: 'As senhas não coincidem.',
            icon: 'warning',
            confirmButtonText: 'Ok',
            confirmButtonColor: '#f5920a'
        });
        return false;
    }

    return true;
}

if (inputSenha) {
    inputSenha.addEventListener("input", verificarSenha);
    confirma.addEventListener("input", verificarConfirmacao);
    confirma.addEventListener("input", verificarSenha);
}
</script>

</body>
</html>