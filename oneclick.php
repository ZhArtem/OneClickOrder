<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

global $USER;
global $APPLICATION;

use Bitrix\Main\Loader;
use Bitrix\Main\Context;
use Bitrix\Currency\CurrencyManager;
use Bitrix\Sale\Order;
use Bitrix\Sale\Basket;
use Bitrix\Sale\DiscountCouponsManager;
use Bitrix\Sale\Delivery;
use Bitrix\Sale\PaySystem;


const PAY_SYSTEM_ID = 2;

if (
    !Loader::includeModule("sale")
    || !Loader::includeModule("catalog")
) {
    die();
}

$siteId = Context::getCurrent()->getSite();

$request = Context::getCurrent()->getRequest();
$name = $request->get("NAME");
$phone = $request->get("PHONE");
$email = $request->get("EMAIL");
$productId = $request->get("PRODUCT_ID");

$currencyCode = CurrencyManager::getBaseCurrency();
DiscountCouponsManager::init();

// Создаём корзину с одним товаром
$basket = Basket::create($siteId);
$item = $basket->createItem('catalog', $productId);
$item->setFields([
    'QUANTITY' => 1,
    'LID' => $siteId,
    'PRODUCT_PROVIDER_CLASS' => '\Bitrix\Catalog\Product\CatalogProvider',
]);
$basket->save();

// Создаём новый заказ
$order = Order::create(
    $siteId,
    $USER->IsAuthorized() ? $USER->getId() : \CSaleUser::GetAnonymousUserID()
);
$order->setPersonTypeId(1);
$order->setField('CURRENCY', $currencyCode);
$order->setBasket($basket);

// Shipment
$shipmentCollection = $order->getShipmentCollection();
$shipment = $shipmentCollection->createItem();
$service = Delivery\Services\Manager::getById(Delivery\Services\EmptyDeliveryService::getEmptyDeliveryServiceId());
$shipment->setFields([
    'DELIVERY_ID' => $service['ID'],
    'DELIVERY_NAME' => $service['NAME'],
    'CURRENCY' => $order->getCurrency(),
]);
$shipmentItemCollection = $shipment->getShipmentItemCollection();
$shipmentItem = $shipmentItemCollection->createItem($item);
$shipmentItem->setQuantity($item->getQuantity());

// Payment
$paymentCollection = $order->getPaymentCollection();
$payment = $paymentCollection->createItem();
$paySystemService = PaySystem\Manager::getObjectById(PAY_SYSTEM_ID);
$payment->setFields([
    'PAY_SYSTEM_ID' => $paySystemService->getField('PAY_SYSTEM_ID'),
    'PAY_SYSTEM_NAME' => $paySystemService->getField('NAME'),
]);

// Устанавливаем свойства
$propetryCollection = $order->getPropertyCollection();
$nameProp = $propetryCollection->getPayerName();
$nameProp->setValue($name);
$phoneProp = $propetryCollection->getPhone();
$phoneProp->setValue($phone);
$emailProp = $propetryCollection->getUserEmail();
$emailProp->setValue($email);

// Сохраняем
$order->doFinalAction(true);
$result = $order->save();
$orderId = $order->getId();

if ($orderId > 0) {
    echo "Ваш заказ оформлен. Id заказа: " . $orderId;
} else {
    echo "Ошибка оформления";
}
