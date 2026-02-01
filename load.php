<?php
declare(strict_types=1);

$id = $_GET["id"] ?? "";
if (!preg_match("/^[A-Z0-9]{6,10}$/", $id)) {
  http_response_code(400);
  exit;
}

$file = __DIR__ . "/data/$id.json";
if (!file_exists($file)) {
  http_response_code(404);
  exit;
}

$data = json_decode(file_get_contents($file), true);
if (!is_array($data)) {
  http_response_code(500);
  exit;
}

$base = "/data/";
$data["sigCustomer"] = !empty($data["sigCustomerFile"])
  ? $base . $data["sigCustomerFile"]
  : "";

$data["sigTech"] = !empty($data["sigTechFile"])
  ? $base . $data["sigTechFile"]
  : "";

unset($data["sigCustomerFile"], $data["sigTechFile"]);

header("Content-Type: application/json; charset=utf-8");
echo json_encode($data);
