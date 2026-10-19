<?php
// Общие функции вакансий: данные из EspoCRM (кеш 60 секунд) и HTML для поисковиков.
// Настройки: private/espocrm.php рядом с httpdocs (в репозиторий не попадает).
if (!defined('KT_VAC')) { http_response_code(403); exit; }
date_default_timezone_set('Asia/Oral');

const VAC_SITE = 'https://konnekteam.kz';
const VAC_TTL = 60;
const VAC_EMPLOYMENT = ['Полная занятость' => 'FULL_TIME', 'Частичная занятость' => 'PART_TIME', 'Проектная работа' => 'CONTRACTOR', 'Стажировка' => 'INTERN'];

function vac_priv(): string { return dirname(__DIR__, 2) . '/private'; }
function vac_cfg(): ?array {
  static $cfg = false;
  if ($cfg === false) {
    $f = vac_priv() . '/espocrm.php';
    $c = is_file($f) ? require $f : null;
    $cfg = is_array($c) && !empty($c['url']) && !empty($c['apiKey']) ? $c : null;
  }
  return $cfg;
}
function vac_ent(string $k): string {
  $def = ['vacancy' => 'CVacancy', 'question' => 'CVacancyQuestion', 'candidate' => 'CCandidate'];
  return (string)(((vac_cfg() ?? [])['entities'] ?? [])[$k] ?? $def[$k]);
}
// Если поля в CRM созданы с префиксом (cSlug), в espocrm.php укажите 'prefix' => 'c'.
function vac_a(string $f): string { $p = (string)((vac_cfg() ?? [])['prefix'] ?? ''); return $p === '' ? $f : $p . ucfirst($f); }
function vac_get(array $r, string $f) { return $r[vac_a($f)] ?? null; }
function vac_cut($s, int $n): string { return mb_substr(trim((string)$s), 0, $n); }
function vac_e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function vac_lines($s): array { return array_values(array_filter(array_map('trim', preg_split('/\R/u', (string)$s) ?: []), fn($x) => $x !== '')); }

function vac_api(string $method, string $path, ?array $body = null): array {
  $cfg = vac_cfg();
  if (!$cfg) return [0, null];
  $h = ['X-Api-Key: ' . $cfg['apiKey'], 'Accept: application/json'];
  $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_CUSTOMREQUEST => $method];
  if ($body !== null) { $h[] = 'Content-Type: application/json'; $o[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE); }
  $o[CURLOPT_HTTPHEADER] = $h;
  $c = curl_init(rtrim($cfg['url'], '/') . '/api/v1/' . $path);
  curl_setopt_array($c, $o);
  $raw = curl_exec($c);
  $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE);
  curl_close($c);
  return [$code, json_decode((string)$raw, true)];
}

function vac_fetch(): ?array {
  $f = ['status', 'slug', 'location', 'employment', 'salary', 'tags', 'duties', 'requirements', 'conditions', 'requireHh', 'deadline'];
  $select = implode(',', array_merge(['id', 'name', 'description', 'createdAt', 'modifiedAt'], array_map('vac_a', $f)));
  $where = [['type' => 'equals', 'attribute' => vac_a('status'), 'value' => 'Опубликована']];
  [$code, $d] = vac_api('GET', vac_ent('vacancy') . '?' . http_build_query(['maxSize' => 50, 'orderBy' => 'createdAt', 'order' => 'desc', 'select' => $select, 'where' => $where]));
  if ($code !== 200 || !is_array($d)) return null;
  $byId = [];
  $today = date('Y-m-d');
  foreach ($d['list'] ?? [] as $r) {
    $slug = strtolower(trim((string)vac_get($r, 'slug')));
    $deadline = (string)(vac_get($r, 'deadline') ?? '');
    if (!preg_match('/^[a-z0-9-]{2,80}$/', $slug) || ($deadline !== '' && $deadline < $today)) continue;
    $byId[$r['id']] = [
      'id' => $r['id'], 'slug' => $slug, 'name' => trim((string)$r['name']),
      'location' => trim((string)vac_get($r, 'location')),
      'employment' => trim((string)vac_get($r, 'employment')),
      'salary' => trim((string)vac_get($r, 'salary')),
      'tags' => vac_lines(vac_get($r, 'tags')),
      'description' => trim((string)($r['description'] ?? '')),
      'duties' => vac_lines(vac_get($r, 'duties')),
      'requirements' => vac_lines(vac_get($r, 'requirements')),
      'conditions' => vac_lines(vac_get($r, 'conditions')),
      'requireHh' => (bool)vac_get($r, 'requireHh'),
      'deadline' => $deadline,
      'datePosted' => substr((string)($r['createdAt'] ?? ''), 0, 10),
      'updatedAt' => substr((string)($r['modifiedAt'] ?? $r['createdAt'] ?? ''), 0, 10),
      'questions' => [],
    ];
  }
  if ($byId) {
    $select = implode(',', array_merge(['id', 'name'], array_map('vac_a', ['question', 'sortOrder', 'minLength', 'vacancyId'])));
    $where = [['type' => 'in', 'attribute' => vac_a('vacancyId'), 'value' => array_keys($byId)]];
    [$code, $q] = vac_api('GET', vac_ent('question') . '?' . http_build_query(['maxSize' => 200, 'orderBy' => vac_a('sortOrder'), 'order' => 'asc', 'select' => $select, 'where' => $where]));
    if ($code !== 200 || !is_array($q)) return null;
    foreach ($q['list'] ?? [] as $r) {
      $vid = (string)vac_get($r, 'vacancyId');
      $text = trim((string)vac_get($r, 'question'));
      if (!isset($byId[$vid]) || $text === '') continue;
      $min = vac_get($r, 'minLength');
      $byId[$vid]['questions'][] = ['label' => trim((string)$r['name']), 'question' => $text, 'minLength' => $min === null ? 20 : max(0, min(2000, (int)$min))];
    }
  }
  return ['time' => time(), 'list' => array_values($byId)];
}

