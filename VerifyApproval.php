<?php

include_once("db.inc");
$pagetitle="Verify Application Number";
include_once("header.inc");
include_once("titlebar.php");
if ( !empty( $_POST['app_number'] ) ) {
	$app_id = intval( $_POST['app_number'] );
   	$query="SELECT a.*, o.org_name, ".
		"c.id as character_id, c.name as character_name, ".
   		"c.org_id as character_org_id, c.subtype, c.char_type, ".
   		"c.background as background, cat.category, ".
   		"cv.venue as character_venue, v.venue as application_venue ".
   		"FROM applications a ".
   		"LEFT JOIN users u on a.user_id=u.id " .
   		"LEFT JOIN organizations o ON u.org_id = o.id " .
   		"LEFT JOIN characters c ON a.character_id = c.id " .
   		"LEFT JOIN venues cv ON c.venue_id = cv.id " .
   		"LEFT JOIN venues v ON a.venue_id = v.id " .
   		"LEFT JOIN categories cat ON a.category = cat.category ".
   		"WHERE a.id=? OR a.app_number like ?";
	$app_number = $_POST['app_number'];
	$db->query( $query, [$app_id, $app_number] );
	if( $app_info = $db->nextRow() ) {
   	  if( $_SESSION['user_id'] != $app_info['user_id'] ) {
	          if (empty($app_info["character_id"]))
		  {
		  	$app_info['character_id'] = "NULL";
		  }
		  $db->query("INSERT INTO activity_log ( activity_date, character_id, user_id, description ) values ( now(), ?, ?, 'Verified Application #' )", [$app_info["character_id"], $_SESSION["user_id"]]);
   		}
   		?>
<table class="data">
	<tr>
		<th valign='top' colspan="4" align="center">Application #<?php echo $app_info["app_number"]?></th>
	</tr>
	<tr>
		<td valign='top'><b>Item:</b></td>
		<td valign='top'><?php echo $app_info["description"]?></td>
		<td valign='top'><b>Item Venue:</b></td>
		<td valign='top'>&nbsp;<?php echo $app_info["application_venue"]?></td>
	</tr>
	<tr>
		<td valign='top'><b>Player:</b></td>
		<td valign='top'><?php echo userInfoPopup($app_info["user_id"])?></a></td>
		<td valign='top'><b>Category:</b></td>
		<td valign='top'>&nbsp;<?php echo $app_info["category"]?></td>
	</tr>
	<tr class="dark">
		<td valign='top'><b>Character:</b></td>
		<td valign='top'><?php
				if ( $app_info['character_id']!=0 && is_numeric( $app_info['character_id'] ) ) {
					echo("$app_info[character_name]<br> ($app_info[character_venue] $app_info[char_type])\n");
					if ( strlen(trim($app_info['subtype']))>0 ) {
						echo("$app_info[subtype]");
					}
   			} else {
   				echo("(Non-Character Application)");
				}
			?>
		</td>
		<td valign="top"><b>Affiliation:</b></td>
		<td valign="top">
			<?php
				$query="select u.id, o.domain ".
       				 "from users u left join organizations o on u.id = o.admin_user_id ".
       				 "where o.id=?";
				$db->query($query, [$app_info['org_id']]);
				$row=$db->nextRow();
				$user_info = $userInfoDAO->getUserInfo($row['id']);
				$orgstname=$user_info["name"];
				$orgstemail=$user_info["email"];
				$orgstdomain=$row["domain"];

				$query="select u2.id as ost_id, " .
				  "u.id as vst_id, ".
					"v.name AS vss_name, o.org_name, c.vss_id ".
					"from characters c ".
					"LEFT JOIN vsss v on c.vss_id=v.id ".
					"LEFT JOIN organizations o ON c.vss_id = -o.id ".
					"LEFT JOIN users u on v.storyteller_id = u.id ".
					"LEFT JOIN users u2 ON u2.id = o.admin_user_id ".
					"where c.id=?";
				$db->query($query, [$app_info['character_id']]);
				if( $row=$db->nextRow() ) {
				  if( $row['vss_id'] > 0 ) {
				$user_info = $userInfoDAO->getUserInfo($row['vst_id']);
    				$vstname=$user_info['name'];
    				$vstemail=$user_info['email'];
    				$vss_name=$row["vss_name"];
  				} else {
						$user_info = $userInfoDAO->getUserInfo($row['ost_id']);
    				$vstname=$user_info['name'];
    				$vstemail=$user_info['email'];
    				$vss_name=$row["org_name"];
				  }
				}

				$query="select u.id, domain, chapter ".
					"from users u left join organizations o on o.admin_user_id = u.id ".
					"where o.id=? and o.chapter != ''";
				$db->query($query, [$app_info['org_id']]);
				$row=$db->nextRow();
				$user_info = Array();
				if(isset($row['id'])) {
					$user_info = $userInfoDAO->getUserInfo($row['id']);
				}
				$cstname = isset($user_info['name']) ? $user_info['name'] : '';
				$cstemail = isset($user_info['email']) ? $user_info['email'] : '';
				$cstdomain = isset($row['domain']) ? $row['domain'] : '';

				$query="select u.id ".
					"from organizations o1 ".
	          "left join organizations o2 on o2.domain = o1.domain and o2.chapter='' ".
          "left join users u on u.id=o2.admin_user_id  ".
  					"where ( o1.id = ? )";
				$db->query($query, [$app_info['org_id']]);
				$row=$db->nextRow();
				if (isset($row['id'])) {
					$user_info = $userInfoDAO->getUserInfo($row['id']);
				} else {
					$user_info = Array();
				}
				$dstname = isset($user_info['name']) ? $user_info['name'] : '';
				$dstemail = isset($user_info['email']) ? $user_info['email'] : '';

				$db->query("SELECT city, state, country FROM organizations WHERE id=?", [$app_info['org_id']]);
				$row=$db->nextRow();
				$thisorg_location = "";
				if ( $row["city"] ) {
					$thisorg_location.=$row["city"];
				}
				if ( $row["state"] ) {
					if ( $thisorg_location!="" ) {
						$thisorg_location.=", ";
					}
					$thisorg_location.=$row["state"];
				}
				if ( $row["country"] ) {
					if ( $thisorg_location!="" ) {
						$thisorg_location.=", ";
					}
					$thisorg_location.=$row["country"];
				}
				echo("$vss_name<br>\n<i>$thisorg_location</i><br>");
				if ( $vstname!="" ) {
					echo("Low ST:&nbsp;<a href=\"mailto: $vstemail\"\n>$vstname</a>\n");
				} else if ( $cstname!="" ) {
					echo("Low ST:&nbsp;<a href=\"mailto: $cstemail\"\n>$cstname</a> (CST)\n");
				} else {
					echo("ST:&nbsp;<a href=\"mailto: $orgstemail\">$orgstname</a>\n");
				}
				if ( $dstname!="" ) {
					echo("<br>Mid ST:&nbsp;<a href=\"mailto: $dstemail\">$dstname</a>\n");
				}
			?>
		</td>
	</tr>
  <tr>
    <td valign="top"><b>Status:</b></td>
    <td valign="top" align="left"><?php echo $app_info["status"]?>&nbsp;</td>
    <td valign="top"><b>Approval Required:</b></td>
    <td valign="top"><?php echo $app_info["required_approval"]?></td>
  </tr>
</table>
<table class="data">
<?php
	if ( $app_info["character_id"]!="0" && is_numeric($app_info["character_id"]) ) {
?>
	  <tr>
	    <th align="center">Character Sheet</th>
	  </tr>
	  <tr>
<?php
		echo("<td>");
	  if ( $app_info["character_sheet"]=="" ) {
			echo("<em>(None)</em>");
		} else {
			$ThisCS = $app_info["character_sheet"];
			echo(formatBlockText($ThisCS));
		}
?>
    </td>
  </tr>
<?php
  }
 ?>
  <tr>
    <th align="center">Mechanics</th>
  </tr>
  <tr>
<?php
	echo("<td>");
	if ( $app_info["mechanics"]=="" )  {
		echo("<em>(None)</em>");
	} else {
		$ThisMechanics=str_replace(chr(13), "<br>",$app_info["mechanics"]);
		$quot_ent = chr(38) . "quot;";
		$ThisMechanics=str_replace("\"", $quot_ent, $ThisMechanics);
		echo("$ThisMechanics");
	}
?>
    </td>
  </tr>
</table>
<br>
<br>
	    <?php
		} else {
		  echo "<div class='error' align='center'>Application not found</div>";
		}
	}
 ?>
<table class="data">
  <tr>
	  <td align="center">
		  <form style="margin: 0; padding: 0;" method="POST" action="<?php echo $_SERVER["PHP_SELF"]?>">
			  <b>Search for Application Number</b><input type="text" name="app_number" value="<?php echo isset($_POST['app_number']) ? htmlspecialchars($_POST['app_number']) : ''?>" size="20"> <input type="submit" value="Find Application">
			</form>
		</td>
	</tr>
</table>
<?php
 ?>