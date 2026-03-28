<?php
session_start();
require "banco.php";

// Verificar se usuário está logado
if (!isset($_SESSION["usuario_id"])) {
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

$id_usuario = $_SESSION["usuario_id"];
$id_notificacao = $_POST['id_notificacao'] ?? null;
$marcar_todas = $_POST['marcar_todas'] ?? false;

header('Content-Type: application/json');

try {
    if ($marcar_todas) {
        // Marcar TODAS as notificações como lidas
        $sql = "UPDATE notificacoes SET lida = TRUE WHERE id_usuario = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_usuario]);
        
        echo json_encode(['success' => true, 'message' => 'Todas as notificações marcadas como lidas']);
    } elseif ($id_notificacao) {
        // Marcar UMA notificação específica como lida
        $sql = "UPDATE notificacoes SET lida = TRUE WHERE id_notificacao = ? AND id_usuario = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_notificacao, $id_usuario]);
        
        echo json_encode(['success' => true, 'message' => 'Notificação marcada como lida']);
    } else {
        echo json_encode(['success' => false, 'error' => 'ID da notificação não fornecido']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>