<?php
session_start();
require "banco.php";

if (!isset($_SESSION["usuario_id"]) || $_SESSION["usuario_tipo"] !== "instituicao") {
    header("Location: login.php");
    exit;
}

$id_ong  = $_SESSION["usuario_id"];
$id_post = (int)($_GET["id"] ?? 0);

if ($id_post > 0) {
    // Confirma que o post pertence à ONG antes de excluir
    $stmt = $pdo->prepare("SELECT imagem FROM posts WHERE id_post = ? AND id_usuario = ?");
    $stmt->execute([$id_post, $id_ong]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($post) {
        // Remove imagem do servidor se existir
        if (!empty($post['imagem']) && file_exists("uploads/" . $post['imagem'])) {
            unlink("uploads/" . $post['imagem']);
        }
        $pdo->prepare("DELETE FROM posts WHERE id_post = ? AND id_usuario = ?")->execute([$id_post, $id_ong]);
    }
}

header("Location: perfil-ong.php");
exit;