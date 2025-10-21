<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

// --- Config ---
$googleUrl = "https://script.google.com/macros/s/AKfycbx2ea_6RtEOIwB1htGeyHI5ls_Wv4gP0rtyvy2oFzoQQQGomn966Fwk7o4eKBq0Qb5fTw/exec";
$localFile = __DIR__ . "/last_client.json";

// --- ROUTAGE SIMPLE ---
$action = $_GET['action'] ?? ''; // ex: ?action=client pour le suivi

// === MODE "CLIENT" : gestion de l’attente ===
if ($action === 'client') {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 🟢 Reçoit une notification de Google Sheet
    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data) {
      echo json_encode(["status" => "error", "message" => "JSON invalide"]);
      exit;
    }

    // Enregistre le client localement
    file_put_contents($localFile, json_encode($data, JSON_PRETTY_PRINT));
    echo json_encode(["status" => "ok", "message" => "Client enregistré", "data" => $data]);
    exit;
  }

  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // 🔵 Lecture du dernier client
    if (!file_exists($localFile)) {
      echo json_encode(["status" => "empty", "message" => "Aucun client pour le moment"]);
    } else {
      $data = json_decode(file_get_contents($localFile), true);
      echo json_encode(["status" => "ok", "data" => $data]);
    }
    exit;
  }
}

// === MODE PAR DÉFAUT : proxy vers Google Apps Script ===
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

echo json_encode(["status" => "error", "message" => "Requête non gérée"]);
