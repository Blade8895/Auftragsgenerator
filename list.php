<?php
declare(strict_types=1);
header("Content-Type: application/json; charset=utf-8");

$dir = __DIR__ . "/data";
if (!is_dir($dir)) {
  echo json_encode(["ok" => true, "items" => []], JSON_UNESCAPED_UNICODE);
  exit;
}

function parseOrderDateToTs(?string $orderDate): ?int {
  if (!$orderDate) return null;

  // erwartet YYYY-MM-DD
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $orderDate)) return null;

  // 00:00:00 lokale Serverzeit
  $ts = strtotime($orderDate . " 00:00:00");
  if ($ts === false) return null;

  return (int)$ts;
}

$items = [];
foreach (glob($dir . "/*.json") as $file) {
  $base = basename($file);
  $id = strtoupper(preg_replace('/\.json$/', '', $base));
  if (!preg_match("/^[A-Z0-9]{6,10}$/", $id)) continue;

  $raw = @file_get_contents($file);
  if ($raw === false) continue;

  $data = json_decode($raw, true);
  if (!is_array($data)) continue;

  $customerName = trim((string)($data["customerName"] ?? ""));
  $orderNo      = trim((string)($data["orderNo"] ?? ""));
  $orderDate    = trim((string)($data["orderDate"] ?? "")); // YYYY-MM-DD

  $ts = parseOrderDateToTs($orderDate);

  // Fallback, wenn orderDate fehlt/ungültig:
  if ($ts === null) {
    $mtime = @filemtime($file);
    $ts = ($mtime === false) ? time() : (int)$mtime;
  }

  $items[] = [
    "id" => $id,
    "customerName" => $customerName,
    "orderNo" => $orderNo,
    "orderDate" => $orderDate,         // roh aus JSON
    "createdTs" => $ts,                // für Anzeige + Filter
    "createdIso" => date("c", $ts),
  ];
}

usort($items, fn($a, $b) => $b["createdTs"] <=> $a["createdTs"]);

echo json_encode(["ok" => true, "items" => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
