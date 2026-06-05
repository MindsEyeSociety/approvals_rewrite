<?php
include_once("db.inc");
include_once("application.inc");

$resultSet = $db->query(
	"SELECT c.id, c.category, v.venue, cv.venue_id " .
	"FROM categories c " .
	"INNER JOIN category_venue cv ON c.id = cv.category_id " .
	"INNER JOIN venues v ON cv.venue_id=v.id " .
	"ORDER BY c.category, v.venue");
$categories = array();

while( $row = $resultSet->nextRow() ) {
	if( ! isset( $categories[$row['category']] ) ) {

		$categories[$row['category']] = array();
		$categories[$row['category']]['venues'] = array();
		$categories[$row['category']]['id'] = $row['id'];
		$categories[$row['category']]['category'] = $row['category'];
	}
	$categories[$row['category']]['venues'][] = $row['venue'];
}

foreach( $categories as $category ) {
	$categories[$category['category']]['venues'] = implode(", ",$category['venues']);
}

$db->query("select * from venues where venue<>'Non Venue-Specific' order by venue");
$venueOptions = $db->getAllRows();

$smarty->assign( "menus", generateMenus() );
$smarty->assign( "categories", $categories );
$smarty->assign( "venueOptions", $venueOptions );
$smarty->assign( "selectSize", count($venueOptions) );
$smarty->assign( "page_title", "Category Listing" );

$smarty->display("ModifyCategoryList1.html");
