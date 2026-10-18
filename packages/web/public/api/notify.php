<?php
// Уведомления о событиях сайта в Telegram (РОП и директор).
// Токен и чаты хранятся ВНЕ сайта: /var/www/vhosts/konnekteam.kz/private/telegram.php
//   <?php return ['token' => '...', 'chats' => ['-100...']];
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
date_default_timezone_set('Asia/Oral');

function out(int $code, bool $ok): void { http_response_code($code); echo json_encode(['ok' => $ok]); exit; }
function cut(string $s, int $n): string { return function_exists('mb_substr') ? mb_substr($s, 0, $n) : substr($s, 0, $n); }
function esc(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') out(405, false);
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && !preg_match('~^https://(www\.)?konnekteam\.kz$~', $origin)) out(403, false);

$root = dirname(__DIR__, 2) . '/private';
$cfgFile = $root . '/telegram.php';
if (!is_file($cfgFile)) out(500, false);
$cfg = require $cfgFile;
$token = (string)($cfg['token'] ?? '');
$chats = (array)($cfg['chats'] ?? []);
if ($token === '' || !$chats) out(500, false);

$in = json_decode((string)file_get_contents('php://input', false, null, 0, 100000), true);
if (!is_array($in)) out(400, false);
$title = trim(cut((string)($in['title'] ?? ''), 120));
$text = trim(cut((string)($in['text'] ?? ''), 20000));
$page = cut((string)($in['page'] ?? ''), 200);
if ($title === '' || $text === '') out(400, false);

// Не больше 5 уведомлений за 10 минут с одного IP.
$rl = $root . '/ratelimit';
if (!is_dir($rl)) @mkdir($rl, 0700, true);
$f = $rl . '/' . md5($_SERVER['REMOTE_ADDR'] ?? '0');
$now = time();
$hits = is_file($f) ? array_filter(array_map('intval', explode(',', (string)file_get_contents($f))), fn($t) => $t > $now - 600) : [];
if (count($hits) >= 5) out(429, false);
$hits[] = $now;
@file_put_contents($f, implode(',', $hits), LOCK_EX);

function tg(string $token, string $chat, string $msg): bool {
  $url = "https://api.telegram.org/bot{$token}/sendMessage";
  $post = http_build_query(['chat_id' => $chat, 'text' => $msg, 'parse_mode' => 'HTML', 'disable_web_page_preview' => 'true']);
  if (function_exists('curl_init')) {
    $c = curl_init($url);
    curl_setopt_array($c, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $post, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    curl_exec($c);
    $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE);
    curl_close($c);
    return $code === 200;
  }
  $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => $post, 'timeout' => 10]]);
  return @file_get_contents($url, false, $ctx) !== false;
}

$head = '<b>' . esc($title) . "</b>\n" . date('d.m.Y H:i') . ' · konnekteam.kz' . esc($page) . "\n\n";
$parts = [];
$len = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
for ($i = 0; $i < $len; $i += 3500) $parts[] = function_exists('mb_substr') ? mb_substr($text, $i, 3500) : substr($text, $i, 3500);
$ok = true;
foreach ($chats as $chat) {
  foreach ($parts as $n => $p) {
    $msg = ($n === 0 ? $head : '<i>(продолжение ' . ($n + 1) . '/' . count($parts) . ")</i>\n") . esc($p);
    $ok = tg($token, (string)$chat, $msg) && $ok;
  }
}
out($ok ? 200 : 502, $ok);
