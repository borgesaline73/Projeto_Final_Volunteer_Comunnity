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
            // Obtém o caminho base do projeto
            $caminho_base = rtrim(dirname($_SERVER["SCRIPT_NAME"]), '/\\');
            if ($caminho_base == "." || $caminho_base == "") {
                $caminho_base = "";
            }
            $link_redefinicao = $protocolo . "://" . $host . $caminho_base . "/redefinir_senha.php?token=" . $token;
            
            $token_gerado = $token;
            $mensagem = "✅ Token gerado com sucesso!";
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
    <style>
        .info-box {
            width: 100%;
            padding: 14px 18px;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 600;
            text-align: center;
            margin-bottom: 4px;
        }
        .info-box.ok {
            background: #eaf7ee;
            color: #2a7d46;
            border: 1.5px solid #b6e8c7;
        }
        .info-box.erro {
            background: #fdecea;
            color: #b91c1c;
            border: 1.5px solid #f5b4b0;
        }
        .info-box.token_gerado {
            background: #fff3e0;
            color: #f5920a;
            border: 1.5px solid #ffd699;
        }
        .token-container {
            background: #f7f7f7;
            padding: 20px;
            border-radius: 16px;
            margin: 20px 0;
            text-align: center;
            border: 2px dashed #f5920a;
        }
        .token-link {
            background: white;
            padding: 12px;
            border-radius: 8px;
            margin: 15px 0;
            word-break: break-all;
            font-family: monospace;
            font-size: 13px;
            border: 1px solid #ddd;
            color: #333;
        }
        .btn-redefinir {
            display: inline-block;
            background: #f5920a;
            color: white;
            padding: 12px 24px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: bold;
            margin-top: 10px;
            transition: all 0.3s;
        }
        .btn-redefinir:hover {
            background: #d97f07;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(245, 146, 10, 0.3);
        }
        .btn-copiar {
            background: #2d2418;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            margin-top: 10px;
            transition: all 0.3s;
        }
        .btn-copiar:hover {
            background: #f5920a;
            transform: scale(1.02);
        }
        .aviso {
            font-size: 12px;
            color: #888;
            margin-top: 15px;
            text-align: center;
        }
        .desc {
            font-size: 14px;
            color: #888;
            font-weight: 600;
            text-align: center;
            margin-bottom: 20px;
            line-height: 1.55;
        }
        .info-token {
            background: #e8f0fe;
            padding: 10px;
            border-radius: 8px;
            margin-top: 10px;
            font-size: 11px;
            color: #1a73e8;
            text-align: left;
            word-break: break-all;
        }
        .info-token strong {
            color: #f5920a;
        }
    </style>
</head>
<body>

<section class="login-screen">

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

        <?php if ($tipo !== "token_gerado"): ?>
            <p class="desc">
                Informe o e-mail cadastrado para gerar<br>um link de recuperação de senha.
            </p>
        <?php endif; ?>

        <?php if ($mensagem && $tipo !== "token_gerado"): ?>
            <div class="info-box <?= $tipo ?>">
                <?= htmlspecialchars($mensagem) ?>
            </div>
        <?php endif; ?>

        <?php if ($tipo === "token_gerado" && $token_gerado): ?>
            <!-- Exibe o token/link diretamente -->
            <div class="info-box token_gerado">
                <?= htmlspecialchars($mensagem) ?>
            </div>
            
            <div class="token-container">
                <strong>🔗 Link de recuperação (válido por 1 hora):</strong>
                
                <div class="token-link" id="linkParaCopiar">
                    <?= htmlspecialchars($link_redefinicao) ?>
                </div>
                
                <button class="btn-copiar" onclick="copiarLink()">
                    📋 Copiar link
                </button>
                
                <br>
                
                <a href="<?= htmlspecialchars($link_redefinicao) ?>" class="btn-redefinir">
                    🔑 Redefinir senha agora
                </a>
                
                <div class="aviso">
                    ⚠️ Importante: Este link é único e expira em 1 hora.<br>
                    Salve-o para usar depois se necessário.
                </div>
                
                <!-- Informações de debug (lembrar de remover após concluir a projeto) -->
                <div class="info-token">
                    <strong>📋 Informações técnicas:</strong><br>
                    Token: <?= htmlspecialchars(substr($token_gerado, 0, 20)) ?>...<br>
                    Expira em: <?= date("d/m/Y H:i:s", strtotime("+1 hour")) ?>
                </div>
            </div>
            
            <script>
                function copiarLink() {
                    const link = document.getElementById('linkParaCopiar').innerText;
                    navigator.clipboard.writeText(link).then(function() {
                        alert('✅ Link copiado para a área de transferência!');
                        // Opcional: mudar texto do botão temporariamente
                        const btn = event.target;
                        const textoOriginal = btn.innerText;
                        btn.innerText = '✅ Copiado!';
                        setTimeout(() => {
                            btn.innerText = textoOriginal;
                        }, 2000);
                    }, function() {
                        alert('❌ Erro ao copiar. Copie manualmente selecionando o texto.');
                    });
                }
            </script>
            
        <?php else: ?>
            <!-- Formulário normal -->
            <form class="form" action="recuperar_senha.php" method="post">
                <input type="email" name="email" placeholder="Seu e-mail cadastrado" required autocomplete="email">
                <button type="submit" class="btn primary">Gerar link de recuperação</button>
            </form>
        <?php endif; ?>

        <a href="login.php" class="forgot" style="margin-top:20px;">Voltar para o login</a>

    </div>

</section>

</body>
</html>