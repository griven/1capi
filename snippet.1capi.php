<?php
// константы которые потом можно перенести в конфиг
define ("CATALOG_ID", 14);          // id ресурса корня каталогов
define ("TV_MIN_INFO", true);       // определяет отдавать минимально необходимую инфу (true) или всю(false)
define ("PRODUCT_TEMPLATE", 4);     // id шаблона товара
define ("CATALOG_TEMPLATE", 3);     // id шаблона каталога
define ("START_DEL_ID", 43);        // id ресурса,начиная с которого можно удалять ресурсы
define ("DEBUG", true);             // флаг отладки
define ("SOLT", 'solt');            // соль для hash функции

$exchange = new Exchange($modx);

class Exchange
{
    public $modx;

    public $cmd;    // функция которую требуется запустить
    public $data;   // данные необходимые для функции
    public $sig;    // подпись
    public $result; // результат работы функции

    function __construct(modX &$modx)
    {
        $this->modx = &$modx;
        $this->result = array();
        $this->process();
    }

    /**
     *  Основная функция
     */
    function process()
    {
        $this->getPostData();

        if($this->approveSig()) {
            switch ($this->cmd) {
                case 'getProducts':
                    $this->getProducts();
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
                case 'getOrders':
                    $this->getOrders();
                    break;
                case 'updateOrder':
                    $this->updateOrder();
                    break;
                case 'getImages':
                    $this->getImages();
                    break;
                case 'putImage':
                    $this->putImage();
                    break;
                default:
                    $this->result["error"] .= "|command not found";
                    break;
            }
        }

        $this->response();
    }

    /**
     * Разбирает полученные данные
     */
    function getPostData()
    {
        $this->cmd = $_POST['cmd'];
        $this->data = json_decode($_POST['data'], true);

        if(DEBUG) {
            if ($this->data == null)
                echo "null data\n";
            print_r($this->data);
        }
    }

    /**
     * Проверяет правильность подписи
     * @return bool - одобрена ли подпись
     */
    function approveSig() {
        $this->sig = md5($_POST['cmd'] . $_POST['data'] . SOLT);
        if($this->sig == $_POST['sig']) {
            return true;
        } else {
            $this->result['error'] .= '|sig failed';
            return false;
        }
    }

    /**
     * Функция формирующая ответ JSON
     */
    function response()
    {
        if (count($this->result) == 0) {
            $this->result["error"] .= "|empty result";
        }
        if(DEBUG) {
            echo "sig ".$this->sig."\n";
            echo "result\n";
        }
        echo json_encode($this->result);
        if(DEBUG) {
            echo "\n\npretty result\n";
            foreach ($this->result as $res) {
                echo json_encode($res) . "\n";
            }
        }
        // иначе неправильно отдается JSON с большой вложенностью (например getProducts)
        exit;
    }

    /**
     * Функция получения товаров
     * если в json не указан id, то получает все товары корневого каталога
     */
    function getProducts()
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
     * Получает всех детей каталога
     */
    function getAllChild()
    {
        $ids = $this->getIdsFromData();
        $id = (count($ids) < 1) ? CATALOG_ID : $ids[0];

        $this->result = $this->modx->getTree($id);
    }

    /**
     * Получает информацию о ресурсе по его id
     * @param $id - номер ресурса информацию о котором нужно узнать
     * @return null|array состояющий из массива resource fields и массива template variables
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
     * @return array|null массив содержащий resource fields
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
     * @return array|null массив содержащий template variables
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
                    $tv_param['source'] = $tv->get('source');
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

        $this->createOrUpdateResource($resourceFields, $tvs, $isCategory);
    }

    /**
     * Создает новый ресурс или обновляет старый с параметрами взятыми из JSON
     * @param $resourceFields - массив полей ресурсов (id, template и т.п.)
     * @param $tvs - массив полей шаблона (tv)
     * @param $isCategory - true- категория, false продукт
     */
    // for test [{"parent":15, "pagetitle": "test"},[{"name":"keywords","value":"container"}, {"name":"meta_title","value":"collection"}] ]
    function createOrUpdateResource($resourceFields, $tvs, $isCategory)
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
     * @return null или массив id
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
     * @return array ассоциативный массив полей ресурса
     */
    function getResourceFields()
    {
        if (isset($this->data[0])) {
            return $this->data[0];
        } else {
            return null;
        }
    }

    /**
     * Получает переменные шаблона
     * @return array ассоциативный массив полей ресурса
     */
    function getTemplateVariables()
    {
        if (isset($this->data[1])) {
            return $this->data[1];
        } else {
            return null;
        }
    }

    /*============================================================
    * Вторая часть
    */

    /**
     * Подключает пакет shopkeeper
     */
    function includeShopkeeper() {
        $modelpath = $this->modx->getOption('core_path') . 'components/shopkeeper3/model/';
        $this->modx->addPackage( 'shopkeeper3', $modelpath );
    }

    /**
     * Получает всю инфу о заказе по его JSON
     */
    function getOrders() {
        $this->includeShopkeeper();

        $ids = $this->getIdsFromData();
        foreach($ids as $id) {
            $order = $this->getOrder($id);
            array_push($this->result,$order);
        }
    }

    /**
     * Получает заказ по его id
     * @param $order_id - номер заказа
     * @return array создержащий все поля заказа
     */
    function getOrder($order_id) {
        $order_data = array();
        if( $order_id ){
            $order = $this->modx->getObject('shk_order',$order_id);
            if( $order ){
                $order_data = $order->toArray();
                $order_data['purchases'] = $this->getPurchases( $order_id );
            }
        }

        return $order_data;
    }

