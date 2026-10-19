<?php
// Анкета кандидата: запись «Кандидат» в EspoCRM со ссылкой на вакансию + уведомление в Telegram.
define('KT_VAC', 1);
require __DIR__ . '/_vac.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function cand_out(int $code, bool $ok, string $error = ''): void {
  http_response_code($code);
  echo json_encode($error === '' ? ['ok' => $ok] : ['ok' => $ok, 'error' => $error], JSON_UNESCAPED_UNICODE);
  exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') cand_out(405, false);
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && !preg_match('~^https://(www\.)?konnekteam\.kz$~', $origin)) cand_out(403, false);

$in = json_decode((string)file_get_contents('php://input', false, null, 0, 200000), true);
if (!is_array($in)) cand_out(400, false, 'Неверный запрос');

$vac = vac_find((vac_load() ?? [])['list'] ?? [], strtolower(trim((string)($in['slug'] ?? ''))));
if (!$vac) cand_out(404, false, 'Вакансия закрыта. Обновите страницу.');
$qs = $vac['questions'];

// Ловушка для ботов: скрытое поле заполнено или анкета отправлена слишком быстро.
if (trim((string)($in['hp'] ?? '')) !== '' || (int)($in['elapsed'] ?? 0) < ($qs ? 30000 : 8000)) cand_out(200, true);

$name = preg_replace('/\s+/u', ' ', vac_cut($in['name'] ?? '', 150));
$phone = preg_replace('/\D/', '', (string)($in['phone'] ?? ''));
$resume = vac_cut($in['resume'] ?? '', 300);
if (count(explode(' ', $name)) < 2) cand_out(400, false, 'Введите полное ФИО');
if (!preg_match('/^[78]\d{10}$/', $phone)) cand_out(400, false, 'Проверьте номер телефона');
$phone = '+7' . substr($phone, -10);
$badResume = $vac['requireHh']
  ? !preg_match('~^https?://(www\.)?hh\.kz/\S*resume~i', $resume)
  : ($resume !== '' && !preg_match('~^https?://\S+$~i', $resume));
if ($badResume) cand_out(400, false, 'Проверьте ссылку на резюме');

$answers = is_array($in['answers'] ?? null) ? array_values($in['answers']) : [];
if (count($answers) !== count($qs)) cand_out(409, false, 'Анкета изменилась. Обновите страницу и заполните её заново.');
$blocks = [];
foreach ($qs as $i => $q) {
  $a = vac_cut($answers[$i] ?? '', 5000);
  if (mb_strlen($a) < $q['minLength']) cand_out(400, false, 'Ответ на вопрос ' . ($i + 1) . ' слишком короткий');
  $blocks[] = ($i + 1) . '. ' . $q['label'] . "\nВопрос: " . $q['question'] . "\nОтвет: " . $a;
}

// Не больше 3 анкет в час с одного IP.
$rl = vac_priv() . '/ratelimit';
if (!is_dir($rl)) @mkdir($rl, 0700, true);
$f = $rl . '/cand-' . md5($_SERVER['REMOTE_ADDR'] ?? '0');
$now = time();
$hits = is_file($f) ? array_filter(array_map('intval', explode(',', (string)file_get_contents($f))), fn($t) => $t > $now - 3600) : [];
if (count($hits) >= 3) cand_out(429, false, 'Слишком много отправок. Попробуйте через час.');
$hits[] = $now;
@file_put_contents($f, implode(',', $hits), LOCK_EX);

$text = 'Вакансия: ' . $vac['name'] . "\nРезюме: " . ($resume !== '' ? $resume : '—') . ($blocks ? "\n\n" . implode("\n\n---\n\n", $blocks) : '');

// 1. EspoCRM
$parts = explode(' ', $name);
$payload = ['lastName' => array_shift($parts), 'firstName' => implode(' ', $parts), 'phoneNumber' => $phone, 'description' => $text, vac_a('vacancyId') => $vac['id']];
if ($resume !== '') $payload[vac_a('resume')] = $resume;
[$code, $res] = vac_api('POST', vac_ent('candidate'), $payload);
$id = $code === 200 ? (string)($res['id'] ?? '') : '';

// 2. Telegram (те же получатели, что у заявок с сайта)
$tgc = is_file(vac_priv() . '/telegram.php') ? require vac_priv() . '/telegram.php' : null;
function cand_tg(array $cfg, string $msg): bool {
  $ok = true;
  foreach ((array)($cfg['chats'] ?? []) as $chat) {
    $c = curl_init('https://api.telegram.org/bot' . $cfg['token'] . '/sendMessage');
    curl_setopt_array($c, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
      CURLOPT_POSTFIELDS => http_build_query(['chat_id' => $chat, 'text' => $msg, 'parse_mode' => 'HTML', 'disable_web_page_preview' => 'true'])]);
    curl_exec($c);
    $ok = ((int)curl_getinfo($c, CURLINFO_HTTP_CODE) === 200) && $ok;
    curl_close($c);
  }
  return $ok;
}
$tgOk = false;
if (is_array($tgc) && !empty($tgc['token'])) {
  $head = '<b>Новый кандидат</b>' . "\nВакансия: " . vac_e($vac['name']) . "\n" . date('d.m.Y H:i') . "\n" . vac_e($name) . ' · ' . vac_e($phone)
    . ($resume !== '' ? "\nРезюме: " . vac_e($resume) : '') . "\n";
  if ($id) {
    $tgOk = cand_tg($tgc, $head . "\n" . '<a href="' . vac_e(rtrim(vac_cfg()['url'], '/') . '/#' . vac_ent('candidate') . '/view/' . $id) . '">Открыть анкету в CRM</a>');
  } else {
    $tgOk = cand_tg($tgc, $head . "\n<b>В CRM не сохранено (код $code). Анкета целиком ниже.</b>");
    for ($i = 0, $n = mb_strlen($text); $i < $n; $i += 3500) cand_tg($tgc, vac_e(mb_substr($text, $i, 3500)));
  }
}
$ok = $id !== '' || $tgOk;
cand_out($ok ? 200 : 502, $ok, $ok ? '' : 'Не удалось отправить анкету. Позвоните нам: +7 700 0 981 981');
