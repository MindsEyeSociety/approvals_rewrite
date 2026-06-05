<?php
include_once("db.inc");
include_once("classes/Category.class.php");
include_once("classes/CategoryDAO.class.php");

$category = new Category( $_POST['category'] );
$venue_ids = (isset($_POST['venue_ids']))?array_unique($_POST['venue_ids']):array();

header("Content-type: text/html");

$categoryDAO = $daoFactory->getCategoryDAO();
if ( !$categoryDAO->isDuplicate($category) ) {
	$category = $categoryDAO->insert( $category );

	foreach ( $venue_ids as $venue_id ) {
		if( !$categoryDAO->isAssignedVenue( $category, $venue_id ) ) {
			$categoryDAO->assignVenue($category,$venue_id);
			print("Assigned $venue_id to $category->category\n");
		}
	}
}
exit(0);

header("Location: ModifyCategoryList1.php");
