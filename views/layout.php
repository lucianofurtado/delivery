<?php
/**
 * Layout do app (topo + navegação + conteúdo).
 * Espera $conteudo (HTML já renderizado) e, opcionalmente, $titulo e $rota_ativa.
 */
$usuario     = usuario_logado();
$rota_atual  = $rota_ativa ?? get('r', 'dashboard');
$slug_atual  = get('slug');
$total_dias  = metricas()['dias'];
$mensagens   = flash_pegar();
$alertas_manut = manutencoes_alerta();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titulo ?? 'Dashboard') ?> · <?= e(config('app_nome', 'GIRO')) ?></title>
<link rel="icon" href="assets/favicon.ico" sizes="any">
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
<meta name="theme-color" content="#E11B22">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700&family=Archivo+Black&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/app.css">
</head>
<body data-tema="escuro">
<div class="app">

  <header class="topo">
    <div class="topo-linha">
      <a class="marca" href="<?= e(url('dashboard')) ?>">
        <span class="marca-logo">G</span>
        <span class="marca-nome"><?= e(config('app_nome', 'GIRO')) ?></span>
      </a>

      <button type="button" class="btn-chip" data-tema-toggle data-tema-label>Modo claro</button>

      <div class="usuario-bloco">
        <div class="usuario-texto">
          <div class="usuario-nome"><?= e($usuario['nome']) ?></div>
          <div class="usuario-sub"><?= e($total_dias) ?> dias registrados</div>
        </div>
        <span class="avatar"><?= e(iniciais($usuario['nome'])) ?></span>
        <a class="btn-chip" href="logout.php">Sair</a>
      </div>
    </div>

    <nav class="nav">
      <a class="nav-item<?= $rota_atual === 'dashboard' ? ' ativo' : '' ?>" href="<?= e(url('dashboard')) ?>">Dashboard</a>
      <?php foreach (plataformas() as $p): ?>
        <?php $ativo = $rota_atual === 'plataforma' && $slug_atual === $p['slug']; ?>
        <a class="nav-item<?= $ativo ? ' ativo' : '' ?>"
           href="<?= e(url('plataforma', ['slug' => $p['slug']])) ?>"><?= e($p['nome']) ?></a>
      <?php endforeach; ?>
      <a class="nav-item<?= $rota_atual === 'km' ? ' ativo' : '' ?>" href="<?= e(url('km')) ?>">KM diário</a>
      <a class="nav-item<?= $rota_atual === 'despesas' ? ' ativo' : '' ?>" href="<?= e(url('despesas')) ?>">Despesas</a>
      <a class="nav-item<?= $rota_atual === 'mensal' ? ' ativo' : '' ?>" href="<?= e(url('mensal')) ?>">Resumo mensal</a>
      <a class="nav-item<?= $rota_atual === 'metas' ? ' ativo' : '' ?>" href="<?= e(url('metas')) ?>">Metas</a>
      <a class="nav-item<?= $rota_atual === 'eventos' ? ' ativo' : '' ?>" href="<?= e(url('eventos')) ?>">Eventos</a>
      <a class="nav-item<?= $rota_atual === 'manutencao' ? ' ativo' : '' ?>" href="<?= e(url('manutencao')) ?>">Manutenção<?php
        if ($alertas_manut !== []): ?><span class="nav-alerta" title="Itens para trocar"><?= e(count($alertas_manut)) ?></span><?php endif; ?></a>
    </nav>
  </header>

  <?php if ($mensagens !== []): ?>
    <div class="flash-area">
      <?php foreach ($mensagens as $msg): ?>
        <div class="flash <?= e($msg['tipo']) ?>"><?= e($msg['texto']) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <main><?= $conteudo ?></main>
</div>

<script src="assets/app.js"></script>
</body>
</html>
