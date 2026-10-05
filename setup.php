<?php
declare(strict_types=1);

/**
 * Instalador do GIRO.
 * Cria o banco, roda o schema e cadastra o usuário único.
 * APAGUE ESTE ARQUIVO depois de instalar.
 */

require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/app/db.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set(config('timezone', 'America/Sao_Paulo'));

$cfg          = config('db');
$usuarioLogin = '';

$erros     = [];
$sucesso   = [];
$avisos    = [];
$instalado = false;

// ---- Requisitos do servidor ----
$requisitos = [];
if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    $requisitos[] = 'PHP 8.1 ou superior (encontrado ' . PHP_VERSION . ').';
}
if (!extension_loaded('pdo_mysql')) {
    $requisitos[] = 'Extensão pdo_mysql (habilite em php.ini: extension=pdo_mysql).';
}
if (!extension_loaded('session')) {
    $requisitos[] = 'Extensão session.';
}
if (!function_exists('mb_strlen')) {
    $avisos[] = 'Extensão mbstring ausente: o sistema funciona, mas habilitá-la é recomendado.';
}

/** Executa um arquivo .sql, statement por statement. */
function rodar_sql(PDO $pdo, string $arquivo): void
{
    $sql = file_get_contents($arquivo);
    if ($sql === false) {
        throw new RuntimeException("Não foi possível ler {$arquivo}");
    }

    // Remove comentários de linha para não confundir a separação por ";"
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;

    foreach (explode(';', $sql) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt !== '') {
            $pdo->exec($stmt);
        }
    }
}

// Já existe usuário cadastrado?
try {
    $pdoTeste = db_servidor($cfg);
    $pdoTeste->exec('USE `' . str_replace('`', '', $cfg['nome']) . '`');
    $qtd = (int) $pdoTeste->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    $instalado = $qtd > 0;
} catch (Throwable $e) {
    // Banco/tabelas ainda não existem: instalação pendente.
}

if (is_post() && !$instalado && $requisitos === []) {
    $usuarioLogin = post('usuario');
    $nome    = post('nome');
    $senha   = (string) ($_POST['senha'] ?? '');
    $senha2  = (string) ($_POST['senha2'] ?? '');
    $demo    = isset($_POST['demo']);

    if (!preg_match('/^[A-Za-z0-9._-]{3,60}$/', $usuarioLogin)) {
        $erros[] = 'O usuário precisa ter de 3 a 60 caracteres: letras, números, ponto, hífen ou sublinhado.';
    }
    if ($nome === '') {
        $erros[] = 'Informe o seu nome.';
    }
    if (txt_len($senha) < 8) {
        $erros[] = 'A senha precisa ter pelo menos 8 caracteres.';
    }
    if ($senha !== $senha2) {
        $erros[] = 'As senhas não conferem.';
    }

    if ($erros === []) {
        try {
            $banco = str_replace('`', '', $cfg['nome']);
            $pdo   = db_servidor($cfg);

            // Em hospedagem compartilhada o banco já existe e o usuário não pode criá-lo.
            try {
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$banco}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            } catch (Throwable $e) {
                $avisos[] = 'Sem permissão para criar o banco — usando o banco já existente.';
            }

            $pdo->exec("USE `{$banco}`");

            rodar_sql($pdo, __DIR__ . '/sql/schema.sql');
            $sucesso[] = 'Banco e tabelas criados.';

            $st = $pdo->prepare(
                'INSERT INTO usuarios (usuario, nome, senha_hash) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE nome = VALUES(nome), senha_hash = VALUES(senha_hash)'
            );
            $st->execute([$usuarioLogin, $nome, password_hash($senha, PASSWORD_DEFAULT)]);
            $sucesso[] = 'Usuário "' . $usuarioLogin . '" criado.';

            if ($demo) {
                rodar_sql($pdo, __DIR__ . '/sql/seed_demo.sql');
                $sucesso[] = 'Dados de exemplo carregados.';
            }

            $instalado = true;
        } catch (Throwable $e) {
            $erros[] = 'Erro ao instalar: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instalação · <?= e(config('app_nome', 'GIRO')) ?></title>
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

<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:32px 20px">
  <div style="width:100%;max-width:460px;display:flex;flex-direction:column;gap:18px">

    <div class="login-marca">
      <span class="marca-logo">G</span>
      <span class="marca-nome"><?= e(config('app_nome', 'GIRO')) ?></span>
    </div>

    <div class="card" style="padding:24px;display:flex;flex-direction:column;gap:16px">
      <div>
        <h1 style="margin:0 0 6px;font-size:22px;font-weight:700">Instalação</h1>
        <p style="margin:0;color:var(--ink2);font-size:14px">
          Banco <strong><?= e($cfg['nome']) ?></strong> em <?= e($cfg['host']) ?>:<?= e($cfg['port']) ?>
        </p>
      </div>

      <?php foreach ($requisitos as $msg): ?>
        <div class="flash erro">Requisito faltando: <?= e($msg) ?></div>
      <?php endforeach; ?>
      <?php foreach ($avisos as $msg): ?>
        <div class="flash erro"><?= e($msg) ?></div>
      <?php endforeach; ?>
      <?php foreach ($erros as $msg): ?>
        <div class="flash erro"><?= e($msg) ?></div>
      <?php endforeach; ?>
      <?php foreach ($sucesso as $msg): ?>
        <div class="flash ok"><?= e($msg) ?></div>
      <?php endforeach; ?>

      <?php if ($requisitos !== []): ?>
        <p style="margin:0;color:var(--ink2);font-size:14px">
          Ajuste os itens acima no servidor e recarregue esta página.
        </p>
      <?php elseif ($instalado): ?>
        <p style="margin:0;color:var(--ink2);font-size:14px">
          O sistema já está instalado. Por segurança, <strong>apague o arquivo setup.php</strong>
          e faça login com o usuário que você cadastrou.
        </p>
        <a class="btn btn-principal" href="login.php">Ir para o login</a>
      <?php else: ?>
        <form method="post" action="setup.php" style="display:flex;flex-direction:column;gap:14px">
          <label class="campo">Usuário de login
            <input type="text" name="usuario" value="<?= e(post('usuario')) ?>" maxlength="60"
                   autocomplete="username" placeholder="seu.usuario" required>
          </label>
          <label class="campo">Seu nome
            <input type="text" name="nome" value="<?= e(post('nome')) ?>" placeholder="Seu nome" required>
          </label>
          <label class="campo">Senha (mínimo 8 caracteres)
            <input type="password" name="senha" autocomplete="new-password" required>
          </label>
          <label class="campo">Repita a senha
            <input type="password" name="senha2" autocomplete="new-password" required>
          </label>
          <label style="display:flex;align-items:center;gap:9px;font-size:14px;color:var(--ink2)">
            <input type="checkbox" name="demo" value="1" style="width:auto;min-height:auto">
            Carregar dados de exemplo (ago/set 2026)
          </label>
          <button type="submit" class="btn btn-principal">Instalar</button>
        </form>
      <?php endif; ?>
    </div>

  </div>
</div>

<script src="assets/app.js"></script>
</body>
</html>
