<?php

namespace Local;

use \Bitrix\Main\Loader; 
use \Bitrix\Main\Data\Cache;
use \CIBlockElement;

/**
 * Класс для работы с местоположениями
 */
class Location
{
	const IBLOCK_ID = 3;

	const CACHE = true;
	const CACHE_TIME = 2678400;

	/**
	 * Получение списка местоположений в структурированном виде
	 *
	 * @example \Local\Location::getList();
	 *
	 * @return array - Структурированный масив местоположений
	 */
	public static function getList()
	{
		$arItems = array();

		$cache = Cache::createInstance(); 
		if ($cache->initCache(self::CACHE_TIME, "getList", "/dev/location/") && self::CACHE) { 
			$arItems = $cache->getVars();
		} elseif ($cache->startDataCache() || !self::CACHE) {
			if (!self::IBLOCK_ID || !Loader::includeModule("iblock")) {
				$cache->abortDataCache();
				return false;
			}

			$arChilds = array();

			$arOrder = array(
				"SORT" => "ASC",
				"NAME" => "ASC",
				"ID" => "ASC",
			);
			$arFilter = Array(
				"IBLOCK_ID" => self::IBLOCK_ID,
				"ACTIVE" => "Y"
			);
			$arSelect = array(
				"ID",
				"NAME",
				"CODE",
				"PROPERTY_PARENT",
				"PROPERTY_BIG",
			);
			//Добавляем к селекту параметры по сео
			$arSelect = array_merge($arSelect, Seo\IblockParams::$arCodes);
			$res = CIBlockElement::GetList($arOrder, $arFilter, false, false, $arSelect);
			while ($arFields = $res->GetNext()) {
				if (!$arFields["PROPERTY_PARENT_VALUE"])
					$arItems[$arFields["ID"]] = $arFields;
				else
					$arChilds[] = $arFields;
			}

			foreach ($arChilds as $arItem) {
				if (!$arItems[$arItem["PROPERTY_PARENT_VALUE"]])
					continue;

				$arItems[$arItem["PROPERTY_PARENT_VALUE"]]["ITEMS"][] = $arItem;
			}

			$cache->endDataCache($arItems); 
		}

		return $arItems;
	}

	/**
	 * Получение данных города по ID
	 *
	 * @example \Local\Location::getById(3);
	 *
	 * @param int $id - ID города
	 * @return array - данные города
	 */
	public static function getById(int $id)
	{
		if (!$id)
			return false;

		$arCity = false;

		$cache = Cache::createInstance(); 
		if ($cache->initCache(self::CACHE_TIME, "getById_" . $id, "/dev/location/") && self::CACHE) { 
			$arCity = $cache->getVars();
		} elseif ($cache->startDataCache() || !self::CACHE) {
			if (!self::IBLOCK_ID || !Loader::includeModule("iblock")) {
				$cache->abortDataCache();
				return false;
			}

			$arOrder = array(
				"PROPERTY_PARENT" => "DESC",
			);
			$arFilter = Array(
				"IBLOCK_ID" => self::IBLOCK_ID,
				"ACTIVE" => "Y",
				"ID" => $id,
			);
			$arSelect = array(
				"ID",
				"NAME",
				"CODE",
				"PROPERTY_PARENT",
				"PROPERTY_BIG",
			);
			//Добавляем к селекту параметры по сео
			$arSelect = array_merge($arSelect, Seo\IblockParams::$arCodes);
			$res = CIBlockElement::GetList($arOrder, $arFilter, false, false, $arSelect);
			$arCity = $res->GetNext();

			$cache->endDataCache($arCity); 
		}

		return $arCity;
	}

	/**
	 * Получение данных города по названию
	 *
	 * @example \Local\Location::getByName("Москва");
	 *
	 * @param sring $name - название города
	 * @return array - данные города
	 */
	public static function getByName(string $name)
	{
		if (!$name)
			return false;

		$arCity = false;

		$cache = Cache::createInstance(); 
		if ($cache->initCache(self::CACHE_TIME, "getByName_" . $name, "/dev/location/") && self::CACHE) { 
			$arCity = $cache->getVars();
		} elseif ($cache->startDataCache() || !self::CACHE) {
			if (!self::IBLOCK_ID || !Loader::includeModule("iblock")) {
				$cache->abortDataCache();
				return false;
			}

			$arOrder = array(
				"PROPERTY_PARENT" => "DESC",
			);
			$arFilter = Array(
				"IBLOCK_ID" => self::IBLOCK_ID,
				"ACTIVE" => "Y",
				"NAME" => "%" . $name . "%",
			);
			$arSelect = array(
				"ID",
				"NAME",
				"CODE",
				"PROPERTY_PARENT",
				"PROPERTY_BIG",
			);
			//Добавляем к селекту параметры по сео
			$arSelect = array_merge($arSelect, Seo\IblockParams::$arCodes);
			$res = CIBlockElement::GetList($arOrder, $arFilter, false, false, $arSelect);
			$arCity = $res->GetNext();

			if ($arCity["PROPERTY_PARENT_VALUE"])
				$arCity["PARENT"] = self::getById($arCity["PROPERTY_PARENT_VALUE"]);

			$cache->endDataCache($arCity); 
		}

		return $arCity;
	}

