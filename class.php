<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\SystemException;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Sale\Order;
use Bitrix\Sale\Basket;
use Bitrix\Sale\Fuser;
use Bitrix\Main\Context;
use Bitrix\Sale\Discount;
use Bitrix\Sale\Discount\Context\Fuser as CFUser;
use CSaleOrder;

class CatalogOrder extends CBitrixComponent implements Controllerable
{

    /**
     * ID информационного блока с товарами
     */
    const DEFAULT_CATALOG_ID = 79;

    /**
     * ID информационного блока с торговыми предложениями
     */
    const DEFAULT_CATALOG_TORG_ID = 80;

    /**
     * Статус успешного добавления к заказу
     */
    const STATUS_ORDER_SUCCESS = 'yes';

    /**
     * Статус ошибки при добавлении к заказу
     */
    const STATUS_ORDER_ERROR = 'fail';

    /**
     * Статус "Товары из разных коллекций в корзине"
     */
    const STATUS_IS_OTHER_COLLECTION = 'product_isset';


    public function onPrepareComponentParams($arParams)
    {
        if (empty($arParams["IBLOCK_ID"]))
        {
            $arParams["IBLOCK_ID"] = self::DEFAULT_CATALOG_ID;
        }

        if (empty($arParams["IBLOCK_TORG_ID"]))
        {
            $arParams["IBLOCK_TORG_ID"] = self::DEFAULT_CATALOG_TORG_ID;
        }

        return $arParams;
    }

    public function configureActions()
    {
        return [
            'order' => [
                'prefilters' => [
                    new ActionFilter\HttpMethod(
                        array(ActionFilter\HttpMethod::METHOD_GET, ActionFilter\HttpMethod::METHOD_POST)
                    ),
                    new ActionFilter\Csrf(),
                ],
                'postfilters' => []
            ]
        ];
    }

    public function executeComponent()
    {
        global $USER;

        if (!CModule::IncludeModule("sale") && !$USER->IsAuthorised())
        {
            return false;
        }

        $arFilter = Array(
            "USER_ID" => $USER->GetID(),
            "LID" => SITE_ID,
            "CANCELED" => "N"
        );
        $rsSales = CSaleOrder::GetList(array(), $arFilter);

        $this->arResult["ORDER_ID"] = array();
        $this->arResult["STATUS_ID"] = array();
        $index = 0;
        while ($arSales = $rsSales->Fetch())
        {
            if ($arSales["STATUS_ID"] !== "F")
            {
                $this->arResult["ORDER"][$index]["ORDER_ID"] = $arSales["ID"];
                $this->arResult["ORDER"][$index]["STATUS_ID"] = $arSales["STATUS_ID"];
                $this->arResult["ORDER"][$index]["DATE_INSERT"] = $arSales["DATE_INSERT"];
                $this->arResult["ORDER"][$index]["PRICE"] = $arSales["PRICE"];
                $index++;
            }
        }

        $this->IncludeComponentTemplate();
    }

    public function orderAction($orderId = '')
    {

        $result = [
            "status" => 'fail'
        ];

        $iBlockId = $this->arParams["IBLOCK_ID"];
        $iBlockTorgId = $this->arParams["IBLOCK_TORG_ID"];

        if ($orderId > 0)
        {
            try {
                $arDiscount = $this->getDiscountForCurrentBasket();

                $arProductsID = $this->getProductIdFromBasket();
                $currentCollection = $this->getCatalogSezonProperty($iBlockId, $iBlockTorgId, $arProductsID);

                $arProductsID = $this->getProductIdFromBasket($orderId);
                $oldCollection = $this->getCatalogSezonProperty($iBlockId, $iBlockTorgId, $arProductsID);

                if ($currentCollection != null && $oldCollection != null && $currentCollection === $oldCollection)
                {
                    $currentBasket = CSaleBasket::GetList(($by = "NAME"), ($order = "ASC"), array("FUSER_ID" => CSaleBasket::GetBasketUserID(), "LID" => SITE_ID, "ORDER_ID" => "NULL"));

                    while ($resBasket = $currentBasket->GetNext())
                    {
                        if (array_key_exists ($resBasket["ID"], $arDiscount))
                        {
                            $arBasketFields["PRICE"] = $arDiscount[$resBasket["ID"]];
                            CSaleBasket::Update($resBasket["ID"], $arBasketFields);
                            $resBasket["PRICE"] = $arBasketFields["PRICE"];
                        }

                        $oldBasket = CSaleBasket::GetList(($by = "NAME"), ($order = "ASC"), array
                        (
                            "FUSER_ID" => CSaleBasket::GetBasketUserID(),
                            "LID" => SITE_ID,
                            "ORDER_ID" => $orderId,
                            "NAME" => $resBasket["NAME"],
                            "PRICE" => $resBasket["PRICE"]
                        ));

                        if ($resOldBasket = $oldBasket->GetNext())
                        {
                            $newQuantity = $resBasket["QUANTITY"] + $resOldBasket["QUANTITY"];

                            $arFields = array(
                                "QUANTITY" => $newQuantity,
                                "ORDER_ID" => $orderId,
                                "DELAY" => "N",
                                "PRICE" => $resBasket["PRICE"],
                                "PRODUCT_PROVIDER_CLASS" => ''
                            );
                            CSaleBasket::Update($resOldBasket["ID"], $arFields);
                            CSaleBasket::Delete($resBasket["ID"]);
                        }
                    }

                    CSaleBasket::OrderBasket($orderId, CSaleBasket::GetBasketUserID(), SITE_ID);

                    $result = [
                        "status" => self::STATUS_ORDER_SUCCESS
                    ];
                }
                else
                {
                    $result = [
                        "status" => self::STATUS_IS_OTHER_COLLECTION
                    ];
                }
            }
            catch (Exception $ex)
            {
                $result = [
                    "status" => self::STATUS_ORDER_ERROR
                ];
            }
        }


        return $result;
    }

