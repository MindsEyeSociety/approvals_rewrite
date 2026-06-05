<?php
/*
This is the XP Earnings and Expenditures page.  The functionality depends upon two tables, called:
earnedxp
and
spentxp

The structure of the tables is as follows:
earnedxp:
id - autonum
character_id - number
eventname - varchar
earneddate - datetime
xpearned - number
notes - text/varchar

spentxp:
id - autonum
character_id - number
itembought - varchar
spentdate - datetime
xpspent - number
notes - text/varchar
*/
  include_once("db.inc");

	$earnedsort= ( isset($_GET["earnedsort"]) ? $_GET["earnedsort"] : "earneddate");
	$spentsort= ( isset($_GET["spentsort"]) ? $_GET["spentsort"] : "spentdate");
	$earnedinverted= ( isset($_GET["earnedinverted"]) ? 1 : 0);
	$spentinverted= ( isset($_GET["spentinverted"]) ? 1 : 0);
	if ( $earnedsort=="earneddate" xor $earnedinverted ) {
		$earneddirection="desc";
	} else {
		$earneddirection="asc";
	}
	if ( $spentsort=="spentdate" xor $spentinverted ) {
		$spentdirection="desc";
	} else {
		$spentdirection="asc";
	}
	
	$character_id = intval( $_GET['character_id'] );

	$query= "select c.name, v.venue, c.subtype, c.user_id ".
			"from characters c left join venues v on c.venue_id=v.id ".
			"where c.id='$character_id' ".
			"order by c.name asc";
  $db->query($query);
  $row=$db->nextRow();
  $character_user_id=$row["user_id"];
  if ( $_SESSION['user_id']==$character_user_id ) {
  	$this_user=1;
  } else {
  	$this_user=0;
  }

	$pagetitle="XP List for $row[name] ($row[subtype]) ($row[venue])";
  include_once("header.inc");
  include_once("titlebar.php");
?>
	<div class="subhead" align="center"><?php echo $pagetitle?></div>
	<div class="normalsmall" align="center"><a href="DisplayCharacter.php?char_id=<?php echo $character_id?>&">Return</a> to Character</div>
<?php	
	$query= "select e.*, unix_timestamp( earneddate) as timestamp ".
			"from earnedxp e ".
			"where character_id='$character_id' ".
			"order by $earnedsort $earneddirection";
  $db->query($query);
  $earnedxp=array();
  while ( $row=$db->nextRow() ) {
  	$earnedxp[]=$row;
  }

	$query= "select s.*, unix_timestamp( spentdate ) as timestamp ".
			"from spentxp s ".
			"where character_id='$character_id' ".
			"order by $spentsort $spentdirection";
  $db->query($query);
  $spentxp=array();
  while ( $row=$db->nextRow() ) {
  	$spentxp[]=$row;
  }
?>

	<?php
		if ( $this_user ) {
	?>
	<table class="data">
		<form action="ModifyCharacterXPListAddXP.php" method="post" style="margin: 0;">
			<input type="hidden" name="character_id" value="<?php echo $character_id?>">
			<input type="hidden" name="table" value="earnedxp">
		<tr>
			<th align="center" colspan="4">Enter New Earned XP</th>
		</tr>
		<tr>
			<th align="center">Event/Source</th>
			<th align="center">Date</th>
			<th align="center">XP Earned</th>
			<th align="center">Notes</th>
		</tr>
		<tr>
			<td>
				<input type="text" size="10" name="eventname">
			</td>
			<td>
				<input type="text" size="7" name="earneddate">
			</td>
			<td>
				<input type="text" size="2" name="xpearned">
			</td>
			<td>
				<input type="text" size="20" name="notes">
			</td>
		</tr>
		<tr>
			<th align="center" colspan="4">
				<input type="submit" value="Enter New Earned XP">
			</th>
		</tr>
	</table>
</form>
	<?php
		}
		$standardvars="character_id=$character_id&spentsort=$spentsort&spentinverted=$spentinverted&";
	?>
<form action="ModifyCharacterXPList2.php" method="post" style="margin: 0;">
<input type="hidden" name="xptype" value="earned">
<input type="hidden" name="character_id" value="<?php echo $character_id?>">
<input type="hidden" name="delete[]" value="0">
<table class="data">
	<tr>
		<th align="center">Earned XP</th>
	</tr>
