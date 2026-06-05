<?php

class Plotkit {
	var $id;
	var $vss_id;
	var $title;
	var $start_date;
	var $end_date;
	var $storyteller_id;
	var $url;
	var $plot_details;
	var $venue_id;

	function __construct( $vss_id = "", $title = "", $start_date = "", $end_date = "", $storyteller_id = "", $url = "", $plot_details = "", $venue_id = "", $id = "" ) {
		 $this->vss_id = $vss_id;
		 $this->title = strip_tags($title);
		 $this->start_date = $start_date;
		 $this->end_date = $end_date;
		 $this->storyteller_id = $storyteller_id;
		 $this->url = $url;
		 $this->plot_details = strip_tags($plot_details);
		 $this->venue_id = $venue_id;
	}
}
