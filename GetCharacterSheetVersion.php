<?php
include_once("db.inc");
require_once("classes/CharacterSheetVersionDAO.class.php");

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit();
}

$version_id = (int)$_GET['version_id'];

$isStoryteller = !empty($_SESSION['admin_vss_list']) || !empty($_SESSION['admin_org_list']);
if (!$isStoryteller) {
    http_response_code(403);
    exit();
}

$versionDAO = new CharacterSheetVersionDAO($db);
$version = $versionDAO->getVersion($version_id);
if ($version === null) {
    http_response_code(404);
    exit();
}

echo formatBlockText($version);
