<?php
include_once("db.inc");

$modifyID = isset( $_POST["modify"] ) ? $_POST['modify'] : "";
$deleteIDs = isset( $_REQUEST["delete"] ) ? $_REQUEST["delete"] : array();

$vssDAO = $daoFactory->getVSSDao();
$vssDAO->deleteByIDs( $deleteIDs );
if( $modifyID != "" && !in_array( $modifyID, $deleteIDs ) ) {
  header("Location: ModifyVSSList3.php?modify=$modifyID&");
} else {
  header("Location: ModifyVSSList1.php");
}
