<?php
/**
 * Плагин: UniversalHtmlCache
 * Автор: Андрей Банников (https://t.me/vectorserver)
 * Описание: Кеширование HTML-страниц для указанных шаблонов MODX Revolution.
 * Поддерживает:
 * - Кеширование страниц по ID ресурса и URI (включая GET-параметры).
 * - Очистка кеша при сохранении ресурса.
 * - Поддержка пагинации и фильтров.
 */

// Включаем отображение ошибок (для отладки)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Массив ID шаблонов, для которых будет работать кеширование
$template_ids = [1, 7, 2, 3, 4, 6, 11, 12, 20];

// События, на которые будет реагировать плагин
$events = ['OnLoadWebDocument', 'OnWebPagePrerender', 'OnDocFormSave'];

// Проверяем, что текущее событие — одно из нужных
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
                file_put_contents($cacheFile, $output);
            }
            break;

        case 'OnDocFormSave':
            // При сохранении ресурса — удаляем все кеш-файлы, связанные с этим ресурсом
            // Это учитывает пагинацию и фильтры (например, ?page=2, ?filter=...), т.к. имя файла включает URI
            $files = glob($cacheDir . $resourceId . '_*.html');

            // Проходим по всем файлам и удаляем их
            foreach ($files as $file) {
                if (file_exists($file)) {
                    unlink($file); // Удаляем кеш-файл
                }
            }
            break;
    }
}
