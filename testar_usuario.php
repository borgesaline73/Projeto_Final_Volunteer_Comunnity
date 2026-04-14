<?php //Página para teste e usuário
require "banco.php";

echo "<h2>Verificando usuários no banco</h2>";

try {
    // Listar todos os usuários
    $stmt = $pdo->query("SELECT id_usuario, nome, email, tipo_usuario FROM usuarios WHERE ativo = true");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($usuarios)) {
        echo "❌ Nenhum usuário encontrado no banco!<br>";
        echo "Você precisa cadastrar um usuário primeiro.<br>";
        echo "<a href='cadastro.php'>Cadastre-se aqui</a>";
    } else {
        echo "✅ Usuários encontrados:<br><br>";
        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<tr><th>ID</th><th>Nome</th><th>Email</th><th>Tipo</th></tr>";
        foreach ($usuarios as $user) {
            echo "<tr>";
            echo "<td>" . $user['id_usuario'] . "</td>";
            echo "<td>" . htmlspecialchars($user['nome']) . "</td>";
            echo "<td>" . htmlspecialchars($user['email']) . "</td>";
            echo "<td>" . htmlspecialchars($user['tipo_usuario']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Testar senha específica se fornecida via GET
    if (isset($_GET['test_email'])) {
        $email = $_GET['test_email'];
        $senha = $_GET['test_senha'] ?? '';
        
        echo "<br><br><strong>Testando login para: $email</strong><br>";
        
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND ativo = true");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo "✅ Usuário encontrado!<br>";
            echo "Senha no banco (hash): " . $user['senha'] . "<br>";
            
            if (password_verify($senha, $user['senha'])) {
                echo "✅ SENHA CORRETA!<br>";
            } else {
                echo "❌ Senha incorreta!<br>";
                echo "Senha digitada: $senha<br>";
            }
        } else {
            echo "❌ Usuário não encontrado!<br>";
        }
    }
    
} catch (PDOException $e) {
    echo "❌ Erro: " . $e->getMessage();
}
?>

<br><br>
<hr>
<h3>Testar login específico</h3>
<form method="get">
    Email: <input type="email" name="test_email" required>
    Senha: <input type="text" name="test_senha" required>
    <button type="submit">Testar</button>
</form>