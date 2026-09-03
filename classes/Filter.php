<?php

class Filter {
	var $venue;
	var $category;
	var $search;
	var $status;
	var $filter_user_id;
	var $org_id;
	var $vss_id;
	var $recentmod;
	var $modinlast;
	var $required_approval;
	var $sortorder;
	var $suborgs;
	var $inverted;
	var $admin_org_list;
	var $admin_vss_list;

	function merge( $otherFilter ) {
		if( !is_null( $otherFilters->venue ) ) { $this->venue = $otherFilters->venue; }
		if( !is_null( $otherFilters->category ) ) { $this->category = $otherFilters->category; }
		if( !is_null( $otherFilters->filter_user_id ) ) { $this->filter_user_id = $otherFilters->filter_user_id; }
		if( !is_null( $otherFilters->status ) ) { $this->status = $otherFilters->status; }
		if( !is_null( $otherFilters->search ) ) { $this->search = $otherFilters->search;}
		if( !is_null( $otherFilters->org_id ) ) { $this->org_id = $otherFilters->org_id; }
		if( !is_null( $otherFilters->modinlast ) ) { $this->modinlast = $otherFilters->modinlast; }
		if( !is_null( $otherFilters->recentmod ) ) { $this->recentmod = $otherFilters->recentmod; }
		if( !is_null( $otherFilters->suborgs ) ) { $this->suborgs = $otherFilters->suborgs; }
		if( !is_null( $otherFilters->sortorder ) ) { $this->category = $otherFilters->sortorder;}
		if( !is_null( $otherFilters->inverted ) ) { $this->inverted = $otherFilters->inverted; }
		if( !is_null( $otherFilters->required_approval ) ) { $this->required_approval = $otherFilters->required_approval;}
		if( !is_null( $otherFilters->vss_id ) ) { $this->vss_id = $otherFilters->vss_id;}
	}

	function mergeArray( $otherFilters ) {
		$changed = false;
		if( array_key_exists( 'venue', $otherFilters ) ) { $changed=true; $this->venue = stripslashes($otherFilters['venue']); }
		if( array_key_exists( 'category', $otherFilters  ) ) { $changed=true; $this->category = stripslashes($otherFilters['category']); }
		if( array_key_exists( 'filter_user_id', $otherFilters ) ) { $changed=true; $this->filter_user_id = $otherFilters['filter_user_id']; }
		if( array_key_exists( 'status', $otherFilters  ) ) { $changed=true; $this->status = stripslashes($otherFilters['status']); }
		if( array_key_exists( 'search', $otherFilters ) ) { $changed=true; $this->search = stripslashes($otherFilters['search']); }
		if( array_key_exists( 'org_id', $otherFilters  ) ) { $changed=true; $this->org_id = $otherFilters['org_id']; }
		if( array_key_exists( 'modinlast', $otherFilters ) ) { $changed=true; $this->modinlast = $otherFilters['modinlast']; }
		if( array_key_exists( 'recentmod', $otherFilters ) ) { $changed=true; $this->recentmod = $otherFilters['recentmod']; }
		if( array_key_exists( 'suborgs', $otherFilters ) ) { $changed=true; $this->suborgs = $otherFilters['suborgs']; }
		if( array_key_exists( 'sortorder', $otherFilters ) ) { $changed=true; $this->sortorder = stripslashes($otherFilters['sortorder']); }
		if( array_key_exists( 'inverted', $otherFilters ) ) { $changed=true; $this->inverted = $otherFilters['inverted']; }
		if( array_key_exists( 'required_approval', $otherFilters ) && ($otherFilters['required_approval'][0] ?? "") != "" ) {
			$changed=true;
			$this->required_approval = $otherFilters['required_approval'];
		}
		if( array_key_exists( 'vss_id', $otherFilters ) ) { $changed=true; $this->vss_id = stripslashes($otherFilters['vss_id']); }
		return $changed;
	}

	function __construct(
		$venue = "",
		$category = "",
		$search = "",
		$status = "open",
		$filter_user_id = 0,
		$org_id = 0,
		$vss_id = 0,
		$recentmod = "",
		$modinlast = "",
		$required_approval = array(),
		$sortorder = "",
		$suborgs = 1,
		$inverted = "",
		$admin_org_list = array(),
		$admin_vss_list = array()
	) {
		$this->venue = $venue;
		$this->category = $category;
		$this->search = $search;
		$this->status = $status;
		$this->filter_user_id = $filter_user_id;
		$this->org_id = $org_id;
		$this->vss_id = $vss_id;
		$this->recentmod = $recentmod;
		$this->modinlast = $modinlast;
		$this->required_approval = $required_approval;
		$this->sortorder = $sortorder;
		$this->suborgs = $suborgs;
		$this->inverted = $inverted;
		$this->admin_org_list = $admin_org_list;
		$this->admin_vss_list = $admin_vss_list;
	}

	function needsOrganizationData() {
		return !empty($this->org_id) || $this->sortorder == "affiliation";
	}

	function needsCommentData() {
		return !empty($this->modinlast) || !empty($this->recentmod);
	}

	function needsCharacterData() {
		return !empty($this->search) || !empty($this->vss_id ) || $this->sortorder == 'character';
	}

	function needsVenueData() {
		return !empty($this->venue) || $this->sortorder == 'venue';
	}

	function needsUserData() {
		return !empty($this->search) || $this->needsOrganizationData() || $this->sortorder == 'player';
	}

	function needsCategoryData() {
		return !empty($this->category);
	}

	function needsVSSData() {
		return !empty($this->vss_id);
	}

	function needsRevisionData() {
		return !empty($this->modinlast) || !empty($this->recentmod);
	}
}