// Кеш: CRM опрашивается не чаще раза в минуту. Если CRM недоступна — показываем последнюю версию.
function vac_load(): ?array {
  $file = vac_priv() . '/cache/vacancies.json';
  $old = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
  if (is_array($old) && filemtime($file) > time() - VAC_TTL) return $old;
  $new = vac_cfg() ? vac_fetch() : null;
  if ($new === null) { if (is_array($old)) { @touch($file); return $old; } return null; }
  if (!is_dir(dirname($file))) @mkdir(dirname($file), 0700, true);
  @file_put_contents($file, json_encode($new, JSON_UNESCAPED_UNICODE), LOCK_EX);
  return $new;
}
function vac_find(array $list, string $slug): ?array { foreach ($list as $v) if ($v['slug'] === $slug) return $v; return null; }
function vac_public(array $v): array { unset($v['id']); return $v; }
function vac_summary(array $v): array { return array_intersect_key($v, array_flip(['slug', 'name', 'location', 'employment', 'salary', 'tags'])); }
function vac_meta(array $v): string { return implode(' · ', array_filter([$v['location'], $v['employment']], 'strlen')); }
function vac_pills(array $v): array { return array_values(array_filter(array_merge([$v['salary']], $v['tags']), 'strlen')); }
function vac_desc(array $v): string { $l = vac_lines($v['description']); return mb_substr($l[0] ?? ($v['name'] . ' в KONNEKTEAM. Заполните анкету на сайте.'), 0, 160); }

