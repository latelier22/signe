<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

$googleUrl = "https://script.google.com/macros/s/AKfycbx2ea_6RtEOIwB1htGeyHI5ls_Wv4gP0rtyvy2oFzoQQQGomn966Fwk7o4eKBq0Qb5fTw/exec";

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $id = $_GET['id'] ?? '';
  $url = $googleUrl . "?id=" . urlencode($id);
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  $response = curl_exec($ch);
  curl_close($ch);
  echo $response;
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $input = file_get_contents("php://input");
  $ch = curl_init($googleUrl);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $input);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  $response = curl_exec($ch);
  curl_close($ch);
  echo $response;
  exit;
}
