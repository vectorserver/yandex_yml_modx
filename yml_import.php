<?php

namespace yml;

class yml_import
{
    public $cacheLifetime = 10 * 60 * 60;
    public $localFile = __DIR__ . '/export_product_yandex.xml';
    public array $categoriesArray = [];
    public array $offersArray = [];
    public array $optsArray = [];

    public function __construct($xmlUrl)
    {
        $this->xmlUrl = $xmlUrl;
        $params_url = parse_url($this->xmlUrl);
        $this->uid = crc32("{$params_url['host']}{$params_url['path']}");


    }

    public function yml_reader ()
    {
        // Проверяем, существует ли локальный файл и не устарел ли он
        if (file_exists($this->localFile) && (time() - filemtime($this->localFile)) < $this->cacheLifetime) {
            // Локальный файл актуален, читаем его
            $xmlContent = file_get_contents($this->localFile);
            if ($xmlContent === false) {
                die("Не удалось загрузить локальный XML-файл.");
            }
        } else {
            // Локальный файл устарел или не существует, загружаем с удаленного сайта
            $xmlContent = file_get_contents($this->xmlUrl);
            if ($xmlContent === false) {
                die("Не удалось загрузить XML-файл с удаленного сайта.");
            }

            // Сохраняем загруженный XML в локальный файл
            file_put_contents($this->localFile, $xmlContent);
        }


        $xmlObject = simplexml_load_string($xmlContent, "SimpleXMLElement", LIBXML_NOCDATA);
        if ($xmlObject === false) {
            die("Ошибка при разборе XML.");
        }




        // Преобразование в массив
        $categories = $xmlObject->shop->categories;

        foreach ($categories->category as $category) {

            $data = [
                'id'=>(string) $category['id'],
                'ymlID'=>(string) "cat_{$this->uid}_{$category['id']}",
                'name'=>(string) $category
            ];
            if ($category['parentId']){
                $data['parentId'] = (string) $category['parentId'];
                $data['ymlID_parent'] = (string) "cat_{$this->uid}_{$category['parentId']}";
            }
            $this->categoriesArray[] = $data;
        }

        $offers = $xmlObject->shop->offers;
        foreach ($offers->offer as $offer) {
            $offer_i = (array) $offer;
            foreach ($offer->param as $param) {
                $param = (array)$param;
                $this->optsArray[$param["@attributes"]["name"]] = $param[0];
                $offer_i['opts'][$param["@attributes"]["name"]] =$param[0];
            }

            $offer_data = array_merge($offer_i,$offer_i["@attributes"]);


            $offer_data['ymlID']= "product_{$this->uid}_{$offer_data['id']}";
            $offer_data['ymlID_parent'] = (string) "cat_{$this->uid}_{$offer_data['categoryId']}";
            unset($offer_data["@attributes"]);
            unset($offer_data["param"]);
            $this->offersArray[] = $offer_data;
        }



    }

}

/*$run = new yml_import("https://www.boyard.biz/storage/export_product_yandex.xml");
$run->yml_reader();
var_dump(($run->categoriesArray));*/