</table>
	<table class="data">
		<tr>
			<?php
				if ( $earnedsort=="eventname" ) {
					if ( $earnedinverted==1 ) {
						$toURL="ModifyCharacterXPList1.php?".$standardvars."earnedsort=eventname&";
					} else {
						$toURL="ModifyCharacterXPList1.php?".$standardvars."earnedsort=eventname&earnedinverted=1&";
					}
				} else {
					$toURL="ModifyCharacterXPList1.php?".$standardvars."earnedsort=eventname&";
				}
			?>
			<th <?php if ( $earnedsort == "eventname" ) {?> class="sort"<?php } ?> align="center">
				<a href="<?php echo $toURL?>">Event/Source</a>
			</th>
			<?php
				if ( $earnedsort=="earneddate" ) {
					if ( $earnedinverted==1 ) {
						$toURL="ModifyCharacterXPList1.php?".$standardvars."earnedsort=earneddate&";
					} else {
						$toURL="ModifyCharacterXPList1.php?".$standardvars."earnedsort=earneddate&earnedinverted=1&";
					}
				} else {
					$toURL="ModifyCharacterXPList1.php?".$standardvars."earnedsort=earneddate&";
				}
			?>
			<th<?php if ( $earnedsort=="earneddate" ) echo(" class=\"sort\""); ?> align="center">
				<a href="<?php echo $toURL?>">Date</a>
			</th>
			<?php
				if ( $earnedsort=="xpearned" ) {
					if ( $earnedinverted==1 ) {
						$toURL="ModifyCharacterXPList1.php?".$standardvars."earnedsort=xpearned&";
					} else {
						$toURL="ModifyCharacterXPList1.php?".$standardvars."earnedsort=xpearned&earnedinverted=1&";
					}
				} else {
					$toURL="ModifyCharacterXPList1.php?".$standardvars."earnedsort=xpearned&";
				}
			?>
			<th <?php if( $earnedsort == "xpearned") { ?> class="sort"<?php } ?> align="center">
				<a href="<?php echo $toURL?>">XP Earned</a>
			</th>
			<?php
				if ( $earnedsort=="notes" ) {
					if ( $earnedinverted==1 ) {
						$toURL="ModifyCharacterXPList1.php?".$standardvars."earnedsort=notes&";
					} else {
						$toURL="ModifyCharacterXPList1.php?".$standardvars."earnedsort=notes&earnedinverted=1&";
					}
				} else {
					$toURL="ModifyCharacterXPList1.php?".$standardvars."earnedsort=notes&";
				}
			?>
			<th <?php if( $earnedsort == "notes" ) { ?> class="sort"<?php } ?> align="center">
				<a href="<?php echo $toURL?>">Notes</a>
			</th>
			<?php
				if ( $this_user ) {
			?>
				<th align="center">Mod</th>
				<th align="center">Del</th>
			<?php
				}
			?>
		</tr>
<?php
	$counter = 0;
	foreach ( $earnedxp as $row ) {
		if( ++$counter %2 == 0 ) {
			echo("<tr class=\"dark\">");
		} else {
			echo("<tr>");
		}
		echo("<td>&nbsp;$row[eventname]</td>\n");
    echo("<td align=\"center\">");
		echo( strftime( "%a %b %e %Y", $row['timestamp'] ) );
		echo("</td>\n");
  	echo("<td align=\"center\">&nbsp;$row[xpearned]</td>\n");
		echo("<td>&nbsp;$row[notes]</td>\n");
  	echo("\n");
		if ( $this_user ) {
		  echo("<td align=\"center\">".
		  		"<input type=\"radio\" onclick=\"submit();\" name=\"modify\" value=\"$row[id]\">\n");
		  echo("</td>\n");
		  echo("<td align=\"center\">".
		  		"<input type=\"checkbox\" name=\"delete[]\" value=\"$row[id]\">\n");
		  echo("</td>\n");
		}
	  echo("</tr>\n");
	}
?>
	</table>
	<?php
		if ( $this_user ) {
	?>
	<table class="data">
		<tr>
			<th align="center">
				<input type="submit" value="Enter Deletions and Changes">
			</th>
		</tr>
	</table>
	<?php
		}
	?>
	</form>