// ── HTML (повторяет компоненты components/vacancy-blocks.tsx) ──────────────
function vac_pills_html(array $items, bool $center = true): string {
  if (!$items) return '';
  $s = '';
  foreach ($items as $t) $s .= '<span style="background:rgba(227,30,36,0.1);border:1px solid rgba(227,30,36,0.3);border-radius:20px;padding:6px 16px;font-size:0.85rem;color:var(--accent)">' . vac_e($t) . '</span>';
  return '<div style="display:flex;gap:12px;justify-content:' . ($center ? 'center' : 'flex-start') . ';flex-wrap:wrap">' . $s . '</div>';
}
function vac_hero_html(string $title, string $sub = '', array $pills = []): string {
  return '<section style="padding:140px 24px 60px;background:#0A0A0A;text-align:center">'
    . '<div style="color:var(--accent);font-size:0.85rem;letter-spacing:2px;text-transform:uppercase;margin-bottom:12px">Карьера в KONNEKTEAM</div>'
    . '<h1 style="font-size:clamp(1.8rem, 4vw, 2.8rem);font-weight:800;margin-bottom:20px;line-height:1.2">' . vac_e($title) . '</h1>'
    . ($sub !== '' ? '<p style="color:rgba(255,255,255,0.6);max-width:560px;margin:0 auto 32px;line-height:1.7">' . vac_e($sub) . '</p>' : '')
    . vac_pills_html($pills) . '</section>';
}
function vac_info_html(array $v): string {
  $s = '';
  foreach (vac_lines($v['description']) as $p) $s .= '<p style="color:rgba(255,255,255,0.75);line-height:1.75;margin-bottom:16px">' . vac_e($p) . '</p>';
  foreach (['duties' => 'Обязанности', 'requirements' => 'Требования', 'conditions' => 'Условия'] as $k => $t) {
    if (!$v[$k]) continue;
    $s .= '<div><h2 style="font-size:1.25rem;font-weight:700;margin:32px 0 12px">' . $t . '</h2><ul style="padding-left:20px;list-style:disc;color:rgba(255,255,255,0.75);line-height:1.75">';
    foreach ($v[$k] as $x) $s .= '<li style="margin-bottom:6px">' . vac_e($x) . '</li>';
    $s .= '</ul></div>';
  }
  if ($v['deadline']) $s .= '<p style="color:rgba(255,255,255,0.5);margin-top:24px;font-size:0.9rem">Принимаем анкеты до ' . vac_e(implode('.', array_reverse(explode('-', $v['deadline'])))) . '</p>';
  return '<section style="padding:0 24px 40px;background:#0A0A0A"><div style="max-width:720px;margin:0 auto">' . $s . '</div></section>'
    . '<section style="padding:20px 24px 100px;background:#0A0A0A"><div style="max-width:720px;margin:0 auto"><h2 style="font-size:1.4rem;font-weight:700;margin-bottom:24px">Анкета кандидата</h2><p style="color:rgba(255,255,255,0.5)">Чтобы откликнуться, заполните анкету на этой странице.</p></div></section>';
}
function vac_cards_html(array $list): string {
  if (!$list) $inner = '<p style="color:rgba(255,255,255,0.6);text-align:center;line-height:1.7">Сейчас открытых вакансий нет. Напишите нам на <a href="mailto:sales@konnekteam.kz" style="color:var(--accent)">sales@konnekteam.kz</a>, если хотите работать в KONNEKTEAM.</p>';
  else {
    $inner = '<div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(min(300px, 100%), 1fr));gap:24px">';
    foreach ($list as $v) {
      $m = vac_meta($v);
      $inner .= '<a href="/vacancy/' . vac_e($v['slug']) . '" class="card" style="text-decoration:none;display:block">'
        . '<h2 style="font-size:1.2rem;font-weight:700;color:#fff;margin-bottom:8px">' . vac_e($v['name']) . '</h2>'
        . ($m !== '' ? '<p style="color:rgba(255,255,255,0.55);font-size:0.9rem;margin-bottom:16px">' . vac_e($m) . '</p>' : '')
        . vac_pills_html(vac_pills($v), false)
        . '<div style="margin-top:20px;color:var(--accent);font-weight:600;font-size:0.9rem">Подробнее и анкета →</div></a>';
    }
    $inner .= '</div>';
  }
  return '<section style="padding:0 24px 100px;background:#0A0A0A"><div style="max-width:1000px;margin:0 auto">' . $inner . '</div></section>';
}
function vac_jobposting(array $v, string $url): array {
  $parts = [];
  foreach (vac_lines($v['description']) as $l) $parts[] = '<p>' . vac_e($l) . '</p>';
  foreach (['duties' => 'Обязанности', 'requirements' => 'Требования', 'conditions' => 'Условия'] as $k => $t)
    if ($v[$k]) $parts[] = '<p><strong>' . $t . '</strong></p><ul><li>' . implode('</li><li>', array_map('vac_e', $v[$k])) . '</li></ul>';
  if (!$parts) $parts[] = '<p>' . vac_e($v['name']) . '</p>';
  $jp = [
    '@context' => 'https://schema.org', '@type' => 'JobPosting', 'title' => $v['name'], 'description' => implode('', $parts),
    'datePosted' => $v['datePosted'] ?: date('Y-m-d'), 'url' => $url, 'directApply' => true,
    'hiringOrganization' => ['@type' => 'Organization', 'name' => 'KONNEKTEAM', 'sameAs' => VAC_SITE, 'logo' => VAC_SITE . '/logo.png'],
    'jobLocation' => ['@type' => 'Place', 'address' => ['@type' => 'PostalAddress', 'streetAddress' => 'ул. А. Тайманова 162/2', 'addressLocality' => 'Уральск', 'addressRegion' => 'Западно-Казахстанская область', 'addressCountry' => 'KZ']],
  ];
  if (isset(VAC_EMPLOYMENT[$v['employment']])) $jp['employmentType'] = VAC_EMPLOYMENT[$v['employment']];
  if ($v['deadline']) $jp['validThrough'] = $v['deadline'] . 'T23:59:59+05:00';
  return $jp;
}
