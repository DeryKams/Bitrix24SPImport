<?php
// runImport.php — финальный импорт по карте соответствий
require_once(__DIR__ . '/crestcurrent.php');
require_once(__DIR__ . '/crest.php');
require_once __DIR__ . '/lib/SimpleXLSX.php';

use Shuchkin\SimpleXLSX;

// Входные данные
$eTypeId = isset($_POST['entityTypeId']) ? (int)$_POST['entityTypeId'] : 0;
$map = isset($_POST['map']) && is_array($_POST['map']) ? $_POST['map'] : [];
$fileToken = $_POST['file_token'] ?? '';

if ($eTypeId <= 0) exit('<div>ENTITY_TYPE_ID не указан</div>');
if (empty($map)) exit('<div>Карта соответствий пуста</div>');
if ($fileToken === '') exit('<div>Нет токена файла</div>');

// --------- сохранение маппинга на пользователя

function b24_get_current_user_id(): int {
    $r2 = CRestCurrent::call('user.current');       // при открытии внутри Б24
    $r1 = CRest::call('user.current');              // при вызове по вебхуку/иначе
    $u  = $r2['result'] ?? $r1['result'] ?? null;
    return isset($u['ID']) ? (int)$u['ID'] : 0;
}

$userId = b24_get_current_user_id();               // ID пользователя, открывшего приложение
if ($userId > 0) {
    $baseDir   = __DIR__ . '/users';               // корневая папка пользователей
    $userDir   = $baseDir . '/' . $userId;         // ./users/123
    // создаём директорию, если её нет (рекурсивно)
    if (!is_dir($userDir)) {
        if (!mkdir($userDir, 0775, true) && !is_dir($userDir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $userDir));
        }              // права 775; @ чтобы не шуметь варнингами
    }

    // Пакет данных, который сохраняем (полезно иметь метаданные)
    $payload = [
        'entityTypeId' => $eTypeId,                // смарт-процесс
        'map'          => $map,                    // карта соответствий поле->колонка
        'savedAt'      => date('c'),               // ISO8601 время сохранения
        'userId'       => $userId,                 // кто сохранял
    ];

    // имя файла привязываем к смарт-процессу
    $filePath = $userDir . '/mapping_sp_' . $eTypeId . '.json';

    // пишем атомарно
    file_put_contents(
        $filePath,
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        LOCK_EX
    );
}

// Путь к ранее сохранённому файлу
$path = __DIR__ . '/uploads/' . basename($fileToken);
if (!is_file($path)) exit('<div>Файл недоступен: ' . htmlspecialchars($fileToken) . '</div>');

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
$created = 0;
$skipped = 0;
$errors = [];

for ($r = 1, $rMax = count($rows); $r < $rMax; $r++) {
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
    if (!$fields) {
        $skipped++;
        continue;
    }

    // Обязательное поле title — подстрахуемся
    if (!isset($fields['title']) || $fields['title'] === '') {
        $fields['title'] = 'Импорт ' . date('Y-m-d H:i:s');
    }

    // Вызов Bitrix24
    $res = CRest::call('crm.item.add', [
        'entityTypeId' => $eTypeId,
        'fields' => $fields,
    ]);

    if (!empty($res['result']['item']['id'])) {
        $created++;
    } else {
        $errors[] = [
            'row' => $r + 1,
            'error' => $res['error_description'] ?? 'unknown',
        ];
    }
}

// Ответ для HTMX
echo "<div><strong>Создано:</strong> {$created}; <strong>пропущено:</strong> {$skipped}</div>";
if ($errors) {
    echo "<details><summary>Ошибки (" . count($errors) . ")</summary><pre>";
    foreach ($errors as $e) {
        echo "Строка {$e['row']}: {$e['error']}\n";
    }
    echo "</pre></details>";
}
