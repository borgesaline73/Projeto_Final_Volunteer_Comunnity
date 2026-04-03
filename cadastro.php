<?php
session_start();
require "banco.php";

$mensagem_erro = '';
$tipo_erro = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nome = $_POST["nome"] ?? "";
    $cpf_cnpj = $_POST["cpf_cnpj"] ?? "";
    $telefone = $_POST["telefone"] ?? "";
    $endereco = $_POST["endereco"] ?? "";
    $bairro = $_POST["bairro"] ?? "";
    $numero = $_POST["numero"] ?? "";
    $cidade = $_POST["cidade"] ?? "";
    $uf = $_POST["uf"] ?? "";
    $email = $_POST["email"] ?? "";
    $senha = $_POST["senha"] ?? "";
    $role = $_POST["role"] ?? "doador";

    // Validações básicas
    if (empty($nome) || empty($cpf_cnpj) || empty($email) || empty($senha)) {
        $mensagem_erro = "Todos os campos obrigatórios devem ser preenchidos.";
        $tipo_erro = "error";
    } elseif (strlen($senha) < 6) {
        $mensagem_erro = "A senha deve ter pelo menos 6 caracteres.";
        $tipo_erro = "error";
    } else {
        // 🔐 HASH CORRETO
        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

        try {
            // Inserir usuário
            $stmt = $pdo->prepare("
                INSERT INTO usuarios (nome, email, senha, cpf_cnpj, tipo_usuario) 
                VALUES (:nome, :email, :senha, :cpf, :tipo)
                RETURNING id_usuario
            ");

            $stmt->execute([
                ":nome" => $nome,
                ":email" => $email,
                ":senha" => $senhaHash,
                ":cpf"   => $cpf_cnpj,
                ":tipo"  => $role
            ]);

            $userId = $stmt->fetchColumn();

            // Inserção por tipo
            if ($role === "doador") {
                $stmt = $pdo->prepare("INSERT INTO doadores (id_doador) VALUES (:id)");
                $stmt->execute([":id" => $userId]);
            } else {
                $endereco_completo = trim("$endereco, $numero - $bairro, $cidade - $uf");
                $stmt = $pdo->prepare("
                    INSERT INTO ongs (id_ong, endereco) 
                    VALUES (:id, :endereco)
                ");
                $stmt->execute([
                    ":id" => $userId,
                    ":endereco" => $endereco_completo
                ]);
            }

            // Redireciona com mensagem de sucesso
            $success_msg = urlencode("✅ Usuário cadastrado com sucesso! Faça login para continuar.");
            header("Location: login.php?msg=$success_msg&tipo=success");
            exit;

        } catch (PDOException $e) {
            if ($e->getCode() == "23505") {
                $mensagem_erro = "E-mail ou CPF/CNPJ já cadastrado.";
                $tipo_erro = "error";
            } else {
                $mensagem_erro = "Erro ao cadastrar: " . $e->getMessage();
                $tipo_erro = "error";
            }
        }
    }
}
?>

<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no" />
    <title>Cadastre-se - Volunteer Community</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/estilo_global.css">
    <link rel="stylesheet" href="css/estilo_cadastro.css">
    
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
        /* Estilo para confinar o SweetAlert dentro do telefone */
        .phone {
            position: relative;
            overflow: hidden;
        }

        .swal2-container.swal-inside-cadastro {
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            z-index: 9999;
        }

        .swal2-container.swal-inside-cadastro .swal2-popup {
            width: 88% !important;
            max-width: 320px !important;
            border-radius: 20px !important;
            font-family: 'Poppins', sans-serif !important;
        }

        .swal2-confirm {
            background-color: #f4822f !important;
            border-radius: 50px !important;
            padding: 10px 24px !important;
            font-weight: 600 !important;
        }

        .swal2-confirm:hover {
            background-color: #e67329 !important;
        }
    </style>
</head>

<body>
    <div class="phone" id="phoneWrapper">
        <div class="main-content">
            <div class="screen">
                <div class="topbar">
                    <button class="back" onclick="history.back()">←</button>
                </div>

                <h1>Cadastre-se</h1>

                <form action="cadastro.php" method="post" id="cadastroForm">
                    <div class="field">
                        <input type="text" name="nome" id="nome" placeholder="Nome completo" required>
                    </div>

                    <div class="grid-2">
                        <div class="field">
                            <input type="text" name="cpf_cnpj" id="cpf_cnpj" placeholder="CPF / CNPJ" required>
                        </div>
                        <div class="field">
                            <input type="tel" name="telefone" id="telefone" placeholder="Telefone">
                        </div>
                    </div>

                    <div class="field">
                        <input type="text" name="endereco" id="endereco" placeholder="Endereço / Rua">
                    </div>

                    <div class="grid-2">
                        <div class="field">
                            <input type="text" name="bairro" id="bairro" placeholder="Bairro">
                        </div>
                        <div class="field">
                            <input type="text" name="numero" id="numero" placeholder="Nº">
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="field">
                            <input type="text" name="cidade" id="cidade" placeholder="Cidade">
                        </div>
                        <div class="field">
                            <input type="text" name="uf" id="uf" placeholder="UF" maxlength="2">
                        </div>
                    </div>

                    <div class="field">
                        <input type="email" name="email" id="email" placeholder="Email" required>
                    </div>

                    <div class="field">
                        <input type="password" name="senha" id="senha" placeholder="Senha" required>
                        <small style="font-size: 11px; color: #888; display: block; margin-top: 4px;">Mínimo 6 caracteres</small>
                    </div>

                    <div class="role-title">Sou?</div>

                    <div class="role-options">
                        <input type="radio" id="role-doador" name="role" value="doador" checked>
                        <label class="pill" for="role-doador">Doador</label>

                        <input type="radio" id="role-inst" name="role" value="instituicao">
                        <label class="pill" for="role-inst">Instituição</label>
                    </div>

                    <div id="role-indicator">
                        Você está se cadastrando como: <span id="role-text">Doador</span>
                    </div>

                    <button class="btn-primary" type="submit" id="btnCadastrar">Cadastrar</button>
                </form>
            </div>
        </div>
    </div>

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // ─── Referência ao elemento .phone para confinar os modais ───────────────
        const phoneEl = document.getElementById('phoneWrapper');

        const swalCadastro = Swal.mixin({
            target: phoneEl,
            confirmButtonColor: '#f4822f',
            cancelButtonColor: '#aaa',
            customClass: {
                container: 'swal-inside-cadastro',
                popup: 'swal-popup-cadastro'
            }
        });

        <?php if (!empty($mensagem_erro) && !empty($tipo_erro)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            swalCadastro.fire({
                title: '❌ Erro no cadastro',
                text: '<?= htmlspecialchars($mensagem_erro) ?>',
                icon: '<?= $tipo_erro ?>',
                confirmButtonText: 'Tentar novamente',
                allowOutsideClick: false
            });
        });
        <?php endif; ?>

        // ─── Atualizar texto do indicador de role ─────────────────────────────────
        const doador = document.getElementById("role-doador");
        const inst = document.getElementById("role-inst");
        const roleText = document.getElementById("role-text");

        function atualizarTexto() {
            roleText.textContent = doador.checked ? "Doador" : "Instituição";
        }

        doador.addEventListener("change", atualizarTexto);
        inst.addEventListener("change", atualizarTexto);

        // ─── Validação do formulário antes de enviar ──────────────────────────────
        document.getElementById('cadastroForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const nome = document.getElementById('nome').value.trim();
            const cpf_cnpj = document.getElementById('cpf_cnpj').value.trim();
            const email = document.getElementById('email').value.trim();
            const senha = document.getElementById('senha').value;
            
            // Validação de campos obrigatórios
            if (!nome || !cpf_cnpj || !email || !senha) {
                await swalCadastro.fire({
                    title: 'Campos obrigatórios',
                    text: 'Por favor, preencha todos os campos obrigatórios.',
                    icon: 'warning',
                    confirmButtonText: 'Ok'
                });
                return false;
            }
            
            // Validação de senha
            if (senha.length < 6) {
                await swalCadastro.fire({
                    title: 'Senha fraca',
                    text: 'A senha deve ter pelo menos 6 caracteres.',
                    icon: 'warning',
                    confirmButtonText: 'Ok'
                });
                return false;
            }
            
            // Validação de email
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                await swalCadastro.fire({
                    title: 'Email inválido',
                    text: 'Por favor, digite um email válido (exemplo@dominio.com)',
                    icon: 'warning',
                    confirmButtonText: 'Ok'
                });
                return false;
            }
            
            // Validação de CPF/CNPJ (básica)
            if (cpf_cnpj.length < 11) {
                await swalCadastro.fire({
                    title: 'CPF/CNPJ inválido',
                    text: 'Por favor, digite um CPF ou CNPJ válido.',
                    icon: 'warning',
                    confirmButtonText: 'Ok'
                });
                return false;
            }
            
            // Se for instituição, validar endereço
            const isInstituicao = document.getElementById('role-inst').checked;
            if (isInstituicao) {
                const endereco = document.getElementById('endereco').value.trim();
                const cidade = document.getElementById('cidade').value.trim();
                const uf = document.getElementById('uf').value.trim();
                
                if (!endereco || !cidade || !uf) {
                    await swalCadastro.fire({
                        title: 'Endereço incompleto',
                        text: 'Instituições precisam informar endereço, cidade e UF.',
                        icon: 'warning',
                        confirmButtonText: 'Ok'
                    });
                    return false;
                }
            }
            
            // Mostrar loading no botão
            const btn = document.getElementById('btnCadastrar');
            const textoOriginal = btn.textContent;
            btn.disabled = true;
            btn.textContent = '⏳ Cadastrando...';
            btn.style.opacity = '0.7';
            
            // Enviar formulário
            this.submit();
        });
        
        // ─── Máscara para CPF/CNPJ ───────────────────────────────────────────────
        document.getElementById('cpf_cnpj').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            if (value.length <= 11) {
                // CPF: 000.000.000-00
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            } else {
                // CNPJ: 00.000.000/0000-00
                value = value.replace(/^(\d{2})(\d)/, '$1.$2');
                value = value.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
                value = value.replace(/\.(\d{3})(\d)/, '.$1/$2');
                value = value.replace(/(\d{4})(\d)/, '$1-$2');
            }
            
            e.target.value = value;
        });
        
        // ─── Máscara para telefone ────────────────────────────────────────────────
        document.getElementById('telefone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            if (value.length <= 10) {
                // (00) 0000-0000
                value = value.replace(/^(\d{2})(\d)/, '($1) $2');
                value = value.replace(/(\d{4})(\d)/, '$1-$2');
            } else {
                // (00) 00000-0000
                value = value.replace(/^(\d{2})(\d)/, '($1) $2');
                value = value.replace(/(\d{5})(\d)/, '$1-$2');
            }
            
            e.target.value = value;
        });
        
        // ─── UF maiúsculo automático ─────────────────────────────────────────────
        document.getElementById('uf').addEventListener('input', function(e) {
            e.target.value = e.target.value.toUpperCase();
        });
    </script>
</body>
</html>