<?php
declare(strict_types=1);

/** Inicia a sessão com cookie endurecido. */
function sessao_iniciar(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'secure'   => $https,
        'samesite' => 'Lax',
    ]);
    session_name('giro_sessao');
    session_start();
}

function usuario_logado(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

function esta_logado(): bool
{
    return usuario_logado() !== null;
}

/** Bloqueia o acesso de quem não está autenticado. */
function exigir_login(): void
{
    if (!esta_logado()) {
        redirect('login.php');
    }
}

/**
 * Tenta autenticar. Devolve null em caso de sucesso ou a mensagem de erro.
 */
function tentar_login(string $usuario, string $senha): ?string
{
    // Freio simples contra força bruta: 5 tentativas por 10 minutos.
    $agora = time();
    $tent  = $_SESSION['tentativas'] ?? ['n' => 0, 'ate' => 0];
    if ($tent['n'] >= 5 && $agora < $tent['ate']) {
        $faltam = (int) ceil(($tent['ate'] - $agora) / 60);

        return "Muitas tentativas. Tente novamente em {$faltam} minuto(s).";
    }

    $u = q1('SELECT id, usuario, nome, senha_hash FROM usuarios WHERE usuario = ? LIMIT 1', [$usuario]);

    if (!$u || !password_verify($senha, $u['senha_hash'])) {
        $_SESSION['tentativas'] = [
            'n'   => ($tent['n'] ?? 0) + 1,
            'ate' => $agora + 600,
        ];

        return 'Usuário ou senha inválidos.';
    }

    // Reidrata o hash se o algoritmo padrão mudou.
    if (password_needs_rehash($u['senha_hash'], PASSWORD_DEFAULT)) {
        exec_sql('UPDATE usuarios SET senha_hash = ? WHERE id = ?', [
            password_hash($senha, PASSWORD_DEFAULT), $u['id'],
        ]);
    }

    exec_sql('UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?', [$u['id']]);

    unset($_SESSION['tentativas']);
    session_regenerate_id(true);
    $_SESSION['usuario'] = [
        'id'      => (int) $u['id'],
        'usuario' => $u['usuario'],
        'nome'    => $u['nome'],
    ];

    return null;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Iniciais para o avatar do topo. */
function iniciais(string $nome): string
{
    $partes = preg_split('/\s+/', trim($nome)) ?: [];
    $ini = '';
    foreach ($partes as $p) {
        if ($p !== '') {
            $ini .= txt_upper(txt_sub($p, 0, 1));
        }
        if (txt_len($ini) >= 2) {
            break;
        }
    }

    return $ini !== '' ? $ini : 'US';
}
