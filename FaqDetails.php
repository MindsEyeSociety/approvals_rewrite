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
	$pagetitle="Enter Changes to FAQ";
	$IGNORE_LOGIN=1;
  include_once("header.inc");
  include_once("titlebar.php");

	if ( isset($_POST["question"]) ) {
		$db->query("select max(ordernum) as max_order from faq where category=?", [$_POST["category"]]);
		$row=$db->nextRow();
		$next_num=$row["max_order"]+1;
		
		$query="insert into faq ".
				"(question, answer, category, itemdate, ordernum) ".
				"values ".
				"(?, ?, ?, now(), ?)";
		$db->query($query, [$_POST["question"], $_POST["Answer"], $_POST["category"], $next_num]);
		
		echo("<div align=center class=subhead>FAQ line item has been Entered.</div>");
	}
	
	
 ?>
<div align="center" class="pagehead">Frequently Asked Questions</div>
<br>
<?php if( $_SESSION['super_user'] ) { ?>

	<form action="FaqDetails.php" method="post">
	<table class="data">
		<tr>
			<th colspan="2">Enter New FAQ Line Item</th>
		</tr>
		<tr>
			<th align="left">Category/Subject</th>
			<td align="left" valign="top">
				<input type="text" size="60" name="category">
			</td>
		</tr>
		<tr>
			<th align="center" valign="top">Question</th>
			<td align="left">
				<textarea name="question" wrap="virtual" cols="60" rows="6"></textarea>
			</td>
		</tr>
		<tr>
			<th align="center" valign="top">Answer</th>
			<td align="left">
				<textarea name="Answer" wrap="virtual" cols="60" rows="6"></textarea>
			</td>
		</tr>
		<tr>
			<th align="center" colspan="2">
				<input type="submit" value="Enter New FAQ Item Now">
			</th>
		</tr>
		</form>
	</table>
	<?php } ?>
	<form action="FaqDetails2.php" method="post" style="margin:0">
	<?php
	$db->query("select * from faq order by category, ordernum");

	$lastCategory = "";
	while ( $row=$db->nextRow() ) {
		if ( $lastCategory != $row['category'] ) {
	?>
	<table class="data">
		<tr>
			<th align="left" class="subhead"><?php echo $row["category"]?></th>
		</tr>
	</table>
		<?php
		  $lastCategory = $row['category'];
		} 
  	if ( $_SESSION["super_user"] ) {
		?>
	<table class="data">
		<tr>
			<th align="left">
				<?php
					echo("<b>Del:</b> <input type=\"checkbox\" name=\"delete[]\" value=\"$row[id]\">");
					echo("<b>Mod:</b> <input type=\"radio\" name=\"modify\" value=\"$row[id]\" onclick=\"submit();\">");
				?>
			</th>
			<th align="left"><?php echo $row["itemdate"]?></th>			
		</tr>
	</table>
<?php } ?>
	<table class="data">
		<tr class="dark">
			<td width="5" nowrap valign="top">
				&nbsp;<b><font color="Red">Q:</font></b>&nbsp;
			</td>
			<td>
				<?php echo nl2br($row["question"])?>
			</td>
		</tr>
		<tr>
			<td width="5" nowrap valign="top">
				&nbsp;<b><font color="Blue">A:</font></b>&nbsp;
			</td>
			<td>
				<?php echo nl2br($row["answer"])?>
			</td>
		</tr>
	</table>
	<?php
	}
	if ( $_SESSION["super_user"] ) {
	?>
	<table class="data">
		<tr>
			<th colspan="3" align="center">
				<input type="submit" value="Enter Deletions Now" name="submitButton">
			</th>
		</tr>
	</table>
	<?php
	}
	?>
</form>
<?php 

include_once("footerbar.inc");