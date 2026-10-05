<?php
declare(strict_types=1);

/** Lê uma chave do config/config.php. */
function config(string $chave, $padrao = null)
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require dirname(__DIR__) . '/config/config.php';
    }

    return $cfg[$chave] ?? $padrao;
}

// ------------------------------------------------------------
// Texto (usa mbstring quando disponível)
// ------------------------------------------------------------

function txt_len(string $s): int
{
    return function_exists('mb_strlen') ? mb_strlen($s) : strlen($s);
}

function txt_sub(string $s, int $inicio, ?int $tam = null): string
{
    return function_exists('mb_substr')
        ? mb_substr($s, $inicio, $tam)
        : substr($s, $inicio, $tam ?? PHP_INT_MAX);
}

function txt_upper(string $s): string
{
    return function_exists('mb_strtoupper') ? mb_strtoupper($s) : strtoupper($s);
}

// ------------------------------------------------------------
// Saída segura
// ------------------------------------------------------------

/** Escapa para HTML. */
function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ------------------------------------------------------------
// Números e datas (padrão pt-BR)
// ------------------------------------------------------------

/** Converte entrada do usuário ("1.234,56" ou "1234.56") em float. */
function n($v): float
{
    if (is_int($v) || is_float($v)) {
        return (float) $v;
    }
    $s = trim((string) $v);
    if ($s === '') {
        return 0.0;
    }
    // "1.234,56" -> "1234.56" ; "1234,56" -> "1234.56"
    if (str_contains($s, ',')) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    }
    $s = preg_replace('/[^0-9.\-]/', '', $s) ?? '';

    return is_numeric($s) ? (float) $s : 0.0;
}

/** R$ 1.234,56 */
function brl($v): string
{
    return 'R$ ' . number_format(n($v), 2, ',', '.');
}

/** R$ 1,2k / R$ 199 / — */
function brl_curto($v): string
{
    $x = n($v);
    if ($x == 0.0) {
        return '—';
    }
    if (abs($x) >= 1000) {
        return 'R$ ' . str_replace('.', ',', number_format($x / 1000, 1, '.', '')) . 'k';
    }

    return 'R$ ' . number_format($x, 0, ',', '.');
}

/** 1.234,5 */
function num($v, int $dec = 0): string
{
    return number_format(n($v), $dec, ',', '.');
}

/** '2026-09-08' -> '08/09' */
function dm(?string $iso): string
{
    if (!$iso) {
        return '—';
    }
    $p = explode('-', substr($iso, 0, 10));

    return count($p) === 3 ? $p[2] . '/' . $p[1] : $iso;
}

/** '2026-09-08' -> '08/09/2026' */
function dmy(?string $iso): string
{
    if (!$iso) {
        return '—';
    }
    $p = explode('-', substr($iso, 0, 10));

    return count($p) === 3 ? $p[2] . '/' . $p[1] . '/' . $p[0] : $iso;
}

/** '2026-09' -> 'Setembro 2026' */
function mes_extenso(string $ym): string
{
    static $meses = [
        1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
        'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro',
    ];
    $m = (int) substr($ym, 5, 2);

    return ($meses[$m] ?? $ym) . ' ' . substr($ym, 0, 4);
}

/** Valida e normaliza uma data vinda de formulário; null se inválida. */
function data_valida(?string $s): ?string
{
    $s = trim((string) $s);
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $s);

    return ($d && $d->format('Y-m-d') === $s) ? $s : null;
}

/** Porcentagem limitada a 0..100, pronta para width de barra. */
function pct(float $parte, float $total): float
{
    if ($total <= 0) {
        return 0.0;
    }

    return max(0.0, min(100.0, ($parte / $total) * 100));
}

/**
 * Cor de texto legível sobre um fundo hexadecimal.
 * Usada nos botões que assumem a cor da plataforma.
 */
function cor_texto(string $hex): string
{
    $hex = ltrim(trim($hex), '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
        return '#FFFFFF';
    }

    // Luminância relativa aproximada (ITU-R BT.601)
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $luz = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

    return $luz > 0.6 ? '#1A1204' : '#FFFFFF';
}

// ------------------------------------------------------------
// Requisição
// ------------------------------------------------------------

function post(string $chave, string $padrao = ''): string
{
    $v = $_POST[$chave] ?? $padrao;

    return is_string($v) ? trim($v) : $padrao;
}

function get(string $chave, string $padrao = ''): string
{
    $v = $_GET[$chave] ?? $padrao;

    return is_string($v) ? trim($v) : $padrao;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** URL interna da aplicação. */
function url(string $rota = 'dashboard', array $params = []): string
{
    $qs = array_merge(['r' => $rota], $params);

    return 'index.php?' . http_build_query($qs);
}

/** Redireciona e encerra. */
function redirect(string $destino): never
{
    header('Location: ' . $destino);
    exit;
}

// ------------------------------------------------------------
// Mensagens flash
// ------------------------------------------------------------

function flash(string $texto, string $tipo = 'ok'): void
{
    $_SESSION['flash'][] = ['texto' => $texto, 'tipo' => $tipo];
}

function flash_pegar(): array
{
    $msgs = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $msgs;
}

// ------------------------------------------------------------
// CSRF
// ------------------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

/** Aborta a requisição se o token não bater. */
function csrf_verificar(): void
{
    $enviado = $_POST['_token'] ?? '';
    if (!is_string($enviado) || !hash_equals($_SESSION['csrf'] ?? '', $enviado)) {
        http_response_code(419);
        exit('Sessão expirada. Volte, atualize a página e tente de novo.');
    }
}
