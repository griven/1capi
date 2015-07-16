<?php
/**
 * API для быстрой загрузки выгрузки информации modx для интеграции с 1С.
 * @license GPL v2
 * @author Vadim Rudnitskiy
 */

include $modx->getOption('core_path').'components/1capi/model/exchange.php';
$exchange = new Exchange($modx);