    /**
     * Получает конкретные покупки по id заказа
     * @param $order_id - номер заказа
     * @return array представление объекта заказа в массиве
     */
    function getPurchases( $order_id ){
        $output = array();

        $query = $this->modx->newQuery('shk_purchases');
        $query->where( array( 'order_id' => $order_id ) );
        $query->sortby( 'id', 'asc' );
        $purchases = $this->modx->getIterator( 'shk_purchases', $query );

        if( $purchases ) {
            foreach( $purchases as $purchase ){
                $p_data = $purchase->toArray();
                if( !empty( $p_data['options'] ) ){
                    $p_data['options'] = json_decode( $p_data['options'], true );
                }

                $fields_data = array();
                if( !empty( $p_data['data'] ) ){
                    $fields_data = json_decode( $p_data['data'], true );
                    unset($p_data['data']);
                }

                $purchase_data = array_merge( $fields_data, $p_data );
                array_push( $output, $purchase_data );
            }
        }
        return $output;
    }

    /**
     * Обновляет заказ
     */
    function updateOrder() {
        if($this->data['id'] > 0) {
            $this->includeShopkeeper();
            $order_data = $this->getOrder($this->data['id']);

            if(count($order_data) > 1) {
                foreach ($this->data as $key=>$value) {
                    if( array_key_exists($key, $order_data)) {
                        if($key != 'id' && $key != 'purchases') {
                            $order_data[$key] = $value;
                        } else if ($key == 'purchases') {
                            $order_data = $this->updatePurchases($order_data, $value);
                        }
                    }
                }
                $order = $this->modx->getObject('shk_order',$this->data['id']);
                $order->fromArray($order_data);
                $order->save();
                $this->result[0] = true;
            }
        }
    }

    /**
     * Обновляет покупки в заказе
     * @param array $order_data массив заказа
     * @param array $new_purchases массив измененных покупок
     * @return array
     */
    function updatePurchases($order_data = array(), $new_purchases = array()) {
        if (isset($order_data['purchases'])) {
            foreach ($new_purchases as $id => $new_purchase) {
                if (isset($order_data['purchases'][$id])) {
                    foreach ($new_purchase as $key => $value) {
                        if (array_key_exists($key, $order_data['purchases'][$id]) && $key != 'id' && $key != 'p_id') {
                            $order_data['purchases'][$id][$key] = $value;
                        }
                    }
                }
            }
        }
        return $order_data;
    }

    /**
     * Получает url картинки и краткую информацию о товаре
     */
    function getImages() {
        $this->result = 'getImages';
        $this->getProducts();

        $products = $this->result;
        $this->result = array();

        for($i = 0; $i<count($products); $i++) {
            if (isset($products[$i][1])) {
                foreach ($products[$i][1] as $id => $tv) {
                    if (isset($tv['type']) && $tv['type'] == 'image') {
                        $file_source = ($tv['source'] == 2) ? 'userfiles/' : '';
                        if ($tv['value']) {
                            $tv['full_url'] = MODX_SITE_URL . $file_source . $tv['value'];
                        } else {
                            $tv['full_url'] = '';
                        }
                        $products[$i][1][$id] = $tv;
                    } else {
                        unset($products[$i][1][$id]);
                    }
                }
            }
        }

        $this->result = $this->cutProductInfo($products);
    }

    /**
     * Обрезает ненужную инфу о товаре
     * @param array $products - массив товаров
     * @return array - обработанный массив товаров
     */
    function cutProductInfo($products = array()) {
        $neededKeys = array('id', 'pagetitle', 'uri');
        for($i = 0; $i<count($products); $i++) {
            if(isset($products[$i][0])) {
                foreach ($products[$i][0] as $key=>$value) {
                    if(!in_array($key,$neededKeys))
                        unset($products[$i][0][$key]);
                }
            }
        }

        return $products;
    }

    /**
     * Загружает картинку и вставляет её в TV определенного ресурса
     */
    function putImage()
    {
        if (isset($this->data['id']) && isset($this->data['tv'])) {
            $ext_array = array('gif', 'jpg', 'jpeg', 'png');
            $uploaddir = MODX_BASE_PATH . 'userfiles/';
            $filename = basename($_FILES['file']['name']);

            $ext = pathinfo($filename, PATHINFO_EXTENSION);

            $res = $this->modx->getObject('modResource',$this->data['id']);
            if(isset($res)) {
                $tv = $res->getTVValue($this->data['tv']);
                if(!isset($tv)) {
                    $this->result['error'] .= '|cant find this tv';
                }
            } else {
                $this->result['error'] .= '|cant find this resource';
            }

            if ($filename != '' && isset($tv)) {
                if (in_array($ext, $ext_array)) {
                    // Делаем уникальное имя файла
                    $filename = mb_strtolower($filename);
                    $filename = str_replace(' ', '_', $filename);
                    $filename = date("Ymdgi") . $filename;

                    $uploadfile = $uploaddir . $filename;
                    if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadfile)) {
                        $res->setTVValue($this->data['tv'], $filename);
                        array_push($this->result, 'image loaded');
                    } else {
                        $this->result['error'] .= '|cant load the file';
                    }
                } else {
                    $this->result['error'] .= '|bad file extension';
                }
            }
        }
    }
}