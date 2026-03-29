<?php
session_start();
require "banco.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION["usuario_tipo"] !== "instituicao") {
    header("Location: feed.php");
    exit;
}

$id_usuario = $_SESSION["usuario_id"];
$titulo     = trim($_POST["titulo"]    ?? "");
$categoria  = trim($_POST["categoria"] ?? "");
$descricao  = trim($_POST["descricao"] ?? "");
$id_post    = (int)($_POST["id_post"]  ?? 0);

if (empty($titulo) || empty($categoria) || empty($descricao)) {
    die("Preencha todos os campos obrigatorios.");
}

$pasta = "uploads/";
if (!is_dir($pasta)) {
    mkdir($pasta, 0755, true);
}

if ($id_post > 0) {

    $stmt = $pdo->prepare("SELECT * FROM posts WHERE id_post = ? AND id_usuario = ?");
    $stmt->execute([$id_post, $id_usuario]);
    $post_atual = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post_atual) {
        header("Location: perfil-ong.php");
        exit;
    }

    $imagemNome = $post_atual['imagem'];

    if (!empty($_POST["remover_imagem"])) {
        if (!empty($imagemNome) && file_exists($pasta . $imagemNome)) {
            unlink($pasta . $imagemNome);
        }
        $imagemNome = null;
    }

    if (!empty($_FILES["imagem"]["name"])) {
        $ext = strtolower(pathinfo($_FILES["imagem"]["name"], PATHINFO_EXTENSION));
        $permitidos = ["jpg", "jpeg", "png", "gif", "webp"];

        if (in_array($ext, $permitidos)) {
            if (!empty($imagemNome) && file_exists($pasta . $imagemNome)) {
                unlink($pasta . $imagemNome);
            }
            $imagemNome = uniqid("post_") . "." . $ext;
            move_uploaded_file($_FILES["imagem"]["tmp_name"], $pasta . $imagemNome);
        }
    }

    $stmt = $pdo->prepare("UPDATE posts SET titulo = ?, categoria = ?, descricao = ?, imagem = ? WHERE id_post = ? AND id_usuario = ?");
    $stmt->execute([$titulo, $categoria, $descricao, $imagemNome, $id_post, $id_usuario]);

    echo "<script>alert('Publicacao atualizada com sucesso!'); window.location='perfil-ong.php';</script>";
    exit;
}

$imagemNome = null;

if (!empty($_FILES["imagem"]["name"])) {
    $ext = strtolower(pathinfo($_FILES["imagem"]["name"], PATHINFO_EXTENSION));
    $permitidos = ["jpg", "jpeg", "png", "gif", "webp"];

    if (in_array($ext, $permitidos)) {
        $imagemNome = uniqid("post_") . "." . $ext;
        move_uploaded_file($_FILES["imagem"]["tmp_name"], $pasta . $imagemNome);
    }
}

$stmt = $pdo->prepare("INSERT INTO posts (id_usuario, titulo, categoria, descricao, imagem) VALUES (:id, :titulo, :categoria, :descricao, :imagem)");
$stmt->execute([":id" => $id_usuario, ":titulo" => $titulo, ":categoria" => $categoria, ":descricao" => $descricao, ":imagem" => $imagemNome]);

echo "<script>alert('Publicacao criada com sucesso!'); window.location='feed.php';</script>";
?>