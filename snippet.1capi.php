<?php
// константы которые потом можно перенести в конфиг
define ("CATALOG_ID", 14);          // id ресурса корня каталогов
define ("PRODUCT_TEMPLATE", 4);     // id шаблона товара
define ("CATALOG_TEMPLATE", 3);     // id шаблона каталога
define ("DEBUG", true);             // флаг отладки
define ("SALT", 'solt');            // соль для hash функции
define ("LIMIT", 1500);             // максимальное количество обрабатываемых элементов

$exchange = new Exchange($modx);

class Exchange
{
    private $modx;
    private $data;

    private $result; // результат работы функции

    public function __construct(modX &$modx)
    {
        $this->modx = &$modx;
        $this->data = new DataClass();
        $this->result = array();
        $this->process();
    }

    /**
     *  Основная функция
     */
    private function process()
    {
        if($this->data->approveSig()) {
            switch ($this->data->getCmd()) {
                // класс Resource
                case 'getAllChild':
                    $resource = new Resource($this->modx, $this->data);
                    $this->result = $resource->getAllChild();
                    break;
                case 'getProducts':
                    $resource = new Resource($this->modx, $this->data, false);
                    $this->result = $resource->get();
                    break;
                case 'getCategories':
                    $resource = new Resource($this->modx, $this->data, true);
                    $this->result = $resource->get();
                    break;
                case 'putProduct':
                    $resource = new Resource($this->modx, $this->data, false);
                    $this->result = $resource->put();
                    break;
                case 'putProductCategory':
                    $resource = new Resource($this->modx, $this->data, true);
                    $this->result = $resource->put();
                    break;
                case 'delProduct':
                    $resource = new Resource($this->modx, $this->data);
                    $this->result = $resource->del();
                    break;
                case 'getProductsCount':
                    $resource = new Resource($this->modx, $this->data, false);
                    $this->result = $resource->getCount();
                    break;
                case 'getCategoriesCount':
                    $resource = new Resource($this->modx, $this->data, true);
                    $this->result = $resource->getCount();
                    break;
                case 'getImages':
                    $resource = new Resource($this->modx, $this->data);
                    $this->result = $resource->getImages();
                    break;
                case 'putImage':
                    $resource = new Resource($this->modx, $this->data);
                    $this->result = $resource->putImage();
                    break;

                // класс Order
                case 'getOrders':
                    $order = new Order($this->modx, $this->data);
                    $this->result = $order->get();
                    break;
                case 'updateOrder':
                    $order = new Order($this->modx, $this->data);
                    $this->result = $order->updateOrder();
                    break;
                case 'setOrderUploadedTo1c':
                    $order = new Order($this->modx, $this->data);
                    $this->result = $order->setOrderUploadedTo1c();
                    break;
                case 'getOrdersCount':
                    $order = new Order($this->modx, $this->data);
                    $this->result = $order->getCount();
                    break;

                // класс User
                case 'getUsers':
                    $user = new User($this->modx, $this->data);
                    $this->result = $user->get();
                    break;
                case 'getUsersCount':
                    $user = new User($this->modx, $this->data);
                    $this->result = $user->getCount();
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
    private function response()
    {
        if (count($this->result) == 0) {
            $this->result["error"] .= "|empty result";
        }
        if(DEBUG) {
            echo "sig ".$this->data->getSig()."\n";
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

    /**
     * Возвращает комманду
     * @return mixed
     */
    public function getCmd() {
        return $this->cmd;
    }

    /**
     * @return mixed - данные переданные в JSON
     */
    public function getData() {
        return $this->data;
    }

    /**
     * @return mixed подпись
     */
    public function getSig() {
        if(DEBUG) {
            $result = $this->sig;
        } else {
            $result = "|can't get sig";
        }
        return $result;
    }

    /**
     * Получает id в виде массива из JSON
     * @return array|null или массив id
     */
    public function getIds()
    {
        $ids = array();
        if (isset($this->data['id'])) {
            if (is_array($this->data['id'])) {
                $ids = $this->data['id'];
                asort($ids);
            } else {
                $ids[] = $this->data['id'];
            }
        } else {
            $ids = null;
        }
        return $ids;
    }

    /**
     * @return int - значение лимита
     */
    public function getLimit() {
        if (isset($this->data['limit']) && is_numeric($this->data['limit'])) {
            $limit = $this->data['limit'];
        } else {
            $limit = LIMIT;
        }
        return $limit;
    }

    /**
     * @return bool - true,есть и лимит и !один id
     */
    public function hasIdAndLimit() {
        $result = false;
        if (isset($this->data['id']) && !is_array($this->data['id'])) {
            if (isset($this->data['limit']) && is_numeric($this->data['limit'])) {
                $result = true;
            }
        }

        return $result;
    }

    public function isMinInfo() {
        $result = false;
        if (isset($this->data['mininfo']) && ($this->data['mininfo'] != false)) {
            $result = true;
        }

        return $result;
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

    /**
     * Получает статус загрузки в 1с переданный POSTом
     * @return int|null
     */
    public function get1cStatusFromData() {
        $status = null;
        if (isset($this->data['uploadedTo1c'])) {
            $status = ($this->data['uploadedTo1c'] == 1) ? 1 : 0;
        }
        return $status;
    }
}

abstract class ModxObject{
    protected $modx;
    protected $data;

    public function __construct(modX &$modx, DataClass &$data){
        $this->modx = &$modx;
        $this->data = &$data;
    }

    /**
     * Возвращает информацию об элементах класса
     * @return mixed
     */
    public function get() {
        list($ids, $limit) = $this->getIdsAndLimit();
        return $this->getElements($ids, $limit);
    }

    /**
     * Возвращает количество элемментов класса
     * @return mixed
     */
    abstract public function getCount();

    /**
     * Получает номера элементов и ограничение по количеству
     * @return mixed
     */
    abstract protected function getIdsAndLimit();

    /**
     * Получает информацию об элементах класса
     * @param $ids - номера элементов о которых нужно узнать информацию
     * @param $limit - ограничение количества обрабатываемых элементов
     * @return mixed - информация об элементах
     */
    abstract protected function getElements($ids, $limit);
}

class User extends ModxObject{

    /**
     * Получает количество пользователей
     * @return int - количество пользователей
     */
    public function getCount() {
        return $this->modx->getCount('modUser');
    }

    /**
     * Получает номера пользователей и ограничение по количеству
     * @return array
     */
    protected function getIdsAndLimit(){
        $ids = $this->data->getIds();
        $limit = $this->data->getLimit();
        $needSlice = $this->data->hasIdAndLimit();

        if(!$ids || $needSlice) {
            $userCollection = $this->modx->getCollection('modUser');
            $allIds = array();
            foreach($userCollection as $user) {
                $allIds[] = $user->get('id');
            }

            $key = ($needSlice) ? array_search($ids[0], $allIds) : 0;
            $allIds = array_slice($allIds,$key);

            $ids = $allIds;
        }

        return array($ids,$limit);
    }

    /**
     * Получает профили пользователей
     * @param $ids - номера элементов
     * @param $limit - ограничение по выборке за раз
     * @return array - массив данных об элементах
     */
    protected function getElements($ids, $limit) {
        $result = array();

        foreach($ids as $id) {
            if($limit-- <= 0) {
                $result[] = array("next_id", $id);
                break;
            }
            $user = $this->modx->getObject('modUser', $id);
            $result[] = $this->getProfile($user);
        }

        return $result;
    }

    /**
     * Получает профиль пользователя из его объекта
     * @param $user - объект пользователя
     * @return array|null
     */
    private function getProfile($user) {
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

class Order extends ModxObject {

    /**
     * Получает количество заказов
     * @return int - количество заказов
     */
    public function getCount() {
        $this->includeShopkeeper();
        return $this->modx->getCount('shk_order');
    }

    /**
     * Обновляет заказ
     */
    function updateOrder() {
        $result[0] = false;
        $data = $this->data->getData();
        if($data['id'] > 0) {
            $this->includeShopkeeper();
            $order_data = $this->getOrder($data['id']);

            if(count($order_data) > 1) {
                foreach ($data as $key=>$value) {
                    if( array_key_exists($key, $order_data)) {
                        if($key != 'id' && $key != 'purchases') {
                            $order_data[$key] = $value;
                        } else if ($key == 'purchases') {
                            $order_data = $this->updatePurchases($order_data, $value);
                        }
                    }
                }
                $order = $this->modx->getObject('shk_order',$data['id']);
                $order->fromArray($order_data);
                $order->save();
                $result[0] = true;
            }
        }

        return $result;
    }

    /**
     * Устанавливает флаг загрузки в 1с
     */
    function setOrderUploadedTo1c() {
        $result[0] = false;
        $this->includeShopkeeper();

        $ids = $this->data->getIds();
        $status = $this->data->get1cStatusFromData();

        if(isset($status)){
            foreach($ids as $order_id) {
                $order = $this->modx->getObject('shk_order',$order_id);
                if(isset($order)) {
                    $order->set('uploadedTo1c', $status);
                    $order->save();
                    $result[0] = true;
                }
            }
        }
        return $result;
    }

    /**
     * Получает номера заказов и ограничение по количеству
     * @return array
     */
    protected function getIdsAndLimit(){
        $ids = $this->data->getIds();
        $limit = $this->data->getLimit();
        $needSlice = $this->data->hasIdAndLimit();

        // если не передан id, получаем все id заказов
        if(count($ids) < 1 || $needSlice) {
            $this->includeShopkeeper();
            $orders = $this->modx->getIterator('shk_order');
            $allIds = array();
            foreach ($orders as $order) {
                $allIds[] = $order->id;
            }

            $key = ($needSlice) ? array_search($ids[0], $allIds) : 0;
            $allIds = array_slice($allIds,$key);

            $ids = $allIds;
        }

        return array($ids, $limit);
    }

    /**
     * Получает всю инфу о заказах
     * @param $ids - номера элементов
     * @param $limit - ограничение по выборке за раз
     * @return array - массив данных об элементах
     */
    protected function getElements($ids, $limit) {
        $status = $this->data->get1cStatusFromData();

        $result = array();
        foreach($ids as $id) {
            if($limit-- <= 0) {
                $result[] = array("next_id", $id);
                break;
            }
            $order = $this->getOrder($id);
            if($status === null || (isset($order['uploadedTo1c']) && $order['uploadedTo1c'] === $status)) {
                $result[] = $order;
            }
        }

        return $result;
    }

    /**
     * Подключает пакет shopkeeper
     */
    private function includeShopkeeper() {
        $modelpath = $this->modx->getOption('core_path') . 'components/shopkeeper3/model/';
        $this->modx->addPackage( 'shopkeeper3', $modelpath );
    }

    /**
     * Получает заказ по его id
     * @param $order_id - номер заказа
     * @return array создержащий все поля заказа
     */
    private function getOrder($order_id) {
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
    private function getPurchases( $order_id ){
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
     * Обновляет покупки в заказе
     * @param array $order_data массив заказа
     * @param array $new_purchases массив измененных покупок
     * @return array
     */
    private function updatePurchases($order_data = array(), $new_purchases = array()) {
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
}

class Resource extends ModxObject {
    private $isFolder;

    public function __construct(modX &$modx, DataClass &$data, $isFolder=null) {
        parent::__construct($modx, $data);
        $this->isFolder = $isFolder;
    }

    /**
     * Получает количество ресурсов
     * @return int - количество ресурсов
     */
    public function getCount() {
        if($this->isFolder !== null) {
            $where = array(
                'isfolder' => ($this->isFolder === true) ? 1 : 0,
            );
        } else {
            $where = null;
        }
        return $this->modx->getCount('modResource', $where);
    }

    /**
     * Получает всех детей каталога
     */
    public function getAllChild()
    {
        $ids = $this->data->getIds();
        $id = (count($ids) < 1) ? CATALOG_ID : $ids[0];

        return $this->modx->getTree($id);
    }

    /**
     * Создает или обновляет ресурс
     */
    public function put()
    {
        $resourceFields = $this->data->getResourceFields();
        $tvs = $this->data->getTemplateVariables();

        $result = $this->createOrUpdateResource($resourceFields, $tvs);
        return $result;
    }

    /**
     * удаляет ресурсы
     */
    public function del()
    {
        $result = array();
        $ids = $this->data->getIds();
        if (is_array($ids)) {
            foreach ($ids as $id) {
                $result[] = $this->removeResource($id);
            }
        }
        return $result;
    }

    /**
     * Получает url картинки и краткую информацию о товаре
     */
    public function getImages() {
        $products = $this->get();

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

        $result = $this->cutProductInfo($products);
        return $result;
    }

    /**
     * Загружает картинку и вставляет её в TV определенного ресурса
     */
    public function putImage()
    {
        $result = array();
        $data = $this->data->getData();

        if (isset($data['id']) && isset($data['tv'])) {
            $ext_array = array('gif', 'jpg', 'jpeg', 'png');
            $uploaddir = MODX_BASE_PATH . 'userfiles/';
            $filename = basename($_FILES['file']['name']);

            $ext = pathinfo($filename, PATHINFO_EXTENSION);

            $res = $this->modx->getObject('modResource',$data['id']);
            if(isset($res)) {
                $tv = $res->getTVValue($data['tv']);
                if(!isset($tv)) {
                    $result['error'] .= '|cant find this tv';
                }
            } else {
                $result['error'] .= '|cant find this resource';
            }

            if ($filename != '' && isset($tv)) {
                if (in_array($ext, $ext_array)) {
                    // Делаем уникальное имя файла
                    $filename = mb_strtolower($filename);
                    $filename = str_replace(' ', '_', $filename);
                    $filename = date("Ymdgi") . $filename;

                    $uploadfile = $uploaddir . $filename;
                    if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadfile)) {
                        $res->setTVValue($data['tv'], $filename);
                        $result[] = 'image loaded';
                    } else {
                        $result['error'] .= '|cant load the file';
                    }
                } else {
                    $result['error'] .= '|bad file extension';
                }
            }
        } else {
            $result['error'] .= '|id or tv not found';
        }

        return $result;
    }

    /**
     * Получает номера ресурсов и ограничение по количеству
     * @return array
     */
    protected function getIdsAndLimit(){
        $ids = $this->data->getIds();
        $limit = $this->data->getLimit();
        $needSlice = $this->data->hasIdAndLimit();

        if (!$ids || $needSlice) {
            $allIds = $this->modx->getChildIds(CATALOG_ID);
            asort($allIds);

            $key = ($needSlice) ? array_search($ids[0], $allIds) : 0;
            $allIds = array_slice($allIds,$key);

            $ids = $allIds;
        }

        return array($ids, $limit);
    }

    /**
     * Получает информацию о ресурсах
     * @param $ids - номера ресурсов
     * @param $limit - ограничение по количеству
     * @return array - информация о ресурсах
     */
    protected function getElements($ids,$limit)
    {
        $result = array();
        foreach ($ids as $id) {
            $resource = $this->modx->getObject('modResource', $id);
            if($resource) {
                if ($this->isFolder === null || $resource->get('isfolder') == $this->isFolder) {
                    if($limit-- <= 0) {
                        $result[] = array("next_id", $id);
                        break;
                    }
                    $result[] = $this->getProductInfo($resource);
                }
            }
        }

        return $result;
    }

    /**
     * Получает информацию о ресурсе по его id
     * @param $resource - ресурс информацию о котором нужно узнать
     * @return array состоит из массива resource fields и массива template variables
     */
    private function getProductInfo($resource)
    {
        $rfs = $this->getResourceFields($resource);
        $tvs = $this->getTemplateVars($resource);

        return array($rfs, $tvs);
    }

    private function getResourceFields($resource) {
        if($this->data->isMinInfo()) {
            $neededKeys = array('id', 'pagetitle', 'longtitle', 'uri');
            foreach($neededKeys as $key) {
                $result[$key] = $resource->get($key);
            }
        } else {
            $result = $resource->toArray();
        }

        return $result;
    }

    /**
     * Получает все TV ресурса по его id
     * @param $resource - ресурс информацию о котором нужно узнать
     * @return array содержащит template variables
     */
    private function getTemplateVars($resource)
    {
        $result = array();
        $tvs = $resource->getMany('TemplateVars');
        $neededTVs = array('sku', 'price');
        foreach ($tvs as $tv) {
            if ($this->data->isMinInfo()) {
                if (in_array($tv->get('name'), $neededTVs)) {
                    $neededKeys = array('name', 'type', 'value');
                    foreach ($neededKeys as $key) {
                        $tv_param[$key] = $tv->get($key);
                    }
                    $result[] = $tv_param;
                }
            } else {
                $tv_param = $tv->toArray();
                $result[] = $tv_param;
            }
        }

        return $result;
    }

    /**
     * Создает новый ресурс или обновляет старый с параметрами взятыми из JSON
     * @param $resourceFields - массив полей ресурсов (id, template и т.п.)
     * @param $tvs - массив полей шаблона (tv)
     * @return array
     */
    // for test [{"parent":15, "pagetitle": "test"},[{"name":"keywords","value":"container"}, {"name":"meta_title","value":"collection"}] ]
    private function createOrUpdateResource($resourceFields, $tvs)
    {
        $objectType = ($this->isFolder) ? 'CollectionContainer' : 'modResource';

        $result = array();
        // находим ресурс
        if ($resourceFields['id'] != null) {
            $isNew = false;
            $resource = $this->modx->getObject($objectType, $resourceFields['id']);
            if ($resource == null) {
                $result["error"] .= "|resource not found";
            }
        } // создаем ресурс
        else {
            $isNew = true;
            if (array_key_exists('pagetitle', $resourceFields) && array_key_exists('parent', $resourceFields)) {
                $resource = $this->modx->newObject($objectType);
            } else {
                $result["error"] .= "|pagetitle or parent not found";
            }
        }

        if (isset($resource)) {
            // стандартные параметры
            $resourceArray = $resource->toArray();
            $resourceArray['published'] = 1;
            $resourceArray['publishedon'] = date('Y-m-d H:i:s');

            if ($this->isFolder) {
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
            $result[] = ($isNew) ? $id : true;

            // tv параметры
            foreach ($tvs as $tv) {
                if (isset($tv['name']) && isset($tv['value'])) {
                    $resource->setTVValue($tv["name"], $tv["value"]);
                }
            }
        }

        return $result;
    }

    /**
     * удаляет ресурс по его id
     * @param $id - номер ресурса
     * @return mixed - сообщение об удалении или ошибке
     */
    private function removeResource($id)
    {
        $result = array();
        $resource = $this->modx->getObject('modResource', $id);
        if ($resource == null) {
            $result["error"] .= "|resource $id not found";
        } else if ($resource->remove() == false) {
            $result["error"] .= "|An error occurred while trying to remove resource $id !";
        } else {
            $result = "$id deleted";
        }

        return $result;
    }

    /**
     * Обрезает ненужную инфу о товаре
     * @param array $products - массив товаров
     * @return array - обработанный массив товаров
     */
    private function cutProductInfo($products = array()) {
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
}