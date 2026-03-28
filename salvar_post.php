<?php
session_start();
require "banco.php";

// Verifica login
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

// Só instituição posta
if ($_SESSION["usuario_tipo"] !== "instituicao") {
    header("Location: feed.php");
    exit;
}

$id_usuario = $_SESSION["usuario_id"];
$titulo = $_POST["titulo"] ?? "";
$categoria = $_POST["categoria"] ?? "";
$descricao = $_POST["descricao"] ?? "";

// Valida
if (empty($titulo) || empty($categoria) || empty($descricao)) {
    die("❌ Preencha todos os campos obrigatórios.");
}

// =============================
//  UPLOAD DA IMAGEM (opcional)
// =============================
$imagemNome = null;

if (!empty($_FILES["imagem"]["name"])) {

    $pasta = "uploads/";
    
    if (!is_dir($pasta)) {
        mkdir($pasta, 0755, true);
    }

    $ext = pathinfo($_FILES["imagem"]["name"], PATHINFO_EXTENSION);
    $imagemNome = uniqid("post_") . "." . $ext;

    move_uploaded_file($_FILES["imagem"]["tmp_name"], $pasta . $imagemNome);
}

// =============================
//  SALVAR NO BANCO
// =============================
$stmt = $pdo->prepare("
    INSERT INTO posts (id_usuario, titulo, categoria, descricao, imagem)
    VALUES (:id, :titulo, :categoria, :descricao, :imagem)
");

$stmt->execute([
    ":id" => $id_usuario,
    ":titulo" => $titulo,
    ":categoria" => $categoria,
    ":descricao" => $descricao,
    ":imagem" => $imagemNome
]);

echo "<script>
alert('✅ Publicação criada com sucesso!');
window.location='feed.php';
</script>";
?>
