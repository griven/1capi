1capi

Данные передаются с помощью метода POST по заранее обозначенному URL

Для вызова api используется следующие поля
cmd - команда
data - данные
sig - подпись
----
file - файл (только для загрузки картинок,поддерживаемые форматы jpg,png,gif)

====================================
Доступные для вызова команды (cmd)

getProducts - получение информации о товаре/товарах либо любого другой ресурса modx (например производителе)
getAllChild - получение всех детей каталога в виде дерева
delProduct - удаление товара/ресурса modx (можно также категорию)
putProduct - добавление/изменение товара/ресурса modx (обязательные параметры parent_id, pagetitle)
putProductCategory - добавление/изменение категории (обязательные параметры parent_id, pagetitle)
getCategories - получение массива категорий
getOrders - получение массива заказов
updateOrder - изменение заказа (обязательный параметр id)
setOrderUploadedTo1c - изменение флага загрузки в 1с для заказа
getImages - получение картинок товара и минимальных данных о нем
putImage - загрузка картинок товара (обязательные параметры id, tv)

====================================
Данные (data) передаються в формате JSON

====================================
Подпись (sig) формируется в виде hash функции

sig это md5-функция от cmd + data + solt (solt - это соль, специальное слово(строка) заранее известное обоим сторонам)
на PHP выглядит так
$sig = md5($cmd . $data . $solt)


====================================
Примеры использования api

+++getProducts, getOrders, getCategories,getImages+++
data: 
result: массив содержащий инфу о всех товарах/заказах/категориях/картинках

data: {"id" : 15}
result: массив содержащий инфу о товарах/заказах/категориях/картинках с id равным 15

data: {"id" : [15,16,17]}
result: массив содержащий инфу о группе товарах/заказах/категориях/картинках с id равными 15, 16 и 17

+++getAllChild+++
data: 
result: массив содержащий все id товаров каталога

data: {"id" : 15 }
result: массив содержащий все id товаров каталога с id = 15

+++delProduct+++
data: {"id" : 47 }
result: ["47 deleted"] или {"error":"|resource not found"}

+++putProduct, putProductCategory+++
добавление
data: [{"parent":15, "pagetitle": "test"},[{"name":"keywords","value":"container"}, {"name":"meta_title","value":"collection"}] ]
result: [87] - id нового ресурса

обновление
data: [{"id":87, "longtitle": "test"}, [{"name":"keywords","value":"container1"}]]
result: [true]

+++updateOrder++
data: {"id" 1, "price":2400}
result: [true]

++setOrderUploadedTo1c++
data: {"id":1, "uploadedTo1c": true} или {"id":1, "uploadedTo1c": 1}
result
[true]

+++putImage++
data: {"id" : 87, "tv":"image"}
file: выбранный файл с форматом jpg,gif или png
result: ["image loaded"]