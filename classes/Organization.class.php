<?php

include_once("OrganizationLevel.class.php");

class Organization {
	var $globe;
	var $nation;
	var $region;
	var $domain;
	var $chapter;
	var $city;
	var $state;
	var $country;
	var $admin_user_id;
	var $org_name;
	var $email;
	var $email_is_google;
	var $active;
	var $id;
	var $_level;

	function __construct(
		$globe = "",
		$nation = "",
		$region = "",
		$domain = "",
		$chapter = "",
		$city = "",
		$state = "",
		$country = "",
		$admin_user_id = "",
		$org_name = "",
		$email = "",
		$email_is_google = "",
		$id = "",
		$active = 1
	) {
		$this->globe = $globe;
		$this->nation = $nation;
		$this->region = $region;
		$this->domain = $domain;
		$this->chapter = $chapter;
		$this->city = $city;
		$this->state = $state;
		$this->country = $country;
		$this->admin_user_id = $admin_user_id;
		$this->org_name = $org_name;
		$this->email = $email;
		$this->email_is_google = $email_is_google;
		$this->id = $id;
		$this->active = $active;
	}

	function getLevel() {
		if( $this->_level == null ) {
			if( $this->chapter != "" && $this->chapter != null ) $this->_level = new OrganizationLevel( "chapter" );
			else if( $this->domain != "" && $this->domain != null ) $this->_level = new OrganizationLevel( "domain" );
			else if( $this->region != "" && $this->region != null ) $this->_level = new OrganizationLevel( "region" );
			else if( $this->nation != "" && $this->nation != null ) $this->_level = new OrganizationLevel( "nation" );
			else if( $this->globe != "" && $this->globe != null ) $this->_level = new OrganizationLevel( "globe" );
		}
		return $this->_level;
	}

	function isHigherThan( $organization ) {
		if( $organization == null || $this->getLevel() == null ) return true;
		$thisLevel = $this->getLevel();
		$organizationLevel = $organization->getLevel();
		return $thisLevel->isHigherThan( $organizationLevel );
	}
}
?>
