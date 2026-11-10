<?php
header("Content-Type: application/json; charset=utf-8");
ini_set("display_errors", 0);
error_reporting(E_ALL);

// 1. Получаем имя станции из GET (по умолчанию chisinau)
$station = isset($_GET["station"]) ? trim($_GET["station"]) : "chisinau";
$station = preg_replace("/[^a-zA-Z0-9_-]/", "", $station);

$station_dir = __DIR__ . "/data/" . $station;
$latest_file = $station_dir . "/latest.csv";

if (!file_exists($latest_file)) {
    $csvFiles = glob($station_dir . "/*.csv");
    if (!empty($csvFiles)) {
        usort($csvFiles, function($a, $b) { return filemtime($b) - filemtime($a); });
        $latest_file = $csvFiles[0];
    }
}

if (!file_exists($latest_file)) {
    http_response_code(404);
    echo json_encode(["error" => "Fișierul local pentru stația '{$station}' nu a fost găsit."]);
    exit;
}

$lines = file($latest_file);
if (!$lines || count($lines) < 2) {
    http_response_code(400);
    echo json_encode(["error" => "Fișierul este gol sau invalid."]);
    exit;
}

function parseCSVDate($dateStr) {
    $dateStr = trim($dateStr);
    if (empty($dateStr)) return false;
    $formats = ["n/j/Y g:iA", "n/j/Y g:i A", "n/j/Y H:i", "Y-m-d H:i:s", "d.m.Y H:i", "d/m/Y H:i"];
    foreach ($formats as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $dateStr);
        if ($dt !== false) return $dt->getTimestamp();
    }
    return strtotime($dateStr);
}

// 2. Логика для формата автологгера (Channel: / Unit:)
$channel_line_idx = -1;
$unit_line_idx = -1;

for ($i = 0; $i < min(10, count($lines)); $i++) {
    if (strpos($lines[$i], "Channel:") !== false) $channel_line_idx = $i;
    if (strpos($lines[$i], "Unit:") !== false) $unit_line_idx = $i;
}

if ($channel_line_idx !== -1) {
    $raw_channels = str_getcsv($lines[$channel_line_idx]);
    $raw_units = ($unit_line_idx !== -1) ? str_getcsv($lines[$unit_line_idx]) : [];

    $channel_map = [];
    for ($col = 1; $col < count($raw_channels); $col++) {
        $ch_name = trim($raw_channels[$col]);
        if ($ch_name !== "" && $ch_name !== "Channel:" && !is_numeric($ch_name)) {
            $unit_val = isset($raw_units[$col]) ? trim($raw_units[$col]) : "";
            $channel_map[$col] = ["name" => $ch_name, "unit" => $unit_val];
        }
    }

    $data_start_idx = ($unit_line_idx !== -1) ? $unit_line_idx + 1 : $channel_line_idx + 2;
    $all_rows = [];

    for ($i = $data_start_idx; $i < count($lines); $i++) {
        $row = str_getcsv($lines[$i]);
        if (count($row) < 2 || empty(trim($row[0]))) continue;
        $ts = parseCSVDate($row[0]);
        if ($ts) $all_rows[] = ["ts" => $ts, "row" => $row];
    }

    if (count($all_rows) > 720) $all_rows = array_slice($all_rows, -720);

    $series_data = [];
    $unique_channels = [];

    foreach ($channel_map as $col_idx => $info) {
        $ch_name = $info["name"];
        $series_data[$ch_name] = [];
        if (!in_array($ch_name, $unique_channels)) $unique_channels[] = $ch_name;
    }

    foreach ($all_rows as $item) {
        $ms = $item["ts"] * 1000;
        $row = $item["row"];
        foreach ($channel_map as $col_idx => $info) {
            $ch_name = $info["name"];
            if (isset($row[$col_idx])) {
                $val_str = trim(str_replace(",", ".", $row[$col_idx]));
                if ($val_str !== "" && is_numeric($val_str)) {
                    $val = round((float)$val_str, 2);
                    $series_data[$ch_name][] = [$ms, $val];
                }
            }
        }
    }

    foreach ($series_data as $ch => &$points) {
        usort($points, function($a, $b) { return $a[0] <=> $b[0]; });
    }

    echo json_encode([
        "station" => strtoupper($station),
        "updated" => date("Y-m-d H:i:s", filemtime($latest_file)),
        "channels" => $unique_channels,
        "series" => $series_data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. Простой формат CSV
$headers = str_getcsv($lines[0]);
$series_data = [];
$unique_channels = [];

for ($col = 1; $col < count($headers); $col++) {
    $ch_name = trim($headers[$col]);
    if ($ch_name !== "") {
        $unique_channels[] = $ch_name;
        $series_data[$ch_name] = [];
    }
}

for ($i = 1; $i < count($lines); $i++) {
    $row = str_getcsv($lines[$i]);
    if (count($row) < 2 || empty(trim($row[0]))) continue;
    $ts = parseCSVDate($row[0]);
    if (!$ts) continue;
    $ms = $ts * 1000;
    for ($col = 1; $col < count($headers); $col++) {
        $ch_name = trim($headers[$col]);
        if (isset($row[$col]) && is_numeric(trim($row[$col]))) {
            $series_data[$ch_name][] = [$ms, (float)trim($row[$col])];
        }
    }
}

echo json_encode([
    "station" => strtoupper($station),
    "updated" => date("Y-m-d H:i:s", filemtime($latest_file)),
    "channels" => $unique_channels,
    "series" => $series_data
], JSON_UNESCAPED_UNICODE);