<?php
// sitemap-vacancies.xml — список открытых вакансий для Google и Яндекса.
define('KT_VAC', 1);
require __DIR__ . '/_vac.php';
header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=300');
$list = (vac_load() ?? [])['list'] ?? [];
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($list as $v) {
  echo '  <url><loc>' . VAC_SITE . '/vacancy/' . vac_e($v['slug']) . '</loc>'
    . ($v['updatedAt'] ? '<lastmod>' . vac_e($v['updatedAt']) . '</lastmod>' : '')
    . "<changefreq>weekly</changefreq></url>\n";
}
echo "</urlset>\n";
