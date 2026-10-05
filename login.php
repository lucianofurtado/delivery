<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

if (esta_logado()) {
    redirect(url('dashboard'));
}

$erro    = null;
$usuario = '';

if (is_post()) {
    csrf_verificar();
    $usuario = post('usuario');
    $erro    = tentar_login($usuario, (string) ($_POST['senha'] ?? ''));

    if ($erro === null) {
        redirect(url('dashboard'));
    }
}

$qtdPlataformas = count(plataformas());
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Entrar · <?= e(config('app_nome', 'GIRO')) ?></title>
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

<div class="login">
  <div class="login-lado">
    <div class="login-marca">
      <span class="marca-logo">G</span>
      <span class="marca-nome"><?= e(config('app_nome', 'GIRO')) ?></span>
    </div>

    <div style="display:flex;flex-direction:column;gap:20px">
      <h1 class="login-titulo">Sua planilha<br><span style="color:var(--red)">virou sistema.</span></h1>
      <p class="login-texto">
        Lançamentos por plataforma, km rodado, despesas e lucro líquido —
        tudo em um lugar, com fechamento mensal automático.
      </p>
      <div class="login-numeros">
        <div>
          <div class="login-numero"><?= e($qtdPlataformas) ?></div>
          <div class="rotulo" style="margin-top:4px">Plataformas ativas</div>
        </div>
        <div>
          <div class="login-numero verde">100%</div>
          <div class="rotulo" style="margin-top:4px">Dados no seu servidor</div>
        </div>
      </div>
    </div>

    <div class="login-rodape">PHP · MySQL · uso pessoal</div>
  </div>

  <div class="login-form-lado">
    <button type="button" class="btn-chip tema-canto" data-tema-toggle data-tema-label>Modo claro</button>

    <form class="login-form" method="post" action="login.php" autocomplete="on">
      <?= csrf_campo() ?>
      <div>
        <h2>Entrar na conta</h2>
        <p>Bem-vindo de volta, entregador.</p>
      </div>

      <?php if ($erro !== null): ?>
        <div class="flash erro"><?= e($erro) ?></div>
      <?php endif; ?>

      <label class="campo">Usuário
        <input type="text" name="usuario" value="<?= e($usuario) ?>"
               placeholder="seu.usuario" autocomplete="username" required autofocus>
      </label>

      <label class="campo">Senha
        <input type="password" name="senha" placeholder="••••••••"
               autocomplete="current-password" required>
      </label>

      <button type="submit" class="btn btn-principal" style="margin-top:4px">Entrar</button>
    </form>
  </div>
</div>

<script src="assets/app.js"></script>
</body>
</html>
