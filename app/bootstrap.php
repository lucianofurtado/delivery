<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/repo.php';

date_default_timezone_set(config('timezone', 'America/Sao_Paulo'));
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

if (config('debug')) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    ini_set('display_errors', '0');
}

sessao_iniciar();

/** Renderiza uma view dentro do layout. */
function render(string $view, array $dados = []): void
{
    extract($dados, EXTR_SKIP);
    $view_arquivo = dirname(__DIR__) . '/views/' . $view . '.php';

    ob_start();
    require $view_arquivo;
    $conteudo = ob_get_clean();

    require dirname(__DIR__) . '/views/layout.php';
}
