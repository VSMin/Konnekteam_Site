<?php
// Вакансии в JSON — для переходов внутри сайта без перезагрузки страницы.
define('KT_VAC', 1);
require __DIR__ . '/_vac.php';
header('Content-Type: application/json; charset=utf-8');
$data = vac_load();
if ($data === null) { http_response_code(503); header('Cache-Control: no-store'); echo '{"list":[]}'; exit; }
header('Cache-Control: public, max-age=30');
$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
$slug = strtolower(trim((string)($_GET['slug'] ?? '')));
if ($slug !== '') {
  $v = vac_find($data['list'], $slug);
  if (!$v) { http_response_code(404); echo '{}'; exit; }
  echo json_encode(['vacancy' => vac_public($v)], $flags);
  exit;
}
echo json_encode(['list' => array_map('vac_summary', $data['list'])], $flags);
