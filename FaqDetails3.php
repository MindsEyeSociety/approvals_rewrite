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
	$pagetitle="Enter Changes to FAQ Line Item";
  include_once("header.inc");
  include_once("titlebar.php");

	$faq_id = $_GET["faq_id"];
	
	$db->query("select * from faq where id=?", [$faq_id]);
	$row=$db->nextRow();
 ?>
	<form action="FaqDetails4.php" method="post">
	<input type="hidden" name="faq_id" value="<?php echo $row["id"]?>">
	<table class="data">
		<tr>
			<th align="center">Modify FAQ Line Item</th>
		</tr>
		<tr>
			<th align="left">Category/Subject</th>
		</tr>
		<tr>
			<td align="left">
				<input type="text" size="60" name="category" value="<?php echo $row["category"]?>">
			</td>
		</tr>
		<tr>
			<th align="center">Question</th>
		</tr>
		<tr>
			<td align="left">
				<textarea name="question" wrap="virtual" cols="60" rows="4"><?php echo $row["question"]?></textarea>
			</td>
		</tr>
		<tr>
			<th align="center">Answer</th>
		</tr>
		<tr>
			<td align="left">
				<textarea name="Answer" wrap="virtual" cols="60" rows="12"><?php echo $row["answer"]?></textarea>
			</td>
		</tr>
		<tr>
			<th align="center">
				<input type="submit" value="Enter Changes to FAQ Item Now">
			</th>
		</tr>
	</table>
</form>

<?php 

include_once("footerbar.inc") 

?>