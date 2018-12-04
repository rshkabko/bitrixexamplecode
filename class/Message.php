<?php

namespace Local\Messages;

use \Bitrix\Main\Loader;
use \CFile;
use \CIBlockElement;
use \Flamix\Base\Log;
use \Exception;
use \Bitrix\Main\Mail\Event; 
use \Bitrix\Main\UserTable;

use \Bitrix\Main\Localization\Loc; 
Loc::loadMessages(__FILE__);

/**
 * Класс для работы с объявлениями
 */
class Message
{
	//логировать оишбки и замечания
	const LOG = true;

	//инфоблок сообщений
	const IBLOCK_TYPE = "messages";
	const IBLOCK_ID = 23;

	//метка прочитанное
	const VIEWED_ENUM_ID = 23;

	//почтовое событие на новое сообщение
	const ADD_MESS_EVENT = "NEW_MESS_ADD";

	/**
	 * Добавление нового сообщения по объявлению
	 *
	 * @param int adId - ID объявления
	 * @param string mess - Сообщение
	 * @param int senderId - ID отправителя
	 * @param int recieverId - ID получателя
	 *
	 * @example \Local\Messages\Message::add(156, "test", 2, 1);
	 *
	 * @return array - результат
	 */
	public static function add(int $adId, string $mess, int $senderId = null, $recieverId = null)
	{
		$arRes = array(
			"status" => "error",
			"mess" => Loc::getMessage("UNDEFINED_ERROR"),
		);

		try {
			if (!Loader::includeModule("iblock"))
				throw new Exception(Loc::getMessage("IBLOCK_IS_NOT_INCLUDED"));

			if (!$senderId) {
				global $USER;
				$senderId = $USER->GetId();
			}

			if (!$senderId)
				throw new Exception(Loc::getMessage("NO_USER"));
				
			if (!$adId)
				throw new Exception(Loc::getMessage("NO_AD_ID"));

			$arFilter = Array("ID" => $adId);
			$arSelect = array("ID", "PROPERTY_USER", "PROPERTY_ACCEPT_MESSAGES");
			$res = CIBlockElement::GetList(Array(), $arFilter, false, false, $arSelect);
			$arAd = $res->GetNext();

			if (!$arAd)
				throw new Exception(Loc::getMessage("AD_NOT_FOUND"));

			if (!$arAd["PROPERTY_ACCEPT_MESSAGES_VALUE"])
				throw new Exception(Loc::getMessage("AD_MESSAGES_IS_NOT_ENABLE"));

			if (!$recieverId)
				$recieverId = $arAd["PROPERTY_USER_VALUE"];

			if (!$recieverId)
				throw new Exception(Loc::getMessage("AD_RECIEVER_NOT_FOUND"));

			if ($senderId == $recieverId)
				throw new Exception(Loc::getMessage("SENDER_RECIEVER_EQUAL"));

			$arBlock = \Local\Messages\Block::get($senderId, $arAd["ID"], $recieverId);
			if ($arBlock)
				throw new Exception(Loc::getMessage("MESS_LINE_BLOCKED"));

			$el = new CIBlockElement;
			$arLoadProductArray = Array(
				"IBLOCK_ID" => self::IBLOCK_ID,
				"NAME" => Loc::getMessage("MESSAGE_FROM") . " " . ConvertTimeStamp(false, "FULL"),
				"ACTIVE_FROM" => ConvertTimeStamp(false, "FULL"),
				"PREVIEW_TEXT" => $mess,
				"PROPERTY_VALUES" => array(
					"AD" => $adId,
					"SENDER" => $senderId,
					"RECIEVER" => $recieverId,
					"PICTURE" => false,
					"FILE" => false,
				),
			);
			$newId = $el->Add($arLoadProductArray);
			if (!$newId)
				throw new Exception($el->LAST_ERROR);

			if (self::ADD_MESS_EVENT) {
				//получаем данные объявления джля формировки письма
				$arFilter = Array("ID" => $adId);
				$res = CIBlockElement::GetList(Array("SORT" => "ASC"), $arFilter);
				if ($ob = $res->GetNextElement()) { 
					$arAd = $ob->GetFields();
					$arAd["PROPERTIES"] = $ob->GetProperties();

					//формируем урл объявления
					$adUrl = \Local\Ads\Ad::getFormUrl($arAd);
				}

				//получаем почту получателя письма
				$dbUsers = UserTable::getList(Array(
					"select" => array("ID", "EMAIL"),
					"filter" => array("ID" => $recieverId),
				));
				$arUser = $dbUsers->fetch();

				//отправляем письмо
				$arEventFields = array(
					"EMAIL" => $arUser["EMAIL"],
					"AD_NAME" => $arAd["NAME"],
					"AD_URL" => $adUrl,
					"MESSAGE_URL" => "/personal/messages/?adid=" . $adId . "&userid=" . $senderId,
				);
				Event::send(array( 
					"EVENT_NAME" => self::ADD_MESS_EVENT, 
					"LID" => SITE_ID, 
					"C_FIELDS" => $arEventFields
				));
			}

			$arRes["status"] = "ok";
			$arRes["id"] = $newId;
			$arRes["mess"] = Loc::getMessage("MESS_ADD_OK");

		} catch (Exception $e) {
			$arRes["mess"] = $e->getMessage();
			if (self::LOG)
				Log::add("Ошибка добавления сообщения", array("method" => "add", "adId" => $adId, "mess" => $mess, "senderId" => $senderId, "recieverId" => $recieverId, "res" => $arRes), "Messages");
		}

		return $arRes;
	}

