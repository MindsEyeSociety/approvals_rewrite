<?php
	include_once( "db.inc" );
	
	$category_id = $_POST["modify"];
	$category = $_POST["category"];
	$venue_ids = $_POST["venue_ids"];
	
	$query = "UPDATE categories SET category=? WHERE id=?";
	$params = [ $category, $category_id ];
	//$db->query(sprintf($query,$db->escape($category),$category_id));
	$db->query($query, $params);

	$query = "SELECT venue_id ".
		"FROM category_venue ".
		"wHERE category_id = ?";
	$params = [ $category_id ];
	$results = $db->query($query, $params);
	$existing_venues = array();
	while( $result = $results->nextRow() ) {
		$existing_venues[] = $result['venue_id'];
	}

	/*$query = "DELETE FROM category_venue ".
		"WHERE category_id = %d and venue_id = %d";
	for( $i=count($existing_venues)-1; $i>=0; $i-- ) {
		if( !in_array( $existing_venues[$i], $venue_ids ) ) {
			$db->query( sprintf( 
				$query, 
				$category_id,
				$existing_venues[$i] ) );
			array_splice( $existing_venues, $i,1 );
		}
	}*/
	// 1. Define the SQL template with '?' instead of '%d'
	$sql = "DELETE FROM category_venue WHERE category_id = ? AND venue_id = ?";

	for ($i = count($existing_venues) - 1; $i >= 0; $i--) {
    		if (!in_array($existing_venues[$i], $venue_ids)) {

        		// 2. Pass the variables as an array to your new query method
        		$db->query($sql, [
            			$category_id,
            			$existing_venues[$i]
        		]);

        		array_splice($existing_venues, $i, 1);
    		}
	}
	
	/*$query = "INSERT INTO category_venue ( category_id, venue_id ) values ( %d, %d )";
	for( $i= count($venue_ids)-1; $i>= 0; $i-- ) {
		if( !in_array( $venue_ids[$i], $existing_venues )) {
			$db->query( sprintf( $query, $category_id, $venue_ids[$i] ) );
			$existing_venues[] = $venue_ids[i];
			array_splice( $venue_ids, $i,1 );
		}
	}*/
	// 1. Swap %d for ?
	$sql = "INSERT INTO category_venue (category_id, venue_id) VALUES (?, ?)";

	for ($i = count($venue_ids) - 1; $i >= 0; $i--) {
    		if (!in_array($venue_ids[$i], $existing_venues)) {

        		// 2. Use the new wrapper with the params array
        		$db->query($sql, [
            			$category_id,
            			$venue_ids[$i]
        		]);

        		// 3. Keep your local arrays in sync
        		$existing_venues[] = $venue_ids[$i];
        		array_splice($venue_ids, $i, 1);
    		}
	}
	header("Location: ModifyCategoryList1.php");
?>
