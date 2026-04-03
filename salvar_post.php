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

// ─── Validação dos campos obrigatórios ──────────────────────────────────────
if (empty($titulo) || empty($categoria) || empty($descricao)) {
    $error_msg = urlencode("Preencha todos os campos obrigatórios.");
    header("Location: criar_post.php" . ($id_post > 0 ? "?id=$id_post&error=1&msg=$error_msg" : "?error=1&msg=$error_msg"));
    exit;
}

$pasta = "uploads/";
if (!is_dir($pasta)) {
    mkdir($pasta, 0755, true);
}

// ─── EDIÇÃO de post existente ───────────────────────────────────────────────
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

    $success_msg = urlencode("Publicação atualizada com sucesso!");
    header("Location: perfil-ong.php?msg=$success_msg&tipo=success");
    exit;
}

// ─── CRIAÇÃO de novo post ────────────────────────────────────────────────────
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

$success_msg = urlencode("Publicação criada com sucesso!");
header("Location: feed.php?msg=$success_msg&tipo=success");
exit;
?>