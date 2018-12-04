<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

header("Content-Type: application/json");

use \Bitrix\Main\Application;
$request = Application::getInstance()->getContext()->getRequest();

use \Bitrix\Main\Localization\Loc; 
Loc::loadMessages(__FILE__);

use \Local\Ads\Favourite;

$arRes = array(
	"status" => "error",
	"mess" => "",
);

if ($request->getPost("action") == "add") {
	$arRes = Favourite::add($request->getPost("id"));

} elseif ($request->getPost("action") == "favourite-multi-del") {
	foreach ($request->getPost("ids") as $k => $id) {
		$arTmpRes = Favourite::del($id);
		if ($arTmpRes["status"] != "ok" || !$k)
			$arRes = $arTmpRes;
	}

} elseif ($request->getPost("action") == "del") {
	$arRes = Favourite::del($request->getPost("id"));

} else {
	$arRes["mess"] = Loc::getMessage("WRONG_ACTION");
}

global $APPLICATION;
$APPLICATION->RestartBuffer();
echo json_encode($arRes);
die();
