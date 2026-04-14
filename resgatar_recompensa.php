<?php //Essa página não vai mais constar por enquanto no projeto! deixar em stand by até segunda ordem
session_start();
require "banco.php";

// Verificar se está logado
if (!isset($_SESSION["usuario_id"])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

// Verificar se é doador
if ($_SESSION["usuario_tipo"] === "instituicao") {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Apenas doadores podem resgatar recompensas']);
    exit;
}

$id_doador = $_SESSION["usuario_id"];
$id_recompensa = $_POST['id_recompensa'] ?? null;

if (!$id_recompensa) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Recompensa não especificada']);
    exit;
}

try {
    // Iniciar transação
    $pdo->beginTransaction();
    
    // Buscar ID do doador na tabela doadores
    $stmt_doador = $pdo->prepare("SELECT id_doador FROM doadores WHERE id_doador = ?");
    $stmt_doador->execute([$id_doador]);
    $doador_info = $stmt_doador->fetch(PDO::FETCH_ASSOC);
    
    if (!$doador_info) {
        throw new Exception("Doador não encontrado");
    }
    
    $id_doador_table = $doador_info['id_doador'];
    
    // Buscar informações da recompensa
    $stmt_recompensa = $pdo->prepare("SELECT * FROM recompensas WHERE id_recompensa = ?");
    $stmt_recompensa->execute([$id_recompensa]);
    $recompensa = $stmt_recompensa->fetch(PDO::FETCH_ASSOC);
    
    if (!$recompensa) {
        throw new Exception("Recompensa não encontrada");
    }
    
    // Calcular pontos do doador
    $sql_pontos = "SELECT COUNT(*) as total FROM doacoes WHERE id_doador = ? AND status = 'RECEBIDA'";
    $stmt_pontos = $pdo->prepare($sql_pontos);
    $stmt_pontos->execute([$id_doador_table]);
    $result = $stmt_pontos->fetch(PDO::FETCH_ASSOC);
    $total_doacoes = $result['total'];
    $pontos = $total_doacoes * 10;
    
    // Verificar se tem pontos suficientes
    if ($pontos < $recompensa['pontos_necessarios']) {
        throw new Exception("Pontos insuficientes");
    }
    
    // Verificar se já resgatou esta recompensa
    $stmt_verifica = $pdo->prepare("SELECT * FROM trocas_pontos WHERE id_doador = ? AND id_recompensa = ?");
    $stmt_verifica->execute([$id_doador_table, $id_recompensa]);
    
    if ($stmt_verifica->fetch()) {
        throw new Exception("Você já resgatou esta recompensa");
    }
    
    // Registrar a troca
    $stmt_troca = $pdo->prepare("INSERT INTO trocas_pontos (id_doador, id_recompensa) VALUES (?, ?)");
    $stmt_troca->execute([$id_doador_table, $id_recompensa]);
    
    // Commit da transação
    $pdo->commit();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Recompensa resgatada com sucesso']);
    
} catch (Exception $e) {
    // Rollback em caso de erro
    $pdo->rollBack();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>