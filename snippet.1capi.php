<?php
// константы которые потом можно перенести в конфиг
define ("CATALOG_ID", 14);
define ("TV_MIN_INFO", 0);
define ("PRODUCT_TEMPLATE", 4);
define ("START_DEL_ID", 43);

$exchange = new Exchange($modx);

class Exchange{
  public $modx;
  
  public $cmd;
  public $data;
  public $sig;
  
  public $result;
  
  function __construct(modX &$modx) {
    $this->modx = &$modx;
    $this->process();
  }
  
  /**
  * Разбирает полученные данные
  */
  function getPostData() {
    $this->cmd = $_POST['cmd'];
    $this->data = json_decode($_POST['data'], true);
    $this->sig   = $_POST['sig'];
    
    if($this->data == null) echo "null data";
    print_r($this->data);
  }
  
  /**
  *  Основная функция
  */
  function process() {
    $this->getPostData();
    
    switch($this->cmd) {
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
      default:
        $this->result["error"] .= "|command not found";
        break;
    }
    
    $this->response();
  }
  
  /**
  * Функция формирующая ответ JSON
  */
  function response() {
    if(count($this->result) == 0) {
      $this->result["error"] .= "|empty result";
    }
    echo "result\n".json_encode($this->result);
    echo "\n\npretty result\n";
    foreach($this->result as $res) {
      echo json_encode($res)."\n";
    }
    // иначе неправильно отдается JSON с большой вложенностью (например getProduct)
    exit; 
  }
  
  /**
  * Функция получения товаров
  * если в json не указан id, то получает все товары корневого каталога
  */
  function getProduct() {
    $ids = $this->getIdsFromData();
    
    if($ids == null) {
      $ids = $this->modx->getChildIds(CATALOG_ID);
    }
    
    $result = array();
    
    foreach($ids as $id) {
      $productInfo = $this->getProductInfo($id);
      if(isset($productInfo)) {
        array_push($result,$productInfo);
      }
    }
    
    $this->result = $result;
  }
  
  /**
  * Функция получения категорий товаров
  */
  function getCategories() {
    $ids = $this->modx->getChildIds(CATALOG_ID);
    
    $result = array();
    
    foreach($ids as $id) {
      $productInfo = $this->getProductInfo($id);
      if(isset($productInfo[0]["isfolder"]) && $productInfo[0]["isfolder"] == 1) {
        array_push($result,$productInfo);
      }
    }
    
    $this->result = $result;
  }
  
  /**
  * Получает всех детей базового каталога
  */
  function getAllChild() {
    $this->result = $this->modx->getTree(CATALOG_ID);
  }
  
  /**
  * Получает информацию о ресурсе по его id
  * @param $id - номер ресурса информацию о котором нужно узнать
  * @return null или массив состояющий из массива resource fields и массива template variables
  */
  function getProductInfo($id) {
    $rfs = $this->getAllResourceFields($id);
    if(isset($rfs)) {
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
  function getAllResourceFields($id) {
    $resource = $this->modx->getObject('modResource', $id);
    if($resource) {
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
  function getAllTemplateVars($id) {
    $resource = $this->modx->getObject('modResource', $id);
    if($resource) {
      $tvs = $resource->getMany('TemplateVars');
      $result = array();
      foreach($tvs as $tv) {
        $tv_param = array();
        if(TV_MIN_INFO) {
          $tv_param['name'] = $tv->get('name');
          $tv_param['type']  = $tv->get('type');
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
  function putProduct($isCategory = false) {
    $ids = $this->getIdsFromData();
    
    if($ids == null) {
      $this->createResource($isCategory);
    } else {
      //$this->updateResource($ids);
    }
  }
  
  /**
  * Создает новый ресурс с параметрами взятыми из JSON
  */
  function createResource($isCategory) {
    if(array_key_exists('pagetitle',$this->data) && array_key_exists('parent',$this->data)) {
      $newResource = $this->modx->newObject('modResource');
      
      // стандартные значения
      $newResArray = $newResource->toArray();
      $newResArray['template'] = PRODUCT_TEMPLATE;
      $newResArray['published'] = 1;
      $newResArray['publishedon'] = date('Y-m-d H:i:s');
      $newResArray['show_in_tree'] = 0;
      $newResArray['isfolder'] = ($isCategory) ? 1 : 0;
     
     // значения полученные из JSON
      foreach($this->data as $key=>$value) {
        if(array_key_exists($key, $newResArray)) {
          $newResArray[$key] = $value;
        }
      }
      
      // сохраненяем ресурс и получяем id
      $newResource->fromArray($newResArray);
      $newResource->save();
      $id = $newResource->get('id');
      $this->result = "$id";
    } else {
      $this->result["error"] .= "|pagetitle or parent not found";
    }
  }
  
  function delProduct() {
    $ids = $this->getIdsFromData();
    if(is_array($ids)) {
      foreach($ids as $id) {
        $this->removeResource($id);
      }
    }
  }
  
  /**
  * удаляет ресурс по его id
  * @param $id - номер ресурса
  */
  function removeResource($id) {
    // условие чисто для разработки (чтобы лишнее не поудалять)
    if( $id > START_DEL_ID) {
      $resource = $this->modx->getObject('modResource', $id);
      if($resource == null) {
        $this->result["error"] .= "|resource not found";
      } else if ($resource->remove() == false) {
        $this->result["error"] .= '|An error occurred while trying to remove the box!';
      } else {
        $this->result = "$id deleted";
      }
    }
  }
  
  /**
  * Получает id в виде массива из JSON
  * @return массив id или null
  */
  function getIdsFromData() {
    $ids = array();
    if(isset($this->data['id'])) {
      if(is_array($this->data['id'])) {
        $ids = $this->data['id'];
      } else {
        array_push($ids,$this->data['id']);
      }
    } else {
      $ids = null;
    }
    return $ids;
  }
  
  
  function updateResource($ids) {
    if(!is_array($ids)) return;
    foreach($ids as $id) {
      $this->updateOneResource($id);
    }
  }
  
  function updateOneResource($id) {
    $resource = $this->modx->getObject('modResource',$id);
    if($resource != null) {
      $resource->set('show_in_tree',0);
      $resource->save();
      echo "resource $id updated\n";
    } else {
      echo "resource $id not found\n";
    }
  }
}