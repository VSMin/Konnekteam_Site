<?php
// Определяет, подключён ли посетитель к сети KONNEKTEAM (страница /speedtest).
// Впишите диапазоны IP клиентов в формате CIDR, например: '185.0.0.0/24',
$ranges = [
];
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$client = false;
$ipl = ip2long($_SERVER['REMOTE_ADDR'] ?? '');
if ($ipl !== false) {
  foreach ($ranges as $cidr) {
    [$net, $bits] = array_pad(explode('/', $cidr), 2, '32');
    $netl = ip2long($net);
    $bits = (int)$bits;
    if ($netl === false || $bits < 0 || $bits > 32) continue;
    $mask = $bits === 0 ? 0 : (~0 << (32 - $bits)) & 0xFFFFFFFF;
    if (($ipl & $mask) === ($netl & $mask)) { $client = true; break; }
  }
}
echo json_encode(['client' => $client]);
