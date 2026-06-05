<?php

include_once("db.inc"); 

function getComment( $id ) {
	global $db;
  $query="select c.*, unix_timestamp( comment_date ) as comment_date, ".
 				 "o.chapter, o.domain, o.region, o.nation, a.user_id as applicant_id ".
         "from comments c left join organizations o on o.id=c.org_id ". 
				 "left join applications a on c.app_id = a.id ".
  	 		 "where c.id=?";
  $db->query($query, [$id]);
  return $db->nextRow();
}

function canEditComment( $comment ) {
	return $_SESSION['user_id'] == $comment['user_id'] && 
				 $_SESSION['user_id'] != $comment['applicant_id'];
}

function setCommentVisibility( $comment_id, $visibility ) {
	global $db;
	$query = "UPDATE comments SET constraint_level = ? WHERE ID=?";
	$db->query($query, [$visibility, $comment_id]);
}

if( isset($_POST['new_visibility']) ) {
	setCommentVisibility( $_GET["comment_id"], $_POST["new_visibility"] );
}

$commentrow = getComment( $_GET["comment_id"] );

?>
<html>
<head>
	<link href="anathema.css" type='text/css' rel='stylesheet'>
	<script type="text/javascript" src="https://code.jquery.com/jquery-1.7.2.min.js"></script>
	<script type="text/javascript" src="javascript/jquery.tooltips.js"></script>
</head>
<body>
<div align="center">
<br>
<a href="javascript:close();">Close</a> this window to return to the Application System<br>
<br>
<table class="data">
<?php if( $commentrow && canEditComment($commentrow) ) { ?>
	<form method="POST" action="<?php echo $_SERVER['PHP_SELF']?>?<?php echo $_SERVER['QUERY_STRING']?>">
<?php } ?>

	<tr>
		<th>Subject</th>
  	<th>Name</th>
		<th>Date</th>
 		<th>Visibility</th>
	</tr>
	<tr class="dark">
  	<td style="margin-left:<?php echo isset($depth) ? $depth*5 : 0?>px;"><?php echo $commentrow ? $commentrow["subject"] : ""?></td>
		<td><?php echo $commentrow ? userInfoPopup($commentrow["user_id"]) : ""?></td>
  	<td align='center'><?php echo $commentrow ? strftime( "%D, %T", $commentrow['comment_date']) : ""?></td>
<?php	if( $commentrow && canEditComment($commentrow) ) { ?>
	  <td align='center'>
		<select name="new_visibility">
<?php 
		$constraint_list = array_merge( array('unconstrained'), 
		                                $_SESSION['constraint_level_list']);
    $found=0;
	  foreach( $constraint_list as $constraint_level ) {
		  if( $found || $constraint_level == $commentrow['constraint_level'] || 
			    ($constraint_level == "unconstrained" && $commentrow['constraint_level']=="" ) ) {
        if( $constraint_level == "unconstrained" ) {
?>
    		  <option value="" <?php if( "" == $commentrow["constraint_level"] ) {?> selected="selected"<?php } ?>><?php echo ucfirst($constraint_level)?></option>
<?php	} else { ?>
    		  <option value="<?php echo $constraint_level?>"<?php if( $constraint_level == $commentrow["constraint_level"] ){ ?> selected="selected"<?php } ?>><?php echo ucfirst($constraint_level)?></option>
<?php		}
			}
		}
?>
	</select>&nbsp;<input type="submit" value="Alter Visbility"></td>
<?php	} else {
    if ( $commentrow && $commentrow['constraint_level'] != "" ) {
    	echo "<td align='center'>".ucfirst( $commentrow['constraint_level'] ) ."</td>\n";
    } else {
    	echo "<td align='center'>Unconstrained</td>\n";
    }
	}
?>
</tr>
<tr>
	<td colspan="5"><?php echo $commentrow ? str_replace( "\n", "<br>\n", $commentrow['comment'] ) : ""?></td>
</tr>
<?php if( $commentrow && canEditComment($commentrow)) {?></form><?php } ?>
  </table>
<br />
<a href="javascript:close();">Close</a> this window to return to the Application System<br>
</div>
</body>
</html>