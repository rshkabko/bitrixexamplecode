<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use \Local\Page;
use \Bitrix\Main\Page\Asset;

use \Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

global $USER;

CJSCore::init(array("date"));

if (!$USER->IsAuthorized() && CSite::InDir(SITE_DIR . "personal/"))
	LocalRedirect(SITE_DIR . "?enter=yes");
?>
<!DOCTYPE html>
<html lang="<?=LANGUAGE_ID?>">
<head>
	<?php $APPLICATION->ShowHead(); ?>
	<title><?php $APPLICATION->ShowTitle(); ?></title>
	<?php
	$instance = Asset::getInstance();
	$instance->addString('<meta name="HandheldFriendly" content="True">');
	$instance->addString('<meta name="viewport" content="width=device-width, initial-scale=1.0">');
	$instance->addString('<meta name="apple-mobile-web-app-capable" content="yes">');

	$instance->addCss(SITE_TEMPLATE_PATH . "/css/fonts.css");
	$instance->addCss(SITE_TEMPLATE_PATH . "/css/libs.css");
	$instance->addCss(SITE_TEMPLATE_PATH . "/css/style.css");
	$instance->addCss(SITE_TEMPLATE_PATH . "/css/dev.css");
	
	$instance->addJs("https://yastatic.net/share2/share.js");
	$instance->addJs(SITE_TEMPLATE_PATH . "/js/libs.js");
	$instance->addJs(SITE_TEMPLATE_PATH . "/js/logic.js");
	$instance->addJs(SITE_TEMPLATE_PATH . "/js/dev.js");
	?>
	<!--[if lt IE 9]>
	<script src="//cdnjs.cloudflare.com/ajax/libs/html5shiv/r29/html5.min.js"></script>
	<script src="//dnjs.cloudflare.com/ajax/libs/es5-shim/4.4.1/es5-shim.min.js"></script>
	<script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
	<![endif]-->
</head>
<?
// profile-items класс для вывода блока авторизованного пользователя
// ordering-items класс для страницы заказа
?>
<body class="<?=(CSite::InDir(SITE_DIR . "message/") ? "ordering-items" : "")?>">
<?php $APPLICATION->ShowPanel(); ?>
<div id="main" class="gen-wrapper css-transitions-after-page-load">
	<header id="header">
		<div class="container flex">
			<div class="header__left">
				<?php if (CSite::InDir(SITE_DIR . "index.php")) { ?>
					<span id="logo">
				<?php } else { ?>
					<a id="logo" href="<?=SITE_DIR?>">
				<?php } ?>
					<?$APPLICATION->IncludeFile(
						SITE_TEMPLATE_PATH . "/inc/header/logo_pic_inc.php", 
						Array(), 
						Array(
							"NAME" => "логотип", //текст всплывающей подсказки на иконке редактирования
							"MODE" => "html", //режим редактирования
							"TEMPLATE" => "empty.php", //шаблон страницы по умолчанию
						),
						false
					);?>
					<span class="logo-text">
						<?$APPLICATION->IncludeFile(
							SITE_TEMPLATE_PATH . "/inc/header/logo_text_inc.php", 
							Array(), 
							Array(
								"NAME" => "текст", //текст всплывающей подсказки на иконке редактирования
								"MODE" => "html", //режим редактирования
								"TEMPLATE" => "empty.php", //шаблон страницы по умолчанию
							),
							false
						);?>
					</span>
				<?php if (CSite::InDir(SITE_DIR . "index.php")) { ?>
					</span>
				<?php } else { ?>
					</a>
				<?php } ?>
				<?$APPLICATION->IncludeComponent(
					"bitrix:menu", 
					"top", 
					array(
						"ROOT_MENU_TYPE" => "top",
						"MENU_CACHE_TYPE" => "A",
						"MENU_CACHE_TIME" => "360000",
						"MENU_CACHE_USE_GROUPS" => "Y",
						"MENU_CACHE_GET_VARS" => array(
						),
						"MAX_LEVEL" => "3",
						"CHILD_MENU_TYPE" => "",
						"USE_EXT" => "Y",
						"DELAY" => "N",
						"ALLOW_MULTI_SELECT" => "N",
						"COMPOSITE_FRAME_MODE" => "A",
						"COMPOSITE_FRAME_TYPE" => "AUTO"
					),
					false
				);?>
			</div>
			<?$APPLICATION->IncludeComponent(
				"bitrix:system.auth.form", 
				"top", 
				array(
					"COMPONENT_TEMPLATE" => "",
					"REGISTER_URL" => "",
					"FORGOT_PASSWORD_URL" => "",
					"PROFILE_URL" => "",
					"ADD_AD_URL" => "/personal/add/",
					"FAVOURITE_URL" => "/personal/favourite/",
					"REPLANISH_URL" => "/personal/balance/",
					"MESS_URL" => "/personal/messages/",
					"SHOW_ERRORS" => "Y",
					"COMPOSITE_FRAME_MODE" => "A",
					"COMPOSITE_FRAME_TYPE" => "DYNAMIC_WITH_STUB"
				),
				false
			);?>
		</div>
	</header>
	<div class="section<?=Page::showClass()?>">
		<div class="container">
			<?$APPLICATION->IncludeComponent(
				"bitrix:menu",
				"personal",
				Array(
					"ROOT_MENU_TYPE" => "personal",
					"MENU_CACHE_TYPE" => "A",
					"MENU_CACHE_TIME" => "360000",
					"MENU_CACHE_USE_GROUPS" => "Y",
					"MENU_CACHE_GET_VARS" => array(
					),
					"MAX_LEVEL" => "1",
					"CHILD_MENU_TYPE" => "",
					"USE_EXT" => "N",
					"DELAY" => "N",
					"ALLOW_MULTI_SELECT" => "N",
					"COMPOSITE_FRAME_MODE" => "A",
					"COMPOSITE_FRAME_TYPE" => "AUTO"
				),
				false
			);?>
			<div class="profile-main">
				<?php \Local\Personal\Helper::showStartMess(); ?>