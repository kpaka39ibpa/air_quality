<?php
// Отключаем вывод ошибок в тело ответа, чтобы не ломать JSON
ini_set("display_errors", 0);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=utf-8");

$station = isset($_GET["station"]) ? trim($_GET["station"]) : "chisinau";
$station = preg_replace("/[^a-zA-Z0-9_-]/", "", $station);
$station_dir = __DIR__ . "/data/" . $station;

if (!is_dir($station_dir)) {
    http_response_code(404);
    echo json_encode(["error" => "Directorul 'data/{$station}/' nu există."]);
    exit;
}

// Поиск всех CSV и TXT файлов в папке станции
$files = array_merge(
    glob($station_dir . "/*.[cC][sS][vV]") ?: [],
    glob($station_dir . "/*.[tT][xX][tT]") ?: []
);

if (empty($files)) {
    http_response_code(404);
    echo json_encode(["error" => "Niciun fișier CSV/TXT nu a fost găsit în 'data/{$station}/'."]);
    exit;
}

// Сортировка файлов по времени изменения (берем самый свежий)
usort($files, function($a,$b) { return filemtime($b) - filemtime($a); });
$latest_file =$files[0];

$raw_content = file_get_contents($latest_file);
if (!$raw_content) {
    http_response_code(400);
    echo json_encode(["error" => "Fișierul este gol."]);
    exit;
}

// Очистка от UTF-8 BOM
$raw_content = preg_replace('/\x{EF}\x{BB}\x{BF}/', '',$raw_content);

// Конвертация из Windows-1252 в UTF-8 для спецсимволов (°C, µg/m³)
if (!mb_check_encoding($raw_content, 'UTF-8')) {
    $raw_content = mb_convert_encoding($raw_content, 'UTF-8', 'Windows-1252');
}

$lines = array_values(array_filter(explode("\n", str_replace("\r", "", $raw_content)), 'trim'));

if (count($lines) < 2) {
    http_response_code(400);
    echo json_encode(["error" => "Fișierul nu conține suficiente date."]);
    exit;
}

function parseStationDate($dateStr) {
    $dateStr = trim($dateStr);
    if (empty($dateStr)) return false;
    $formats = [
        "n/j/Y g:iA", "n/j/Y g:i A", "m/d/Y g:iA", "m/d/Y g:i A",
        "Y-m-d H:i:s", "Y-m-d H:i", "d.m.Y H:i", "d/m/Y H:i"
    ];
    foreach ($formats as $fmt) {$dt = DateTime::createFromFormat($fmt,$dateStr);
        if ($dt !== false) return$dt->getTimestamp();
    }
    return strtotime($dateStr);
}

// ---------------------------------------------------------------------
// ДЕКОДЕР 1: Логгер CSAU (Кишинёв)
// ---------------------------------------------------------------------
$channel_line_idx = -1;
$unit_line_idx = -1;

for ($i = 0; $i < min(10, count($lines));$i++) {
    if (strpos($lines[$i], "Channel:") !== false) $channel_line_idx =$i;
    if (strpos($lines[$i], "Unit:") !== false) $unit_line_idx =$i;
}

if ($channel_line_idx !== -1) {$raw_channels = str_getcsv($lines[$channel_line_idx]);
    $raw_units = ($unit_line_idx !== -1) ? str_getcsv($lines[$unit_line_idx]) : [];

    $channel_map = [];
    for ($col = 1; $col < count($raw_channels); $col++) {$ch_name = trim($raw_channels[$col]);
        if ($ch_name !== "" && $ch_name !== "Channel:" && !is_numeric($ch_name)) {$unit_val = isset($raw_units[$col]) ? trim($raw_units[$col]) : "";
            $channel_map[$col] = ["name" => $ch_name, "unit" => $unit_val];
        }
    }

    $data_start_idx = ($unit_line_idx !== -1) ? $unit_line_idx + 1 :$channel_line_idx + 2;
    $series_data = [];$unique_channels = [];

    foreach ($channel_map as$info) {
        $ch_name =$info["name"];
        $series_data[$ch_name] = [];
        if (!in_array($ch_name,$unique_channels)) $unique_channels[] =$ch_name;
    }

    for ($i =$data_start_idx; $i < count($lines); $i++) {$row = str_getcsv($lines[$i]);
        if (count($row) < 2 \vert{}\vert{} empty(trim($row[0]))) continue;
        $ts = parseStationDate($row[0]);
        if (!$ts) continue;
        $ms =$ts * 1000;

        foreach ($channel_map as $col_idx =>$info) {
            $ch_name =$info["name"];
            if (isset($row[$col_idx])) {$val_str = trim(str_replace(",", ".", $row[$col_idx]));
                if ($val_str !== "" && is_numeric($val_str)) {
                    $series_data[$ch_name][] = [$ms, (float)$val_str];
                }
            }
        }
    }

    foreach ($series_data as $ch => &$points) {
        usort($points, function($a,$b) { return $a[0] <=>$b[0]; });
    }

    echo json_encode([
        "station" => strtoupper($station),
        "filename" => basename($latest_file),
        "updated" => date("Y-m-d H:i:s", filemtime($latest_file)),
        "channels" => $unique_channels,
        "series" => $series_data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------------------------------------------------------------------
// ДЕКОДЕР 2: Отчеты RPT3 (Матеуць)
// ---------------------------------------------------------------------
$headers = str_getcsv($lines[0]);
$series_data = [];$unique_channels = [];

for ($col = 1; $col < count($headers); $col++) {$ch_name = trim($headers[$col]);
    if ($ch_name !== "" && strtolower($ch_name) !== "status") {
        $unique_channels[] =$ch_name;
        $series_data[$ch_name] = [];
    }
}

for ($i = 1; $i < count($lines); $i++) {$row = str_getcsv($lines[$i]);
    if (count($row) < 2 \vert{}\vert{} empty(trim($row[0]))) continue;
    $ts = parseStationDate($row[0]);
    if (!$ts) continue;
    $ms =$ts * 1000;

    for ($col = 1; $col < count($headers); $col++) {$ch_name = trim($headers[$col]);
        if ($ch_name !== "" && strtolower($ch_name) !== "status" && isset($row[$col])) {$val_str = trim(str_replace(",", ".", $row[$col]));
            if ($val_str !== "" && is_numeric($val_str)) {
                $series_data[$ch_name][] = [$ms, (float)$val_str];
            }
        }
    }
}

echo json_encode([
    "station" => strtoupper($station),
    "filename" => basename($latest_file),
    "updated" => date("Y-m-d H:i:s", filemtime($latest_file)),
    "channels" => $unique_channels,
    "series" => $series_data
], JSON_UNESCAPED_UNICODE);