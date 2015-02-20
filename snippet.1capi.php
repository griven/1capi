<?php
// константы которые потом можно перенести в конфиг
define ("CATALOG_ID", 14);          // id ресурса корня каталогов
define ("TV_MIN_INFO", true);      // определяет отдавать минимально необходимую инфу (true) или всю(false)
define ("PRODUCT_TEMPLATE", 4); // id шаблона товара
define ("CATALOG_TEMPLATE", 3); // id шаблона каталога
define ("START_DEL_ID", 43);       // id ресурса,начиная с которого можно удалять ресурсы

//$exchange = new Exchange($modx);
phpinfo();
exit;

class Exchange
{
    public $modx;

    public $cmd;
    public $data;
    public $sig;

    public $result;

    function __construct(modX &$modx)
    {
        $this->modx = &$modx;
        $this->process();
    }

    /**
     * Разбирает полученные данные
     */
    function getPostData()
    {
        $this->cmd = $_POST['cmd'];
        $this->data = json_decode($_POST['data'], true);
        $this->sig = $_POST['sig'];

        if ($this->data == null)
            echo "null data\n";
        print_r($this->data);
    }

    /**
     *  Основная функция
     */
    function process()
    {
        $this->getPostData();

        switch ($this->cmd) {
            case 'getProduct':
                $this->getProduct();
                break;
            case 'getAllChild':
                $this->getAllChild();
                break;
            case 'delProduct':
                $this->delProduct();
                break;
            case 'putProduct':
                $this->putProduct(false);
                break;
            case 'putProductCategory':
                $this->putProduct(true);
                break;
            case 'getCategories':
                $this->getCategories();
                break;
            case 'getPurchase':
                $this->getPurchase();
                break;
            default:
                $this->result["error"] .= "|command not found";
                break;
        }

        $this->response();
    }

    /**
     * Функция формирующая ответ JSON
     */
    function response()
    {
        if (count($this->result) == 0) {
            $this->result["error"] .= "|empty result";
        }
        echo "result\n" . json_encode($this->result);
        echo "\n\npretty result\n";
        foreach ($this->result as $res) {
            echo json_encode($res) . "\n";
        }
        // иначе неправильно отдается JSON с большой вложенностью (например getProduct)
        exit;
    }

    /**
     * Функция получения товаров
     * если в json не указан id, то получает все товары корневого каталога
     */
    function getProduct()
    {
        $ids = $this->getIdsFromData();

        if ($ids == null) {
            $ids = $this->modx->getChildIds(CATALOG_ID);
        }

        $result = array();

        foreach ($ids as $id) {
            $productInfo = $this->getProductInfo($id);
            if (isset($productInfo)) {
                array_push($result, $productInfo);
            }
        }

        $this->result = $result;
    }

    /**
     * Функция получения категорий товаров
     */
    function getCategories()
    {
        $ids = $this->modx->getChildIds(CATALOG_ID);

        $result = array();

        foreach ($ids as $id) {
            $productInfo = $this->getProductInfo($id);
            if (isset($productInfo[0]["isfolder"]) && $productInfo[0]["isfolder"] == 1) {
                array_push($result, $productInfo);
            }
        }

        $this->result = $result;
    }

    /**
     * Получает всех детей базового каталога
     */
    function getAllChild()
    {
        $this->result = $this->modx->getTree(CATALOG_ID);
    }

    /**
     * Получает информацию о ресурсе по его id
     * @param $id - номер ресурса информацию о котором нужно узнать
     * @return null или массив состояющий из массива resource fields и массива template variables
     */
    function getProductInfo($id)
    {
        $rfs = $this->getAllResourceFields($id);
        if (isset($rfs)) {
            $tvs = $this->getAllTemplateVars($id);
            $result = array($rfs, $tvs);
        } else {
            $result = null;
        }
        return $result;
    }

    /**
     * Получает все поля ресурса resource fields по его id
     * @param $id - номер ресурса информацию о котором нужно узнать
     * @return массив содержащий resource fields
     */
    function getAllResourceFields($id)
    {
        $resource = $this->modx->getObject('modResource', $id);
        if ($resource) {
            $result = $resource->toArray();
        } else {
            $result = null;
        }
        return $result;
    }

    /**
     * Получает все TV ресурса по его id
     * @param $id - номер ресурса информацию о котором нужно узнать
     * @return массив содержащий template variables
     */
    function getAllTemplateVars($id)
    {
        $resource = $this->modx->getObject('modResource', $id);
        if ($resource) {
            $tvs = $resource->getMany('TemplateVars');
            $result = array();
            foreach ($tvs as $tv) {
                $tv_param = array();
                if (TV_MIN_INFO) {
                    $tv_param['name'] = $tv->get('name');
                    $tv_param['type'] = $tv->get('type');
                    $tv_param['value'] = $tv->get('value');
                } else {
                    $tv_param = $tv->toArray();
                }
                array_push($result, $tv_param);
            }
        } else {
            $result = null;
        }
        return $result;
    }

