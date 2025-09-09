<?php
require_once(__DIR__ . '/crestcurrent.php');
require_once(__DIR__ . '/crest.php');
//для file_exist требуется оригинальное имя файл
$original_filename = $_FILES["file"]["name"];
$tmpPath = $_FILES['file']['tmp_name'];    // временный путь во время загрузки самого запроса

//Проверяем существует ли файл
if (!empty($_FILES['file'])) {
    move_uploaded_file($tmpPath, 'temp/' . $original_filename);
//    echo "Файл принят";
//    echo '<br>';
}

//Проверяем существует ли файл в папке
if (file_exists(__DIR__ . '/temp/' . $original_filename)) {

    //Создаем переменную, чтобы в парсере к ней обращаться
    $pathFile = __DIR__ . '/temp/' . $original_filename;

    //При успехе подключаем парсер
    require 'pars.php';

} else {
    echo "Ошибка существования файла";
}

?>
