<?php
session_start();
require "banco.php";

// Verificar se usuário está logado e é instituição
if (!isset($_SESSION["usuario_id"]) || ($_SESSION["usuario_tipo"] ?? null) !== "instituicao") {
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$id_ong = $_SESSION["usuario_id"];
$id_doacao = $_POST['id_doacao'] ?? null;
$marcar_todas = $_POST['marcar_todas'] ?? false;

header('Content-Type: application/json');

try {
    if ($marcar_todas) {
        // Marcar TODAS as coletas como visualizadas
        // Primeiro, identificar todas as coletas da ONG (hoje e próximos dias)
        $sql_coletas = "SELECT d.id_doacao FROM doacoes d 
                       JOIN coletas c ON d.id_doacao = c.id_doacao
                       WHERE d.id_ong = ? 
                       AND d.status = 'AGENDADA'
                       AND c.data_agendada >= CURRENT_DATE
                       AND c.data_agendada <= CURRENT_DATE + INTERVAL '7 days'";
        
        $stmt_coletas = $pdo->prepare($sql_coletas);
        $stmt_coletas->execute([$id_ong]);
        $coletas = $stmt_coletas->fetchAll(PDO::FETCH_ASSOC);
        
        // Marcar cada coleta como visualizada
        foreach ($coletas as $coleta) {
            $sql_insert = "INSERT INTO coletas_visualizadas (id_ong, id_doacao, visualizada) 
                           VALUES (?, ?, TRUE)
                           ON CONFLICT (id_ong, id_doacao) 
                           DO UPDATE SET visualizada = TRUE, data_visualizacao = CURRENT_TIMESTAMP";
            $stmt_insert = $pdo->prepare($sql_insert);
            $stmt_insert->execute([$id_ong, $coleta['id_doacao']]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Todas as coletas marcadas como visualizadas']);
        
    } elseif ($id_doacao) {
        // Extrair ID da doação se for um ID de notificação
        if (strpos($id_doacao, 'coleta_') === 0) {
            $id_doacao = str_replace('coleta_', '', $id_doacao);
        }
        
        // Verificar se a doação pertence à ONG
        $sql_verificar = "SELECT id_doacao FROM doacoes WHERE id_ong = ? AND id_doacao = ?";
        $stmt_verificar = $pdo->prepare($sql_verificar);
        $stmt_verificar->execute([$id_ong, $id_doacao]);
        
        if ($stmt_verificar->rowCount() === 0) {
            echo json_encode(['success' => false, 'error' => 'Coleta não encontrada ou não pertence a esta ONG']);
            exit;
        }
        
        // Marcar UMA coleta específica como visualizada
        $sql = "INSERT INTO coletas_visualizadas (id_ong, id_doacao, visualizada) 
                VALUES (?, ?, TRUE)
                ON CONFLICT (id_ong, id_doacao) 
                DO UPDATE SET visualizada = TRUE, data_visualizacao = CURRENT_TIMESTAMP";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_ong, $id_doacao]);
        
        echo json_encode(['success' => true, 'message' => 'Coleta marcada como visualizada']);
    } else {
        echo json_encode(['success' => false, 'error' => 'ID da coleta não fornecido']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>