	/**
	 * Количество непрочитанных сообщений пользователя
	 *
	 * @param int userId - ID пользователя
	 *
	 * @example \Local\Messages\Message::getNewCount(1);
	 *
	 * @return int - Количество
	 */
	public static function getNewCount(int $userId = null)
	{
		if (!$userId) {
			global $USER;
			$userId = $USER->GetID();
		}

		if (!$userId)
			return 0;

		$arFilter = Array(
			"IBLOCK_ID" => self::IBLOCK_ID,
			"PROPERTY_RECIEVER" => $userId,
			"ACTIVE" => "Y",
			"PROPERTY_VIEWED" => false
		);
		$cnt = CIBlockElement::GetList(Array(), $arFilter, array());
		return intVal($cnt);
	}

	/**
	 * Список сообщений выбранной цепочки + данные объявления по цепочке
	 * 
	 * @param int userId - ID пользователя
	 * @param int adId - ID объявления
	 * @param int otherUserId - ID второго пользователя
	 * @param int lastUpd - метка времени от которой нужно получать сообщения
	 *
	 * @example \Local\Messages\Message::getLine(1, 154, 2);
	 *
	 * @return array - массив с сообщениями и данными объявления для цепочки
	 */
	public static function getLine(int $userId = null, int $adId, int $otherUserId, int $lastUpd = null)
	{
		if (!Loader::includeModule("iblock"))
			return array();

		if (!$userId) {
			global $USER;
			$userId = $USER->GetID();
		}

		if (!$userId)
			return array();

		$arMessages = array();
		$arAd = array();

		//сразу же получаемпрофили двух пользователей по переписке
		$arProfiles = \Local\Personal\User::adProfileData(array($userId, $otherUserId));

		//получаем все сообщения по объявлению с учетом получателя-отправителя
		$arFilter = Array(
			"IBLOCK_ID" => self::IBLOCK_ID,
			"ACTIVE" => "Y",
			"PROPERTY_AD" => $adId,
			array(
				"LOGIC" => "OR",
				array(
					"PROPERTY_SENDER" => $userId,
					"PROPERTY_RECIEVER" => $otherUserId,
				),
				array(
					"PROPERTY_SENDER" => $otherUserId,
					"PROPERTY_RECIEVER" => $userId,
				)
			),
		);

		//если есть метка посленей подгрузки - получаем сообщения только те, что изменены или добавлены позже
		if ($lastUpd)
			$arFilter[">TIMESTAMP_X"] = FormatDate("d.m.Y H:i:s", $lastUpd);

		$arSelect = array(
			"ID",
			"TIMESTAMP_X",
			"ACTIVE_FROM",
			"PROPERTY_AD",
			"PROPERTY_SENDER",
			"PROPERTY_RECIEVER",
			"PROPERTY_VIEWED",
			"PREVIEW_TEXT",
		);
		$arUserIds = array();
		$arAdsIds = array();
		$res = CIBlockElement::GetList(Array("ACTIVE_FROM" => "ASC"), $arFilter, false, false, $arSelect);
		while ($arFields = $res->GetNext()) {
			//формируем метку соленего изменения объявления
			$timestampU = MakeTimeStamp($arFields["TIMESTAMP_X"]);

			//добавляем подпись отправителя и получателя
			$senderTitle = $arProfiles[$arFields["PROPERTY_SENDER_VALUE"]]["TITLE"];
			$recieverTitle = $arProfiles[$arFields["PROPERTY_RECIEVER_VALUE"]]["TITLE"];

			//если сообщение предназначено текущему пользвоател и не прочитано - проставляем метку прочитанное
			if (!$arFields["PROPERTY_VIEWED_VALUE"] && $userId == $arFields["PROPERTY_RECIEVER_VALUE"]) {
				if (self::setViewed($arFields["ID"])) {
					$arFields["PROPERTY_VIEWED_VALUE"] = "Y";
				}
			}

			//пишем в результат
			$arMessages[] = array(
				"ID" => $arFields["ID"],
				"TIMESTAMP_X" => $arFields["TIMESTAMP_X"],
				"DATE" => $arFields["ACTIVE_FROM"],
				"TIMESTAMP_U" => $timestampU,
				"SENDER" => $arFields["PROPERTY_SENDER_VALUE"],
				"SENDER_TITLE" => $senderTitle,
				"RECIEVER" => $arFields["PROPERTY_RECIEVER_VALUE"],
				"RECIEVER_TITLE" => $recieverTitle,
				"TEXT" => $arFields["PREVIEW_TEXT"],
				"VIEWED" => ($arFields["PROPERTY_VIEWED_VALUE"] ? true : false),
			);
		}

		if (!$arMessages)
			return array();

		//если есть метка посленей подгрузки - не получаем объявления, оно ни уже получено ранее
		if (!$lastUpd) {
			//получем данные объявления
			$arFilterAds = Array(
				"IBLOCK_ID" => \Local\Ads\Ad::IBLOCK_ID,
				"ID" => $adId,
			);
			$res = CIBlockElement::GetList(Array(), $arFilterAds);
			while ($ob = $res->GetNextElement()) { 
				$arFields = $ob->GetFields();
				$arFields["PROPERTIES"] = $ob->GetProperties();
					
				//изображение
				$arFile = CFile::ResizeImageGet($arFields["PREVIEW_PICTURE"], array("width" => 145, "height" => 145), BX_RESIZE_IMAGE_EXACT);

				//подробная странциа
				$adUrl = \Local\Ads\Ad::getFormUrl($arFields);

				//город
				$cityId = $arFields["PROPERTIES"]["CITY"]["VALUE"];
				$arCity = \Local\Location::getById($cityId);

				//цены
				$arPrices = \Local\Ads\Ad::getPrice($arFields["ID"]);

				//тип организации
				$arOrgTypeId = $arFields["PROPERTIES"]["ORG_TYPE"]["VALUE"];
				$arOrgTypes = \Local\Ads\Directory::getList("ORG_TYPE", array(), array("ID" => $arOrgTypeId));
				$arOrgType = $arOrgTypes[0];

				//пишем в результат
				$arAd = array(
					"ID" => $arFields["ID"],
					"NAME" => $arFields["NAME"],
					"PICTURE" => $arFile["src"],
					"DETAIL_PAGE_URL" => $adUrl,

					"STATUS" => $arFields["PROPERTIES"]["STATUS"]["VALUE_ENUM_ID"],

					"PHONE" => $arFields["PROPERTIES"]["PHONE"]["VALUE"],
					"ADDRESS" => $arFields["PROPERTIES"]["ADDRESS"]["VALUE"],
					"CITY" => $cityId,
					"CITY_NAME" => $arCity["NAME"],

					"ORG_TYPE" => $arOrgTypeId,
					"ORG_TYPE_NAME" => $arOrgType["NAME"],

					"PRICES" => $arPrices,
				);
			}

			if (!$arAd)
				return array();
		}

		return array(
			"AD" => $arAd,
			"MESSAGES" => $arMessages,
		);
	}