	/**
	 * Получение данных города по коду
	 *
	 * @example \Local\Location::getByCode("moskva");
	 *
	 * @param sring $code - название города
	 * @return array - данные города
	 */
	public static function getByCode(string $code)
	{
		if (!$code)
			return false;

		$arCity = false;

		$cache = Cache::createInstance(); 
		if ($cache->initCache(self::CACHE_TIME, "getByCode_" . $code, "/dev/location/") && self::CACHE) { 
			$arCity = $cache->getVars();
		} elseif ($cache->startDataCache() || !self::CACHE) {
			if (!self::IBLOCK_ID || !Loader::includeModule("iblock")) {
				$cache->abortDataCache();
				return false;
			}

			$arOrder = array(
				"PROPERTY_PARENT" => "DESC",
			);
			$arFilter = Array(
				"IBLOCK_ID" => self::IBLOCK_ID,
				"ACTIVE" => "Y",
				"CODE" => $code,
			);
			$arSelect = array(
				"ID",
				"NAME",
				"CODE",
				"PROPERTY_PARENT",
				"PROPERTY_BIG",
			);
			//Добавляем к селекту параметры по сео
			$arSelect = array_merge($arSelect, Seo\IblockParams::$arCodes);
			$res = CIBlockElement::GetList($arOrder, $arFilter, false, false, $arSelect);
			$arCity = $res->GetNext();

			if ($arCity["PROPERTY_PARENT_VALUE"])
				$arCity["PARENT"] = self::getById($arCity["PROPERTY_PARENT_VALUE"]);

			$cache->endDataCache($arCity); 
		}

		return $arCity;
	}

	/**
	 * Получение данных города по умолчанию
	 *
	 * @example \Local\Location::getDefault();
	 *
	 * @return array - данные города
	 */
	public static function getDefault()
	{
		$arCity = false;

		$cache = Cache::createInstance(); 
		if ($cache->initCache(self::CACHE_TIME, "defaultCity", "/dev/location/") && self::CACHE) { 
			$arCity = $cache->getVars();
		} elseif ($cache->startDataCache() || !self::CACHE) {
			if (!self::IBLOCK_ID || !Loader::includeModule("iblock")) {
				$cache->abortDataCache();
				return false;
			}

			$arOrder = array(
				"PROPERTY_PARENT" => "DESC",
			);
			$arFilter = Array(
				"IBLOCK_ID" => self::IBLOCK_ID,
				"ACTIVE" => "Y",
				"!PROPERTY_DEFAULT" => false,
			);
			$arSelect = array(
				"ID",
				"NAME",
				"CODE",
				"PROPERTY_PARENT",
				"PROPERTY_BIG",
			);
			//Добавляем к селекту параметры по сео
			$arSelect = array_merge($arSelect, Seo\IblockParams::$arCodes);
			$res = CIBlockElement::GetList($arOrder, $arFilter, false, false, $arSelect);
			$arCity = $res->GetNext();

			if (!$arCity) {
				$cache->abortDataCache();
				return false;
			}

			if ($arCity["PROPERTY_PARENT_VALUE"])
				$arCity["PARENT"] = self::getById($arCity["PROPERTY_PARENT_VALUE"]);

			$cache->endDataCache($arCity); 
		}

		return $arCity;
	}

	/**
	 * Получение города пользоватля
	 *
	 * @example \Local\Location::getUserCity();
	 *
	 * @return array - Данные города пользователя
	 */
	public static function getUserCity()
	{
		$SypexGeo = new \Flamix\Location\SypexGeo();
		$arUserLocation = $SypexGeo->getLocation();

		$arCity = false;
		if ($arUserLocation["city"]["name"])
			$arCity = self::getByName($arUserLocation["city"]["name"]);

		if (!$arCity)
			$arCity = self::getDefault();

		return $arCity;
	}
}
