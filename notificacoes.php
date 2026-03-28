<?php
session_start();
require "banco.php";

// Bloqueio para usuários não logados
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

// Tipo do usuário
$tipo = $_SESSION["usuario_tipo"] ?? null;
$id_usuario = $_SESSION["usuario_id"];

// Rota do botão + e perfil
if ($tipo === "instituicao") {
    $rotaPlus = "criar_post.php";
    $rotaPerfil = "perfil-ong.php";
} else {
    $rotaPlus = "agendar_coleta.php";
    $rotaPerfil = "perfil.php";
}

// Buscar notificações baseadas no tipo de usuário
$notificacoes_hoje = [];
$notificacoes_semana = [];
$notificacoes_anteriores = [];

try {
    // Para INSTITUIÇÕES: Buscar coletas agendadas com status de visualização
    if ($tipo === "instituicao") {
        // Notificações de HOJE - Coletas agendadas para hoje (não visualizadas)
        $sql_hoje = "SELECT d.*, u.nome as nome_doador, u.email as email_doador,
                            c.data_agendada, c.endereco as local_coleta,
                            cv.visualizada as ja_visualizada,
                            'COLETA_AGENDADA' as tipo_notificacao
                     FROM doacoes d 
                     JOIN usuarios u ON d.id_doador = u.id_usuario 
                     JOIN coletas c ON d.id_doacao = c.id_doacao
                     LEFT JOIN coletas_visualizadas cv ON d.id_doacao = cv.id_doacao AND cv.id_ong = ?
                     WHERE d.id_ong = ? 
                     AND DATE(c.data_agendada) = CURRENT_DATE
                     AND d.status = 'AGENDADA'
                     ORDER BY c.data_agendada ASC";
        
        $stmt_hoje = $pdo->prepare($sql_hoje);
        $stmt_hoje->execute([$id_usuario, $id_usuario]);
        $coletas_hoje = $stmt_hoje->fetchAll(PDO::FETCH_ASSOC);
        
        // Converter coletas em notificações
        foreach ($coletas_hoje as $coleta) {
            $notificacoes_hoje[] = [
                'id_notificacao' => 'coleta_' . $coleta['id_doacao'],
                'id_doacao' => $coleta['id_doacao'],
                'mensagem' => $coleta['nome_doador'] . ' agendou uma coleta de ' . $coleta['tipo'] . 
                             ' para ' . date('H:i', strtotime($coleta['data_agendada'])) . 
                             ' no local: ' . $coleta['local_coleta'],
                'data_envio' => $coleta['data_agendada'],
                'lida' => $coleta['ja_visualizada'] ?? false,
                'tipo' => 'COLETA_AGENDADA',
                'dados_coleta' => $coleta
            ];
        }

        // Notificações desta SEMANA - Coletas agendadas para esta semana (não visualizadas)
        $sql_semana = "SELECT d.*, u.nome as nome_doador, u.email as email_doador,
                              c.data_agendada, c.endereco as local_coleta,
                              cv.visualizada as ja_visualizada,
                              'COLETA_AGENDADA' as tipo_notificacao
                       FROM doacoes d 
                       JOIN usuarios u ON d.id_doador = u.id_usuario 
                       JOIN coletas c ON d.id_doacao = c.id_doacao
                       LEFT JOIN coletas_visualizadas cv ON d.id_doacao = cv.id_doacao AND cv.id_ong = ?
                       WHERE d.id_ong = ? 
                       AND DATE(c.data_agendada) BETWEEN CURRENT_DATE + INTERVAL '1 day' AND CURRENT_DATE + INTERVAL '7 days'
                       AND d.status = 'AGENDADA'
                       AND (cv.visualizada IS NULL OR cv.visualizada = FALSE)
                       ORDER BY c.data_agendada ASC";
        
        $stmt_semana = $pdo->prepare($sql_semana);
        $stmt_semana->execute([$id_usuario, $id_usuario]);
        $coletas_semana = $stmt_semana->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($coletas_semana as $coleta) {
            $notificacoes_semana[] = [
                'id_notificacao' => 'coleta_' . $coleta['id_doacao'],
                'id_doacao' => $coleta['id_doacao'],
                'mensagem' => $coleta['nome_doador'] . ' agendou uma coleta de ' . $coleta['tipo'] . 
                             ' para ' . date('d/m H:i', strtotime($coleta['data_agendada'])) . 
                             ' no local: ' . $coleta['local_coleta'],
                'data_envio' => $coleta['data_agendada'],
                'lida' => $coleta['ja_visualizada'] ?? false,
                'tipo' => 'COLETA_AGENDADA',
                'dados_coleta' => $coleta
            ];
        }

        // Notificações mais antigas - Coletas anteriores (visualizadas ou não)
        $sql_anteriores = "SELECT d.*, u.nome as nome_doador, u.email as email_doador,
                                  c.data_agendada, c.endereco as local_coleta,
                                  cv.visualizada as ja_visualizada,
                                  'COLETA_AGENDADA' as tipo_notificacao
                           FROM doacoes d 
                           JOIN usuarios u ON d.id_doador = u.id_usuario 
                           JOIN coletas c ON d.id_doacao = c.id_doacao
                           LEFT JOIN coletas_visualizadas cv ON d.id_doacao = cv.id_doacao AND cv.id_ong = ?
                           WHERE d.id_ong = ? 
                           AND DATE(c.data_agendada) < CURRENT_DATE
                           AND d.status = 'AGENDADA'
                           ORDER BY c.data_agendada DESC 
                           LIMIT 10";
        
        $stmt_anteriores = $pdo->prepare($sql_anteriores);
        $stmt_anteriores->execute([$id_usuario, $id_usuario]);
        $coletas_anteriores = $stmt_anteriores->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($coletas_anteriores as $coleta) {
            $notificacoes_anteriores[] = [
                'id_notificacao' => 'coleta_' . $coleta['id_doacao'],
                'id_doacao' => $coleta['id_doacao'],
                'mensagem' => $coleta['nome_doador'] . ' agendou uma coleta de ' . $coleta['tipo'] . 
                             ' para ' . date('d/m/Y H:i', strtotime($coleta['data_agendada'])) . 
                             ' no local: ' . $coleta['local_coleta'],
                'data_envio' => $coleta['data_agendada'],
                'lida' => $coleta['ja_visualizada'] ?? false,
                'tipo' => 'COLETA_AGENDADA',
                'dados_coleta' => $coleta
            ];
        }

    } else {
        // Para DOADORES: Buscar notificações do usuário
        $sql_hoje = "SELECT * FROM notificacoes 
                    WHERE id_usuario = ? 
                    AND data_envio::date = CURRENT_DATE
                    ORDER BY data_envio DESC";
        $stmt_hoje = $pdo->prepare($sql_hoje);
        $stmt_hoje->execute([$id_usuario]);
        $notificacoes_hoje = $stmt_hoje->fetchAll(PDO::FETCH_ASSOC);

        $sql_semana = "SELECT * FROM notificacoes 
                      WHERE id_usuario = ? 
                      AND data_envio::date BETWEEN CURRENT_DATE - INTERVAL '7 days' AND CURRENT_DATE - INTERVAL '1 day'
                      ORDER BY data_envio DESC";
        $stmt_semana = $pdo->prepare($sql_semana);
        $stmt_semana->execute([$id_usuario]);
        $notificacoes_semana = $stmt_semana->fetchAll(PDO::FETCH_ASSOC);

        $sql_anteriores = "SELECT * FROM notificacoes 
                          WHERE id_usuario = ? 
                          AND data_envio::date < CURRENT_DATE - INTERVAL '7 days'
                          ORDER BY data_envio DESC 
                          LIMIT 10";
        $stmt_anteriores = $pdo->prepare($sql_anteriores);
        $stmt_anteriores->execute([$id_usuario]);
        $notificacoes_anteriores = $stmt_anteriores->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    $error_db = "Erro ao carregar notificações: " . $e->getMessage();
    error_log("Erro PostgreSQL: " . $e->getMessage());
}

// Função para formatar data relativa
function formatarDataRelativa($data) {
    $agora = new DateTime();
    $data_notificacao = new DateTime($data);
    $diferenca = $agora->diff($data_notificacao);
    
    if ($diferenca->days == 0) {
        if ($diferenca->h == 0) {
            return $diferenca->i . ' min atrás';
        }
        return $diferenca->h . ' h atrás';
    } elseif ($diferenca->days == 1) {
        return 'Ontem';
    } elseif ($diferenca->days < 7) {
        return $diferenca->days . ' dias atrás';
    } else {
        return $data_notificacao->format('d/m/Y');
    }
}

// Função para obter ícone baseado no tipo de notificação
function getIconeMensagem($mensagem, $tipo = null) {
    if ($tipo === 'COLETA_AGENDADA') {
        return '📦';
    } elseif (strpos($mensagem, 'agendou') !== false) {
        return '📦';
    } elseif (strpos($mensagem, 'publicou') !== false) {
        return '📢';
    } elseif (strpos($mensagem, 'curtiu') !== false) {
        return '❤️';
    } elseif (strpos($mensagem, 'comentou') !== false) {
        return '💬';
    } else {
        return '🔔';
    }
}

// Função para extrair título da mensagem
function getTituloMensagem($mensagem, $tipo = null) {
    if ($tipo === 'COLETA_AGENDADA') {
        return 'Nova Coleta Agendada';
    } elseif (strpos($mensagem, 'agendou') !== false) {
        return 'Nova Coleta';
    } elseif (strpos($mensagem, 'publicou') !== false) {
        return 'Nova Publicação';
    } elseif (strpos($mensagem, 'curtiu') !== false) {
        return 'Nova Curtida';
    } elseif (strpos($mensagem, 'comentou') !== false) {
        return 'Novo Comentário';
    } else {
        return 'Notificação';
    }
}

// Calcular total de não lidas
if ($tipo === "instituicao") {
    $total_nao_lidas = 0;
    foreach ($notificacoes_hoje as $notif) {
        if (!$notif['lida']) $total_nao_lidas++;
    }
    foreach ($notificacoes_semana as $notif) {
        if (!$notif['lida']) $total_nao_lidas++;
    }
} else {
    $total_nao_lidas = 0;
    foreach (array_merge($notificacoes_hoje, $notificacoes_semana) as $notif) {
        if (!$notif['lida']) $total_nao_lidas++;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notificações - Volunteer Community</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/estilo_global.css">
<link rel="stylesheet" href="css/estilo_notificacoes.css">
</head>

<body>

<div class="phone">

  <!-- HEADER -->
  <div class="header">
    <h1>Notificações 
      <?php if ($tipo === "instituicao"): ?>
        <span class="user-type-badge">ONG</span>
      <?php else: ?>
        <span class="user-type-badge">Doador</span>
      <?php endif; ?>
    </h1>
    <button class="clear-btn" id="clear-btn" onclick="marcarTodasComoLidas()"
            <?php if ($total_nao_lidas === 0): ?>disabled style="opacity: 0.5; cursor: not-allowed;"<?php endif; ?>>
      Limpar <?php if ($total_nao_lidas > 0): ?>(<?= $total_nao_lidas ?>)<?php endif; ?>
    </button>
  </div>

  <!-- CONTEÚDO COM SCROLL -->
  <div class="content">
    <!-- HOJE -->
    <?php if (!empty($notificacoes_hoje)): ?>
    <div class="section">
      <div class="section-title">Hoje</div>
      <div class="list">
        <?php foreach ($notificacoes_hoje as $notificacao): ?>
          <div class="notification-item <?= (!$notificacao['lida']) ? 'unread' : '' ?>" 
               onclick="marcarComoLida('<?= $notificacao['id_notificacao'] ?>', '<?= $notificacao['id_doacao'] ?? '' ?>', this)">
            <div class="notification-header">
              <div class="notification-icon">
                <?= getIconeMensagem($notificacao['mensagem'], $notificacao['tipo'] ?? null) ?>
              </div>
              <div class="notification-content">
                <div class="notification-title">
                  <?= getTituloMensagem($notificacao['mensagem'], $notificacao['tipo'] ?? null) ?>
                </div>
                <div class="notification-message">
                  <?= htmlspecialchars($notificacao['mensagem']) ?>
                </div>
                <?php if (isset($notificacao['dados_coleta'])): ?>
                  <div class="notification-details">
                    <div><strong>Doador:</strong> <?= htmlspecialchars($notificacao['dados_coleta']['nome_doador']) ?></div>
                    <div><strong>Email:</strong> <?= htmlspecialchars($notificacao['dados_coleta']['email_doador']) ?></div>
                    <div><strong>Tipo:</strong> <?= htmlspecialchars($notificacao['dados_coleta']['tipo']) ?></div>
                    <?php if (!empty($notificacao['dados_coleta']['descricao_item'])): ?>
                      <div><strong>Descrição:</strong> <?= htmlspecialchars($notificacao['dados_coleta']['descricao_item']) ?></div>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
                <div class="notification-time">
                  <?= formatarDataRelativa($notificacao['data_envio']) ?>
                  <?php if ($notificacao['lida']): ?>
                    <span style="color: #2ecc71; margin-left: 5px;">✓ Visualizada</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- ESTA SEMANA -->
    <?php if (!empty($notificacoes_semana)): ?>
    <div class="section">
      <div class="section-title">Próximos dias</div>
      <div class="list">
        <?php foreach ($notificacoes_semana as $notificacao): ?>
          <div class="notification-item <?= (!$notificacao['lida']) ? 'unread' : '' ?>" 
               onclick="marcarComoLida('<?= $notificacao['id_notificacao'] ?>', '<?= $notificacao['id_doacao'] ?? '' ?>', this)">
            <div class="notification-header">
              <div class="notification-icon">
                <?= getIconeMensagem($notificacao['mensagem'], $notificacao['tipo'] ?? null) ?>
              </div>
              <div class="notification-content">
                <div class="notification-title">
                  <?= getTituloMensagem($notificacao['mensagem'], $notificacao['tipo'] ?? null) ?>
                </div>
                <div class="notification-message">
                  <?= htmlspecialchars($notificacao['mensagem']) ?>
                </div>
                <?php if (isset($notificacao['dados_coleta'])): ?>
                  <div class="notification-details">
                    <div><strong>Doador:</strong> <?= htmlspecialchars($notificacao['dados_coleta']['nome_doador']) ?></div>
                    <div><strong>Email:</strong> <?= htmlspecialchars($notificacao['dados_coleta']['email_doador']) ?></div>
                    <div><strong>Tipo:</strong> <?= htmlspecialchars($notificacao['dados_coleta']['tipo']) ?></div>
                    <?php if (!empty($notificacao['dados_coleta']['descricao_item'])): ?>
                      <div><strong>Descrição:</strong> <?= htmlspecialchars($notificacao['dados_coleta']['descricao_item']) ?></div>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
                <div class="notification-time">
                  <?= formatarDataRelativa($notificacao['data_envio']) ?>
                  <?php if ($notificacao['lida']): ?>
                    <span style="color: #2ecc71; margin-left: 5px;">✓ Visualizada</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- MAIS ANTIGAS -->
    <?php if (!empty($notificacoes_anteriores)): ?>
    <div class="section">
      <div class="section-title">Coletas anteriores</div>
      <div class="list">
        <?php foreach ($notificacoes_anteriores as $notificacao): ?>
          <div class="notification-item" 
               onclick="marcarComoLida('<?= $notificacao['id_notificacao'] ?>', '<?= $notificacao['id_doacao'] ?? '' ?>', this)">
            <div class="notification-header">
              <div class="notification-icon">
                <?= getIconeMensagem($notificacao['mensagem'], $notificacao['tipo'] ?? null) ?>
              </div>
              <div class="notification-content">
                <div class="notification-title">
                  <?= getTituloMensagem($notificacao['mensagem'], $notificacao['tipo'] ?? null) ?>
                </div>
                <div class="notification-message">
                  <?= htmlspecialchars($notificacao['mensagem']) ?>
                </div>
                <?php if (isset($notificacao['dados_coleta'])): ?>
                  <div class="notification-details">
                    <div><strong>Doador:</strong> <?= htmlspecialchars($notificacao['dados_coleta']['nome_doador']) ?></div>
                    <div><strong>Email:</strong> <?= htmlspecialchars($notificacao['dados_coleta']['email_doador']) ?></div>
                    <div><strong>Tipo:</strong> <?= htmlspecialchars($notificacao['dados_coleta']['tipo']) ?></div>
                    <?php if (!empty($notificacao['dados_coleta']['descricao_item'])): ?>
                      <div><strong>Descrição:</strong> <?= htmlspecialchars($notificacao['dados_coleta']['descricao_item']) ?></div>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
                <div class="notification-time">
                  <?= formatarDataRelativa($notificacao['data_envio']) ?>
                  <?php if ($notificacao['lida']): ?>
                    <span style="color: #2ecc71; margin-left: 5px;">✓ Visualizada</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- MENSAGEM VAZIA -->
    <?php if (empty($notificacoes_hoje) && empty($notificacoes_semana) && empty($notificacoes_anteriores)): ?>
    <div class="empty-box">
      <?php if ($tipo === "instituicao"): ?>
        📭<br>
        <strong>Nenhuma coleta agendada</strong><br>
        <small>As coletas agendadas para sua ONG aparecerão aqui</small>
      <?php else: ?>
        📭<br>
        <strong>Nenhuma notificação</strong><br>
        <small>Suas notificações aparecerão aqui</small>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- MENU INFERIOR FIXO -->
  <div class="bottom">
    <a href="feed.php" class="menu-item">
      🏠
      <span>Feed</span>
    </a>

    <button class="plus-btn" onclick="window.location.href='<?= $rotaPlus ?>'">+</button>

    <a href="notificacoes.php" class="menu-item active">
      🔔
      <span>Notificações</span>
      <?php if ($total_nao_lidas > 0): ?>
        <span class="badge" id="badge-count"><?= $total_nao_lidas ?></span>
      <?php endif; ?>
    </a>

    <a href="<?= $rotaPerfil ?>" class="menu-item">
      👤
      <span>Perfil</span>
    </a>
  </div>

</div>

<script>
// Variável para controlar o tipo de usuário
const tipoUsuario = "<?= $tipo ?>";

// Função para marcar notificação como lida
async function marcarComoLida(idNotificacao, idDoacao, elemento) {
    elemento.classList.remove('unread');
    
    const timeElement = elemento.querySelector('.notification-time');
    if (timeElement && !timeElement.innerHTML.includes('✓ Visualizada')) {
        timeElement.innerHTML += ' <span style="color: #2ecc71; margin-left: 5px;">✓ Visualizada</span>';
    }
    
    if (tipoUsuario === "instituicao") {
        try {
            const formData = new FormData();
            const idParaEnviar = idDoacao || idNotificacao.replace('coleta_', '');
            formData.append('id_doacao', idParaEnviar);
            
            const response = await fetch('marcar_coleta_visualizada.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (!data.success) {
                elemento.classList.add('unread');
                if (timeElement) {
                    timeElement.innerHTML = timeElement.innerHTML.replace('<span style="color: #2ecc71; margin-left: 5px;">✓ Visualizada</span>', '');
                }
            } else {
                atualizarContadorNotificacoes();
            }
        } catch (error) {
            elemento.classList.add('unread');
            if (timeElement) {
                timeElement.innerHTML = timeElement.innerHTML.replace('<span style="color: #2ecc71; margin-left: 5px;">✓ Visualizada</span>', '');
            }
        }
    } else {
        try {
            const formData = new FormData();
            formData.append('id_notificacao', idNotificacao);
            
            const response = await fetch('marcar_como_lida.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (!data.success) {
                elemento.classList.add('unread');
                if (timeElement) {
                    timeElement.innerHTML = timeElement.innerHTML.replace('<span style="color: #2ecc71; margin-left: 5px;">✓ Visualizada</span>', '');
                }
            } else {
                atualizarContadorNotificacoes();
            }
        } catch (error) {
            elemento.classList.add('unread');
            if (timeElement) {
                timeElement.innerHTML = timeElement.innerHTML.replace('<span style="color: #2ecc71; margin-left: 5px;">✓ Visualizada</span>', '');
            }
        }
    }
}

// Função para marcar todas como lidas
async function marcarTodasComoLidas() {
    const confirmMessage = tipoUsuario === "instituicao" 
        ? 'Marcar TODAS as coletas como visualizadas?' 
        : 'Marcar TODAS as notificações como lidas?';
    
    if (!confirm(confirmMessage)) return;
    
    try {
        const formData = new FormData();
        formData.append('marcar_todas', true);
        
        const endpoint = tipoUsuario === "instituicao" 
            ? 'marcar_coleta_visualizada.php' 
            : 'marcar_como_lida.php';
        
        const response = await fetch(endpoint, { method: 'POST', body: formData });
        const data = await response.json();
        
        if (data.success) {
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
                const timeElement = item.querySelector('.notification-time');
                if (timeElement && !timeElement.innerHTML.includes('✓ Visualizada')) {
                    timeElement.innerHTML += ' <span style="color: #2ecc71; margin-left: 5px;">✓ Visualizada</span>';
                }
            });
            
            atualizarContadorNotificacoes();
            
            const clearBtn = document.getElementById('clear-btn');
            if (clearBtn) {
                clearBtn.innerHTML = 'Limpar';
                clearBtn.disabled = true;
                clearBtn.style.opacity = '0.5';
                clearBtn.style.cursor = 'not-allowed';
            }
            
            alert(tipoUsuario === "instituicao" ? 'Todas as coletas foram marcadas como visualizadas!' : 'Todas as notificações foram marcadas como lidas!');
        } else {
            alert('Erro: ' + data.error);
        }
    } catch (error) {
        alert('Erro ao conectar com o servidor');
    }
}

// Função para atualizar contador de notificações
function atualizarContadorNotificacoes() {
    const unreadCount = document.querySelectorAll('.notification-item.unread').length;
    const badge = document.getElementById('badge-count');
    const clearBtn = document.getElementById('clear-btn');
    
    if (unreadCount > 0) {
        if (badge) {
            badge.textContent = unreadCount;
        }
        if (clearBtn) {
            clearBtn.innerHTML = `Limpar (${unreadCount})`;
            clearBtn.disabled = false;
            clearBtn.style.opacity = '1';
            clearBtn.style.cursor = 'pointer';
        }
    } else {
        if (badge) badge.remove();
        if (clearBtn) {
            clearBtn.innerHTML = 'Limpar';
            clearBtn.disabled = true;
            clearBtn.style.opacity = '0.5';
            clearBtn.style.cursor = 'not-allowed';
        }
    }
}

// Inicializar
document.addEventListener('DOMContentLoaded', function() {
    atualizarContadorNotificacoes();
    document.body.style.overflow = 'hidden';
});
</script>

</body>
</html>