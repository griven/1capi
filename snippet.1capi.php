<?php
define ("CATALOG_ID", 14);
define ("TV_MIN_INFO", 0);

$exchange = new Exchange($modx);

class Exchange{
  public $modx;
  
  public $cmd;
  public $data;
  public $sig;
  
  function __construct(modX &$modx) {
    $this->modx = &$modx;
    $this->process();
  }
  
  /**
  * Разбирает полученные данные
  */
  function getPostData() {
    $this->cmd = $_POST['cmd'];
    $this->data = json_decode($_POST['data']);
    $this->sig   = $_POST['sig'];
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
        $this->putProduct();
        break;
    }
  }
  
  /**
  * Функция получения товаров
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
    print_r($result);
  }
  
  /**
  * Получает всех детей базового каталога
  */
  function getAllChild() {
    print_r($this->modx->getTree(CATALOG_ID));
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
      echo "not found $id";
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
      echo "not found $id";
      $result = null;
    }
    return $result;
  }
  
  function putProduct() {
    $ids = $this->getIdsFromData();
    if($ids == null) {
      $this->createResource();
    } else {
      $this->updateResource($ids);
    }
  }
  
  function createResource() {
    $parent = 15;
    $template = 4;
    $publishedon =  date('Y-m-d H:i:s');
    $newResource = $this->modx->newObject('modResource');
    $newResource->set('pagetitle',$publishedon);
    $newResource->set('parent',$parent);
    $newResource->set('template',$template);
    $newResource->set('published',1);
    $newResource->set('show_in_tree',0);
    $newResource->set('publishedon',$publishedon);
    $newResource->save();
    $id = $newResource->get('id');
    $newResource->set('pagetitle','test page '.$id);
    $newResource->save();
    echo "new id is $id\n";
  }
  
  function delProduct() {
    echo "delProduct\n";
    $ids = $this->getIdsFromData();
    if($ids==null) $ids= array();
    foreach($ids as $id) {
      $this->removeResource($id);
    }
  }
  
  function removeResource($id) {
    echo "removeResource $id!\n";
    // условие чисто для разработки (чтобы лишнее не поудалять)
    if( $id > 43) { 
      $resource = $this->modx->getObject('modResource', $id);
      if($resource == null) {
        echo "resource not found";
      } else if ($resource->remove() == false) {
        echo 'An error occurred while trying to remove the box!';
      }
    }
  }
  
  function getIdsFromData() {
    echo "getIdsFromData\n";
    $ids = array();
    if(isset($this->data->id)) {
      if(is_array($this->data->id)) {
        $ids = $this->data->id;
      } else {
        array_push($ids,$this->data->id);
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