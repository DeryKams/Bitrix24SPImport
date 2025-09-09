<?php
// pars.php — принимает XLSX, сохраняет, читает заголовки и отдаёт форму маппинга
require_once(__DIR__ . '/crestcurrent.php');
require_once(__DIR__ . '/crest.php');
require_once __DIR__ . '/lib/SimpleXLSX.php';

use Shuchkin\SimpleXLSX;

// 0) Проверяем entityTypeId (может прийти из формы или hx-vals)
$eTypeId = isset($_POST['entityTypeId']) ? (int)$_POST['entityTypeId'] : 0;

// 1) Проверяем файл
if (empty($_FILES['xlsx']['tmp_name'])) {
    exit('<div>Загрузите .xlsx</div>');
}

// 2) Сохраняем файл во временную папку и выдаём "токен"
$uploadDir = __DIR__ . '/uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$fileToken = 'u_' . bin2hex(random_bytes(8)) . '.xlsx'; // простой токен-имя
$savePath = $uploadDir . '/' . $fileToken;

if (!move_uploaded_file($_FILES['xlsx']['tmp_name'], $savePath)) {
    exit('<div>Не удалось сохранить файл</div>');
}

// 3) Парсим файл и достаём заголовки первой строки
if (!$xlsx = Shuchkin\SimpleXLSX::parse($savePath)) {
    exit('<div>Не удалось прочитать XLSX</div>');
}

$rows = $xlsx->rows();
if (!$rows || !isset($rows[0])) {
    exit('<div>В файле нет данных</div>');
}

// Чистим заголовки: обрезать пробелы, убрать пустые
$headers = array_values(array_filter(array_map('trim', $rows[0]), fn($v) => $v !== ''));


// 4) Показываем превью заголовков и форму, которая пойдёт в fieldsSP.php
?>
<?php
$placementRaw = $_POST['PLACEMENT_OPTIONS'] ?? '';
$try = $placementRaw;
if ($try && ($tmp = urldecode($try)) && $tmp !== $try) $try = $tmp;
if ($try && ($tmp = base64_decode($try, true)) !== false) $try = $tmp;
$placement = $try ? json_decode($try, true) : [];
if (!is_array($placement)) $placement = [];

function findDealId($arr) {
    $stack = [$arr]; $keys = ['DEAL_ID','dealId','ENTITY_ID','entityId','ID','id'];
    while ($stack) {
        $cur = array_pop($stack);
        if (!is_array($cur)) continue;
        foreach ($cur as $k=>$v) {
            if (in_array($k,$keys,true) && is_scalar($v) && (int)$v>0) return (int)$v;
            if (is_array($v)) $stack[]=$v;
        }
    }
    return 0;
}
$dealId = findDealId($placement);
?>

<form hx-post="runImport.php" hx-target="#step" hx-swap="innerHTML">
    <input type="hidden" name="entityTypeId" value="<?= (int)$eTypeId ?>">
    <input type="hidden" name="file_token"    value="<?= htmlspecialchars($fileToken) ?>">
    <input type="hidden" name="PLACEMENT_OPTIONS"
           value="<?= htmlspecialchars($_POST['PLACEMENT_OPTIONS'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

    <?php if ($dealId > 0): ?>
        <input type="hidden" name="defaults[parentId2]"  value="<?= (int)$dealId ?>">
        <!-- ЗАМЕНИ 'crm_entity' на реальный код UF (например, UF_CRM_XXXXX), если он отличается -->
        <input type="hidden" name="defaults[crm_entity]" value="<?= 'D_'.(int)$dealId ?>">
        <div style="margin:8px 0;color:#555">
            По умолчанию: parentId2 = <?= (int)$dealId ?>, crm_entity = <?= 'D_'.(int)$dealId ?>
        </div>
    <?php endif; ?>


<div>
    <h3>Колонки файла:</h3>
    <?php foreach ($headers as $h): ?>
        <span style="display:inline-block;margin:2px 4px;padding:2px 6px;border:1px solid #ddd;">
      <?= htmlspecialchars($h) ?>
    </span>
    <?php endforeach; ?>
</div>

<form id="build-mapping"
      hx-post="fieldsSP.php"
      hx-target="#step"
      hx-swap="innerHTML"
      hx-include="#entityTypeId, #placement_opts"><!-- тянем выбранный СП и PLACEMENT_OPTIONS из DOM -->

    <!-- путь к сохранённому файлу -->
    <input type="hidden" name="file_token" value="<?= htmlspecialchars($fileToken) ?>">

    <!-- заголовки колонок -->
    <?php foreach ($headers as $h): ?>
        <input type="hidden" name="headers[]" value="<?= htmlspecialchars($h) ?>">
    <?php endforeach; ?>

    <!-- дубль на случай отсутствия #placement_opts (перестраховка) -->
    <input type="hidden" name="PLACEMENT_OPTIONS"
           value="<?= htmlspecialchars($placementRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

    <br>
    <button type="submit">Построить соответствия полей</button>
</form>
