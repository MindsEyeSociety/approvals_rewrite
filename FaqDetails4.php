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

	$query="update faq set category=?, question=?, answer=? where id=?";
	$params = [
		$_POST["category"],
		$_POST["question"],
		$_POST["Answer"],
		$_POST["faq_id"]
	];
	$db->query($query, $params);
	
	header("Location:FaqDetails.php");
	
?>