<?php
// -------- PROXY JSON STRICT --------

// Pas d’erreurs PHP en sortie (sinon HTML)
@ini_set('display_errors', 0);
@error_reporting(0);

// Vide les tampons d’output (évite BOM/echo)
if (function_exists('ob_get_level')) {
  while (ob_get_level() > 0) { @ob_end_clean(); }
}

// En-têtes CORS + JSON
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

// --- Config --- (pas de .env)
$googleUrl = "https://script.google.com/macros/s/AKfycbzbqgHi4_dRGsqvkpHnH4zx3gnsCMaR3VA5GsKQxltVTgz1KpczsxfzZfgaUMOaiD8t/exec";
$localFile = __DIR__ . "/last_client.json";
$action = $_GET['action'] ?? '';

// Réponse JSON d’erreur
function http_json_error($msg, $code = 502) {
  http_response_code($code);
  echo json_encode(["status" => "error", "message" => $msg], JSON_UNESCAPED_UNICODE);
  exit;
}

// === MODE "CLIENT": attendre / recevoir sélection depuis la Sheet ===
if ($action === 'client') {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true);
    if (!$data || !isset($data['id'])) {
      echo json_encode(["status" => "error", "message" => "JSON invalide ou incomplet"]);
      exit;
    }
    file_put_contents($localFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo json_encode(["status" => "ok", "message" => "Client enregistré", "data" => $data]);
    exit;
  }

  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!file_exists($localFile)) {
      echo json_encode(["status" => "empty", "message" => "Aucun client pour le moment"]);
      exit;
    }
    $content = file_get_contents($localFile);
    $data = json_decode($content, true);
    if (!$data || !isset($data['id'])) {
      @unlink($localFile);
      echo json_encode(["status" => "empty", "message" => "Aucun client pour le moment"]);
      exit;
    }
    echo json_encode(["status" => "ok", "data" => $data], JSON_UNESCAPED_UNICODE);
    exit;
  }
}

// === PROXY GET -> Apps Script (lecture JSON pur)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $id = $_GET['id'] ?? '';
  $url = $googleUrl . "?id=" . urlencode($id);

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 30,
  ]);
  $response = curl_exec($ch);
  $err = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  curl_close($ch);

  if ($err) http_json_error("Proxy GET cURL: $err");
  if ($code < 200 || $code >= 300) http_json_error("Proxy GET HTTP $code");

  $json = json_decode($response, true);
  if ($json === null) http_json_error("Réponse non JSON depuis Apps Script (GET)");
  echo $response;
  exit;
}

// === PROXY POST -> Apps Script (écriture & PDF)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $input = file_get_contents("php://input");

  $ch = curl_init($googleUrl);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => $input,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 60,
  ]);
  $response = curl_exec($ch);
  $err = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  curl_close($ch);

  if ($err) http_json_error("Proxy POST cURL: $err");
  if ($code < 200 || $code >= 300) http_json_error("Proxy POST HTTP $code");

  $json = json_decode($response, true);
  if ($json === null) http_json_error("Réponse non JSON depuis Apps Script (POST)");

  if (($json['status'] ?? '') === 'success' && file_exists($localFile)) {
    @unlink($localFile); // retour à "en attente"
  }

  echo json_encode($json, JSON_UNESCAPED_UNICODE);
  exit;
}

http_json_error("Requête non gérée", 400);
