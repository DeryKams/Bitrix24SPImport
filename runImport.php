<?php
// runImport.php — финальный импорт по карте соответствий
require_once(__DIR__ . '/crestcurrent.php');
require_once(__DIR__ . '/crest.php');
require_once __DIR__ . '/lib/SimpleXLSX.php';
use Shuchkin\SimpleXLSX;

// Входные данные
$eTypeId   = isset($_POST['entityTypeId']) ? (int)$_POST['entityTypeId'] : 0;
$map       = isset($_POST['map']) && is_array($_POST['map']) ? $_POST['map'] : [];
$fileToken = $_POST['file_token'] ?? '';

if ($eTypeId <= 0)     exit('<div>ENTITY_TYPE_ID не указан</div>');
if (empty($map))       exit('<div>Карта соответствий пуста</div>');
if ($fileToken === '') exit('<div>Нет токена файла</div>');


// Путь к ранее сохранённому файлу
$path = __DIR__ . '/uploads/' . basename($fileToken);
if (!is_file($path)) exit('<div>Файл недоступен: '.htmlspecialchars($fileToken).'</div>');

// Парсим XLSX повторно (stateless)
if (!$xlsx = Shuchkin\SimpleXLSX::parse($path)) {
    exit('<div>Не удалось распарсить XLSX</div>');
}
$rows = $xlsx->rows();
if (count($rows) < 2) exit('<div>Нет данных (только заголовки)</div>');

//  Индекс заголовков
$headers = array_map('trim', $rows[0]);
$colIndex = [];
foreach ($headers as $i => $name) {
    if ($name !== '') $colIndex[$name] = $i;
}

//  Импорт построчно
$created = 0; $skipped = 0; $errors = [];

for ($r = 1; $r < count($rows); $r++) {
    $row = $rows[$r];
    $fields = [];

    // Собираем значения по карте
    foreach ($map as $crmCode => $header) {
        if ($header === '' || !isset($colIndex[$header])) continue; // пропустить
        $value = $row[$colIndex[$header]] ?? null;
        if (is_string($value)) $value = trim($value);               // простая нормализация
        $fields[$crmCode] = $value;
    }
    //  Подмешиваем дефолты
    $defaults = isset($_POST['defaults']) && is_array($_POST['defaults']) ? $_POST['defaults'] : [];
    foreach ($defaults as $code => $val) {
        if (!array_key_exists($code, $fields) || $fields[$code] === '' || $fields[$code] === null) {
            $fields[$code] = $val;
        }
    }
    if (isset($fields['PARENT_ID_2'])) {
        if (is_string($fields['PARENT_ID_2'])) {
            $fields['PARENT_ID_2'] = [$fields['PARENT_ID_2']]; // завернём в массив
        } elseif (is_array($fields['PARENT_ID_2'])) {
            // уже ок
        } else {
            unset($fields['PARENT_ID_2']); // неподдерживаемый формат — лучше убрать
        }
    }
    // Если ничего не собрано — пропускаем строку
    if (!$fields) { $skipped++; continue; }

    // Обязательное поле title — подстрахуемся
    if (!isset($fields['title']) || $fields['title'] === '') {
        $fields['title'] = 'Импорт '.date('Y-m-d H:i:s');
    }

    // Вызов Bitrix24
    $res = CRest::call('crm.item.add', [
        'entityTypeId' => $eTypeId,
        'fields'       => $fields,
    ]);

    if (!empty($res['result']['item']['id'])) {
        $created++;
    } else {
        $errors[] = [
            'row'   => $r + 1,
            'error' => $res['error_description'] ?? 'unknown',
        ];
    }
}

// Ответ для HTMX
echo "<div><strong>Создано:</strong> {$created}; <strong>пропущено:</strong> {$skipped}</div>";
if ($errors) {
    echo "<details><summary>Ошибки (".count($errors).")</summary><pre>";
    foreach ($errors as $e) {
        echo "Строка {$e['row']}: {$e['error']}\n";
    }
    echo "</pre></details>";
}
