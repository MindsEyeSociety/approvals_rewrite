<?php

include("db.inc"); 

if( $_POST['new_visibility'] ) {
  $new_visibility= $_POST['new_visibility'];
	$comment_id = $_GET['comment_id'];
	$query = "UPDATE comments SET constraint_level = ? WHERE id=?";
	$params = [ $new_visibility, $comment_id ];
	$db->query( $query, $params );
}

?>
<html>
<head><link href="anathema.css" type='text/css' rel='stylesheet'></head>
<body>
<div align="center">
<br>
<a href="javascript:close();">Close</a> this window to return to the Application System<br>
<br>
<table class="data">
<?php
  $query="select c.*, unix_timestamp( comment_date ) as comment_date, ".
         "u.id as user_id, ".
 				 "o.chapter, o.domain, o.region, o.nation, a.user_id as applicant_id ".
         "from comments c left join users u on c.user_id=u.id ".
	 			 "left join organizations o on o.id=c.org_id ". 
				 "left join applications a on c.app_id = a.id ".
  	 		 "where c.id=?";
  $params = [ $_GET[comment_id] ];
  $db->query($query,$params);
  $commentrow=$db->nextRow();
  $user_info = $userInfoDAO->getUserInfo($commentrow['user_id']);
  $commentrow['user_name'] = $user_info['name'];
  $commentrow['email'] = $user_info['email'];
  
	if( $_SESSION['user_id'] == $commentrow['user_id'] &&
	    $_SESSION['user_id'] != $commentrow['applicant_id']) {
	  print"<form method=\"POST\" action=\"$PHP_SELF?$_SERVER[QUERY_STRING]\">\n";
	}

  print( "<tr>\n" );
  print( "<th>Subject</th>\n" );
  print( "<th>Name</th>\n" );
  print( "<th>Date</th>\n" );
  print( "<th>Visibility</th>\n" );
  print( "</tr>\n" );
    
  echo "<tr class=\"dark\">\n";
  echo "<td>".str_repeat("&nbsp;", $depth * 3 )."$commentrow[subject]</td>\n";
  echo "<td><a href=\"mailto:$commentrow[email]\">$commentrow[user_name]</a></td>\n";
  echo "<td align='center'>".strftime( "%D, %T", $commentrow['comment_date']) ."</td>\n";
	if( $_SESSION['user_id'] == $commentrow['user_id'] && 
	    $_SESSION['user_id'] != $commentrow['applicant_id']) {
	  print("<td align='center'>");
		print("<select name=\"new_visibility\">\n");
		$constraint_list = array_merge( array('unconstrained'), 
		                                $_SESSION['constraint_level_list']);
    $found=0;
	  foreach( $constraint_list as $constraint_level ) {
		  if( $found || $constraint_level == $commentrow['constraint_level'] || 
			    ($constraint_level == "unconstrained" && $commentrow['constraint_level']=="" ) ) {
        if( $constraint_level == "unconstrained" ) {
    		  print("<option value=\"\"");
				} else {
    		  print("<option value=\"$constraint_level\"");
				}
  			if( $constraint_level==$commentrow['constraint_level'] ||
    				($constraint_level == "unconstrained" && $commentrow['constraint_level']=="" ) ) {
  			  print (" SELECTED");
					$found = 1;
  			}
  			print(">".ucfirst( $constraint_level ) ."</option>\n");
			}
		}
		print("</select>&nbsp;<input type=\"submit\" value=\"Alter Visbility\"></td>\n");
	} else {
    if ( $commentrow['constraint_level'] != "" ) {
    	echo "<td align='center'>".ucfirst( $commentrow['constraint_level'] ) ."</td>\n";
    } else {
    	echo "<td align='center'>Unconstrained</td>\n";
    }
	}
  echo "</tr>\n";
  echo "<tr>\n";
  echo "<td colspan=\"5\">". str_replace( "\n", "<br>\n", $commentrow['comment'] ). "\n";
  echo "</td>\n";
  echo "</tr>\n";
	if( $_SESSION['user_id'] == $commentrow['user_id'] &&
	    $_SESSION['user_id'] != $commentrow['applicant_id']) {
	  print "</form>\n";
	}
  echo "</table>\n";
 ?>
 </td></tr>
</table>
<br>
<a href="javascript:close();">Close</a> this window to return to the Application System<br>
</div>
</body>
</html>