    /**
     * Создает или обновляет ресурс
     * @param $isCategory - создать категорию или же просто товар
     */
    function putProduct($isCategory = false)
    {
        $resourceFields = $this->getResourceFields();
        $tvs = $this->getTemplateVariables();

        $this->createResource($resourceFields, $tvs, $isCategory);
    }

    /**
     * Создает новый ресурс или обновляет старый с параметрами взятыми из JSON
     * @param $resourceFields - массив полей ресурсов (id, template и т.п.)
     * @param $tvs - массив полей шаблона (tv)
     * @param $isCategory - true- категория, false продукт
     */
    // for test [{"parent":15, "pagetitle": "test"},[{"name":"keywords","value":"container"}, {"name":"meta_title","value":"collection"}] ]
    function createResource($resourceFields, $tvs, $isCategory)
    {
        $objectType = ($isCategory) ? 'CollectionContainer' : 'modResource';

        // находим ресурс
        if ($resourceFields['id'] != null) {
            $resource = $this->modx->getObject($objectType, $resourceFields['id']);
            if ($resource == null) {
                $this->result["error"] .= "|resource not found";
            }
        } // создаем ресуср
        else {
            if (array_key_exists('pagetitle', $resourceFields) && array_key_exists('parent', $resourceFields)) {
                $resource = $this->modx->newObject($objectType);
            } else {
                $this->result["error"] .= "|pagetitle or parent not found";
            }
        }

        if (isset($resource)) {
            // стандартные параметры
            $resourceArray = $resource->toArray();
            $resourceArray['published'] = 1;
            $resourceArray['publishedon'] = date('Y-m-d H:i:s');

            if ($isCategory) {
                $resourceArray['isfolder'] = 1;
                $resourceArray['template'] = CATALOG_TEMPLATE;
                $resourceArray['show_in_tree'] = 1;
            } else {
                $resourceArray['isfolder'] = 0;
                $resourceArray['template'] = PRODUCT_TEMPLATE;
                $resourceArray['show_in_tree'] = 0;
            }

            // полученные из JSON параметры
            foreach ($resourceFields as $key => $value) {
                if (array_key_exists($key, $resourceArray) && $key != 'id') {
                    $resourceArray[$key] = $value;
                }
            }

            // сохраненяем ресурс и получяем id
            $resource->fromArray($resourceArray);
            $resource->save();
            $id = $resource->get('id');
            $this->result = "$id";

            // tv параметры
            foreach ($tvs as $tv) {
                if (isset($tv['name']) && isset($tv['value'])) {
                    $resource->setTVValue($tv["name"], $tv["value"]);
                }
            }
        }
    }

    /**
     * удаляет ресурсы
     */
    function delProduct()
    {
        $ids = $this->getIdsFromData();
        if (is_array($ids)) {
            foreach ($ids as $id) {
                $this->removeResource($id);
            }
        }
    }

    /**
     * удаляет ресурс по его id
     * @param $id - номер ресурса
     */
    function removeResource($id)
    {
        // условие чисто для разработки (чтобы лишнее не поудалять)
        if ($id > START_DEL_ID) {
            $resource = $this->modx->getObject('modResource', $id);
            if ($resource == null) {
                $this->result["error"] .= "|resource not found";
            } else if ($resource->remove() == false) {
                $this->result["error"] .= '|An error occurred while trying to remove the box!';
            } else {
                array_push($this->result, "$id deleted");
            }
        }
    }

    /**
     * Получает id в виде массива из JSON
     * @return массив id или null
     */
    function getIdsFromData()
    {
        $ids = array();
        if (isset($this->data['id'])) {
            if (is_array($this->data['id'])) {
                $ids = $this->data['id'];
            } else {
                array_push($ids, $this->data['id']);
            }
        } else {
            $ids = null;
        }
        return $ids;
    }

    /**
     * Получает поля(переменные) ресурса
     * @return ассоциативный массив полей ресурса
     */
    function getResourceFields()
    {
        if (isset($this->data[0])) {
            return $this->data[0];
        }
    }

    /**
     * Получает переменные шаблона
     * @return ассоциативный массив полей ресурса
     */
    function getTemplateVariables()
    {
        if (isset($this->data[1])) {
            return $this->data[1];
        }
    }

    /*============================================================
    * Вторая часть
    */

    function getPurchase()
    {

    }
}
















