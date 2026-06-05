<?php
class OrganizationLevel {
	var $GLOBE = "globe";
	var $NATION = "nation";
	var $REGION = "region";
	var $DOMAIN = "domain";
	var $CHAPTER = "chapter";
	var $organization_level;

	function __construct( $value ) {
		$this->organization_level = $value;	
	}
	
	function isHigherThan( $organization ) {
		if( $organization == null ) return true;
		if( $this->organization_level == "globe" ) return true;
		if( $this->organization_level == "nation" 
			&& $organization->organization_level != "globe" ) 
			return true;
		if( $this->organization_level == "region" 
			&& $organization->organization_level != "globe"  
			&& $organization->organization_level != "nation" )
			return true;			
		if( $this->organization_level == "domain" 
			&& $organization->organization_level != "globe"  
			&& $organization->organization_level != "nation"  
			&& $organization->organization_level != "region" )
			return true;			
		if( $this->organization_level == "chapter" 
			&& $organization->organization_level != "globe"  
			&& $organization->organization_level != "nation"  
			&& $organization->organization_level != "region"  
			&& $organization->organization_level != "domain" )
			return true;			
		return false;
	}
	}
