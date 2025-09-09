<?php
// fieldsSP.php — тянет поля CRM и строит таблицу соответствий поле - колонка файла
require_once(__DIR__ . '/crestcurrent.php');
require_once(__DIR__ . '/crest.php');

//Забираем ID сделки
$placementRaw = $_POST['PLACEMENT_OPTIONS'] ?? '';
$placement = $placementRaw ? json_decode($placementRaw, true) : [];
$dealId = 0;
// пытаемся надёжно вытащить ID сделки из разных возможных ключей
foreach (['DEAL_ID', 'dealId', 'ENTITY_ID', 'ID'] as $k) {
    if (isset($placement[$k]) && (int)$placement[$k] > 0) {
        $dealId = (int)$placement[$k];
        print_r($dealId);
        break;
    }
}
// иногда бывает вложенность
if (!$dealId && isset($placement['options']['ID'])) $dealId = (int)$placement['options']['ID'];
if (!$dealId && isset($placement['value']['ID'])) $dealId = (int)$placement['value']['ID'];


// Заберём данные из pars.php
$eTypeId = isset($_POST['entityTypeId']) ? (int)$_POST['entityTypeId'] : 0;
$headers = isset($_POST['headers']) && is_array($_POST['headers']) ? $_POST['headers'] : [];
$fileToken = $_POST['file_token'] ?? '';

if ($eTypeId <= 0) exit('<div>ENTITY_TYPE_ID не указан</div>');
if (empty($headers)) exit('<div>Нет заголовков файла. Сначала загрузите XLSX.</div>');
if ($fileToken === '') exit('<div>Нет токена файла</div>');

// Тянем поля смарт-процесса
$resp = CRest::call('crm.item.fields', [
        'entityTypeId' => $eTypeId,
        'useOriginalUfNames' => 'Y',
]);
if (!$resp || empty($resp['result']['fields'])) {
    exit('<div>Не удалось получить поля для entityTypeId ' . $eTypeId . '</div>');
}

$fields = $resp['result']['fields'];

// рисуем таблицу соответствий: поле CRM  select(headers)
?>
<form id="mapping-form"
      hx-post="runImport.php"
      hx-target="#step"
      hx-swap="innerHTML">

    <!-- протащим обратно entityTypeId  file_token -->
    <input type="hidden" name="entityTypeId" value="<?= htmlspecialchars($eTypeId) ?>">
    <input type="hidden" name="file_token" value="<?= htmlspecialchars($fileToken) ?>">
    <input type="hidden" name="PLACEMENT_OPTIONS"
           value="<?= htmlspecialchars($_POST['PLACEMENT_OPTIONS'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <?php if ($dealId > 0): ?>
        <!-- parentId2  связь со сделкой  -->
        <input type="hidden" name="defaults[parentId2]" value="<?= $dealId ?>">

        <!-- crm_entity UF_CRM_*  строка формата  -->
        <input type="hidden" name="defaults[crm_entity]" value="<?= 'D_'.$dealId ?>">

        <div style="margin:8px 0;color:#555">
            По умолчанию будет установлена связь со сделкой #<?= (int)$dealId; ?>:
            <code>crm_entity</code> = <?= (int)$dealId; ?>.
        </div>
    <?php endif; ?>
    <h3>Соответствие полей</h3>
    <table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;">
        <tr>
            <th>Поле CRM</th>
            <th>Колонка XLSX</th>
            <th>Тип</th>
        </tr>

        <?php foreach ($fields as $code => $meta): ?>
            <?php
            // отфильтруем поля только для ввода (readOnly пропускаем)
            if (!empty($meta['isReadOnly'])) continue;
            $title = $meta['title'] ?? $code;
            $type = $meta['type'] ?? 'string';
            ?>
            <tr>
                <td>
                    <strong><?= htmlspecialchars($title) ?></strong><br>
                    <small><?= htmlspecialchars($code) ?></small>
                </td>
                <td>
                    <label>
                        <select name="map[<?= htmlspecialchars($code) ?>]">
                            <option value="">— пропустить —</option>
                            <?php foreach ($headers as $h): ?>
                                <option value="<?= htmlspecialchars($h) ?>"><?= htmlspecialchars($h) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </td>
                <td><?= htmlspecialchars($type) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <br>
    <button type="submit">Запустить импорт</button>
</form>