<form action="ModifyCharacterXPListAddXP.php" method="post" style="margin: 0;">
	<input type="hidden" name="character_id" value="<?php echo $character_id?>">
	<input type="hidden" name="table" value="spentxp">
	<table class="data">
	<?php
		if ( $this_user ) {
	?>
		<tr>
			<th align="center" colspan="4">Enter New Spent XP</th>
		</tr>
		<tr>
			<th align="center">Item Bought</th>
			<th align="center">Date</th>
			<th align="center">XP Spent</th>
			<th align="center">Notes</th>
		</tr>
		<tr>
			<td>
				<input type="text" size="10" name="itembought">
			</td>
			<td>
				<input type="text" size="7" name="spentdate">
			</td>
			<td>
				<input type="text" size="2" name="xpspent">
			</td>
			<td>
				<input type="text" size="20" name="notes">
			</td>
		</tr>
		<tr>
			<th align="center" colspan="4">
				<input type="submit" value="Enter New Spent XP">
			</th>
		</tr>
	</table>
	</form>
	<?php
		}
		$standardvars="character_id=$character_id&earnedsort=$earnedsort&earnedinverted=$earnedinverted&";
	?>
	<form action="ModifyCharacterXPList2.php" method="post" style="margin: 0;">
	<input type="hidden" name="xptype" value="spent">
	<input type="hidden" name="character_id" value="<?php echo $character_id?>">
	<input type="hidden" name="delete[]" value="0">
	<table class="data">
		<tr>
			<th align="center">Spent XP</th>
		</tr>
	</table>
	<table class="data">
		<tr>
			<?php
				if ( $spentsort=="itembought" ) {
					if ( $spentinverted==1 ) {
						$toURL="ModifyCharacterXPList1.php?".$standardvars."spentsort=itembought&";
					} else {
						$toURL="ModifyCharacterXPList1.php?".$standardvars."spentsort=itembought&spentinverted=1&";
					}
				} else {
					$toURL="ModifyCharacterXPList1.php?".$standardvars."spentsort=itembought&";
				}
			?>
			<th <?php if( $spentsort=="itembought" ) echo("class=\"sort\""); ?> align="center"><a href="<?php echo $toURL?>">Item Bought</a></th>
			<?php
				if ( $spentsort=="spentdate" ) {
					if ( $spentinverted==1 ) {
						$toURL="ModifyCharacterXPList1.php?".$standardvars."spentsort=spentdate&";
					} else {
						$toURL="ModifyCharacterXPList1.php?".$standardvars."spentsort=spentdate&spentinverted=1&";
					}
				} else {
					$toURL="ModifyCharacterXPList1.php?".$standardvars."spentsort=spentdate&";
				}
			?>
			<th <?php if( $spentsort=="spentdate" ) echo ("class=\"sort\"");?> align="center"><a href="<?php echo $toURL?>">Date</a></th>
			<?php
				if ( $spentsort=="xpspent" ) {
					if ( $spentinverted==1 ) {
						$toURL="ModifyCharacterXPList1.php?".$standardvars."spentsort=xpspent&";
					} else {
						$toURL="ModifyCharacterXPList1.php?".$standardvars."spentsort=xpspent&spentinverted=1&";
					}
				} else {
					$toURL="ModifyCharacterXPList1.php?".$standardvars."spentsort=xpspent&";
				}
			?>
			<th <?php if ( $spentsort=="xpspent" ) { echo("class=\"$cellclass\" "); }?> align="center"><a href="<?php echo $toURL?>">XP Spent</a></th>
			<?php
				if ( $spentsort=="notes" ) {
					if ( $spentinverted==1 ) {
						$toURL="ModifyCharacterXPList1.php?".$standardvars."spentsort=notes&";
					} else {
						$toURL="ModifyCharacterXPList1.php?".$standardvars."spentsort=notes&spentinverted=1&";
					}
				} else {
					$toURL="ModifyCharacterXPList1.php?".$standardvars."spentsort=notes&";
				}
			?>
			<th <?php if ( $spentsort=="notes" ) echo("class=\"$cellclass\""); ?> align="center">
				<a href="<?php echo $toURL?>">Notes</a>
			</th>
			<?php
				if ( $this_user ) {
			?>
				<th align="center">Mod</th>
				<th align="center">Del</th>
			<?php
				}
			?>
		</tr>
<?php
	$counter = 0;
	foreach ( $spentxp as $row ) {
		if( ++$counter % 2 == 0 ) {
			echo("<tr class=\"dark\">");
		} else {
			echo("<tr>");
		}
  	echo("<td>&nbsp;$row[itembought]</td>\n");
    echo("<td align=\"center\">");
		echo( strftime( "%a %b %e %Y", $row['timestamp'] ) );
		echo("</td>\n");
  	echo("<td align=\"center\">&nbsp;$row[xpspent]</td>\n");
		echo("<td>&nbsp;$row[notes]</td>\n");
  	echo("\n");
		if ( $this_user ) {
		  echo("<td align=\"center\">".
		  		"<input type=\"radio\" onclick=\"submit();\" name=\"modify\" value=\"$row[id]\">\n");
		  echo("</td>\n");
		  echo("<td align=\"center\">".
		  		"<input type=\"checkbox\" name=\"delete[]\" value=\"$row[id]\">\n");
		  echo("</td>\n");
		  echo("</tr>\n");
		}
	}
?>
	</table>
	<?php
		if ( $this_user ) {
	?>
	<table class="data">
		<tr>
			<th align="center">
				<input type="submit" value="Enter Deletions and Changes">
			</th>
		</tr>
	</table>
	<?php
		}
	?>
</form>

<?php include_once("footerbar.inc") ?>