	/**
	 * Установка статуса Просмотрено
	 *
	 * @param int messId - ID сообщения
	 * @param bool viewed - просмотрено/нет
	 *
	 * @example \Local\Messages\Message::setViewed(1645, false);
	 *
	 * @return bool - результат
	 */
	public static function setViewed(int $messId, bool $viewed = true)
	{
		if (!Loader::includeModule("iblock"))
			return false;

		$el = new CIBlockElement;
		$arLoadProductArray = \Flamix\Helpers\Iblock::makeUpdateArray($messId);
		$arLoadProductArray["PROPERTY_VALUES"]["VIEWED"] = ($viewed ? self::VIEWED_ENUM_ID : false);
		$upd = $el->Update($messId, $arLoadProductArray);
		if ($upd) {
			return false;
		} else {
			if (self::LOG)
				Log::add("Ошибка установки статуса прочитано", array("method" => "setViewed", "messId" => $messId, "viewed" => $viewed, "mess" => $el->LAST_ERROR), "Messages");
			return true;
		}
		// CIBlockElement::SetPropertyValuesEx($messId, self::IBLOCK_ID, array("VIEWED" => ($viewed ? self::VIEWED_ENUM_ID : false)));

		return true;
	}

	/**
	 * Список цепочек сообщений
	 * По сути это список объявлений по которым есть сообщения у пользователя
	 *
	 * @param int userId - ID пользователя
	 * @param string type - тип списка (all/my)
	 * @param int lastUpd - метка времени от которой нужно получать сообщения
	 *
	 * @example \Local\Messages\Message::getLines(1, "my", 1543318870);
	 *
	 * @return array - массив объявлений по которым есть сообщения у пользователя
	 */
	public static function getLines(int $userId = null, string $type = "all", int $lastUpd = null)
	{
		if (!Loader::includeModule("iblock"))
			return array();

		if (!$userId) {
			global $USER;
			$userId = $USER->GetID();
		}

		if (!$userId)
			return array();

		$arMessages = array();
		$arAds = array();

		$arFilter = Array(
			"IBLOCK_ID" => self::IBLOCK_ID,
			"ACTIVE" => "Y",
		);
		//если тип списка - только по моис объявлениям
		if ($type == "my") {
			//получаем мои объявления
			$arFilterAds = Array(
				"IBLOCK_ID" => \Local\Ads\Ad::IBLOCK_ID,
				"PROPERTY_USER" => $userId
			);
			$arSelectAds = array(
				"ID",
				"NAME",
				"PREVIEW_PICTURE",
				"DETAIL_TEXT",
			);
			$res = CIBlockElement::GetList(Array(), $arFilterAds, false, false, $arSelectAds);
			while ($arFields = $res->GetNext()) {
				//обрабатываем картинку
				$arFile = CFile::ResizeImageGet($arFields["PREVIEW_PICTURE"], array("width" => 145, "height" => 145), BX_RESIZE_IMAGE_EXACT);
				$arFields["PREVIEW_PICTURE_RESIZE"] = $arFile["src"];

				//пишем для дальнейшей работы
				$arAds[$arFields["ID"]] = $arFields;
			}
			//если нет ни одного моего объявления - значит и сообщений быть не может
			if (!$arAds)
				return array();

			//в фильтр добавляем метку с объявлениями
			$arFilter["PROPERTY_AD"] = array_keys($arAds);
		} else {
			//если тип списка по всех объявлениям - получаем все, где текущий пользователь либо отправитель либо получатель
			$arFilter[] = array(
				"LOGIC" => "OR",
				array("PROPERTY_SENDER" => $userId),
				array("PROPERTY_RECIEVER" => $userId)
			);
		}

		//если есть метка посленей подгрузки - получаем сообщения только те, что изменены или добавлены позже
		if ($lastUpd)
			$arFilter[">TIMESTAMP_X"] = FormatDate("d.m.Y H:i:s", $lastUpd);

		$arSelect = array(
			"ID",
			"TIMESTAMP_X",
			"ACTIVE_FROM",
			"PROPERTY_AD",
			"PROPERTY_SENDER",
			"PROPERTY_RECIEVER",
			"PROPERTY_VIEWED",
			"PREVIEW_TEXT",
		);
		$arUserIds = array();
		$arAdsIds = array();
		$res = CIBlockElement::GetList(Array("ACTIVE_FROM" => "DESC"), $arFilter, false, false, $arSelect);
		while ($arFields = $res->GetNext()) {
			$adId = $arFields["PROPERTY_AD_VALUE"];

			//определяем второго пользователя по сообщению, первый - он сам
			$otherUser = $arFields["PROPERTY_SENDER_VALUE"];
			if ($otherUser == $userId)
				$otherUser = $arFields["PROPERTY_RECIEVER_VALUE"];

			//пишем его
			$arFields["OTHER_USER"] = $otherUser;

			//нам нужно только последнее сообщение по дате, так что если одно уже есть - остальные не обрабатываем
			if ($arMessages[$adId . "_" . $otherUser])
				continue;

			//пишем получателей для дальнейшей обработки
			if (!in_array($arFields["PROPERTY_SENDER_VALUE"], $arUserIds))
				$arUserIds[] = $arFields["PROPERTY_SENDER_VALUE"];

			// if (!in_array($arFields["PROPERTY_RECIEVER_VALUE"], $arUserIds))
			// 	$arUserIds[] = $arFields["PROPERTY_RECIEVER_VALUE"];

			//если тип не мои - получаем все объявления по сообщениям
			//для мои этого не нужно - они получены выше
			if ($type != "my" && !in_array($adId, $arAdsIds))
				$arAdsIds[] = $adId;

			//формируем метку соленего изменения объявления
			$arFields["TIMESTAMP_U"] = MakeTimeStamp($arFields["TIMESTAMP_X"]);

			//пишем в результат
			$arMessages[$adId . "_" . $otherUser] = $arFields;
		}

		if (!$arMessages)
			return array();

		//если нужно получить объявления по сообщениям - получаем
		if ($type != "my" && $arAdsIds) {
			$arFilterAds = Array(
				"IBLOCK_ID" => \Local\Ads\Ad::IBLOCK_ID,
				"ID" => $arAdsIds,
			);
			$arSelectAds = array(
				"ID",
				"NAME",
				"PREVIEW_PICTURE",
				"DETAIL_TEXT",
			);
			$res = CIBlockElement::GetList(Array(), $arFilterAds, false, false, $arSelectAds);
			while ($arFields = $res->GetNext()) {
				$arFile = CFile::ResizeImageGet($arFields["PREVIEW_PICTURE"], array("width" => 145, "height" => 145), BX_RESIZE_IMAGE_EXACT);
				$arFields["PREVIEW_PICTURE_RESIZE"] = $arFile["src"];

				$arAds[$arFields["ID"]] = $arFields;
			}
		}

		//получаем профили отправителей сообщений
		if ($arUserIds)
			$arProfiles = \Local\Personal\User::adProfileData($arUserIds);

		//дописываем данные объявлений и имя отправителя к мообщениям
		foreach ($arMessages as &$arMessage) {
			$arMessage["AD"] = $arAds[$arMessage["PROPERTY_AD_VALUE"]];
			$arMessage["SENDER_TITLE"] = $arProfiles[$arMessage["PROPERTY_SENDER_VALUE"]]["TITLE"];
		}

		return $arMessages;
	}

