<?php
/*
This page is designed to save public mechanics for applications so that
other users can apply for the same items (such as the "Force" discipline).

It requires a table called:
mechanics

This table has the following fields:
id, an autonum
user_id, a number
commentdate, a date
comment, a memo/text
subject, a varchar(255)
active, a yes/no (bit)
*/

  include_once("db.inc");
  $pagetitle="Custom Mechanics";

  include_once("header.inc");
  include_once("titlebar.php");

  $query="SELECT m.*, v.venue ".
					"FROM mechanics m left join ".
					"venues v on m.venue_id=v.id ".
					"ORDER BY v.id, m.category, m.title" ;
  $result = $db->query($query);
  if ( $_SESSION['admin_level']=="nation" || 
       $_SESSION['admin_level']=="globe" ||
       $_SESSION['super_user'] ) {
?>

<a href="MechanicsDetails.php?mode=Add&">Add New Mechanics</a>
<?php
  }
 ?>
<table class="data">
  <tr>
	  <th>Venue</th>
	  <th>Category</th>
	  <th>Title</th>
  </tr>
<?php
	$count = 0;
	while ( $row=$result->nextRow() ) {
 ?>
    <tr<?php if( ++$count % 2) { ?> class="dark"<?php } ?>>
      <td valign="top"><?php echo $row['venue']?></td>
      <td valign="top"><?php echo $row['category']?></td>
      <td valign="top">
        <a href="MechanicsDetails.php?id=<?php echo $row["id"]?>"><?php echo $row['title']?></a>
      </td>
    </tr>
<?php
  }
?>
</table>
<?php

include_once("footerbar.inc");
