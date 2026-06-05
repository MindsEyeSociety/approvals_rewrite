<?php
class VSSKey{
	var $vss_id;
	var $assistant;
	function __construct( $vss_id = null, $assistant = false ) {
		$this->vss_id = $vss_id;
		$this->assistant = $assistant;
	}
}

class OrganizationKey {
	var $organization_id;
	var $assistant;
	var $venue_id;
	var $org_level;

	function __construct( $organization_id = null, $assistant = false, $venue_id = null, $organization_level = null) {
		$this->organization_id = $organization_id;
		$this->organization_level = $organization_level;
		$this->assistant = $assistant;
		$this->venue_id = $venue_id;
	}

	function setOrganization( $organizationVO ) {
		$this->organization_id = $organizationVO->id;
		$this->organization_level = $organizationVO->getLevel();
	}
}

class Keymaster {
	var $_vssKeys = array();
	var $_organizationKeys = array();
	var $_super_user = false;

	function __construct( $super_user = false ) {
		$this->_super_user = $super_user;
	}

	function addVSSKey( $vssKey ) {
		if( !isset( $_vssKeys[$vssKey->vss_id] ) ) {
			$vssKeys[$vssKey->vss_id] = array();
		}
		$_vssKeys[$vssKey->vss_id][] = $vssKey;
	}

	function addOrgKey( $orgKey ) {
		if( !isset( $_organizationKeys[$orgKey->organization_id] ) ) {
			$organizationKeys[$orgKey->organization_id] = array();
		}
		$_organizationKeys[$orgKey->organization_id][] = $orgKey;
	}

	function isOrgStoryteller( $org_id ) {
		return (
			isset( $this->_organizationKeys[$org_id] )
			&& count( $this->_organizationKeys[$org_id] ) > 0
		);
	}

	function isOrgFinal( $org_id ) {
		if( !isset( $this->_organizationKeys[$org_id]) ) return false;
		$org_keys = $this->_organizationKeys[$org_id];
		foreach( $org_keys as $key ) {
			if( !$key->assistant ) return true;
		}
		return false;
	}

	function isVSSStoryteller( $vss_id ) {
		return (
			isset( $this->_vssKeys[$vss_id] )
			&& count( $this->_vssKeys[$vss_id] ) > 0
		);
	}

	function isVSSFinal( $vss_id ) {
		if( !isset( $this->_vssKeys[$vss_id]) ) return false;
		$keys = $this->_vssKeys[$vss_id];
		foreach( $keys as $key ) {
			if( !$key->assistant ) return true;
		}
		return false;
	}

	function getHighestOrgLevel() {
		$topLevel = null;
		foreach( $this->_organizationKeys as $orgKeys ) {
			foreach( $orgKeys as $key ) {
				if( $key->organization_level != null && $key->organization_level->isHigherThan($topLevel) ) {
					$topLevel = $key;
				}
			}
		}
		return $topLevel;
	}

	function getHighestFinalOrgLevel() {
		$topLevel = null;
		foreach( $this->_organizationKeys as $orgKeys ) {
			foreach( $orgKeys as $key ) {
				if( !$key->assistant && $key->organization_level != null && $key->organization_level->isHigherThan($topLevel) ) {
					$topLevel = $key;
				}
			}
		}
		return $topLevel;
	}

}