    /**
     * Получить ID товаров корзины
     *
     * @param string $orderID - ID заказа
     * @return array
     */
    private function getProductIdFromBasket($orderID = "NULL")
    {
        $result = [];

        $currentBasket = CSaleBasket::GetList(($by = "NAME"), ($order = "ASC"), array("FUSER_ID" => CSaleBasket::GetBasketUserID(), "LID" => SITE_ID, "ORDER_ID" => $orderID));

        while ($resBasket = $currentBasket->GetNext())
        {
            $result[] = $resBasket["PRODUCT_ID"];
        }

        return $result;
    }

    /**
     * Получить скидки текущей корзины
     *
     * @return array
     */
    private function getDiscountForCurrentBasket()
    {
        $arDiscount = [];

        $basket = Basket::loadItemsForFUser(
            Fuser::getId(),
            Context::getCurrent()->getSite()
        ); // текущая корзина

        $fuser = new CFUser($basket->getFUserId(true));
        $discounts = Discount::buildFromBasket($basket, $fuser);
        $discounts->calculate();
        $result = $discounts->getApplyResult(true);

        $prices = $result['PRICES']['BASKET']; // цены товаров с учетом скидки

        foreach($prices as $key => $price)
        {
            $arDiscount[$key] = $price["PRICE"];
        }

        return $arDiscount;
    }

    /**
     * Получить название коллекции для корзины или null, если в корзине есть товары
     * из разных коллекций (сезонов)
     *
     * @param $ibID
     * @param $ibTorgID
     * @param $arProductsID
     * @return mixed|null
     */
    private function getCatalogSezonProperty($ibID, $ibTorgID, $arProductsID)
    {
        $result = [];

        $iBlockElem = CIBlockElement::GetList(
            array(),
            array(
                "IBLOCK_ID" => $ibTorgID,
                "ID" => $arProductsID
            ),
            false,
            false,
            array("PROPERTY_CML2_LINK")
        );

        while ($dbRes = $iBlockElem->Fetch())
        {
            $arTorgProductID[] = $dbRes["PROPERTY_CML2_LINK_VALUE"];
        }

        $iBlockElemProd = CIBlockElement::GetList(
            array(),
            array(
                "IBLOCK_ID" => $ibID,
                "ID" => $arTorgProductID
            ),
            false,
            false,
            array("PROPERTY_SEZON")
        );

        while ($sezonIBlock = $iBlockElemProd->Fetch())
        {
            $result[] = $sezonIBlock["PROPERTY_SEZON_VALUE"];
        }

       return $this->compareSeazonsFromElements($result);

    }

    /**
     * Сравнить названия сезонов в массиве и вернуть название сезона,
     * если они одинаковы или null, если есть различия
     *
     * @param $arSeazons
     * @return mixed|null
     */
    private function compareSeazonsFromElements($arSeazons)
    {
        if (!empty($arSeazons))
        {
            $arFirstElement[] = $arSeazons[0];
            $arOtherElements = array_diff($arSeazons, $arFirstElement);
        }

        if (!empty($arOtherElements))
        {
            return null;
        }
        else
        {
            return $arSeazons[0];
        }
    }
}
?>