	/**
	 * Список деактивированных сообщений
	 * По сути это список объявлений по которым есть сообщения у пользователя
	 *
	 * @param int userId - ID пользователя
	 * @param string type - тип списка (all/my)
	 * @param int lastUpd - метка времени от которой нужно получать сообщения
	 *
	 * @example \Local\Messages\Message::getDeletedLinesIds(1, "my", 1543318870);
	 *
	 * @return array - массив объявлений по которым есть сообщения у пользователя
	 */
	public static function getDeletedLinesIds(int $userId = null, string $type = "all", int $lastUpd = null)
	{
		if (!Loader::includeModule("iblock"))
			return array();

		if (!$userId) {
			global $USER;
			$userId = $USER->GetID();
		}

		if (!$userId)
			return array();

		$arMessages = array();
		$arAds = array();

		$arFilter = Array(
			"IBLOCK_ID" => self::IBLOCK_ID,
			"ACTIVE" => "N",
		);
		//если тип списка - только по моис объявлениям
		if ($type == "my") {
			//получаем мои объявления
			$arFilterAds = Array(
				"IBLOCK_ID" => \Local\Ads\Ad::IBLOCK_ID,
				"PROPERTY_USER" => $userId
			);
			$arSelectAds = array(
				"ID",
			);
			$res = CIBlockElement::GetList(Array(), $arFilterAds, false, false, $arSelectAds);
			while ($arFields = $res->GetNext()) {
				//пишем для дальнейшей работы
				$arAdsIds[] = $arFields["ID"];
			}
			//если нет ни одного моего объявления - значит и сообщений быть не может
			if (!$arAdsIds)
				return array();

			//в фильтр добавляем метку с объявлениями
			$arFilter["PROPERTY_AD"] = $arAdsIds;
		} else {
			//если тип списка по всех объявлениям - получаем все, где текущий пользователь либо отправитель либо получатель
			$arFilter[] = array(
				"LOGIC" => "OR",
				array("PROPERTY_SENDER" => $userId),
				array("PROPERTY_RECIEVER" => $userId)
			);
		}

		//если есть метка посленей подгрузки - получаем сообщения только те, что изменены или добавлены позже
		if ($lastUpd)
			$arFilter[">TIMESTAMP_X"] = FormatDate("d.m.Y H:i:s", $lastUpd);

		$arSelect = array(
			"ID",
			"TIMESTAMP_X",
			"PROPERTY_AD",
			"PROPERTY_SENDER",
			"PROPERTY_RECIEVER",
		);
		$res = CIBlockElement::GetList(Array("ACTIVE_FROM" => "DESC"), $arFilter, false, false, $arSelect);
		while ($arFields = $res->GetNext()) {
			$adId = $arFields["PROPERTY_AD_VALUE"];

			//определяем второго пользователя по сообщению, первый - он сам
			$otherUser = $arFields["PROPERTY_SENDER_VALUE"];
			if ($otherUser == $userId)
				$otherUser = $arFields["PROPERTY_RECIEVER_VALUE"];

			//формируем метку соленего изменения объявления
			$arFields["TIMESTAMP_U"] = MakeTimeStamp($arFields["TIMESTAMP_X"]);

			$lineId = $adId . "_" . $otherUser;

			//нам нужно только последнее сообщение по дате, так что если одно уже есть - остальные не обрабатываем
			if (in_array($lineId, haystack))
				continue;

			//пишем в результат
			$arMessages[$lineId] = array(
				"LINE_ID" => $lineId,
				"TIMESTAMP_U" => $arFields["TIMESTAMP_U"],
			);
		}

		return $arMessages;
	}

