<?php

include_once( "db.inc" );

$chapter_ids=array();
$db->query("SELECT id FROM organizations WHERE nation='USA' and chapter != '' and chapter is not null");
while( $info = $db->nextRow() ) {
  $chapter_ids[] = $info['id'];
}

foreach( $chapter_ids as $chapter_id ) {
	echo("Killing CST for $chapter_id<br>\n");
	$db->query("UPDATE users SET admin_org_id=NULL, assistant=0 WHERE admin_org_id=$chapter_id");
	$db->query("UPDATE organizations SET admin_user_id=NULL WHERE id=$chapter_id");
}