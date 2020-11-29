<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();?>
<?php
$this->setFrameMode(false);

if (!empty($arResult["ORDER"])):?>
    <div class="okno-back"></div>
    <div id="okno">
        <span href="#" class="close"></span>
        <div class="form_head">
            <h2><?=GetMessage("CHOOSE_ORDER")?></h2>
        </div>

        <div class="add-for-order-catalog">
                <div class="order-in-catalog">
                    <div class="order-list-up"><span class="grey"><?=GetMessage("ISSET_ORDER")?></span></div>
                    <ul class="order-list">
                        <?foreach ($arResult["ORDER"] as $key=>$arOrder):?>
                            <li data-id="<?=$arOrder["ORDER_ID"]?>">
                                <?=GetMessage("ORDER_INFO", array(
                                        "#ORDER_ID#" => $arOrder["ORDER_ID"],
                                        "#ORDER_DATE#" => $arOrder["DATE_INSERT"],
                                        "#ORDER_PRICE#" => $arOrder["PRICE"]
                                ))?>
                            </li>
                        <?endforeach;?>
                    </ul>
                </div>
                <div class="limit"></div>
                    <span class="add-to-order-catalog transition_bg">
                                            <i></i><span><?=GetMessage("ADD_TO_ORDER")?></span>
                                        </span>
        </div>
    </div>
    <a class="open-choose btn btn-default" href="#okno"><?=GetMessage("ADD_TO_ORDER")?></a>
<?endif;?>

