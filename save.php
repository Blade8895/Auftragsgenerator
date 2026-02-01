<?php
declare(strict_types=1);
header("Content-Type: application/json; charset=utf-8");

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!is_array($data)) {
  http_response_code(400);
  echo json_encode(["ok" => false, "error" => "invalid json"]);
  exit;
}

$id = $data["id"] ?? "";
if (!preg_match("/^[A-Z0-9]{6,10}$/", $id)) {
  http_response_code(400);
  echo json_encode(["ok" => false, "error" => "invalid id"]);
  exit;
}

$dir = __DIR__ . "/data";
if (!is_dir($dir)) mkdir($dir, 0775, true);

/* ---------- SIGNATURES ---------- */
function saveSig(?string $dataUrl, string $file): ?string {
  if (!$dataUrl || !str_starts_with($dataUrl, "data:image/jpeg;base64,")) {
    return null;
  }
  $bin = base64_decode(substr($dataUrl, 23));
  file_put_contents($file, $bin, LOCK_EX);
  return basename($file);
}

$sigCustomerFile = saveSig($data["sigCustomer"] ?? null, "$dir/{$id}-sig-customer.jpg");
$sigTechFile     = saveSig($data["sigTech"] ?? null, "$dir/{$id}-sig-tech.jpg");

/* ---------- JSON ---------- */
unset($data["sigCustomer"], $data["sigTech"]);
$data["sigCustomerFile"] = $sigCustomerFile;
$data["sigTechFile"]     = $sigTechFile;

file_put_contents(
  "$dir/$id.json",
  json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
  LOCK_EX
);

echo json_encode(["ok" => true, "id" => $id]);
