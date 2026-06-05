<?php
include_once("header.inc");

// Setup template object
$app_root = "/var/www/approvals_2017";
ini_set('include_path', ini_get("include_path").PATH_SEPARATOR."$app_root/include");

require_once( '/usr/share/php/smarty3/SmartyBC.class.php' );

$smarty = new SmartyBC;
$smarty->template_dir = "$app_root/templates";
$smarty->compile_dir = "$app_root/templates_c";
$smarty->caching = true;
$smarty->force_compile = true;
$smarty->compile_check = true;

// This function is used by smarty templates to display the user details in the tooltips
$smarty->register_modifier( "userInfoPopup", "userInfoPopup" );
$smarty->register_modifier( "nl2br", "nl2br" );
$smarty->register_function( "activateUserPopups", "activateUserPopups");
$smarty->assign( "menus",generateMenus());
