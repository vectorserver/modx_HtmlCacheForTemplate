<h5>Генерация кеша страниц, для шаблонов [[++template_ids_cache]]</h5>
<pre id="items_log"></pre>

<script>
  {set $items = '!pdoPage' | snippet : [
    'parents' => '0',
          'return' => 'data',
          'limit'=>100,
          'where'=>['link_attributes:NOT LIKE'=>'%data-cache%'],
          'templates' => '[[++template_ids_cache]]',
          'ajaxMode' => 'default',
          'sortby' => 'editedon',
          'sortdir' => 'DESC',
          'strictMode'=>0
  ]}

  {set $data = ['items'=>$items,'pageCount'=>'pageCount'| placeholder]}

  let data = {$data | toJSON}

  let currentPage = {$.get.page ? : 1};

  const logElement = document.getElementById('items_log');

  function log(message) {
    // Используем innerHTML вместо textContent, чтобы отображать HTML
    logElement.innerHTML += message + '<br>'; // <br> для переноса строки
    logElement.scrollTop = logElement.scrollHeight; // прокрутка вниз
  }

  async function startCaching() {
    console.log('data', currentPage, data.items.length);

    if (!data.items.length) {
      log('Кеширование закончено!');
      return false;
    }

    log(`Начинаем кеширование ${ data.items.length } URI на странице ${ currentPage } из ${ data.pageCount }`);

    // Проходим по каждому uri и делаем fetch
    for (let i = 0; i < data.items.length; i++) {
      const item = data.items[i];
      const fullUrl = '{$_modx->config.site_url}' + item.uri;
      console.log(fullUrl);

      try {
        const startTime = Date.now(); // Засекаем время начала
        const response = await fetch(fullUrl);
        const endTime = Date.now(); // Засекаем время окончания
        const duration = endTime - startTime; // Разница — время ответа


        if (response.ok) {

          if (duration < 2000) {
            log(`⚠️ Уже закеширован: ${ fullUrl } (${ duration }ms) Шаблон ${ item.template } #ID <a target="_blank" href="/manager/?a=resource/update&id=${ item.id }">${ item.id }</a>`);
          } else{
            log(`✅ Кеш создан: ${ fullUrl } (${ duration }ms) Шаблон ${ item.template }  #ID <a target="_blank" href="/manager/?a=resource/update&id=${ item.id }">${ item.id }</a>`  );
          }

        } else {
          log(`❌ Ошибка при кешировании: ${ fullUrl } — ${ response.status } (${ duration }ms)`);
        }
      } catch (err) {
        const endTime = Date.now();
        const duration = endTime - startTime; // На случай ошибки
        log(`❌ Ошибка сети: ${ fullUrl } (${ duration }ms)`, err);
      }
    }


    // После завершения кеширования текущей страницы — редирект на следующую
    const nextPage = currentPage + 1;
    log(`Переход на страницу ${ nextPage }...`);
    window.location.href = `{$_modx->config.site_url}/{$_modx->resource.uri}/?page=${ nextPage }`;

  }

  document.addEventListener('DOMContentLoaded', function () {
    startCaching();
  });
</script>