	/**
	 * Удаление сообщений
	 *
	 * @param int userId - ID пользователя
	 * @param array arLines - массив с массивами вида (ad => adID, user => userId)
	 *
	 * @example \Local\Messages\Message::deleteLines(1, "my");
	 *
	 * @return array - результат
	 */
	public static function deleteLines(int $userId = null, array $arLines)
	{
		$arRes = array(
			"status" => "error",
			"mess" => Loc::getMessage("UNDEFINED_ERROR"),
		);

		try {
			if (!Loader::includeModule("iblock"))
				throw new Exception(Loc::getMessage("IBLOCK_IS_NOT_INCLUDED"));

			if (!$userId) {
				global $USER;
				$userId = $USER->GetId();
			}

			if (!$userId)
				throw new Exception(Loc::getMessage("NO_DELETE_USER"));
			
			$arFilter = Array(
				"IBLOCK_ID" => self::IBLOCK_ID,
				"ACTIVE" => "Y",
			);
			//удаление происходит цепочками, поэтому получаем все сообщения, что относятся к цепочке
			$arLinesFilter = array(
				"LOGIC" => "OR",
			);
			foreach ($arLines as $arLine) {
				$arLinesFilter[] = array(
					"PROPERTY_AD" => $arLine["ad"],//для объявления
					array(//где текущий пользователь другой пользователь проходит как отправитель или получатель
						"LOGIC" => "OR",
						array(
							"PROPERTY_SENDER" => $userId,
							"PROPERTY_RECIEVER" => $arLine["user"],
						),
						array(
							"PROPERTY_SENDER" => $arLine["user"],
							"PROPERTY_RECIEVER" => $userId,
						),
					)
				);
			}
			$arFilter[] = $arLinesFilter;
			$arSelect = array(
				"ID",
			);
			$lastError = "";
			//получаем и удаляем
			$res = CIBlockElement::GetList(Array(), $arFilter, flase, false, $arSelect);
			while ($arFields = $res->GetNext()) { 
				$el = new CIBlockElement;
				$upd = $el->Update($arFields["ID"], Array("ACTIVE" => "N"));
				if (!$upd)
					$lastError = $el->LAST_ERROR;
			}

			if (strlen($lastError) > 0)
				throw new Exception($lastError);

			$arRes["status"] = "ok";
			$arRes["mess"] = Loc::getMessage("MESS_DELETE_OK");

		} catch (Exception $e) {
			$arRes["mess"] = $e->getMessage();
			if (self::LOG)
				Log::add("Ошибка удаления сообщений", array("method" => "deleteLines", "userId" => $userId, "arLines" => $arLines, "res" => $arRes), "Messages");
		}

		return $arRes;
	}

