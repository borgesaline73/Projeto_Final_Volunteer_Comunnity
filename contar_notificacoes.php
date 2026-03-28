<?php
session_start();
require "banco.php";

if (!isset($_SESSION["usuario_id"])) {
    echo json_encode(['total' => 0]);
    exit;
}

$id_usuario = $_SESSION["usuario_id"];

try {
    $sql = "SELECT COUNT(*) as total 
            FROM notificacoes 
            WHERE id_usuario = ? AND lida = FALSE";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_usuario]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['total' => $result['total'] ?? 0]);
} catch (PDOException $e) {
    error_log("Erro ao contar notificações: " . $e->getMessage());
    echo json_encode(['total' => 0]);
}
?>