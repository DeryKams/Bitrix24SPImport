<?php
// linkedSP.php — отдает <label> + <select name="entityTypeId"> со списком СП
require_once(__DIR__ . '/crestcurrent.php');
require_once(__DIR__ . '/crest.php');


//Забираем переданную ID сделки
$placement_json = $_POST['PLACEMENT_OPTIONS'] ?? ($_REQUEST['PLACEMENT_OPTIONS'] ?? '');
$placement_options = [];
if ($placement_json !== '') {
    $placement_options = json_decode($placement_json, true);
    if (!is_array($placement_options)) {
        $placement_options = [];
    }
}

//print_r($placement_options);

//Получаем все поля сделки, чтобы дальше отфильтровать Parent_ID, связанных смарт-процессов
$deal = CRest::call(
        'crm.deal.get',
        [
                'ID' => $placement_options['ID']
        ]
);

//print_r($deal);
//Выводим данные по сделке

//Получаем именно численные ID из PARENT ID, связанных смарт-процессов
$parentKeys = [];

if (!empty($deal['result'])) {
    foreach ($deal['result'] as $key => $value) {
        if (strpos($key, 'PARENT_ID_') === 0) {
            $parentKeys[] = $key;   // собираем только имена полей
        }
    }
}
//В $parentKeys содержаться только данные типа 	PARENT_ID_1036

// Убираем из parentKeys все PARENT_ID_, чтобы получить только ID
foreach ($parentKeys as $parentKey) {
    $prefix = str_replace('PARENT_ID_', '', $parentKey);
    $entityTypes[] = $prefix;
}


// Нормализуем вход на всякий битриксовый случай
$entityTypes = array_map('intval', $entityTypes ?? []);
$entityTypes = array_values(array_unique(array_filter($entityTypes))); // уберём пустые/дубли

$options = []; // сюда соберём eid => title

//Если мы получили список СП
if ($entityTypes) {
    // Тянем список типов, чтобы получить ЧЕЛОВЕЧЕСКИЕ названия
    //    (фильтруем по entityTypeId локально)
    $types = CRest::call('crm.type.list', [
            'filter' => [],  // можно тянуть все, дальше отфильтруем
            'select' => ['title', 'entityTypeId'],
    ]);

    if (!empty($types['result']['types'])) {
        foreach ($types['result']['types'] as $t) {
            $eid = (int)($t['entityTypeId'] ?? 0);
            if (!$eid) continue;

            // оставляем только те, что есть в $entityTypes
            if (!in_array($eid, $entityTypes, true)) continue;

            $title = trim($t['title'] ?? '') ?: ('СП ' . $eid); // читаемое имя с запасным вариантом
            $options[$eid] = $title;
        }
    }

    //  Если каких-то eid нет в ответе API (скрыты/невернули) — добавим их "как есть"
    foreach ($entityTypes as $eid) {
        if (!isset($options[$eid])) {
            $options[$eid] = 'СП ' . $eid; // дефолтная подпись
        }
    }
}
?>

<!-- Отрисовываем селект name="entityTypeId" — ключ! он уйдет в pars.php -->
<select id="entityTypeId" name="entityTypeId" required>
    <option value="" disabled selected>— выберите смарт-процесс —</option>
    <?php foreach ($options as $eid => $title): ?>
        <option value="<?= htmlspecialchars($eid) ?>">
            <?= htmlspecialchars($title) ?> (<?= htmlspecialchars($eid) ?>)
        </option>
    <?php endforeach; ?>
</select>
