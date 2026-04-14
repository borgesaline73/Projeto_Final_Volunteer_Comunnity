
<?php
//Arquivo para teste de login, deixar em stand by até finalizar tudo
$senha = "123456";

$hash = password_hash($senha, PASSWORD_DEFAULT);

echo "Hash gerado: " . $hash . "<br>";

if (password_verify("123456", $hash)) {
    echo "Senha válida!";
} else {
    echo "Senha inválida!";
}
