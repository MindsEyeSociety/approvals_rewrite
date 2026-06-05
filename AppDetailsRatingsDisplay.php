<?php
$commentDAO = $daoFactory->getCommentDAO();
$ratings = $commentDAO->readRatings( $app_info->id );

$TotalRatings=0;
foreach( $ratings as $rating) {
	$TotalRatings+=$rating["rating"];
}

$ThisAverage=0;
if ( count($ratings)>0 ) {
	$ThisAverage=$TotalRatings/count($ratings);
}

$smarty->assign( "ratings", $ratings );
$smarty->assign("average_rating", $ThisAverage );
$smarty->assign("vote_count", count($ratings) );
$smarty->display("AppDetailsRatingsDisplay.html");