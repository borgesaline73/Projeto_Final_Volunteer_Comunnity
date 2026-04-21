<?php
session_start();
require "banco.php";

header('Content-Type: application/json');

if (!isset($_SESSION["usuario_id"]) || $_SESSION["usuario_tipo"] !== "instituicao") {
    echo json_encode(["sucesso" => false, "erro" => "Não autorizado"]);
    exit;
}

$id_ong = $_SESSION["usuario_id"];
$acao   = $_POST["acao"] ?? $_GET["acao"] ?? null;

try {
    if ($acao === "adicionar") {
        $nome = trim($_POST["nome"] ?? "");
        $tipo = $_POST["tipo"] ?? "";

        if (empty($nome) || !in_array($tipo, ["ACEITO", "RECUSADO"])) {
            echo json_encode(["sucesso" => false, "erro" => "Dados inválidos"]);
            exit;
        }

        // Verifica duplicata - CORRIGIDO: use 'id' em vez de 'id_item'
        $chk = $pdo->prepare("SELECT id FROM itens_ong WHERE id_ong=? AND nome=? AND tipo=?");
        $chk->execute([$id_ong, $nome, $tipo]);
        if ($chk->fetch()) {
            echo json_encode(["sucesso" => false, "erro" => "Item já cadastrado"]);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO itens_ong (id_ong, nome, tipo) VALUES (?, ?, ?)");
        $stmt->execute([$id_ong, $nome, $tipo]);
        
        // CORRIGIDO: retorna o ID inserido
        echo json_encode(["sucesso" => true, "id_item" => $pdo->lastInsertId(), "nome" => $nome]);

    } elseif ($acao === "remover") {
        $id_item = (int)($_POST["id_item"] ?? 0);

        if ($id_item <= 0) {
            echo json_encode(["sucesso" => false, "erro" => "ID inválido"]);
            exit;
        }

        // CORRIGIDO: use 'id' em vez de 'id_item'
        $stmt = $pdo->prepare("DELETE FROM itens_ong WHERE id=? AND id_ong=?");
        $stmt->execute([$id_item, $id_ong]);
        echo json_encode(["sucesso" => true]);

    } else {
        echo json_encode(["sucesso" => false, "erro" => "Ação desconhecida"]);
    }

} catch (PDOException $e) {
    error_log("Erro no banco: " . $e->getMessage());
    echo json_encode(["sucesso" => false, "erro" => "Erro no banco de dados: " . $e->getMessage()]);
}
?>