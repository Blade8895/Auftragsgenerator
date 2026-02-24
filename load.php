<?php
declare(strict_types=1);

$id = strtoupper((string)($_GET["id"] ?? ""));
if (!preg_match("/^[A-Z0-9]{6,10}$/", $id)) {
  http_response_code(400);
  exit;
}

function resolveDataDir(string $root): array {
  $lower = $root . "/data";
  $upper = $root . "/Data";

  if (is_dir($lower)) return [$lower, "data"];
  if (is_dir($upper)) return [$upper, "Data"];
  return [$lower, "data"];
}

function detectWebBasePath(): string {
  $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '/');
  $dir = str_replace('\\', '/', dirname($scriptName));
  if ($dir === '.' || $dir === '/') return '';
  return rtrim($dir, '/');
}

[$dir, $dirWeb] = resolveDataDir(__DIR__);
$file = "$dir/$id.json";
if (!file_exists($file)) {
  http_response_code(404);
  exit;
}

$data = json_decode((string)file_get_contents($file), true);
if (!is_array($data)) {
  http_response_code(500);
  exit;
}

$base = detectWebBasePath() . "/$dirWeb/";
$data["sigCustomer"] = !empty($data["sigCustomerFile"])
  ? $base . $data["sigCustomerFile"]
  : "";

$data["sigTech"] = !empty($data["sigTechFile"])
  ? $base . $data["sigTechFile"]
  : "";

unset($data["sigCustomerFile"], $data["sigTechFile"]);

header("Content-Type: application/json; charset=utf-8");
echo json_encode($data);
