<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Volunteer Community – Início</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
  <style>
    :root{
    --brand:#ff8a00;
    --text:#fff;
    --muted:#d7d7d7;
    --bg-url: linear-gradient(to bottom, #fdb777, #f98d1c, #f27600);
  }
  *{box-sizing:border-box}
  html,body{height:100%}
  body{
    margin:0; font-family:Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif; color:var(--text);
    background:#0e0e0f;
  }
  .hero{
    position:relative; min-height:100dvh; display:grid; place-items:center; isolation:isolate;
  }
  .hero::before{
    content:""; position:absolute; inset:0; z-index:-2;
    background:var(--bg-url);
    background-size:cover; background-position:center;
  }
  .hero::after{
    content:""; position:absolute; inset:0; z-index:-1;
    background:linear-gradient(to bottom, rgba(0,0,0,.45), rgba(0,0,0,.75));
  }
  .card{
    width:min(560px, 92vw);
    padding:40px 32px 32px;
    border-radius:16px;
    background:rgba(0,0,0,.55);
    border:1px solid rgba(255,255,255,.1);
    box-shadow:0 20px 60px rgba(0,0,0,.45);
    backdrop-filter: blur(6px) saturate(1.05);
    display:flex; flex-direction:column; align-items:center; gap:24px;
    text-align:center;
  }
  .logo{width:140px; height:auto; display:block}
  .title{margin:8px 0 0; font-size:36px; font-weight:800;}
  .subtitle{margin-top:-2px; font-size:12px; letter-spacing:.38em; color:var(--muted)}
  .actions{width:100%; display:flex; flex-direction:column; gap:14px;}
  .btn{
    width:100%; padding:14px 18px; border-radius:14px; border:1px solid transparent;
    font-weight:700; font-size:15px; text-align:center; cursor:pointer; text-decoration:none;
    transition:transform .06s ease, filter .2s ease;
  }
  .btn:active{ transform:translateY(1px) scale(.997) }
  .btn.primary{ background:linear-gradient(180deg,#ff9d2d,var(--brand)); color:#1b1206; }
  .btn.primary:hover{ filter:brightness(1.04); }
  .divider{font-size:12px; color:var(--muted); display:flex; align-items:center; gap:12px; justify-content:center}
  .divider::before,.divider::after{content:""; height:1px; flex:1; background:linear-gradient(90deg,transparent,#ffffff35,transparent)}
  .btn.ghost{ background:rgba(255,255,255,.06); color:var(--brand); border:1px solid rgba(255,255,255,.14); }
  .btn.ghost:hover{ background:rgba(255,255,255,.10); }
  </style>
</head>
<body>

<section class="hero">
  <div class="card">
    <img src="imagens/logo.png" alt="Volunteer Community" class="logo">
    <h1 class="title">Volunteer</h1>
    <div class="subtitle">COMMUNITY</div>
    <div class="actions">
      <a href="login.php" class="btn primary">Entrar</a>
      <div class="divider">ou</div>
      <a href="cadastro.php" class="btn ghost">Criar sua conta</a>
    </div>
  </div>
</section>

</body>
</html>