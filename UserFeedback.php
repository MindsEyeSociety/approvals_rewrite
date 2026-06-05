<?php
/*
This page is designed to take user feedback.

It requires a table called:
feedback

This table has the following fields:
id, an autonum
user_id, a number
commentdate, a date
comment, a memo/text
subject, a varchar(255)
active, a yes/no (bit)
*/

  include_once("db.inc");
  $pagetitle="Enter User Feedback";

  include_once("header.inc");
  include_once("titlebar.php");

  // Grab the first message number from the URL
  $first_message = isset( $_GET['msg'] ) ? $_GET['msg'] : 0 ;
  
  // Set up some variables for changing things later.
  $msg_display = 20;
  
  if ( $_SERVER['REQUEST_METHOD'] == 'POST' ) {
    if ( !empty($_POST["comment"]) && !empty( $_POST["subject"] ) ) {
      if( isset( $_POST["announcement"] ) ) {
        $announcement = 1;
      } else {
        $announcement = 0;
      }
      $query="INSERT into feedback ".
          "(user_id, commentdate, comment, subject, active, root_id, parent_id, url, announcement) ".
          "values ".
          "(?, now(), ?, ?, '1', '0', '0', ?, ?)";
      $db->query($query, [$_SESSION["user_id"], $_POST["comment"], $_POST["subject"], $_POST["url"], $announcement]);
      echo("<div align=center class=subhead>Your Comment has been Entered.</div>");
      unset($_POST['comment']);
      unset($_POST['Subject']);
      unset($_POST['url']);
    } else {
      echo("<div align=center class=error>You must enter both a subject and a comment.</div>");
    }
  }
  
 ?>

<?php 
  if ( $_SESSION["super_user"] && isset($_GET["feedback_id"]) && isset($_GET["new_active_value"]) ) {
    $db->query("update feedback set active=? where id=?", [$_GET["new_active_value"], $_GET["feedback_id"]]);
  }
  
  $db->query("SELECT count(*) AS thread_count FROM feedback WHERE parent_id = 0");
  $row = $db->nextRow();
  $msg_total = $row['thread_count'];
  
  $query="SELECT f.* ".
       "FROM feedback f left join feedback f2 on f.id=f2.parent_id ".
       "WHERE f.parent_id=0 ";
	if ( !isset($_GET["ShowAllRecords"]) ) {
		 $query.="AND ( unix_timestamp(f.commentdate)>? ".
		 				 "  OR unix_timestamp(f2.commentdate)>? ) "; 
	}		 
	$query.="order by active desc, commentdate desc ".
       "limit ?, ?";
  if ( !isset($_GET["ShowAllRecords"]) ) {
    $result = $db->query($query, [$_SESSION["last_login_date"], $_SESSION["last_login_date"], intval($first_message), intval($msg_display)]);
  } else {
    $result = $db->query($query, [intval($first_message), intval($msg_display)]);
  }

	if ( !isset($_GET["ShowAllRecords"]) ) {
		$tourl="UserFeedback.php?ShowAllRecords=1&";
		$linklabel="Show All Records";
	} else {
		$tourl="UserFeedback.php";
		$linklabel="Show only Records which have been modified since my last login";
	}
?>
<div style="text-align:left;">
	 <a href="<?php echo $tourl?>"><?php echo $linklabel?></a>
</div>
<table class="data">
  <tr>
    <th colspan="4">
			Feedback Threads
			<?php
			  if ( !isset($_GET["ShowAllRecords"]) ) {
					 echo("modified since your last login");
				}
			?>
		</th>
  </tr>
  <tr>
    <th>Subject</th>
    <th>By</th>
    <th>Posts</th>
    <th>Last Post</th>
  </tr>
