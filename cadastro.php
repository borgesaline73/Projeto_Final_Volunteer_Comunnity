<?php
session_start();
require "banco.php";

$mensagem_erro = '';
$tipo_erro = '';

// ════════════════════════════════════════════════════════════
//  FUNÇÃO: Consulta CNPJ na BrasilAPI e atualiza o banco
// ════════════════════════════════════════════════════════════
function verificarCNPJAutomatico(PDO $pdo, int $id_usuario, string $cnpj): array
{
    $cnpj_limpo = preg_replace('/\D/', '', $cnpj);

    if (strlen($cnpj_limpo) !== 14) {
        $pdo->prepare("UPDATE usuarios SET verificada = false, verificacao_status = 'rejeitada' WHERE id_usuario = ?")
            ->execute([$id_usuario]);
        return ['status' => 'rejeitada', 'mensagem' => 'CNPJ inválido (deve ter 14 dígitos).'];
    }

    $ch = curl_init("https://brasilapi.com.br/api/cnpj/v1/{$cnpj_limpo}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_erro = curl_error($ch);
    curl_close($ch);

    if ($curl_erro || $response === false) {
        $pdo->prepare("UPDATE usuarios SET verificada = false, verificacao_status = 'pendente' WHERE id_usuario = ?")
            ->execute([$id_usuario]);
        return ['status' => 'pendente', 'mensagem' => 'Não foi possível consultar a Receita Federal. Seu cadastro ficará em análise.'];
    }

    $data = json_decode($response, true);

    // CNPJ não encontrado
    if ($http_code === 404) {
        $pdo->prepare("UPDATE usuarios SET verificada = false, verificacao_status = 'rejeitada' WHERE id_usuario = ?")
            ->execute([$id_usuario]);
        return ['status' => 'rejeitada', 'mensagem' => 'CNPJ não encontrado na Receita Federal.'];
    }

    // Erro na API
    if ($http_code !== 200 || empty($data['razao_social'])) {
        $pdo->prepare("UPDATE usuarios SET verificada = false, verificacao_status = 'pendente' WHERE id_usuario = ?")
            ->execute([$id_usuario]);
        return ['status' => 'pendente', 'mensagem' => 'Não foi possível validar o CNPJ no momento. Entraremos em contato em breve.'];
    }

    // ⭐ VERIFICA SITUAÇÃO CADASTRAL
    $situacao = $data['descricao_situacao_cadastral'] ?? $data['situacao_cadastral'] ?? '';
    $situacao_normalizada = strtolower(trim($situacao));
    
    $cnpj_ativo = (
        $situacao_normalizada === 'ativa' ||
        $situacao_normalizada === 'atividade' ||
        $situacao_normalizada === '2'
    );
    
    $cnpj_suspenso = (
        str_contains($situacao_normalizada, 'suspensa') ||
        str_contains($situacao_normalizada, 'baixado') ||
        str_contains($situacao_normalizada, 'nula') ||
        str_contains($situacao_normalizada, 'inapta')
    );

    if ($cnpj_ativo && !$cnpj_suspenso) {
        $pdo->prepare("UPDATE usuarios SET verificada = true, verificacao_status = 'aprovada' WHERE id_usuario = ?")
            ->execute([$id_usuario]);
        return ['status' => 'aprovada', 'mensagem' => 'CNPJ verificado com sucesso! Sua ONG foi aprovada automaticamente.'];
    }

    // CNPJ inativo
    $pdo->prepare("UPDATE usuarios SET verificada = false, verificacao_status = 'rejeitada' WHERE id_usuario = ?")
        ->execute([$id_usuario]);
    return ['status' => 'rejeitada', 'mensagem' => "CNPJ com situação cadastral: {$situacao}. A ONG precisa estar ativa na Receita Federal."];
}

// ════════════════════════════════════════════════════════════
//  PROCESSAMENTO DO FORMULÁRIO
// ════════════════════════════════════════════════════════════
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nome     = trim($_POST["nome"]     ?? "");
    $cpf_cnpj = trim($_POST["cpf_cnpj"] ?? "");
    $telefone = trim($_POST["telefone"] ?? "");
    $endereco = trim($_POST["endereco"] ?? "");
    $bairro   = trim($_POST["bairro"]   ?? "");
    $numero   = trim($_POST["numero"]   ?? "");
    $cidade   = trim($_POST["cidade"]   ?? "");
    $uf       = trim($_POST["uf"]       ?? "");
    $email    = trim($_POST["email"]    ?? "");
    $senha    = $_POST["senha"]         ?? "";
    $role     = $_POST["role"]          ?? "doador";

    // Validações básicas
    if (empty($nome) || empty($cpf_cnpj) || empty($email) || empty($senha)) {
        $mensagem_erro = "Todos os campos obrigatórios devem ser preenchidos.";
        $tipo_erro = "error";
    } elseif (strlen($senha) < 6) {
        $mensagem_erro = "A senha deve ter pelo menos 6 caracteres.";
        $tipo_erro = "error";
    } else {
        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

        try {
            // ── 1. Inserir usuário ──────────────────────────────────────────
            $stmt = $pdo->prepare("
                INSERT INTO usuarios (nome, email, senha, cpf_cnpj, tipo_usuario)
                VALUES (:nome, :email, :senha, :cpf, :tipo)
                RETURNING id_usuario
            ");
            $stmt->execute([
                ":nome"  => $nome,
                ":email" => $email,
                ":senha" => $senhaHash,
                ":cpf"   => $cpf_cnpj,
                ":tipo"  => $role
            ]);
            $userId = $stmt->fetchColumn();

            // ── 2. Inserção na tabela específica por tipo ───────────────────
            if ($role === "doador") {

                $stmt = $pdo->prepare("INSERT INTO doadores (id_doador) VALUES (:id)");
                $stmt->execute([":id" => $userId]);

                // Doadores não precisam de verificação → redireciona direto
                $success_msg = urlencode("✅ Cadastro realizado com sucesso! Faça login para continuar.");
                header("Location: login.php?msg=$success_msg&tipo=success");
                exit;

            } else {

                $endereco_completo = trim("$endereco, $numero - $bairro, $cidade - $uf");
                $stmt = $pdo->prepare("INSERT INTO ongs (id_ong, endereco) VALUES (:id, :endereco)");
                $stmt->execute([":id" => $userId, ":endereco" => $endereco_completo]);

                // ══ VERIFICAÇÃO AUTOMÁTICA DO CNPJ ══════════════════════════
                $resultado = verificarCNPJAutomatico($pdo, $userId, $cpf_cnpj);

                // Monta mensagem de redirecionamento conforme resultado
                switch ($resultado['status']) {
                    case 'aprovada':
                        $msg  = urlencode("✅ Cadastro realizado! " . $resultado['mensagem']);
                        $tipo = "success";
                        break;
                    case 'rejeitada':
                        $msg  = urlencode("⚠️ Cadastro salvo, mas o CNPJ não foi verificado: " . $resultado['mensagem'] . " Entre em contato com o suporte.");
                        $tipo = "warning";
                        break;
                    default: // pendente
                        $msg  = urlencode("⏳ Cadastro realizado! " . $resultado['mensagem']);
                        $tipo = "info";
                        break;
                }

                header("Location: login.php?msg=$msg&tipo=$tipo");
                exit;
            }

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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <style>
        .phone { position: relative; overflow: hidden; }

        .swal2-container.swal-inside-cadastro {
            position: absolute !important;
            top: 0 !important; left: 0 !important;
            width: 100% !important; height: 100% !important;
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
        .swal2-confirm:hover { background-color: #e67329 !important; }

        /* Aviso de verificação automática visível ao selecionar Instituição */
        #aviso-verificacao {
            display: none;
            background: #fff8f0;
            border: 1.5px solid #f4822f;
            border-radius: 12px;
            padding: 10px 14px;
            font-size: 12px;
            color: #c25e00;
            margin-bottom: 12px;
            line-height: 1.5;
        }
        #aviso-verificacao.show { display: block; }
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

                    <!-- Aviso de verificação automática (aparece ao selecionar Instituição) -->
                    <div id="aviso-verificacao">
                        🔍 <strong>Verificação automática:</strong> ao cadastrar, seu CNPJ será consultado
                        na Receita Federal. ONGs com CNPJ válido e ativo são aprovadas na hora.
                    </div>

                    <button class="btn-primary" type="submit" id="btnCadastrar">Cadastrar</button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const phoneEl = document.getElementById('phoneWrapper');

        const swalCadastro = Swal.mixin({
            target: phoneEl,
            confirmButtonColor: '#f4822f',
            cancelButtonColor: '#aaa',
            customClass: { container: 'swal-inside-cadastro' }
        });

        <?php if (!empty($mensagem_erro)): ?>
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

        // ─── Role: atualizar texto e aviso ────────────────────────────────────────
        const doador         = document.getElementById("role-doador");
        const inst           = document.getElementById("role-inst");
        const roleText       = document.getElementById("role-text");
        const avisoVerif     = document.getElementById("aviso-verificacao");

        function atualizarRole() {
            const isInst = inst.checked;
            roleText.textContent = isInst ? "Instituição" : "Doador";
            avisoVerif.classList.toggle("show", isInst);
        }

        doador.addEventListener("change", atualizarRole);
        inst.addEventListener("change", atualizarRole);

        // ─── Validação + loading antes de enviar ──────────────────────────────────
        document.getElementById('cadastroForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const nome    = document.getElementById('nome').value.trim();
            const cpfCnpj = document.getElementById('cpf_cnpj').value.trim();
            const email   = document.getElementById('email').value.trim();
            const senha   = document.getElementById('senha').value;
            const isInst  = inst.checked;

            if (!nome || !cpfCnpj || !email || !senha) {
                await swalCadastro.fire({ title: 'Campos obrigatórios', text: 'Preencha todos os campos obrigatórios.', icon: 'warning', confirmButtonText: 'Ok' });
                return;
            }
            if (senha.length < 6) {
                await swalCadastro.fire({ title: 'Senha fraca', text: 'A senha deve ter pelo menos 6 caracteres.', icon: 'warning', confirmButtonText: 'Ok' });
                return;
            }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                await swalCadastro.fire({ title: 'Email inválido', text: 'Digite um email válido (exemplo@dominio.com)', icon: 'warning', confirmButtonText: 'Ok' });
                return;
            }
            if (cpfCnpj.replace(/\D/g, '').length < 11) {
                await swalCadastro.fire({ title: 'CPF/CNPJ inválido', text: 'Digite um CPF ou CNPJ válido.', icon: 'warning', confirmButtonText: 'Ok' });
                return;
            }
            if (isInst) {
                const endereco = document.getElementById('endereco').value.trim();
                const cidade   = document.getElementById('cidade').value.trim();
                const uf       = document.getElementById('uf').value.trim();
                if (!endereco || !cidade || !uf) {
                    await swalCadastro.fire({ title: 'Endereço incompleto', text: 'Instituições precisam informar endereço, cidade e UF.', icon: 'warning', confirmButtonText: 'Ok' });
                    return;
                }

                // Avisa que a verificação pode levar alguns segundos
                swalCadastro.fire({
                    title: '🔍 Verificando CNPJ...',
                    html: 'Consultando a Receita Federal.<br><small>Isso pode levar alguns segundos.</small>',
                    icon: 'info',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => Swal.showLoading()
                });
            }

            // Loading no botão
            const btn = document.getElementById('btnCadastrar');
            btn.disabled = true;
            btn.textContent = '⏳ Cadastrando...';
            btn.style.opacity = '0.7';

            this.submit();
        });

        // ─── Máscaras ─────────────────────────────────────────────────────────────
        document.getElementById('cpf_cnpj').addEventListener('input', function(e) {
            let v = e.target.value.replace(/\D/g, '');
            if (v.length <= 11) {
                v = v.replace(/(\d{3})(\d)/, '$1.$2')
                     .replace(/(\d{3})(\d)/, '$1.$2')
                     .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            } else {
                v = v.replace(/^(\d{2})(\d)/, '$1.$2')
                     .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
                     .replace(/\.(\d{3})(\d)/, '.$1/$2')
                     .replace(/(\d{4})(\d)/, '$1-$2');
            }
            e.target.value = v;
        });

        document.getElementById('telefone').addEventListener('input', function(e) {
            let v = e.target.value.replace(/\D/g, '');
            v = v.length <= 10
                ? v.replace(/^(\d{2})(\d)/, '($1) $2').replace(/(\d{4})(\d)/, '$1-$2')
                : v.replace(/^(\d{2})(\d)/, '($1) $2').replace(/(\d{5})(\d)/, '$1-$2');
            e.target.value = v;
        });

        document.getElementById('uf').addEventListener('input', function(e) {
            e.target.value = e.target.value.toUpperCase();
        });
    </script>
</body>
</html>