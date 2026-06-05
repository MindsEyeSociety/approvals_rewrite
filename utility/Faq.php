<?php 
  include_once("db.inc");
	$pagetitle="FAQ";
  include_once("header.inc");
	include_once("titlebar.php"); 
?>

<h1>Frequently Asked Questions</h1>
<ol>
  <li>
    <b>Q</b>: The name on all of the comments changed when my CST gave their
      account to the new ST.  What's wrong?<br>
    <b>A</b>: Accounts on the Approvals Database are intended to be per-person,
      not "CST" or "DST" accounts.  The old CST should take back their account
      from the new CST, and the new CST should have their DST or RST upgrade 
      their own account to have CST privileges</li>
  <br>
  <li>
    <b>Q</b>: My application has vanished from the database!  What happened?
      How can I get my application back?<br>
    <b>A</b>: Your application is still in the database, but most likely your
      filters are filtering the application out from your view.  The most 
      common way this will happen is if you have the "Modified since my last
      Visit" box checked, though you should review all of your filter settings
      to make sure there's nothing interfering with your apps</li>
  <br>
  <li>
    <b>Q</b>: Our Chapter/Domain/Region/Nation just changed storytellers.  How
      do we set up the new storyteller in the database?<br>
    <b>A</b>: There are two ways to handle this:  The outgoing storyteller can 
      go into the <a href="http://legacy.modernenigmasociety.org/approvals_2017/UserList.php">User
      List</a>, click on the new storyteller, and use the "Storyteller For"
      Field to set them to the ST of the chapter/domain/whatever.  They can 
      then go into their own 
      <a href="http://legacy.Modernenigmasociety.org/approvals_2017/UserDisplay.php?mode=edit">Personal
      Information</a>, and set themselves to Storyteller for: (None).<br>
      <br>
      The second way is for the next storyteller up to conduct all of these 
      changes, I.E. a DST can change the CST of a chapter in their 
      jurisdiction, an RST can change DST's and independent Chapters' CST's
      etc.</li>
  <br>
  <li>
    <b>Q</b>: <br>
    <b>A</b>: </li>
  <br>
</ol>

<?php include_once("footerbar.inc") ?>
