<?php
require_once __DIR__ . '/crest.php';


$installResult = CRest::installApp();

//логирование /appFolder/install.log
function _log($msg)
{
    $f = __DIR__ . '/install.log';
    @file_put_contents($f, '[' . date('c') . '] ' . (is_scalar($msg) ? $msg : print_r($msg, true)) . PHP_EOL, FILE_APPEND);
}

// формируем URL обработчика плейсмента
$isHttps = (
        (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
        (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] === '443') ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
);
$scheme = $isHttps ? 'https' : 'http';
$host = $_SERVER['SERVER_NAME'] ?? 'localhost';
$port = $_SERVER['SERVER_PORT'] ?? null;
$portPart = in_array((string)$port, ['80', '443'], true) ? '' : ':' . $port;
$basePath = str_replace($_SERVER['DOCUMENT_ROOT'], '', __DIR__);
$handlerBackUrl = $scheme . '://' . $host . $portPart . $basePath . '/placement.php';

/**
 *  узнаём текущее состояние через placement.get
 * если уже привязан ТОЧНО тот же HANDLER  пропускаем
 * если другой — unbind
 */
$bindOk = false;
$get = CRest::call('placement.get', ['PLACEMENT' => 'CRM_DEAL_DETAIL_TAB']);
_log(['placement.get' => $get]);

$currentHandler = null;
if (!isset($get['error']) && !empty($get['result']) && is_array($get['result'])) {
    foreach ($get['result'] as $row) {
        if (!empty($row['HANDLER'])) {
            $currentHandler = $row['HANDLER'];
            break;
        }
    }
}

if ($currentHandler && rtrim($currentHandler, '/') === rtrim($handlerBackUrl, '/')) {
    // уже привязано к нашему обработчику — отлично
    $bindOk = true;
    _log('placement already bound to our handler → skip bind');
} else {
    // снимаем всё на этом placement (на всякий случай)
    $un = CRest::call('placement.unbind', ['PLACEMENT' => 'CRM_DEAL_DETAIL_TAB']);
    _log(['placement.unbind' => $un]);

    // пробуем привязать
    $bind = CRest::call('placement.bind', [
            'PLACEMENT' => 'CRM_DEAL_DETAIL_TAB',
            'HANDLER' => $handlerBackUrl,
            'TITLE' => 'Импорт в смарт-процессы',
    ]);
    _log(['placement.bind' => $bind]);

    if (isset($bind['error'])) {
        $desc = $bind['error_description'] ?? '';
        // если “already binded” (бывает при гонке запросов) — не считаем ошибкой
        if ($bind['error'] === 'ERROR_CORE' && stripos($desc, 'already binded') !== false) {
            $bindOk = true;
        } else {
            $bindOk = false; // залогировано выше
        }
    } else {
        $bindOk = !empty($bind['result']);
    }
}

if (isset($installResult['rest_only']) && $installResult['rest_only'] === false):
    ?>
    <!doctype html>
    <html lang="ru">
    <head>
        <meta charset="utf-8">
        <script src="https://api.bitrix24.com/api/v1/"></script>
    </head>
    <body style="font:14px/1.4 sans-serif;padding:16px">
    <?php
    // Успех установки определяется installApp; привязка вспомогательная
    $installOk = (!empty($installResult['install']) && $installResult['install'] === true);
    if ($installOk) :
        ?>
        <div>installation has been finished</div>
        <script>
            BX24.init(function () {
                BX24.installFinish();
            });
        </script>
    <?php else: ?>
        <div>installation error</div>
    <?php endif; ?>
    </body>
    </html>
<?php
endif;
