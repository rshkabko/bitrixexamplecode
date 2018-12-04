<?php

namespace Local\Messages;

use \Bitrix\Main\Loader;
use \Flamix\Base\Log;
use \CIBlockElement;
use \Exception;

use \Bitrix\Main\Localization\Loc; 
Loc::loadMessages(__FILE__);

/**
 * Класс для работы с блокировками (черным списком)
 */
class Block
{
	//логировать оишбки и замечания
	const LOG = true;

	const IBLOCK_ID = 24;

	/**
	 * Добавление новой блокировки по сообщениям
	 *
	 * @param int userId - ID пользователя
	 * @param int adId - ID объявления
	 * @param int blockUserId - ID заблокированного пользователя
	 *
	 * @example \Local\Messages\Block::add(1, 156, 2);
	 *
	 * @return array - результат
	 */
	public static function add(int $userId = null, int $adId, int $blockUserId)
	{
		$arRes = array(
			"status" => "error",
			"mess" => Loc::getMEssage("UNDEFINED_ERROR"),
		);

		try {
			if (!Loader::includeModule("iblock"))
				throw new Exception(Loc::getMessage("IBLOCK_IS_NOT_INCLUDED"));

			if (!$userId) {
				global $USER;
				$userId = $USER->GetId();
			}

			if (!$userId)
				throw new Exception(Loc::getMessage("NO_USER"));
				
			if (!$adId)
				throw new Exception(Loc::getMessage("NO_AD_ID"));
				
			if (!$blockUserId)
				throw new Exception(Loc::getMessage("NO_BLOCK_USER_ID"));

			if (self::get($userId, $adId, $blockUserId)) {
				$arRes["status"] = "ok";
				throw new Exception(Loc::getMessage("ALREADY_BLOCKED"));
			}

			$el = new CIBlockElement;
			$arLoadProductArray = Array(
				"IBLOCK_ID" => self::IBLOCK_ID,
				"NAME" => Loc::getMessage("BLOCK_FROM") . " " . ConvertTimeStamp(false, "FULL"),
				"PROPERTY_VALUES" => array(
					"AD" => $adId,
					"USER" => $userId,
					"BLOCKED_USER" => $blockUserId,
				),
			);
			$newId = $el->Add($arLoadProductArray);
			if (!$newId)
				throw new Exception($el->LAST_ERROR);

			$arFilter = Array(
				"IBLOCK_ID" => \Local\Messages\Message::IBLOCK_ID,
				"ACTIVE" => "Y",
				"PROPERTY_VIEWED" => false,
				"PROPERTY_AD" => $adId,
				array(
					"LOGIC" => "OR",
					array(
						"PROPERTY_SENDER" => $userId,
						"PROPERTY_RECIEVER" => $blockUserId,
					),
					array(
						"PROPERTY_SENDER" => $blockUserId,
						"PROPERTY_RECIEVER" => $userId,
					),
				),
			);
			$arSlect = array(
				"ID",
			);
			$res = CIBlockElement::GetList(Array("SORT" => "ASC"), $arFilter);
			while ($arFields = $res->GetNext()) { 
				CIBlockElement::SetPropertyValuesEx($arFields["ID"], \Local\Messages\Message::IBLOCK_ID, array("VIEWED" => \Local\Messages\Message::VIEWED_ENUM_ID));
			}

			$arRes["status"] = "ok";
			$arRes["id"] = $newId;
			$arRes["mess"] = Loc::getMessage("BLOCK_ADD_OK");

		} catch (Exception $e) {
			$arRes["mess"] = $e->getMessage();
			if (self::LOG)
				Log::add("Ошибка добавления блокировки", array("method" => "add", "userId" => $userId, "adId" => $adId, "blockUserId" => $blockUserId), "MessagesBlock");
		}

		return $arRes;
	}

	/**
	 * Получение блокировки
	 *
	 * @param int userId - ID пользователя
	 * @param int adId - ID объявления
	 * @param int blockUserId - ID заблокированного пользователя
	 *
	 * @example \Local\Messages\Block::get(1, 157, 2);
	 *
	 * @return фккфн - данные блокировки, если она есть
	 */
	public static function get(int $userId = null, int $adId, int $blockUserId)
	{
		if (!Loader::includeModule("iblock"))
			return false;

		if (!$userId) {
			global $USER;
			$userId = $USER->GetId();
		}

		if (!$userId)
			return false;

		$arFilter = Array(
			"IBLOCK_ID" => self::IBLOCK_ID,
			"ACTIVE" => "Y",
			"PROPERTY_AD" => $adId,
			array(
				"LOGIC" => "OR",
				array(
					"PROPERTY_USER" => $userId,
					"PROPERTY_BLOCKED_USER" => $blockUserId,
				),
				array(
					"PROPERTY_USER" => $blockUserId,
					"PROPERTY_BLOCKED_USER" => $userId,
				),
			),
		);
		
		$arSelect = array(
			"ID",
			"PROPERTY_USER",
			"PROPERTY_AD",
			"PROPERTY_BLOCKED_USER",
		);
		$res = CIBlockElement::GetList(Array("SORT" => "ASC"), $arFilter, false, false, $arSelect);
		if ($arFields = $res->GetNext()) {
			return array(
				"ID" => $arFields["ID"],
				"USER" => $arFields["PROPERTY_USER_VALUE"],
				"AD" => $arFields["PROPERTY_AD_VALUE"],
				"BLOCKED_USER" => $arFields["PROPERTY_BLOCKED_USER_VALUE"],
				"CAN_UNBLOCK" => ($arFields["PROPERTY_USER_VALUE"] == $userId ? true : false),
			);
		}

		return false;
	}

	/**
	 * Удаление блокировки
	 *
	 * @param int blockId - ID блокировки
	 *
	 * @example \Local\Messages\Block::delete(1577);
	 *
	 * @return array - результат
	 */
	public static function delete(int $blockId)
	{
		$arRes = array(
			"status" => "error",
			"mess" => Loc::getMessage("UNDEFINED_ERROR"),
		);

		try {
			if (!Loader::includeModule("iblock"))
				throw new Exception(Loc::getMessage("IBLOCK_IS_NOT_INCLUDED"));

			if (!$blockId)
				throw new Exception(Loc::getMessage("NO_BLOCK_ID"));

			$arFilter = Array(
				"IBLOCK_ID" => self::IBLOCK_ID,
				"ID" => $blockId,
			);
			$arSelect = array(
				"ID"
			);
			$res = CIBlockElement::GetList(Array("SORT" => "ASC"), $arFilter, false, false, $arSelect);
			$arFields = $res->GetNext();
			if (!$arFields)
				throw new Exception(Loc::getMessage("BLOCK_NOT_FOUND"));

			$del = CIBlockElement::Delete($blockId);
			if (!$del)
				throw new Exception(Loc::getMessage("BLOCK_DELETE_ERROR"));

			$arRes["status"] = "ok";
			$arRes["mess"] = Loc::getMessage("BLOCK_DELETE_OK");

		} catch (Exception $e) {
			$arRes["mess"] = $e->getMessage();
			if (self::LOG)
				Log::add("Ошибка удаления блокировки", array("method" => "delete", "blockId" => $blockId), "MessagesBlock");
		}

		return $arRes;
	}
}