	/**
	 * Удаление сообщений пользователя
	 *
	 * @param int userId - ID пользователя
	 *
	 * @example \Local\Messages\Message::deleteLines(1);
	 *
	 * @return array - результат
	 */
	public static function deactivateUser(int $userId = null)
	{
		$arRes = array(
			"status" => "error",
			"mess" => Loc::getMessage("UNDEFINED_ERROR"),
		);

		try {
			if (!Loader::includeModule("iblock"))
				throw new Exception(Loc::getMessage("IBLOCK_IS_NOT_INCLUDED"));

			if (!$userId) {
				global $USER;
				$userId = $USER->GetId();
			}

			if (!$userId)
				throw new Exception(Loc::getMessage("NO_DELETE_USER"));
			
			//получаем сообщения что относятся к пользователю
			$arFilter = Array(
				"IBLOCK_ID" => self::IBLOCK_ID,
				"ACTIVE" => "Y",
				array(
					array(
						"LOGIC" => "OR",
						array(
							"PROPERTY_SENDER" => $userId,
						),
						array(
							"PROPERTY_RECIEVER" => $userId,
						),
					)
				)
			);
			$arSelect = array(
				"ID",
			);
			$lastError = "";
			//получаем и удаляем
			$res = CIBlockElement::GetList(Array(), $arFilter, flase, false, $arSelect);
			while ($arFields = $res->GetNext()) { 
				$el = new CIBlockElement;
				$upd = $el->Update($arFields["ID"], Array("ACTIVE" => "N"));
				if (!$upd)
					$lastError = $el->LAST_ERROR;
			}

			if (strlen($lastError) > 0)
				throw new Exception($lastError);

			$arRes["status"] = "ok";
			$arRes["mess"] = Loc::getMessage("MESS_DELETE_OK");

		} catch (Exception $e) {
			$arRes["mess"] = $e->getMessage();
			if (self::LOG)
				Log::add("Ошибка удаления сообщений", array("method" => "deactivateUser", "id" => $userId, "res" => $arRes), "Messages");
		}

		return $arRes;
	}
}
