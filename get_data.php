<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 1. Получаем имя станции (по умолчанию chisinau)
\ = isset(\['station']) ? trim(\['station']) : 'chisinau';
\ = preg_replace('/[^a-zA-Z0-9_-]/', '', \);

\ = __DIR__ . "/data/" . \;
\ = \ . "/latest.csv";

if (!file_exists(\)) {
    \ = glob(\ . "/*.csv");
    if (!empty(\)) {
        usort(\, function(\, \) { return filemtime(\) - filemtime(\); });
        \ = \[0];
    }
}

if (!file_exists(\)) {
    http_response_code(404);
    echo json_encode(["error" => "Fi?ierul local pentru sta?ia '{\}' nu a fost gasit."]);
    exit;
}

\ = file(\);
if (!\ || count(\) < 2) {
    http_response_code(400);
    echo json_encode(["error" => "Fi?ierul este gol sau invalid."]);
    exit;
}

function parseCSVDate(\) {
    \ = trim(\);
    if (empty(\)) return false;
    \ = ['n/j/Y g:iA', 'n/j/Y g:i A', 'n/j/Y H:i', 'Y-m-d H:i:s', 'd.m.Y H:i', 'd/m/Y H:i'];
    foreach (\ as \) {
        \ = DateTime::createFromFormat(\, \);
        if (\ !== false) return \->getTimestamp();
    }
    return strtotime(\);
}

// 2. Логика для формата автологгера (Channel: / Unit:)
\ = -1;
\ = -1;

for (\ = 0; \ < min(10, count(\)); \++) {
    if (strpos(\[\], 'Channel:') !== false) \ = \;
    if (strpos(\[\], 'Unit:') !== false) \ = \;
}

if (\ !== -1) {
    \ = str_getcsv(\[\]);
    \ = (\ !== -1) ? str_getcsv(\[\]) : [];

    \ = [];
    for (\ = 1; \ < count(\); \++) {
        \ = trim(\[\]);
        if (\ !== '' && \ !== 'Channel:' && !is_numeric(\)) {
            \ = isset(\[\]) ? trim(\[\]) : '';
            \[\] = ['name' => \, 'unit' => \];
        }
    }

    \ = (\ !== -1) ? \ + 1 : \ + 2;
    \ = [];

    for (\ = \; \ < count(\); \++) {
        \ = str_getcsv(\[\]);
        if (count(\) < 2 || empty(trim(\[0]))) continue;
        \ = parseCSVDate(\[0]);
        if (\) \[] = ['ts' => \, 'row' => \];
    }

    if (count(\) > 720) \ = array_slice(\, -720);

    \ = [];
    \ = [];

    foreach (\ as \ => \) {
        \ = \['name'];
        \[\] = [];
        if (!in_array(\, \)) \[] = \;
    }

    foreach (\ as \) {
        \ = \['ts'] * 1000;
        \ = \['row'];
        foreach (\ as \ => \) {
            \ = \['name'];
            if (isset(\[\])) {
                \ = trim(str_replace(',', '.', \[\]));
                if (\ !== '' && is_numeric(\)) {
                    \ = round((float)\, 2);
                    \[\][] = [\, \];
                }
            }
        }
    }

    foreach (\ as \ => &\) {
        usort(\, function(\, \) { return \[0] <=> \[0]; });
    }

    echo json_encode([
        "station" => strtoupper(\),
        "updated" => date("Y-m-d H:i:s", filemtime(\)),
        "channels" => \,
        "series" => \
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. Простой формат CSV (для обычных файлов)
\ = str_getcsv(\[0]);
\ = [];
\ = [];

for (\ = 1; \ < count(\); \++) {
    \ = trim(\[\]);
    if (\ !== '') {
        \[] = \;
        \[\] = [];
    }
}

for (\ = 1; \ < count(\); \++) {
    \ = str_getcsv(\[\]);
    if (count(\) < 2 || empty(trim(\[0]))) continue;
    \ = parseCSVDate(\[0]);
    if (!\) continue;
    \ = \ * 1000;
    for (\ = 1; \ < count(\); \++) {
        \ = trim(\[\]);
        if (isset(\[\]) && is_numeric(trim(\[\]))) {
            \[\][] = [\, (float)trim(\[\])];
        }
    }
}

echo json_encode([
    "station" => strtoupper(\),
    "updated" => date("Y-m-d H:i:s", filemtime(\)),
    "channels" => \,
    "series" => \
], JSON_UNESCAPED_UNICODE);
