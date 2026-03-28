<?php
session_start();
require "banco.php";

// Verificar se usuário está logado
if (!isset($_SESSION["usuario_id"])) {
    header('Content-Type: application/json');
    echo json_encode(['novas' => []]);
    exit;
}

$id_usuario = $_SESSION["usuario_id"];
$tipo = $_SESSION["usuario_tipo"] ?? null;
$ultima_verificacao = $_GET['ultima_verificacao'] ?? time();

$novas_notificacoes = [];

try {
    // Para INSTITUIÇÕES: Buscar coletas agendadas desde a última verificação
    if ($tipo === "instituicao") {
        $sql = "SELECT d.*, u.nome as nome_doador, u.email as email_doador,
                       c.data_agendada, c.endereco as local_coleta,
                       'COLETA_AGENDADA' as tipo_notificacao
                FROM doacoes d 
                JOIN usuarios u ON d.id_doador = u.id_usuario 
                JOIN coletas c ON d.id_doacao = c.id_doacao
                WHERE d.id_ong = ? 
                AND c.data_agendada > to_timestamp(?)
                AND d.status = 'AGENDADA'
                ORDER BY c.data_agendada DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_usuario, $ultima_verificacao]);
        $coletas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($coletas as $coleta) {
            $novas_notificacoes[] = [
                'id' => 'coleta_' . $coleta['id_doacao'],
                'mensagem' => $coleta['nome_doador'] . ' agendou uma coleta de ' . $coleta['tipo'] . 
                             ' para ' . date('d/m H:i', strtotime($coleta['data_agendada'])) . 
                             ' no local: ' . $coleta['local_coleta'],
                'data_envio' => $coleta['data_agendada'],
                'tipo' => 'COLETA_AGENDADA',
                'detalhes' => 'Doador: ' . $coleta['nome_doador'] . 
                             '<br>Email: ' . $coleta['email_doador'] . 
                             '<br>Tipo: ' . $coleta['tipo'] .
                             (!empty($coleta['descricao_item']) ? '<br>Descrição: ' . $coleta['descricao_item'] : '')
            ];
        }
    } else {
        // Para DOADORES: Buscar notificações desde a última verificação
        $sql = "SELECT * FROM notificacoes 
                WHERE id_usuario = ? 
                AND data_envio > to_timestamp(?)
                ORDER BY data_envio DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_usuario, $ultima_verificacao]);
        $notificacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($notificacoes as $notif) {
            $novas_notificacoes[] = [
                'id' => $notif['id_notificacao'],
                'mensagem' => $notif['mensagem'],
                'data_envio' => $notif['data_envio'],
                'tipo' => $notif['tipo'] ?? null
            ];
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'novas' => $novas_notificacoes,
        'timestamp_atual' => time()
    ]);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'novas' => [],
        'timestamp_atual' => time(),
        'error' => $e->getMessage()
    ]);
}
?>