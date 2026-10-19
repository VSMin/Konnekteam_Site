<?php
// Страницы /vacancy и /vacancy/<адрес>: готовый HTML для поисковиков из данных CRM, без пересборки сайта.
define('KT_VAC', 1);
require __DIR__ . '/_vac.php';

$slug = strtolower(trim((string)($_GET['slug'] ?? '')));
$list = (vac_load() ?? [])['list'] ?? [];
$vac = $slug !== '' ? vac_find($list, $slug) : null;
$notFound = $slug !== '' && !$vac;

$shell = @file_get_contents(dirname(__DIR__) . '/_shell/' . ($vac ? 'vacancy.html' : 'vacancy-list.html'));
if ($shell === false) { http_response_code(503); header('Content-Type: text/plain; charset=utf-8'); echo 'Страница обновляется, попробуйте через минуту.'; exit; }

$ld = [];
if ($vac) {
  $title = 'Вакансия: ' . $vac['name'];
  $canonical = VAC_SITE . '/vacancy/' . $vac['slug'];
  $desc = vac_desc($vac);
  $ld[] = vac_jobposting($vac, $canonical);
  $ld[] = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Главная', 'item' => VAC_SITE . '/'],
    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Вакансии', 'item' => VAC_SITE . '/vacancy'],
    ['@type' => 'ListItem', 'position' => 3, 'name' => $vac['name'], 'item' => $canonical],
  ]];
  $main = vac_hero_html($vac['name'], vac_meta($vac), vac_pills($vac)) . vac_info_html($vac);
  $boot = ['vacancy' => vac_public($vac)];
} else {
  $title = $notFound ? 'Вакансия закрыта' : 'Вакансии';
  $canonical = VAC_SITE . '/vacancy';
  $desc = 'Открытые вакансии KONNEKTEAM в Уральске. Выберите вакансию и заполните анкету на сайте.';
  $main = $notFound
    ? vac_hero_html('Вакансия закрыта', 'Эта вакансия закрыта или не найдена. Посмотрите открытые вакансии:')
    : vac_hero_html('Вакансии', 'Выберите вакансию и заполните анкету. Ответим в течение 3 рабочих дней.');
  $main .= vac_cards_html($list);
  $boot = ['list' => array_map('vac_summary', $list)];
}

$full = $title . ' | KONNEKTEAM — Уральск';
$head = [
  '<title>' . vac_e($full) . '</title>',
  '<meta name="description" content="' . vac_e($desc) . '" />',
  '<link rel="canonical" href="' . vac_e($canonical) . '" />',
  '<meta property="og:title" content="' . vac_e($full) . '" />',
  '<meta property="og:description" content="' . vac_e($desc) . '" />',
  '<meta property="og:url" content="' . vac_e($canonical) . '" />',
  '<meta name="twitter:title" content="' . vac_e($full) . '" />',
  '<meta name="twitter:description" content="' . vac_e($desc) . '" />',
];
$jf = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP;
foreach ($ld as $o) $head[] = '<script type="application/ld+json">' . json_encode($o, $jf) . '</script>';
$seo = "<!--seo-->\n\t\t" . implode("\n\t\t", $head) . "\n\t\t<!--/seo-->";

$html = preg_replace_callback('~<!--seo-->.*?<!--/seo-->~s', fn() => $seo, $shell, 1);
$html = str_replace('</head>', '<script type="application/json" id="vac-data">' . json_encode($boot, $jf) . "</script>\n\t</head>", $html);
$html = str_replace('<div id="root">', '<div id="root" data-csr="1">', $html);
$html = str_replace('<!--vac--><!--/vac-->', $main, $html);

header('Content-Type: text/html; charset=utf-8');
if ($notFound) {
  http_response_code(404);
  header('Cache-Control: no-store');
  $html = str_replace('content="index, follow"', 'content="noindex, follow"', $html);
} else {
  header('Cache-Control: public, max-age=60');
}
echo $html;
