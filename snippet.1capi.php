<?php
// константы которые потом можно перенести в конфиг
define ("CATALOG_ID", 14);          // id ресурса корня каталогов
define ("TV_MIN_INFO", true);       // определяет отдавать минимально необходимую инфу (true) или всю(false)
define ("PRODUCT_TEMPLATE", 4);     // id шаблона товара
define ("CATALOG_TEMPLATE", 3);     // id шаблона каталога
define ("START_DEL_ID", 43);        // id ресурса,начиная с которого можно удалять ресурсы
define ("DEBUG", true);             // флаг отладки
define ("SALT", 'solt');            // соль для hash функции

$exchange = new Exchange($modx);

class Exchange
{
    private $modx;
    private $data;

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
        $this->data = new DataClass();

        if($this->data->approveSig()) {
            switch ($this->data->getCmd()) {
                case 'getAllChild':
                    $this->getAllChild();
                    break;
                case 'getProducts':
                    $this->getResources(false);
                    break;
                case 'getCategories':
                    $this->getResources(true);
                    break;
                case 'putProduct':
                    $this->putProduct(false);
                    break;
                case 'putProductCategory':
                    $this->putProduct(true);
                    break;
                case 'delProduct':
                    $this->delProduct();
                    break;
                case 'getImages':
                    $this->getImages();
                    break;
                case 'putImage':
                    $this->putImage();
                    break;

                case 'getOrders':
                    $this->getOrders();
                    break;
                case 'updateOrder':
                    $this->updateOrder();
                    break;
                case 'setOrderUploadedTo1c':
                    $this->setOrderUploadedTo1c();
                    break;

                case 'getUsers':
                    $user = new User($this->modx, $this->data);
                    $this->result = $user->get();
                    break;

                default:
                    $this->result["error"] .= "|command not found";
                    break;
            }
        } else {
            $this->result['error'] .= '|sig failed';
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
        // иначе неправильно отдается JSON с большой вложенностью (например getResources)
        exit;
    }

    /**
     * @param $isFolder - true получить только категории, false только товары
     */
    function getResources($isFolder)
    {
        $ids = $this->getIdsFromData();

        if ($ids == null) {
            $ids = $this->modx->getChildIds(CATALOG_ID);
        }

        $result = array();

        foreach ($ids as $id) {
            $productInfo = $this->getProductInfo($id);
            if (isset($productInfo[0]["isfolder"]) && $productInfo[0]["isfolder"] == $isFolder) {
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
            $isNew = false;
            $resource = $this->modx->getObject($objectType, $resourceFields['id']);
            if ($resource == null) {
                $this->result["error"] .= "|resource not found";
            }
        } // создаем ресурс
        else {
            $isNew = true;
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
            $this->result[] = ($isNew) ? $id : true;

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
                $this->result["error"] .= '|An error occurred while trying to remove resource!';
            } else {
                array_push($this->result, "$id deleted");
            }
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
        $status = $this->get1cStatusFromData();

        // если не передан id, получаем все id заказов
        if(count($ids) < 1) {
            $orders = $this->modx->getIterator('shk_order');
            foreach ($orders as $order) {
                $ids[] = $order->id;
            }
        }

        foreach($ids as $id) {
            $order = $this->getOrder($id);
            if($status === null || (isset($order['uploadedTo1c']) && $order['uploadedTo1c'] === $status)) {
                array_push($this->result,$order);
            }
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
        $this->getResources(false);

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

    /**
     * Устанавливает флаг загрузки в 1с
     */
    function setOrderUploadedTo1c() {
        $this->includeShopkeeper();

        $ids = $this->getIdsFromData();
        $status = $this->get1cStatusFromData();

        if(isset($status)){
            foreach($ids as $order_id) {
                $order = $this->modx->getObject('shk_order',$order_id);
                if(isset($order)) {
                    $order->set('uploadedTo1c', $status);
                    $order->save();
                    $this->result[0] = true;
                }
            }
        }
    }

    /**
     * Получает статус загрузки в 1с переданный POSTом
     * @return int|null
     */
    function get1cStatusFromData() {
        $status = null;
        if (isset($this->data['uploadedTo1c'])) {
            $status = ($this->data['uploadedTo1c'] == 1) ? 1 : 0;
        }
        return $status;
    }
}

class DataClass
{
    private $cmd;    // функция которую требуется запустить
    private $data;   // данные необходимые для функции
    private $sig;    // подпись

    /**
     * Разбирает полученные данные
     */
    public function __construct()
    {
        $this->cmd = $_POST['cmd'];
        $this->data = json_decode($_POST['data'], true);

        if(DEBUG) {
            echo $this->cmd;
            if ($this->data == null)
                echo "null data\n";
            print_r($this->data);
        }
    }

    /**
     * Проверяет правильность подписи
     * @return bool - одобрена ли подпись
     */
    public function approveSig() {
        $this->sig = md5($_POST['cmd'] . $_POST['data'] . SALT);
        return ($this->sig == $_POST['sig']);
    }

    public function getCmd() {
        return $this->cmd;
    }

    /**
     * Получает id в виде массива из JSON
     * @return null или массив id
     */
    public function getIdsFromData()
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
    public function getResourceFields()
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
    public function getTemplateVariables()
    {
        if (isset($this->data[1])) {
            return $this->data[1];
        } else {
            return null;
        }
    }
}

abstract class ModxObject{
    protected $modx;
    protected $data;

    public function __construct(modX &$modx, DataClass &$data){
        $this->modx = &$modx;
        $this->data = &$data;
    }
    abstract public function get();
}

class User extends ModxObject{
    /**
     * Получает профили пользователей
     */
    public function get() {
        $ids = $this->data->getIdsFromData();

        if($ids) {
            foreach($ids as $id) {
                $user = $this->modx->getObject('modUser', $id);
                $result[] = $this->getUserProfile($user);
            }
        } else {
            $userCollection = $this->modx->getCollection('modUser');
            foreach($userCollection as $user) {
                $result[] = $this->getUserProfile($user);
            }
        }

        return $result;
    }

    /**
     * Получает профиль пользователя из его объекта
     * @param $user - объект пользователя
     * @return array|null
     */
    private function getUserProfile($user) {
        $result = null;
        if($user) {
            $profile = $user->getOne('Profile');
            if($profile) {
                $result = $profile->toArray();
            }
        }
        return $result;
    }
}