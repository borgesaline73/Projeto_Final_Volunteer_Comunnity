<?php
session_start();
require "banco.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

$categoria = $_GET["categoria"] ?? "todos";

$mapCategorias = [
  'educacao'  => 'Educação',
  'saude'     => 'Saúde',
  'alimentos' => 'Alimentos',
  'campanhas' => 'Campanhas'
];

$categoria_banco = null;
if ($categoria !== "todos" && isset($mapCategorias[$categoria])) {
    $categoria_banco = $mapCategorias[$categoria];
}

$tipoUsuario = $_SESSION["usuario_tipo"] ?? null;

if ($tipoUsuario === "instituicao") {
    $acaoPlus = "criar_post.php";
    $rotaPerfil = "perfil-ong.php";
} else {
    $acaoPlus = "agendar_coleta.php";
    $rotaPerfil = "perfil.php";
}

$badgeMap = [
    'Educação'  => ['label' => 'Educação',  'class' => 'badge-blue'],
    'Saúde'     => ['label' => 'Saúde',     'class' => 'badge-green'],
    'Alimentos' => ['label' => 'Alimentos', 'class' => 'badge-orange'],
    'Campanhas' => ['label' => 'Campanhas', 'class' => 'badge-red'],
];

function inicialNome($nome) {
    return mb_strtoupper(mb_substr(trim($nome), 0, 1));
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Campanhas</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/estilo_global.css">
<link rel="stylesheet" href="css/estilo_campanhas.css">
</head>

<body>
<div class="phone">

  <!-- HEADER -->
  <div class="header">
    <h1>ONGs <br>do Mês</h1>

    <div class="tabs-container">
      <button class="tabs-scroll-btn left" id="scrollLeftBtn">
        <i class="fas fa-chevron-left"></i>
      </button>

      <div class="tabs-wrapper">
        <div class="tabs" id="tabsContainer">
          <a href="campanhas.php?categoria=todos"     class="tab <?= $categoria == 'todos'     ? 'active' : '' ?>">Todos</a>
          <a href="campanhas.php?categoria=educacao"  class="tab <?= $categoria == 'educacao'  ? 'active' : '' ?>">Educação</a>
          <a href="campanhas.php?categoria=saude"     class="tab <?= $categoria == 'saude'     ? 'active' : '' ?>">Saúde</a>
          <a href="campanhas.php?categoria=alimentos" class="tab <?= $categoria == 'alimentos' ? 'active' : '' ?>">Alimentos</a>
          <a href="campanhas.php?categoria=campanhas" class="tab <?= $categoria == 'campanhas' ? 'active' : '' ?>">Campanhas</a>
        </div>
      </div>

      <button class="tabs-scroll-btn right" id="scrollRightBtn">
        <i class="fas fa-chevron-right"></i>
      </button>
    </div>
  </div>

  <!-- FEED -->
  <div class="feed-container">
    <?php
    try {
        if ($categoria == "todos") {
            $query = $pdo->query("SELECT p.*, u.nome, u.id_usuario as id_ong
                                  FROM posts p
                                  JOIN usuarios u ON p.id_usuario = u.id_usuario
                                  ORDER BY p.data_post DESC");
        } else {
            $query = $pdo->prepare("SELECT p.*, u.nome, u.id_usuario as id_ong
                                    FROM posts p
                                    JOIN usuarios u ON p.id_usuario = u.id_usuario
                                    WHERE p.categoria = ?
                                    ORDER BY p.data_post DESC");
            $query->execute([$categoria_banco]);
        }

        $posts = $query->fetchAll(PDO::FETCH_ASSOC);

        if (!$posts || count($posts) === 0): ?>

          <div class="empty-feed">
            <div>
              <p style="font-size:20px; margin-bottom:8px;">📭</p>
              <p><strong>Nenhuma publicação encontrada</strong></p>
              <p style="font-size:12px; margin-top:6px; color:#bbb;">
                <?= $categoria != 'todos' ? 'na categoria "' . htmlspecialchars($categoria) . '"' : 'no momento' ?>
              </p>
            </div>
          </div>

        <?php else: ?>

          <?php foreach ($posts as $post):
            $descricao     = $post['descricao'];
            $textoCurto    = strlen($descricao) > 150 ? substr($descricao, 0, 150) . '...' : $descricao;
            $temTextoLongo = strlen($descricao) > 150;
            $temImagem     = !empty($post['imagem']);
            $catKey        = $post['categoria'] ?? '';
            $badge         = $badgeMap[$catKey] ?? ['label' => $catKey, 'class' => 'badge-default'];
            $inicial       = inicialNome($post['nome']);
          ?>

            <div class="post-card <?= !$temImagem ? 'no-image' : '' ?>">

              <?php if ($temImagem): ?>
                <div class="post-image-wrapper">
                  <img src="uploads/<?= htmlspecialchars($post['imagem']) ?>"
                       class="post-image"
                       alt="<?= htmlspecialchars($post['titulo']) ?>"
                       onerror="this.closest('.post-image-wrapper').style.display='none'">
                  <div class="post-image-title">
                    <?= htmlspecialchars($post['titulo']) ?>
                  </div>
                </div>
              <?php endif; ?>

              <div class="post-card-body">

                <?php if (!$temImagem): ?>
                  <div class="post-card-header">
                    <h3><?= htmlspecialchars($post['titulo']) ?></h3>
                    <div class="post-meta">
                      <?= date("d/m/Y · H:i", strtotime($post['data_post'])) ?>
                    </div>
                  </div>
                <?php endif; ?>

                <?php if (!empty($catKey)): ?>
                  <div class="badges-row">
                    <span class="categoria-badge <?= $badge['class'] ?>">
                      <?= htmlspecialchars($badge['label']) ?>
                    </span>
                    <?php if ($tipoUsuario === "doador"): ?>
                      <span class="categoria-badge badge-teal">Voluntários</span>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>

                <div class="ong-info">
                  <div class="ong-avatar-placeholder"><?= $inicial ?></div>
                  <div class="ong-details">
                    <p class="ong-name"><?= htmlspecialchars($post['nome']) ?></p>
                    <p class="ong-verified">
                      <span class="check"><i class="fas fa-circle-check"></i></span>
                      Usuário Verificado
                    </p>
                  </div>
                </div>

                <?php if (!$temImagem): ?>
                  <div class="post-content" id="content-<?= $post['id_post'] ?>">
                    <?= nl2br(htmlspecialchars($textoCurto)) ?>
                  </div>
                  <?php if ($temTextoLongo): ?>
                    <button class="read-more" onclick="toggleContent(<?= $post['id_post'] ?>, this)">Ler mais</button>
                  <?php endif; ?>
                <?php endif; ?>

                <?php if ($tipoUsuario === "doador"): ?>
                  <button class="doacao-btn"
                          onclick="efetuarDoacao(<?= $post['id_ong'] ?>, '<?= htmlspecialchars(addslashes($post['titulo'])) ?>')">
                    💝 Efetuar Doação para esta ONG
                  </button>
                <?php elseif ($tipoUsuario === "instituicao"): ?>
                  <button class="doacao-btn" disabled>
                    ⚠️ Apenas doadores podem efetuar doações
                  </button>
                <?php endif; ?>

              </div>
            </div>

          <?php endforeach; ?>

        <?php endif; ?>

    <?php } catch (PDOException $e) { ?>
      <div class="empty-feed">
        <div>
          <p style="font-size:20px; margin-bottom:8px;">⚠️</p>
          <p><strong>Erro ao carregar publicações</strong></p>
          <p style="font-size:12px; margin-top:6px; color:#bbb;">Tente recarregar a página</p>
        </div>
      </div>
    <?php } ?>
  </div>

  <!-- MENU INFERIOR -->
  <div class="bottom">
    <a href="feed.php" class="menu-item">
      🏠<span>Feed</span>
    </a>

    <a href="campanhas.php" class="menu-item active">
      📢<span>Campanhas</span>
    </a>

    <button class="plus-btn" onclick="window.location.href='<?= $acaoPlus ?>'">+</button>

    <a href="notificacoes.php" class="menu-item">
      🔔<span>Notificações</span>
    </a>

    <a href="<?= $rotaPerfil ?>" class="menu-item">
      👤<span>Perfil</span>
    </a>
  </div>

</div>

<script>
function efetuarDoacao(idOng, tituloOng) {
    if (confirm(`Deseja efetuar uma doação para a ONG: "${tituloOng}"?`)) {
        window.location.href = `agendar_coleta.php?ong=${idOng}&titulo=${encodeURIComponent(tituloOng)}`;
    }
}

function toggleContent(postId, button) {
    const content = document.getElementById('content-' + postId);
    const isExpanded = content.classList.contains('expanded');
    content.classList.toggle('expanded', !isExpanded);
    button.textContent = isExpanded ? 'Ler mais' : 'Ler menos';
}

document.addEventListener('DOMContentLoaded', function () {
    const tabsEl      = document.getElementById('tabsContainer');
    const wrapperEl   = document.querySelector('.tabs-wrapper');
    const btnLeft     = document.getElementById('scrollLeftBtn');
    const btnRight    = document.getElementById('scrollRightBtn');
    let pos = 0;

    function getMax() {
        return Math.max(0, tabsEl.scrollWidth - wrapperEl.offsetWidth);
    }

    function render() {
        const max     = getMax();
        const hasFlow = max > 0;

        // Mostra setas só se há overflow real
        btnLeft.classList.toggle('show', hasFlow);
        btnRight.classList.toggle('show', hasFlow);

        // Estado desabilitado nas bordas
        btnLeft.classList.toggle('disabled',  pos <= 0);
        btnRight.classList.toggle('disabled', pos >= max);

        tabsEl.style.transform = `translateX(-${pos}px)`;
    }

    function move(delta) {
        pos = Math.max(0, Math.min(pos + delta, getMax()));
        render();
    }

    btnLeft.addEventListener('click',  () => move(-110));
    btnRight.addEventListener('click', () => move(+110));

    // Centraliza aba ativa
    const activeTab = document.querySelector('.tab.active');
    setTimeout(() => {
        if (activeTab) {
            const center = activeTab.offsetLeft
                         - wrapperEl.offsetWidth / 2
                         + activeTab.offsetWidth  / 2;
            pos = Math.max(0, Math.min(center, getMax()));
        }
        render();
    }, 60);

    window.addEventListener('resize', () => {
        pos = Math.min(pos, getMax());
        render();
    });
});

document.body.style.overflow = 'hidden';
</script>

</body>
</html>