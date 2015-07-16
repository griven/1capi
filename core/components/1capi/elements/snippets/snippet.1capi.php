<?php
/**
 * API для быстрой загрузки выгрузки информации modx для интеграции с 1С.
 * @license GPL v2
 * @author Vadim Rudnitskiy
 */

/**
 * id ресурса корня каталогов
 */
define ("CATALOG_ID", 14);

/**
 * id шаблона товара
 */
define ("PRODUCT_TEMPLATE", 4);

/**
 * id шаблона каталога
 */
define ("CATALOG_TEMPLATE", 3);

/**
 * флаг отладки
 */
define ("DEBUG", true);

/**
 * соль для hash функции
 */
define ("SALT", 'solt');

/**
 * максимальное количество обрабатываемых элементов
 */
define ("LIMIT", 1500);

include $modx->getOption('core_path').'components/collections/1capi/model/exchange.php';
$exchange = new Exchange($modx);