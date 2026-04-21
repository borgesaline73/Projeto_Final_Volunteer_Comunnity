<?php
require "banco.php";
session_start();

$mensagem = "";
$tipo     = "";
$token_gerado = "";
$link_redefinicao = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = "Por favor, informe um e-mail válido.";
        $tipo     = "erro";
    } else {
        // Verifica se o email existe no banco
        $stmt = $pdo->prepare("SELECT id_usuario, nome FROM usuarios WHERE email = :email AND ativo = true");
        $stmt->execute([":email" => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Gera token seguro
            $token = bin2hex(random_bytes(32));
            $expira = date("Y-m-d H:i:s", strtotime("+1 hour"));

            // Salva token no banco de dados
            $ins = $pdo->prepare("
                INSERT INTO recuperacao_senha (id_usuario, token, expira_em, usado)
                VALUES (:id, :token, :expira, false)
            ");
            $ins->execute([
                ":id"     => $user["id_usuario"],
                ":token"  => $token,
                ":expira" => $expira,
            ]);

            // Prepara o link de redefinição
            $protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $host = $_SERVER["HTTP_HOST"];
            $caminho_base = rtrim(dirname($_SERVER["SCRIPT_NAME"]), '/\\');
            if ($caminho_base == "." || $caminho_base == "") {
                $caminho_base = "";
            }
            $link_redefinicao = $protocolo . "://" . $host . $caminho_base . "/redefinir_senha.php?token=" . $token;

            $token_gerado = $token;
            $mensagem = "Token gerado com sucesso!";
            $tipo = "token_gerado";
        } else {
            $mensagem = "E-mail não encontrado no sistema.";
            $tipo = "erro";
        }
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Volunteer Community – Recuperar Senha</title>

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
        .swal2-container.swal-inside-recuperar {
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            z-index: 9999;
        }
        .swal2-container.swal-inside-recuperar .swal2-popup {
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
    </style>
</head>
<body>

<section class="login-screen" id="loginWrapper">

    <!-- Header laranja com curva -->
    <div class="header">
        <a href="login.php" class="btn-back" aria-label="Voltar">&#8592;</a>
        <div class="logo-wrapper">
            <img src="imagens/logo.png" alt="Volunteer Community" class="logo">
            <span class="logo-text">Volunteer</span>
            <span class="logo-sub">Community</span>
        </div>
    </div>

    <div class="content">

        <h2>Recuperar senha</h2>

        <p class="desc">
            Informe o e-mail cadastrado para gerar<br>um link de recuperação de senha.
        </p>

        <!-- Formulário sempre visível (SweetAlert cuida dos feedbacks) -->
        <form class="form" action="recuperar_senha.php" method="post">
            <input type="email" name="email" placeholder="Seu e-mail cadastrado" required autocomplete="email">
            <button type="submit" class="btn primary">Gerar link de recuperação</button>
        </form>

        <a href="login.php" class="forgot" style="margin-top:20px;">Voltar para o login</a>

    </div>

</section>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const loginEl = document.getElementById('loginWrapper');

const swalRecuperar = Swal.mixin({
    target: loginEl,
    customClass: {
        container: 'swal-inside-recuperar',
    }
});

<?php if ($tipo === "erro"): ?>
// Exibe erro (e-mail inválido ou não encontrado)
document.addEventListener('DOMContentLoaded', function () {
    swalRecuperar.fire({
        title: 'Atenção',
        text: '<?= addslashes(htmlspecialchars($mensagem)) ?>',
        icon: 'error',
        confirmButtonText: 'Tentar novamente',
        confirmButtonColor: '#f5920a'
    });
});

<?php elseif ($tipo === "token_gerado" && $token_gerado): ?>
// Exibe link gerado com sucesso
document.addEventListener('DOMContentLoaded', function () {
    swalRecuperar.fire({
        title: '✅ Link gerado!',
        html: `
            <p style="font-size:13px; color:#555; margin-bottom:10px;">
                Link válido por <strong>1 hora</strong>. Copie ou acesse diretamente:
            </p>
            <div id="swal-link-box" style="
                background: #f7f7f7;
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 10px;
                font-family: monospace;
                font-size: 11px;
                word-break: break-all;
                color: #333;
                text-align: left;
                margin-bottom: 10px;
            "><?= htmlspecialchars($link_redefinicao) ?></div>
            <button onclick="copiarLinkSwal(event)" style="
                background: #2d2418;
                color: white;
                border: none;
                padding: 8px 16px;
                border-radius: 8px;
                cursor: pointer;
                font-size: 12px;
                font-family: 'Nunito', sans-serif;
                font-weight: 700;
                margin-bottom: 4px;
            ">📋 Copiar link</button>
            <p style="font-size:10px; color:#aaa; margin-top:8px;">
                ⚠️ Este link é único e expira em 1 hora.
            </p>
        `,
        icon: 'success',
        showCancelButton: true,
        confirmButtonText: '🔑 Redefinir senha agora',
        cancelButtonText: 'Fechar',
        confirmButtonColor: '#f5920a',
        cancelButtonColor: '#aaa',
        allowOutsideClick: false,
        allowEscapeKey: false
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '<?= htmlspecialchars($link_redefinicao) ?>';
        }
    });
});

function copiarLinkSwal(event) {
    const link = document.getElementById('swal-link-box').innerText.trim();
    navigator.clipboard.writeText(link).then(function () {
        const btn = event.target;
        const textoOriginal = btn.innerText;
        btn.innerText = '✅ Copiado!';
        btn.style.background = '#f5920a';
        setTimeout(() => {
            btn.innerText = textoOriginal;
            btn.style.background = '#2d2418';
        }, 2000);
    }, function () {
        alert('❌ Erro ao copiar. Copie manualmente selecionando o texto.');
    });
}
<?php endif; ?>
</script>

</body>
</html>