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

function resolveDataDir(string $root): string {
  $lower = $root . "/data";
  $upper = $root . "/Data";

  if (is_dir($lower)) return $lower;
  if (is_dir($upper)) return $upper;

  mkdir($lower, 0775, true);
  return $lower;
}

$dir = resolveDataDir(__DIR__);
$jsonPath = "$dir/$id.json";
$existing = [];
if (is_file($jsonPath)) {
  $decoded = json_decode((string)file_get_contents($jsonPath), true);
  if (is_array($decoded)) $existing = $decoded;
}

function extractSigFileName(?string $value): ?string {
  if (!$value) return null;
  $path = parse_url($value, PHP_URL_PATH);
  if (!is_string($path)) return null;
  $file = basename($path);
  return preg_match('/^[A-Z0-9]{6,10}-sig-(customer|tech)\.jpg$/i', $file) ? $file : null;
}

function sigOperation(?string $incoming, ?string $existingFile, string $expectedFile): string {
  if ($incoming !== null && str_starts_with($incoming, 'data:image/jpeg;base64,')) return 'replace';
  if ($incoming === '') return 'clear';

  $incomingFile = extractSigFileName($incoming);
  if ($incomingFile && $existingFile && strcasecmp($incomingFile, $existingFile) === 0) return 'keep';
  if ($incomingFile && strcasecmp($incomingFile, $expectedFile) === 0) return 'keep';

  return $existingFile ? 'keep' : 'none';
}

function requireConfirmation(bool $confirmed, string $field): void {
  if ($confirmed) return;
  http_response_code(409);
  echo json_encode(["ok" => false, "error" => "signature_confirmation_required", "field" => $field]);
  exit;
}

function saveSig(string $dataUrl, string $file): ?string {
  if (!str_starts_with($dataUrl, "data:image/jpeg;base64,")) {
    return null;
  }
  $bin = base64_decode(substr($dataUrl, 23), true);
  if ($bin === false) {
    return null;
  }
  file_put_contents($file, $bin, LOCK_EX);
  return basename($file);
}

$confirmCustomer = !empty($data['confirmOverwriteSigCustomer']);
$confirmTech = !empty($data['confirmOverwriteSigTech']);

$incomingCustomer = isset($data['sigCustomer']) ? (string)$data['sigCustomer'] : null;
$incomingTech = isset($data['sigTech']) ? (string)$data['sigTech'] : null;

$existingCustomerFile = isset($existing['sigCustomerFile']) ? (string)$existing['sigCustomerFile'] : null;
$existingTechFile = isset($existing['sigTechFile']) ? (string)$existing['sigTechFile'] : null;

$customerFileName = "{$id}-sig-customer.jpg";
$techFileName = "{$id}-sig-tech.jpg";

$customerOp = sigOperation($incomingCustomer, $existingCustomerFile, $customerFileName);
$techOp = sigOperation($incomingTech, $existingTechFile, $techFileName);

if ($existingCustomerFile && in_array($customerOp, ['replace', 'clear'], true)) {
  requireConfirmation($confirmCustomer, 'sigCustomer');
}
if ($existingTechFile && in_array($techOp, ['replace', 'clear'], true)) {
  requireConfirmation($confirmTech, 'sigTech');
}

$sigCustomerFile = $existingCustomerFile;
$sigTechFile = $existingTechFile;

if ($customerOp === 'replace') {
  $sigCustomerFile = saveSig((string)$incomingCustomer, "$dir/$customerFileName");
} elseif ($customerOp === 'clear') {
  if ($existingCustomerFile) {
    @unlink("$dir/$existingCustomerFile");
  }
  $sigCustomerFile = null;
}

if ($techOp === 'replace') {
  $sigTechFile = saveSig((string)$incomingTech, "$dir/$techFileName");
} elseif ($techOp === 'clear') {
  if ($existingTechFile) {
    @unlink("$dir/$existingTechFile");
  }
  $sigTechFile = null;
}

unset($data["sigCustomer"], $data["sigTech"], $data['confirmOverwriteSigCustomer'], $data['confirmOverwriteSigTech']);
$data["sigCustomerFile"] = $sigCustomerFile;
$data["sigTechFile"] = $sigTechFile;

file_put_contents(
  $jsonPath,
  json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
  LOCK_EX
);

echo json_encode(["ok" => true, "id" => $id]);