<?php
  $lastrow="xx";
  while ( $row=$result->nextRow() ) {
		if ( $lastrow!=$row["id"] ) {
		$lastrow=$row["id"];
    $info_query = $db->query("SELECT unix_timestamp(commentdate) as commentdate, user_id ".
                             "FROM feedback WHERE parent_id=? OR id=?", [$row["id"], $row["id"]]);
    $response_count = 0;
    $response_date = 0;
    $response_user = 0;
    while( $info_row = $info_query->nextRow() ) {
      if( $response_date < $info_row["commentdate"] ) {
        $response_date = $info_row['commentdate'];
        $response_user = $info_row['user_id'];
      }
      $response_count++;
    }
    
 ?>
    <tr>
      <td valign="top">
        <a href="UserFeedbackShowThread.php?id=<?php echo $row["id"]?>" target="_blank"><?php echo $row["subject"]?></a>
        <?php if( $row['announcement'] ) { echo "<font color='red'>Announcement</font>"; } ?>
      </td>
      <td valign="top"><?php echo userInfoPopup($row['user_id'])?></td>
      <td valign="top" align="center">
        <?php  if( $response_count ) { echo "$response_count"; } ?>
      </td>
      <td valign="top">
        <?php 
        if( $response_count ) {
          $response_date = strftime( "%c", $response_date );
          echo "On $response_date by " . userInfoPopup($response_user);
        } else {
          echo "&nbsp;";
        }
        ?>
      </td>
    </tr>
<?php
    }
  }
?>
  </table>
  <table class="data">
    <tr>
        <th width="50%"><?php
	$ShowAllRecords=( isset($_GET["ShowAllRecords"])?"ShowAllRecords=1&":"" );
  if( $first_message > 0 ) {
    $new_message = ( $first_message > $msg_display ) ? ( $first_message - $msg_display ) : 0;
    echo("<a href=\"UserFeedback.php?msg=$new_message&$ShowAllRecords\"><< Prev</a>");
  } else {
    echo("&nbsp;");
  }
  ?></th>
        <th align="right" width="50%"><?php
  if( $first_message + $msg_display < $msg_total ) {
    $new_message = $first_message + $msg_display;
    echo("<a href=\"UserFeedback.php?msg=$new_message&$ShowAllRecords\">Next >></a>");
  } else {
    echo("&nbsp;");
  }
  ?></th>
  </tr>
</table>

<form action="UserFeedback.php" method="post">
<table class="data">
  <tr>
    <th align="center"><font color="red">Please read the <a href="FaqDetails.php">FAQ</a> and existing feedback items before posting a question</font></th>
  </tr>
  <tr>
    <th align="center">Enter New Feedback about the Approvals System Below</th>
  </tr>
<?php
 if( isset( $_GET['pagefeedback'] ) ) {
   $feedback_url = $_SERVER['HTTP_REFERER'];
   $_POST['Subject'] = "Feedback on \"$_GET[pagefeedback]\"";
 } else {
   $feedback_url = isset($_POST['url']) ? $_POST['url'] : '';
 }
 ?>
  <tr>
    <th align="left">Subject
  <?php
    if( $_SESSION['super_user'] ) {
      echo("<span class='nonboldsmall'>(Announcement? <input type='checkbox' name='Announcement'>)</span>");
    }
  ?>
    </th>
  </tr>
  <tr>
    <td align="left">
      <input type="text" size="40" name="subject" value="<?php echo htmlentities(isset($_POST['Subject']) ? stripslashes($_POST['Subject']) : '')?>">
    </td>
  </tr>
  <tr>
    <th align="left">Reference URL</th>
  </tr>
  <tr>
    <td align="left">
      <input type="text" size="40" name="url" value="<?php echo htmlentities($feedback_url)?>">
    </td>
  </tr>
  <tr>
    <th align="left">Comment</th>
  </tr>
  <tr>
    <td align="left">
      <textarea name="comment" wrap="virtual" cols="60" rows="10"><?php echo htmlentities(isset($_POST['comment']) ? stripslashes($_POST['comment']) : '')?></textarea>
    </td>
  </tr>
  <tr>
    <th align="center">
      <input type="submit" value="Enter Comment Now">
    </th>
  </tr>
</table>  
</form>
<?php 
include_once("footerbar.inc") 
?>