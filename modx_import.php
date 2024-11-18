<?php

use yml\yml_import;

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

define('MODX_API_MODE', true);
require_once '../../../index.php';
require_once __DIR__ . "/yml_import.php";

/**
 * @property \modX $modx
 */
class modx_import
{
    private $yml_url;
    public array $config;

    /**
     * @param modX $modx
     * @param $yml_url
     * @param array $config
     */
    public function __construct(&$modx, $yml_url, $config = [])
    {
        $this->modx = $modx;
        $this->modx->getService('error', 'error.modError');
        $this->modx->setLogLevel(modX::LOG_LEVEL_FATAL);
        $this->modx->setLogTarget(XPDO_CLI_MODE ? 'ECHO' : 'HTML');

        $this->yml_url = $yml_url;
        $this->yml = new yml_import($this->yml_url);
        $this->yml->yml_reader();

        $default = [];
        $this->config = array_merge($config, $default);
    }

    public function up_category()
    {

        foreach ($this->yml->categoriesArray as $category) {


            /** @var msCategory $res */
            /** @var modTemplateVarResource $tv */
            $res = $this->modx->getObject('msCategory', [
                'pagetitle' => $category['name']
            ]);


            if (!$res) {
                $res = $this->modx->newObject('msCategory');
                $res->set('pagetitle', $category['name']);
                $res->set('alias', $res->cleanAlias($category['name']));
                $res->set('template', $this->config['cat_template']);
                $res->set('parent', $this->config['root_parent']);
                $res->set('deleted', 1);
                $res->set('deletedby', 7777);
                $res->set('published', 1);
                $res->save();

                $res->setTVValue($this->config['tv_ymlID'], $category['ymlID']);
            }

            $res->set('deleted', 0);


            if ($category["ymlID_parent"]) {

                $tv = $this->modx->getObject('modTemplateVarResource', ['tmplvarid' => $this->config['tv_ymlID'], 'value' => $category["ymlID_parent"]]);
                if ($tv) {
                    $res->set('parent', $tv->contentid);
                    var_dump($tv->contentid, $res->id);
                }
            }

            $res->save();


        }

    }

    public function up_options()
    {
        foreach ($this->yml->optsArray as $option_name => $value) {


            /** @var msOption $getOptionProd */
            $getOptionProd = $this->modx->getObject('msOption', ['caption' => $option_name, 'category' => $this->config['category_options']]);

            if (!$getOptionProd) {
                $tmp = $this->modx->newObject('modResource');
                $addOpt = [
                    'caption' => $option_name,
                    'category' => $this->config['category_options'],
                    'type' => 'combo-options',
                    'description' => 'Например: ' . $value,
                    'key' => str_replace('-', '_', $tmp->cleanAlias($option_name)),
                ];
                $getOptionProd = $this->modx->newObject('msOption', $addOpt);
                $getOptionProd->save();
            }
            $getOptionProd->set('description', 'Например: ' . $value);
            $getOptionProd->save();

            var_dump($getOptionProd->toArray());

        }
    }

