<?php
session_start();
require "banco.php";

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
        die("⚠️ Todos os campos obrigatórios devem ser preenchidos.");
    }

    if (strlen($senha) < 6) {
        die("⚠️ A senha deve ter pelo menos 6 caracteres.");
    }

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
            $stmt = $pdo->prepare("
                INSERT INTO ongs (id_ong, endereco) 
                VALUES (:id, :endereco)
            ");
            $stmt->execute([
                ":id" => $userId,
                ":endereco" => $endereco
            ]);
        }

        echo "<script>alert('✅ Usuário cadastrado com sucesso!'); window.location='login.php';</script>";
        exit;

    } catch (PDOException $e) {
        if ($e->getCode() == "23505") {
            echo "<script>alert('⚠️ E-mail ou CPF/CNPJ já cadastrado.'); history.back();</script>";
        } else {
            echo "Erro: " . $e->getMessage();
        }
    }
}
?>

<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Cadastre-se - Volunteer Community</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="css/estilo_global.css">
    <link rel="stylesheet" href="css/estilo_cadastro.css">
</head>

<body>
    <div class="phone">
        <div class="main-content">
            <div class="screen">
                <div class="topbar">
                    <button class="back" onclick="history.back()">←</button>
                </div>

                <h1>Cadastre-se</h1>

                <form action="cadastro.php" method="post" id="cadastroForm">
                    <div class="field">
                        <input type="text" name="nome" placeholder="Nome completo" required>
                    </div>

                    <div class="grid-2">
                        <div class="field">
                            <input type="text" name="cpf_cnpj" placeholder="CPF / CNPJ" required>
                        </div>
                        <div class="field">
                            <input type="tel" name="telefone" placeholder="Telefone">
                        </div>
                    </div>

                    <div class="field">
                        <input type="text" name="endereco" placeholder="Endereço / Rua">
                    </div>

                    <div class="grid-2">
                        <div class="field">
                            <input type="text" name="bairro" placeholder="Bairro">
                        </div>
                        <div class="field">
                            <input type="text" name="numero" placeholder="Nº">
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="field">
                            <input type="text" name="cidade" placeholder="Cidade">
                        </div>
                        <div class="field">
                            <input type="text" name="uf" placeholder="UF" maxlength="2">
                        </div>
                    </div>

                    <div class="field">
                        <input type="email" name="email" placeholder="Email" required>
                    </div>

                    <div class="field">
                        <input type="password" name="senha" placeholder="Senha" required>
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

                    <button class="btn-primary" type="submit">Cadastrar</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        const doador = document.getElementById("role-doador");
        const inst = document.getElementById("role-inst");
        const roleText = document.getElementById("role-text");

        function atualizarTexto(){
            roleText.textContent = doador.checked ? "Doador" : "Instituição";
        }

        doador.addEventListener("change", atualizarTexto);
        inst.addEventListener("change", atualizarTexto);

        document.getElementById('cadastroForm').addEventListener('submit', function(e) {
            const senha = document.querySelector('input[name="senha"]').value;
            if (senha.length < 6) {
                e.preventDefault();
                alert('⚠️ A senha deve ter pelo menos 6 caracteres.');
                return false;
            }

            const email = document.querySelector('input[name="email"]').value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('⚠️ Email inválido.');
                return false;
            }

            return true;
        });
    </script>
</body>
</html>