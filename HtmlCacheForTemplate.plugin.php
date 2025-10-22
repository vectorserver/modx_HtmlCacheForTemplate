<?php
/**
 * Плагин: HtmlCacheForTemplate
 * Автор: Андрей Банников (https://t.me/vectorserver)
 * Описание: Кеширование HTML-страниц для указанных шаблонов MODX Revolution.
 * Поддерживает:
 * - Кеширование страниц по ID ресурса и URI (включая GET-параметры).
 * - Очистка кеша при сохранении ресурса.
 * - Поддержка пагинации и фильтров.
 * - Очистка кеша при сохранении шаблона.
 */

// Включаем отображение ошибок (для отладки)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Массив ID шаблонов, для которых будет работать кеширование
/** @var modX $modx */
$template_ids = $modx->getOption('template_ids_cache')? explode(",",$modx->getOption('template_ids_cache')) :[1];

$tablePrefix = $modx->config['table_prefix'];

// Проверяем, какое событие произошло
switch ($modx->event->name) {
    case 'OnLoadWebDocument':
    case 'OnWebPagePrerender':
    case 'OnDocFormSave':
        // Проверяем, что событие — одно из нужных
        $events = ['OnLoadWebDocument', 'OnWebPagePrerender', 'OnDocFormSave'];



        if (in_array($modx->event->name, $events)) {
            /**
             * Получаем ресурс в зависимости от события:
             * - При OnDocFormSave — используется переменная $resource (передаётся из события).
             * - При других событиях — $modx->resource (текущий ресурс).
             */
            $res = ($modx->event->name == 'OnDocFormSave') ? $resource : $modx->resource;

            // Получаем ID шаблона текущего ресурса
            $template = $res->get('template');

            // Проверяем, нужен ли кеш для этого шаблона
            if (!in_array($template, $template_ids)) {
                return; // Если шаблон не входит в список — выходим
            }

            // Путь к папке кеша (создаём отдельную папку для каждого шаблона)
            $cacheDir = MODX_CORE_PATH . "cache/html_pages/{$template}/";
            @mkdir($cacheDir, 0777, true); // Создаём папку, если её нет

            // Получаем ID ресурса
            $resourceId = $res->get('id');

            // Получаем URI с GET-параметрами (для поддержки пагинации и фильтров)
            $uri = $_SERVER['REQUEST_URI'] ?? $res->get('uri');

            // Генерируем имя файла: ID_ + md5(URI)
            // Это позволяет кешировать разные варианты одной страницы (например, ?page=2)
            $cacheFileName = $resourceId . '_' . md5($uri) . '.html';
            $cacheFile = $cacheDir . $cacheFileName;

            // Основная логика плагина — в зависимости от события
            switch ($modx->event->name) {
                case 'OnLoadWebDocument':
                    // Проверяем, есть ли кеш-файл
                    if (file_exists($cacheFile)) {
                        // Если файл существует — сразу отдаём его и выходим
                        readfile($cacheFile);
                        exit;
                    }
                    break;

                case 'OnWebPagePrerender':
                    // Если кеш-файл ещё не существует — сохраняем текущую страницу
                    if (!file_exists($cacheFile)) {
                        // Получаем полностью отрендеренную страницу
                        $output = &$res->_output;
                        // Сохраняем в кеш-файл
                        $output_file = str_replace(["\n", "\r", "\t"], "", $output); // Удаление \n, \r, \t
                        $output_file = preg_replace('/\s+/', ' ', $output_file);    // Замена множественных пробелов на один
                        $output_file = trim($output_file);                           // Удаление пробелов в начале и конце

                        file_put_contents($cacheFile, $output_file);
                        $date = date('Y-m-d H:i:s');
                        $modx->query("UPDATE `{$tablePrefix}site_content` SET `link_attributes` ='data-cache=\"{$date}\"' WHERE `id` = '{$resourceId}';");

                    }
                    break;

                case 'OnDocFormSave':
                    // При сохранении ресурса — удаляем все кеш-файлы, связанные с этим ресурсом
                    // Это учитывает пагинацию и фильтры (например, ?page=2, ?filter=...), т.к. имя файла включает URI
                    $files = glob($cacheDir . $resourceId . '_*.html');

                    $modx->query("UPDATE `{$tablePrefix}site_content` SET `link_attributes`='' WHERE `id` = '{$resourceId}';");

                    // Проходим по всем файлам и удаляем их
                    foreach ($files as $file) {
                        if (file_exists($file)) {
                            unlink($file); // Удаляем кеш-файл
                        }
                    }
                    break;
            }
        }
        break;

    case 'OnBeforeTempFormSave':
        // Получаем ID шаблона из события

        $template = $id;

        // Проверяем, нужен ли кеш для этого шаблона
        if (!in_array($template, $template_ids)) {
            return; // Если шаблон не входит в список — выходим
        }

        // Путь к папке кеша
        $cacheDir = MODX_CORE_PATH . "cache/html_pages/{$template}/";

        $modx->query("UPDATE `{$tablePrefix}site_content` SET  `link_attributes`='' WHERE `template` = '{$template}';");

        // Удаляем папку кеша, если она существует
        if (is_dir($cacheDir)) {
            // Рекурсивное удаление папки и файлов
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($cacheDir, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                unlink($file->getPathname());
            }

            // Удаляем саму папку
            rmdir($cacheDir);
        }
        break;
}
