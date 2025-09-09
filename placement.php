<?php
require_once(__DIR__ . '/crestcurrent.php');
require_once(__DIR__ . '/crest.php'); ?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Импорт в смарт-процесс</title>
    <!-- HTMX -->
    <script src="lib/htmx.min.js"></script>
    <style>
        /* минимальная сетка и аккуратные элементы */
        body {
            font: 14px/1.4 system-ui, Segoe UI, Roboto, Arial, sans-serif;
            margin: 20px;
        }

        .row {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }

        label {
            font-weight: 600;
        }

        input[type="number"], input[type="text"] {
            padding: 6px 8px;
            width: 200px;
        }

        button {
            padding: 6px 10px;
            cursor: pointer;
        }

        #step {
            margin-top: 18px;
        }

        .dropzone {
            border: 2px dashed #bbb;
            border-radius: 8px;
            padding: 18px;
            text-align: center;
            color: #666;
        }

        .dropzone.drag {
            border-color: #333;
            color: #111;
            background: #fafafa;
        }

        .hint {
            color: #777;
            font-size: 12px;
        }
    </style>
</head>
<body>
<?php
// placement.php
// 1) Получаем PLACEMENT_OPTIONS от Битрикс
$placement_options_raw = $_REQUEST['PLACEMENT_OPTIONS'] ?? '';
// Если пришло массивом — приведём к JSON
if ($placement_options_raw === '' && !empty($_REQUEST['PLACEMENT_OPTIONS'])) {
    $placement_options_raw = json_encode($_REQUEST['PLACEMENT_OPTIONS'], JSON_UNESCAPED_UNICODE);
}
?>

<!-- ФОРМА №1. Только ОДНА: entityTypeId + файл .xlsx -->
<form id="upload-form"
      class="uploader"
      method="post"
      enctype="multipart/form-data"
      hx-post="pars.php"
      hx-target="#step"
      hx-swap="innerHTML"
      hx-encoding="multipart/form-data">

    <div class="row">
        <input type="hidden" id="placement_opts" name="PLACEMENT_OPTIONS"
               value="<?= htmlspecialchars($placement_options_raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

        <div id="sp-select"
             hx-post="linkedSP.php"
             hx-trigger="load"
             hx-target="this"
             hx-swap="innerHTML"
             hx-include="#placement_opts"
        >
        </div>
    </div>
    <div id="state"></div>

    <div class="row">
        <!-- ОДИН input[type=file] -->
        <input type="file" id="xlsx" name="xlsx" accept=".xlsx" required>
        <button style="display: none" type="submit">Загрузить и разобрать файл</button>
        <button type="button" id="reset-file-btn">Сбросить файл</button>
    </div>

    <!-- Необязательная drag&drop зона для удобства -->
    <div id="dz" class="dropzone">
        Перетащите .xlsx сюда или выберите файл кнопкой выше
        <div class="hint">Поддерживается только .xlsx, первая строка — заголовки</div>
    </div>
</form>

<!-- Сюда HTMX будет подставлять шаги (prevue → маппинг → импорт) -->
<div id="step"></div>

<script>
    // Автосабмит при выборе файла (если хочется быстрее)
    const form = document.getElementById('upload-form');
    const file = document.getElementById('xlsx');
    file.addEventListener('change', () => {
        if (file.files.length) form.requestSubmit();
    });

    // Простая drag&drop реализация без зависимостей
    const dz = document.getElementById('dz');
    ;['dragenter', 'dragover'].forEach(ev => dz.addEventListener(ev, e => {
        e.preventDefault();
        e.stopPropagation();
        dz.classList.add('drag');
    }));
    ;['dragleave', 'drop'].forEach(ev => dz.addEventListener(ev, e => {
        e.preventDefault();
        e.stopPropagation();
        dz.classList.remove('drag');
    }));
    dz.addEventListener('drop', e => {
        const f = [...e.dataTransfer.files].find(f => /\.xlsx$/i.test(f.name));
        if (f) {
            file.files = new DataTransfer().files;
            const dt = new DataTransfer();
            dt.items.add(f);
            file.files = dt.files;
            form.requestSubmit();
        }
    });

</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form  = document.getElementById('upload-form');
        let fileInp = document.getElementById('xlsx');

        // если у тебя автосабмит при выборе файла — вернём этот хэндлер после клонирования
        function attachAutoSubmit(inp) {
            inp.addEventListener('change', function () {
                if (inp.files && inp.files.length) form.requestSubmit();
            });
        }
        attachAutoSubmit(fileInp);

        document.getElementById('reset-file-btn').addEventListener('click', function (e) {
            e.preventDefault();

            // 1) Попытка простого очищения
            try { fileInp.value = ''; } catch (_) {}

            // 2) Гарантированный вариант: заменить на клон (чтобы потом снова выбрать тот же файл)
            const clone = fileInp.cloneNode(true);   // копирует атрибуты (id, name, accept, required)
            fileInp.replaceWith(clone);
            fileInp = clone;                          // обновляем ссылку
            attachAutoSubmit(fileInp);                // вернём автосабмит

            // 3) Почистим состояние и результаты
            const state = document.getElementById('state');
            if (state) state.innerHTML = '';          // скрытые headers[] + file_token
            const step  = document.getElementById('step');
            if (step)  step.innerHTML  = '';          // превью/маппинг/результаты

            // 4) (если есть) уберём подсветку дропа
            const dz = document.getElementById('dz');
            if (dz) dz.classList.remove('drag');

            // 5) Фокус на новый инпут
            fileInp.focus();
        });
    });
</script>

</body>
</html>
