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
  include_once("include/pagination.inc");

  // Grab the first message number from the URL
  $first_message = isset( $_GET['msg'] ) ? intval($_GET['msg']) : 0 ;
  
  // Set up some variables for changing things later.
  $msg_display = 5;
  
  if ( $_SERVER['REQUEST_METHOD'] == 'POST' ) {
    if ( !empty($_POST["comment"]) && !empty( $_POST["subject"] ) ) {
      $query="INSERT into feedback ".
          "(user_id, commentdate, comment, subject, active, root_id, parent_id,url) ".
          "values ".
          "(?, now(), ?, ?, '1', '0', ?, ?)";
      $db->query($query, [$_SESSION["user_id"], $_POST["comment"], $_POST["subject"], $_POST["parent_id"], $_POST["url"]]);
      echo("<div align=center class=subhead>Your Comment has been Entered.</div>");
      unset($_POST['comment']);
      unset($_POST['subject']);
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
  
  $db->query("SELECT count(*) AS thread_count FROM feedback WHERE parent_id = ?", [$_GET["id"]]);
  $row = $db->nextRow();
  $msg_total = $row['thread_count'];
  $query="select f.* ".
         "from feedback f ".
         "where f.id=?";
  $res = $db->query($query, [$_GET["id"]]);
  $parent_row=$res->nextRow();
 ?>
  <table class="data">
    <tr>
      <th><?php echo $parent_row['subject']?></th>
    </tr>
    <tr class="dark">
      <td>
        Posted by <?php echo userInfoPopup($parent_row['user_id'])?> on <?php echo $parent_row["commentdate"]?></b>
      </td>
    </tr>
    <tr>
      <td><?php echo nl2br( $parent_row['comment'])?></td>
    </tr>
    <?php if ( $parent_row['url'] != '' ) { ?>
    <tr>
      <td>Reference URL: <a href="<?php echo $parent_row['url']?>"><?php echo $parent_row['url']?></a></td>
    </tr>
    <?php } ?>
    <tr>
      <th>Responses</th>
    </tr>
<?php  
  $query="select f.* from feedback f ".
       "where parent_id=? ".
       "order by active desc, commentdate desc ".
       "limit ?, ?";
  $res = $db->query($query, [$_GET["id"], paginationOffset($first_message), $msg_display]);
  $msg_count = 0;
  while ( $row=$res->nextRow() ) {
    $msg_count += 1;
 ?>
    <tr class="dark">
      <td>
        Posted by <?php echo userInfoPopup($row["user_id"])?> on <?php echo $row["commentdate"]?></b>
      </td>
    </tr>
    <tr>
      <td>
<div style="font-weight:bold"><?php echo $row["subject"]?></div>
<?php echo nl2br( $row['comment'] )?>
      </td>
    </tr>
    <?php if ( $row['url'] != '' ) { ?>
    <tr>
      <td>Reference URL: <a href="<?php echo $row['url']?>"><?php echo $row['url']?></a></td>
    </tr>
    <?php } ?>
<?php
  }
  if( !$msg_count ) {
    echo "<tr><td align=\"center\"><i>(none)</i></td></tr>\n";
  }
?>
  </table>
  <table class="data">
    <tr>
        <th width="50%"><?php
  if( $first_message > 0 ) {
    $new_message = ( $first_message > $msg_display ) ? ( $first_message - $msg_display ) : 0;
    echo("<a href=\"UserFeedbackShowThread.php?id=" . urlencode($_GET["id"]) . "&msg=$new_message\"><< Prev</a>");
  } else {
    echo("&nbsp;");
  }
  ?></th>
		<th align="right" width="50%"><?php
  if( $first_message + $msg_display < $msg_total ) {
    $new_message = $first_message + $msg_display;
    echo("<a href=\"UserFeedbackShowThread.php?id=" . urlencode($_GET["id"]) . "&msg=$new_message\">Next >></a>");
  } else {
    echo("&nbsp;");
  }
  
  ?></td>
  </tr>
</table>
<form action="UserFeedbackShowThread.php?id=<?php echo urlencode($_GET["id"])?>&" method="post">
<table class="data">
  <tr>
    <th align="center">Respond:</th>
  </tr>
  <tr>
    <th align="left">Subject</th>
  </tr>
  <tr>
    <td align="left">
      <input type="text" size="40" name="subject" value="<?php echo htmlspecialchars($parent_row['subject'])?>">
    </td>
  </tr>
<?php
 if( isset( $_GET['pagefeedback'] ) ) {
   $feedback_url = $_SERVER['HTTP_REFERRER'];
 } else {
   $feedback_url = isset($_POST['url']) ? $_POST['url'] : '';
 }
 ?>
    <tr>
      <th align="left">Reference URL</th>
    </tr>
    <tr>
      <td align="left">
        <input type="text" size="40" name="url" value="<?php echo htmlspecialchars($feedback_url)?>">
      </td>
    </tr>
    <tr>
      <th align="left">Comment</th>
    </tr>
    <tr>
      <td align="left">
        <textarea name="comment" wrap="virtual" cols="60" rows="10"></textarea>
      </td>
    </tr>
    <tr>
      <th align="center">
        <input type="hidden" name="parent_id" value="<?php echo htmlspecialchars($_GET['id'])?>">
        <input type="submit" value="Enter Comment Now">
      </th>
    </tr>
  </table>
</form>
<?php 
include_once("footerbar.inc") 
?>