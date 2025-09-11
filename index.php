<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" type="text/css" href="css/Style.css">
    <title>Импорт в смарт-процессы — панель</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<div class="container">
    <!-- STRIP ВКЛАДОК -->
    <div class="tabs" role="tablist" aria-label="Навигация">
        <button class="tab-btn" id="tab-home" role="tab" aria-controls="panel-home" aria-selected="true">Главная
        </button>
        <button class="tab-btn" id="tab-files" role="tab" aria-controls="panel-files" aria-selected="false">Загруженные
            файлы
        </button>
        <button class="tab-btn" id="tab-logs" role="tab" aria-controls="panel-logs" aria-selected="false">Логи</button>

    </div>
    <div class="page-title">
        <h1 style="margin:0;">Импорт в смарт-процессы</h1>
    </div>
    <!-- ВКЛАДКА: ГЛАВНАЯ -->
    <section class="panel" id="panel-home" role="tabpanel" aria-labelledby="tab-home" aria-hidden="false">
        <h3>Текущий пользователь Bitrix24</h3>
        <div id="name">
            <?php
            require_once(__DIR__ . '/crestcurrent.php');

            // берём результат из CRestCurrent, если есть
            $r1 = CRest::call('user.current');
            $r2 = CRestCurrent::call('user.current');

            $u = $r2['result'] ?? $r1['result'] ?? null;
            echo $u ? htmlspecialchars(($u['NAME'] ?? '') . ' ' . ($u['LAST_NAME'] ?? '')) : '<span class="muted">Пользователь не получен</span>';
            ?>
        </div>
        <h2>Данные из REQUEST последнего запроса</h2>
        <pre> <?php print_r($_REQUEST); ?></pre>
    </section>

    <?php
    // region FILES

    ?>
    <!-- ВКЛАДКА: ФАЙЛЫ -->
    <section class="panel" id="panel-files" role="tabpanel" aria-labelledby="tab-files" aria-hidden="true">
        <h2>Загруженные файлы
            <button class="btn btn-outline right" onclick="location.reload()">Обновить список</button>
        </h2>
        <p class="section-note">Источник: <code><?= htmlspecialchars(__DIR__ . '/uploads/') ?></code></p>
        <?php
        //
        function listUploadedFiles($dir)
        {
            $out = [];
            if (!is_dir($dir)) return $out;
            foreach (scandir($dir) as $entry) {
                if ($entry === '.' || $entry === '..') continue;
                $path = $dir . '/' . $entry;
                if (!is_file($path)) continue;
                $out[$entry] = @filectime($path) ?: @filemtime($path) ?: 0;
            }
            arsort($out); // по дате убыв.
            return $out;
        }

        // Папка с файлами
        $dir = rtrim(__DIR__, '/') . '/uploads';
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $dir));
            }
        }

        //  Актуальный список до возможного удаления (для белого списка)
        $filesWithDates = listUploadedFiles($dir);

        //  Обработка удаления
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['__action'] ?? '') === 'delete_files') {
            $toDelete = isset($_POST['files']) && is_array($_POST['files']) ? $_POST['files'] : [];
            $allowed = array_keys($filesWithDates); // белый список — только то, что показали
            $deleted = 0;
            $failed = 0;
            foreach ($toDelete as $name) {
                $base = basename($name);                 // защита от traversal
                if (!in_array($base, $allowed, true)) {
                    $failed++;
                    continue;
                }
                $path = $dir . '/' . $base;
                if (is_file($path) && @unlink($path)) {
                    $deleted++;
                } else {
                    $failed++;
                }
            }
            echo '<div style="margin:8px 0;padding:8px;border:1px solid #e5e5e5;background:#f7fff7">'
                    . 'Удалено: <strong>' . $deleted . '</strong>'
                    . ($failed ? ' | Не удалось: <strong>' . $failed . '</strong>' : '')
                    . '</div>';
            // перечитываем список после удаления
            $filesWithDates = listUploadedFiles($dir);
        }

        //  Вывод
        if (!$filesWithDates) {
            echo '<p class="muted">Файлы не найдены.</p>';
        } else {
            // утилита размера
            $human = function ($bytes) {
                $u = ['Б', 'КБ', 'МБ', 'ГБ', 'ТБ'];
                $i = 0;
                while ($bytes >= 1024 && $i < count($u) - 1) {
                    $bytes /= 1024;
                    $i++;
                }
                return sprintf('%.1f %s', $bytes, $u[$i]);
            };
            ?>
            <!-- форма удаления -->
            <form method="post" onsubmit="return confirm('Удалить отмеченные файлы?');">
                <div style="margin-top:10px; display:flex; gap:8px;">
                    <button type="submit">Удалить отмеченные</button>
                    <button type="button"
                            onclick="document.querySelectorAll('.file-chk').forEach(cb=>cb.checked=false)">
                        Снять выделение
                    </button>
                </div>
                <input type="hidden" name="__action" value="delete_files">
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th style="width:36px;">
                                <input type="checkbox" id="check-all" title="Выделить все">
                            </th>
                            <th>Имя файла</th>
                            <th>Размер</th>
                            <th>Дата</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($filesWithDates as $file => $ts): ?>
                            <?php
                            $path = $dir . '/' . $file;
                            $url = 'uploads/' . rawurlencode($file);
                            $size = is_file($path) ? filesize($path) : 0;
                            ?>
                            <tr>
                                <td>
                                    <label>
                                        <input class="file-chk" type="checkbox" name="files[]"
                                               value="<?= htmlspecialchars($file) ?>">
                                    </label>
                                </td>
                                <td><a href="<?= htmlspecialchars($url) ?>" download><?= htmlspecialchars($file) ?></a>
                                </td>
                                <td><?= htmlspecialchars($human($size)) ?></td>
                                <td><?= htmlspecialchars(date('Y-m-d H:i:s', $ts)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
            <script>
                // чекбокс выделить всё
                (function () {
                    var master = document.getElementById('check-all');
                    if (!master) return;
                    master.addEventListener('change', function () {
                        document.querySelectorAll('.file-chk').forEach(function (cb) {
                            cb.checked = master.checked;
                        });
                    });
                })();
            </script>
            <?php
        }
        ?>
    </section>
    <?php
    // region FILES

    ?>
    <?php
    // region LOGS
    //#Region LOGS
    ?>
    <!-- ВКЛАДКА: ЛОГИ -->
    <section class="panel" id="panel-logs" role="tabpanel" aria-labelledby="tab-logs" aria-hidden="true">
        <h2>Логи</h2>
        <?php
        //  Определяем папку logs
        $candidates = [
                __DIR__ . '/logs',
                dirname(__DIR__) . '/logs',
                rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/') . '/logs',
        ];
        $logsRoot = null;
        foreach ($candidates as $p) {
            if (is_dir($p)) {
                $logsRoot = $p;
                break;
            }
        }
        if ($logsRoot === null) {
            $logsRoot = __DIR__ . '/logs';
        }

        // URL префикс ссылки доступны только если logs под DOCUMENT_ROOT
        $logsUrlPrefix = 'logs/';

        // Удаление отмеченных файлов
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['__action'] ?? '') === 'delete_logs') {
            $baseReal = realpath($logsRoot);
            $deleted = 0;
            $failed = 0;
            $req = isset($_POST['files']) && is_array($_POST['files']) ? $_POST['files'] : [];
            foreach ($req as $rel) {
                $rel = ltrim($rel, '/');                      // относительный путь от logs/
                $full = $logsRoot . '/' . $rel;
                $real = realpath($full);
                if ($real && $baseReal && strpos($real, $baseReal . DIRECTORY_SEPARATOR) === 0 && is_file($real)) {
                    @unlink($real) ? $deleted++ : $failed++;
                } else {
                    $failed++;
                }
            }
            echo '<div style="margin:8px 0;padding:8px;border:1px solid #e5e5e5;background:#f7fff7">'
                    . 'Удалено файлов: <strong>' . $deleted . '</strong>'
                    . ($failed ? ' | Не удалось: <strong>' . $failed . '</strong>' : '')
                    . '</div>';
        }

        // Удаление пустых папок
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['__action'] ?? '') === 'delete_empty_log_dirs') {
            $baseReal = realpath($logsRoot);
            $removed = 0;
            $failed = 0;

            // функция: папка пуста, если нет файлов внутри и на 1 уровень глубже
            $isEmptyLogDir = function (string $dir): bool {
                $lvl1 = array_diff(@scandir($dir) ?: [], ['.', '..']);
                foreach ($lvl1 as $e1) {
                    $p1 = $dir . '/' . $e1;
                    if (is_file($p1)) return false;
                    if (is_dir($p1)) {
                        $lvl2 = array_diff(@scandir($p1) ?: [], ['.', '..']);
                        foreach ($lvl2 as $e2) {
                            if (is_file($p1 . '/' . $e2)) return false;
                        }
                    }
                }
                return true;
            };

            // обходим только директории первого уровня
            foreach (array_diff(@scandir($logsRoot) ?: [], ['.', '..']) as $d) {
                $dir = $logsRoot . '/' . $d;
                if (!is_dir($dir)) continue;
                $real = realpath($dir);
                if (!$real || !$baseReal || strpos($real, $baseReal . DIRECTORY_SEPARATOR) !== 0) {
                    $failed++;
                    continue;
                }

                if ($isEmptyLogDir($dir)) {
                    // сначала удалим пустые подпапки (1 уровень), затем саму папку
                    foreach (array_diff(@scandir($dir) ?: [], ['.', '..']) as $e1) {
                        $p1 = $dir . '/' . $e1;
                        if (is_dir($p1)) {
                            @rmdir($p1);
                        } // безопасно: папка пуста по условию
                    }
                    @rmdir($dir) ? $removed++ : $failed++;
                }
            }

            echo '<div style="margin:8px 0;padding:8px;border:1px solid #e5e5e5;background:#fff7f7">'
                    . 'Удалено пустых папок: <strong>' . $removed . '</strong>'
                    . ($failed ? ' | Не удалось: <strong>' . $failed . '</strong>' : '')
                    . '</div>';
        }

        if (!is_dir($logsRoot)) {
            echo '<p class="muted">Папка логов не найдена: <code>' . htmlspecialchars($logsRoot) . '</code></p>';
        } else {
            // Список верхнего уровня: файлы и папки-«даты»
            $entries = @scandir($logsRoot) ?: [];
            $rootFiles = [];  // [rel => ts]
            $dateDirs = [];  // [['name'=>dir,'time'=>ts], ...]

            foreach ($entries as $e) {
                if ($e === '.' || $e === '..') continue;
                $p = $logsRoot . '/' . $e;
                if (is_file($p)) {
                    $rootFiles[$e] = @filectime($p) ?: @filemtime($p) ?: 0;
                } elseif (is_dir($p)) {
                    $dateDirs[] = ['name' => $e, 'time' => @filemtime($p) ?: 0];
                }
            }
            arsort($rootFiles);
            usort($dateDirs, function ($a, $b) {
                return $b['time'] <=> $a['time'];
            });

            // утилита размера
            $human = function ($bytes) {
                $u = ['Б', 'КБ', 'МБ', 'ГБ', 'ТБ'];
                $i = 0;
                while ($bytes >= 1024 && $i < count($u) - 1) {
                    $bytes /= 1024;
                    $i++;
                }
                return sprintf('%.1f %s', $bytes, $u[$i]);
            };

            //  Панель управления папками (кнопка удалить пустые)
            ?>
            <form method="post" style="margin:10px 0" onsubmit="return confirm('Удалить все ПУСТЫЕ папки логов?');">
                <input type="hidden" name="__action" value="delete_empty_log_dirs">
                <button type="submit">Удалить пустые папки</button>
            </form>
        <?php

        //Таблица файлов на верхнем уровне logs/
        if ($rootFiles) {
        echo '<h3>Файлы (верхний уровень)</h3>';
        ?>

            <form method="post" onsubmit="return confirm('Удалить отмеченные файлы?');">
                <input type="hidden" name="__action" value="delete_logs">
                <table>
                    <thead>
                    <tr>
                        <th style="width:36px;"><input type="checkbox" id="check-all-root" title="Выделить все"></th>
                        <th>Имя файла</th>
                        <th>Размер</th>
                        <th>Дата создания</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rootFiles as $name => $ts): ?>
                        <?php
                        $full = $logsRoot . '/' . $name;
                        $url = $logsUrlPrefix ? $logsUrlPrefix . rawurlencode($name) : null;
                        $sz = is_file($full) ? filesize($full) : 0;
                        ?>
                        <tr>
                            <td><label>
                                    <input class="file-chk-root" type="checkbox" name="files[]"
                                           value="<?= htmlspecialchars($name) ?>">
                                </label>
                            </td>
                            <td>
                                <?php if ($url): ?>
                                    <a href="<?= htmlspecialchars($url) ?>" download><?= htmlspecialchars($name) ?></a>
                                <?php else: ?>
                                    <?= htmlspecialchars($name) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($human($sz)) ?></td>
                            <td><?= htmlspecialchars(date('Y-m-d H:i:s', $ts)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="margin-top:10px; display:flex; gap:8px;">
                    <button type="submit">Удалить отмеченные</button>
                    <button type="button"
                            onclick="document.querySelectorAll('.file-chk-root').forEach(cb=>cb.checked=false)">Снять
                        выделение
                    </button>
                </div>
            </form>
            <script>
                (function () {
                    var master = document.getElementById('check-all-root');
                    if (master) master.addEventListener('change', function () {
                        document.querySelectorAll('.file-chk-root').forEach(cb => cb.checked = master.checked);
                    });
                })();
            </script>
        <?php
        } else {
            echo '<p class="muted">На верхнем уровне файлов нет.</p>';
        }

        // Подвкладки по папкам-датам (с чекбоксами и удалением)
        if ($dateDirs) {
        echo '<div class="tabs" role="tablist" aria-label="Даты логов">';
        $first = true;
        foreach ($dateDirs as $d) {
            $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $d['name']);
            echo '<button class="tab-btn sub" role="tab" id="tab-date-' . $safe . '" aria-controls="panel-date-' . $safe . '" aria-selected="' . ($first ? 'true' : 'false') . '">'
                    . htmlspecialchars($d['name']) . '</button>';
            $first = false;
        }
        echo '</div>';

        $first = true;
        foreach ($dateDirs

        as $d) {
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $d['name']);
        $dateDir = $logsRoot . '/' . $d['name'];

        echo '<section class="panel sub" id="panel-date-' . $safe . '" role="tabpanel" aria-hidden="' . ($first ? 'false' : 'true') . '">';
        echo '<h3>Папка: ' . htmlspecialchars($d['name']) . '</h3>';

        // собираем файлы на 1 уровень вглубь
        $filesWithDates = []; // ключ — относительный путь от logsRoot (date/sub/file.log или date/file.log)
        $level1 = array_diff(@scandir($dateDir) ?: [], ['.', '..']);
        foreach ($level1 as $e1) {
            $p1 = $dateDir . '/' . $e1;
            if (is_file($p1)) {
                $filesWithDates[$d['name'] . '/' . $e1] = @filectime($p1) ?: @filemtime($p1) ?: 0;
            } elseif (is_dir($p1)) {
                $level2 = array_diff(@scandir($p1) ?: [], ['.', '..']);
                foreach ($level2 as $e2) {
                    $p2 = $p1 . '/' . $e2;
                    if (is_file($p2)) {
                        $filesWithDates[$d['name'] . '/' . $e1 . '/' . $e2] = @filectime($p2) ?: @filemtime($p2) ?: 0;
                    }
                }
            }
        }
        arsort($filesWithDates);

        if (!$filesWithDates) {
            echo '<p class="muted">Файлов нет.</p>';
        } else {
        ?>
            <form method="post" onsubmit="return confirm('Удалить отмеченные файлы?');">
                <input type="hidden" name="__action" value="delete_logs">
                <div style="margin-bottom:10px; display:flex; gap:8px;">
                    <button type="submit">Удалить отмеченные</button>
                    <button type="button"
                            onclick="document.querySelectorAll('.file-chk-<?= htmlspecialchars($safe) ?>').forEach(cb=>cb.checked=false)">
                        Снять выделение
                    </button>
                </div>

                <table>
                    <thead>
                    <tr>
                        <th style="width:36px;"><input type="checkbox" id="check-all-<?= htmlspecialchars($safe) ?>"
                                                       title="Выделить все"></th>
                        <th>Путь</th>
                        <th>Размер</th>
                        <th>Дата создания</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($filesWithDates as $rel => $tsFile): ?>
                        <?php
                        $full = $logsRoot . '/' . $rel;
                        $sz = is_file($full) ? filesize($full) : 0;
                        $url = null;
                        if ($logsUrlPrefix !== null) {
                            $parts = array_map('rawurlencode', explode('/', $rel));
                            $url = $logsUrlPrefix . implode('/', $parts);
                        }
                        ?>
                        <tr>
                            <td><label>
                                    <input class="file-chk-<?= htmlspecialchars($safe) ?>" type="checkbox"
                                           name="files[]"
                                           value="<?= htmlspecialchars($rel) ?>">
                                </label>
                            </td>
                            <td>
                                <?php if ($url): ?>
                                    <a href="<?= htmlspecialchars($url) ?>" download><?= htmlspecialchars($rel) ?></a>
                                <?php else: ?>
                                    <?= htmlspecialchars($rel) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($human($sz)) ?></td>
                            <td><?= htmlspecialchars(date('Y-m-d H:i:s', $tsFile)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="margin-top:10px; display:flex; gap:8px;">
                    <button type="submit">Удалить отмеченные</button>
                    <button type="button"
                            onclick="document.querySelectorAll('.file-chk-<?= htmlspecialchars($safe) ?>').forEach(cb=>cb.checked=false)">
                        Снять выделение
                    </button>
                </div>
            </form>
            <script>
                (function () {
                    var master = document.getElementById('check-all-<?= htmlspecialchars($safe) ?>');
                    if (master) master.addEventListener('change', function () {
                        document.querySelectorAll('.file-chk-<?= htmlspecialchars($safe) ?>').forEach(cb => cb.checked = master.checked);
                    });
                })();
            </script>
            <?php
        }

            echo '</section>';
            $first = false;
        }
        } else {
            echo '<p class="muted">Папок с датами нет.</p>';
        }

            echo '<p class="muted" style="margin-top:10px;">Читаю из: <code>' . htmlspecialchars(realpath($logsRoot) ?: $logsRoot) . '</code></p>';
        }
        ?>
    </section>


    <?php
    // endregion LOGS

    ?>

    <script>
        // Переключатель для КАЖДОГО tablist отдельно (внешний и вложенные)
        document.querySelectorAll('[role="tablist"]').forEach(tablist => {
            const tabs = tablist.querySelectorAll('[role="tab"]');
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    // деактивируем все табы и их панели внутри ЭТОГО tablist
                    tabs.forEach(t => {
                        t.setAttribute('aria-selected', 'false');
                        const pid = t.getAttribute('aria-controls');
                        const panel = document.getElementById(pid);
                        if (panel) panel.setAttribute('aria-hidden', 'true');
                    });
                    // активируем текущий
                    tab.setAttribute('aria-selected', 'true');
                    const panel = document.getElementById(tab.getAttribute('aria-controls'));
                    if (panel) panel.setAttribute('aria-hidden', 'false');
                });
            });
        });
    </script>
</div>
</body>
</html>
