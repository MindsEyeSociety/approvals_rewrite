<?php
/*
This page is designed to allow on-the-fly addition and modification of the FAQ.

It requires a table called:
faq

This table has the following fields:
id, an autonum
question, a memo/text
answer, a memo/text
category, a varchar(255)
itemdate, a date
ordernum, a number
*/

  include_once("db.inc");

	if ( isset($_POST["submitButton"]) && is_array($_POST["delete"]) ) {
		$placeholders = implode(",", array_fill(0, count($_POST["delete"]), "?"));
		$db->query("delete from faq where id in ($placeholders)", $_POST["delete"]);
		header("Location:FaqDetails.php");
		exit();
	} else if ( isset($_POST["modify"] ) ) {
		header("Location:FaqDetails3.php?faq_id={$_POST["modify"]}&");
		exit();
	} else {
		header("Location:FaqDetails.php");
		exit();
	}
?>