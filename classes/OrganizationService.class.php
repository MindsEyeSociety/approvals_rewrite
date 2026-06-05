<?php

class OrganizationService {
	var $organizationDAO;

	function __construct( $organizationDAO ) {
		$this->organizationDAO = $organizationDAO;
	}

	function getLabeledOrganizationTree( $organization_IDs ) {
		$organizations = $this->organizationDAO->readOrganizationsByIDs( $organization_IDs );

		for( $i=0; $i<count($organizations); $i++ ) {
			if ( strlen($organizations[$i]["chapter"]) > 0 ) {
				 $organizations[$i]['label'] = "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&#149;&nbsp;{$organizations[$i]['chapter']}&nbsp;({$organizations[$i]['org_name']})";
			} else if ( strlen($organizations[$i]["domain"]) > 0 ) {
				 $organizations[$i]['label'] = "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&#149;&nbsp;{$organizations[$i]['domain']}&nbsp;({$organizations[$i]['org_name']})";
			} else if ( strlen($organizations[$i]["region"]) > 0 ) {
				 $organizations[$i]['label'] = "&nbsp;&nbsp;&nbsp;&#149;&nbsp;{$organizations[$i]['region']}&nbsp;({$organizations[$i]['org_name']})";
			} else if ( strlen($organizations[$i]["nation"]) > 0 ) {
				 $organizations[$i]['label'] = "&#149;&nbsp;{$organizations[$i]['nation']}&nbsp;({$organizations[$i]['org_name']})";
			} else {
				 $organizations[$i]['label'] = "Global";
			}
		}
		return $organizations;
	}
}