    public function up_products()
    {

        // Получаем значение offset из GET-параметров, если его нет - устанавливаем 0
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

        // Количество элементов для обработки за раз
        $limit = $this->config['limit_iteration'];

        // Получаем часть массива offersArray в зависимости от offset
        $offersChunk = array_slice($this->yml->offersArray, $offset, $limit);


        foreach ($offersChunk as $offer) {
            /** @var msProduct $res */
            /** @var modTemplateVarResource $tv */
            /** @var msVendor $vendor */
            /** @var msOption $options */


            $tv = $this->modx->getObject('modTemplateVarResource', ['tmplvarid' => $this->config['tv_ymlID'], 'value' => $offer["ymlID"]]);
            if (!$tv) {
                $res = $this->modx->newObject('msProduct');
                $res->set('pagetitle', $offer['name']);
                $res->set('alias', $res->cleanAlias($offer['name']));
                $res->set('template', $this->config['product_template']);
                $res->set('parent', $this->config['root_parent']);
                $res->set('deleted', 1);
                $res->set('show_in_tree', 0);
                $res->set('hidemenu', 1);
                $res->set('deletedby', 7777);
                $res->save();

                $res->setTVValue($this->config['tv_ymlID'], $offer['ymlID']);

                var_dump("NEW: {$offer['name']}<br>");
            } else {


                $res = $this->modx->getObject('msProduct', $tv->contentid);

                $res->set('deleted', 0);
                $res->set('hidemenu', 1);
                $res->set('published', 1);
                $res->set('content', strip_tags($offer['description'], '<br><p><strong><table><tr><th><td>'));


                //parent
                $tv_parent = $this->modx->getObject('modTemplateVarResource', ['tmplvarid' => $this->config['tv_ymlID'], 'value' => $offer["ymlID_parent"]]);
                if ($tv_parent) {
                    $res->set('parent', $tv_parent->contentid);

                }

                //vendor
                $vendor = $this->modx->getObject('msVendor', array('name' => $offer['vendor']));
                if (!$vendor) {
                    $vendor = $this->modx->newObject('msVendor');
                    $vendor->set('name', $offer['vendor']);
                    $vendor->save();
                }
                if ($vendor->id) $res->set('vendor', $vendor->id);




                //$options
                $options = [];
                foreach ($offer["opts"] as $option_name => $option_value) {

                    /** @var msOption $getOptionProd */
                    $getOptionProd = $this->modx->getObject('msOption', ['caption' => $option_name, 'category' => $this->config['category_options']]);

                    if ($getOptionProd->key) {
                        $options[$getOptionProd->key] = [$option_value];
                    }

                }

                $res->set('options', $options);

                //Data
                $res->set('color', [$offer["opts"]["Цвет изделия"]]);
                $res->set('article', $offer['vendorCode']);
                $res->set('weight', $offer['weight']);


                //picture

                if ($offer["picture"] && !$res->Data->image) {

                    foreach ($offer["picture"] as $picture) {
                        // Получаем имя файла из URL
                        $filename = basename($picture); // Извлекаем имя файла
                        $filepath = MODX_BASE_PATH . '/assets/api/yandex_yml/tmp_images/' . $filename; // Полный путь

                        // Проверяем, существует ли файл
                        if (!file_exists($filepath)) {
                            // Если файл не существует, загружаем его
                            // Здесь ваш код для загрузки файла
                            $filepath = $this->downloadImage($picture, $filepath);
                        }
                        if ($filepath){
                            $data = [
                                'id' => $res->id,
                                'file' => $filepath,
                            ];

                            $response = $this->modx->runProcessor('gallery/upload', $data, [
                                'processors_path' => MODX_CORE_PATH . 'components/minishop2/processors/mgr/',
                            ]);
                        }


                    }

                }


                //price
                $rub_price = round($offer['price'] * $this->config['cource_USD'],2);
                $res->set('price', $rub_price);

                $res->save();
                $res->setTVValue('usd_price',$offer['price']);
                $res->setTVValue('rub_price',$rub_price);


                var_dump("UP: {$offer['name']} - <a target='_blank' href='https://шукшурин.рф/manager/index.php?a=resource/update&id={$res->id}'>{$res->id}</a><br>");
            }
        }

        // Проверяем, остались ли еще элементы для обработки
        if ($offset + $limit < count($this->yml->offersArray)) {
            // Увеличиваем offset для следующего запроса
            $nextOffset = $offset + $limit;

            // Получаем текущий URL без параметров
            $currentUrl = strtok($_SERVER["REQUEST_URI"], '?');

            // Формируем новый URL с обновленным offset
            echo '<a id="clickMe" href="' . $currentUrl . '?offset=' . $nextOffset . '">Далее</a>';
            echo "<script>document.addEventListener('DOMContentLoaded', function() {
            var clickMeElement = document.getElementById('clickMe');
            clickMeElement.click();
            console.log('Click Offset: $nextOffset');
    
    
});</script>";
        } else {
            echo "Все товары обработаны!";
            $this->modx->cacheManager->refresh();
        }

    }


    // Пример функции для загрузки изображения
    private function downloadImage($url, $filepath)
    {
        // Здесь необходимо использовать cURL или file_get_contents для загрузки файла
        // Пример с использованием file_get_contents
        $imageData = file_get_contents($url);

        if ($imageData !== false) {
            file_put_contents($filepath, $imageData);
            return $filepath;
        } else {
            return false;
            //exit("Ошибка при загрузке файла $url.<br>");
        }
    }

}

$config = [
    'root_parent' => 14,
    'cat_template' => 3,
    'category_options' => 18,
    'product_template' => 4,
    'tv_ymlID' => 27,
    'limit_iteration' => 5,
    'cource_USD' => 99.93, //99,93 Российский рубль
];

$run = new modx_import($modx, "https://www.boyard.biz/storage/export_product_yandex.xml?v=1", $config);

//$run->up_category();
//$run->up_options();
$run->up_products();

