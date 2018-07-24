<?
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
CModule::IncludeModule('sale');
CModule::IncludeModule('catalog');
CModule::IncludeModule('iblock');

use Bitrix\Sale\Order;
use Bitrix\Sale\Basket;
use Bitrix\Currency\CurrencyManager;
use Bitrix\Main\Context;
use Bitrix\Sale\Delivery\Services\Manager;

if (
    check_bitrix_sessid() && ($productID = intval($_REQUEST['productID'])) &&
    ($phone = $_REQUEST['phone']) && strlen($phone) &&
    ($name = $_REQUEST['name']) && strlen($name)
) {
    $res = CIBlockElement::GetByID($productID);
    if ($ar_res = $res->GetNext()) {
        $productName = $ar_res['NAME'];
        $productXmlId = $ar_res['XML_ID'];
    }

    $basket = Basket::create(SITE_ID);
    $basketItem = $basket->createItem('catalog', $productID);
    $basketItem->setFields(array(
        'PRODUCT_ID' => $productID,
        'NAME' => $productName,
        'PRODUCT_XML_ID' => $productXmlId,
        'CATALOG_XML_ID' => 'akbmaster_s1',
        'QUANTITY' => 1,
        'CURRENCY' => CurrencyManager::getBaseCurrency(),
        'LID' => Context::getCurrent()->getSite(),
        'PRODUCT_PROVIDER_CLASS' => 'CCatalogProductProvider',
    ));
    $order = Order::create(SITE_ID,
        $USER->IsAuthorized() ? $USER->GetID() : 4608); // если пользователь не авторизирован то ставим админа в постановщики заказа
    $order->setPersonTypeId(1); // физическое лицо
    $order->setBasket($basket);

    // свойства заказа
    $propertyCollection = $order->getPropertyCollection();
    // $propertyCollection->getPhone()->setValue($phone);
    $propertyCollection->getItemByOrderPropertyId(3)->setValue($phone);
    $propertyCollection->getPayerName()->setValue($name);

    $shipmentCollection = $order->getShipmentCollection();
    $shipment = $shipmentCollection->createItem(Manager::getObjectById(2)); // самовывоз
    $shipmentItemCollection = $shipment->getShipmentItemCollection(); // коллекция товаров отгрузки

    // наполнение отгрузки
    $itemShipment = $shipmentItemCollection->createItem($basketItem);
    $itemShipment->setQuantity($basketItem->getQuantity());

    // метод оплаты
    $paymentCollection = $order->getPaymentCollection();
    $payment = $paymentCollection->createItem(Bitrix\Sale\PaySystem\Manager::getObjectById(1)); // наличными

    // выставление суммы оплаты делаем равной сумме заказа
    $payment->setField('SUM', $order->getPrice());
    $payment->setField('CURRENCY', $order->getCurrency());

    if ($result = $order->save()) {

        echo 'success';
        exit();
    }
}
http_response_code(400);
