<?php
include_once("classes/Application.class.php");
include_once("classes/Organization.class.php");
include_once("classes/Character.class.php");
include_once("classes/Venue.class.php");
include_once("classes/ApplicationRow.class.php");

class ApplicationDAO {
	function __construct( $db ) {
		$this->db = $db;
	}

	function readByID( $id ) {
		$query = "SELECT a.*, ".
			" o.org_name, ".
			" c.name as character_name, c.org_id as character_org_id, c.subtype, c.char_type, c.background, c.venue_id as character_venue_id, ".
			" cat.category, cv.venue as character_venue ".
			"FROM applications a ".
			"LEFT JOIN users u ON a.user_id = u.id ".
			"LEFT JOIN organizations o on u.org_id = o.id ".
			"LEFT JOIN characters c ON c.id = a.character_id ".
			"LEFT JOIN categories cat ON a.category = cat.category ".
			"LEFT JOIN venues cv on c.venue_id = cv.id ".
			"WHERE a.ID=?";
		$this->db->query( $query, [$id] );
		$rowData = $this->db->nextRow();
		if( $rowData != null ) {
			$application = new Application(
				$rowData['app_number'],
				$rowData['user_id'],
				$rowData['org_id'],
				$rowData['character_id'],
				$rowData['venue_id'],
				$rowData['category'],
				$rowData['required_approval'],
				$rowData['status'],
				$rowData['active'],
				$rowData['description'],
				$rowData['justification'],
				$rowData['character_sheet'],
				$rowData['mechanics'],
				$rowData['creation_date'],
				$rowData['status_change_date'],
				$rowData['update_date'] /*,
				new Approval( $rowData['low_approval_user_id'], $rowData["low_approval_date"], $id ),
				new Approval( $rowData['mid_approval_user_id'], $rowData["mid_approval_date"], $id ),
				new Approval( $rowData['high_approval_user_id'], $rowData["high_approval_date"], $id ),
				new Approval( $rowData['top_approval_user_id'], $rowData["top_approval_date"], $id ),
				new Approval( $rowData['global_approval_user_id'], $rowData["global_approval_date"], $id ) */
			);
			$character = new Character(
				$rowData['character_id'],
				$rowData['character_name'],
				null,
				$rowData['subtype'],
				null,
				$rowData['char_type'],
				null,
				null,
				$rowData['background']
			);
			$character->venue = new Venue(
				$rowData['character_venue_id'],
				$rowData['character_venue']
			);
			$application->character = $character;

			$application->id = $rowData["id"];
		} else {
			$application = new Application();
		}
		return $application;
	}

	function readSecurityInfoByID( $id ) {
		$query = "SELECT a.user_id, u.org_id as user_org_id, c.vss_id, a.org_id ".
			"FROM applications a ".
			"LEFT JOIN users u on a.user_id=u.id ".
			"LEFT JOIN characters c on a.character_id = c.id ".
			"WHERE a.id=?";
		$this->db->query( $query, [$id] );
		return $this->db->nextRow();
	}

	function insert( &$application ) {
		$query =
			"INSERT INTO applications (".
			"  user_id,".
			"  character_id,".
			"  venue_id,".
			"  category,".
			"  status,".
			"  required_approval,".
			"  active,".
			"  description,".
			"  justification,".
			"  character_sheet,".
			"  mechanics,".
			"  creation_date,".
			"  status_change_date,".
			"  update_date,".
			"  org_id".
			") VALUES (".
			"  ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, now(), now(), now(), ?".
			")";
		$this->db->query( $query, [
			$application->user_id,
			$application->character_id,
			$application->venue_id,
			$application->category,
			$application->status,
			$application->required_approval,
			$application->description,
			$application->justification,
			$application->character_sheet,
			$application->mechanics,
			$application->org_id
		] );
		$application->id = $this->db->getInsertId();
	}

	function update( $application ) {
		$query =
			"UPDATE applications SET ".
			"  app_number = ?, ".
			"  user_id = ?, ".
			"  org_id = ?, ".
			"  character_id = ?, ".
			"  venue_id = ?, ".
			"  category = ?, ".
			"  required_approval = ?, ".
			"  status = ?, ".
			"  active = ?, ".
			"  description = ?, ".
			"  justification = ?, ".
			"  character_sheet = ?, ".
			"  mechanics = ?, ".
			"  creation_date = ?," .
			"  status_change_date = ?, ".
			"  update_date = ? ".
			"WHERE ID=?";
		$this->db->query( $query, [
			$application->app_number,
			$application->user_id,
			$application->org_id,
			$application->character_id,
			$application->venue_id,
			$application->category,
			$application->required_approval,
			$application->status,
			$application->active,
			$application->description,
			$application->justification,
			$application->character_sheet,
			$application->mechanics,
			$application->creation_date,
			$application->status_change_date,
			$application->update_date,
			$application->id
		] );

	}

	function readApplicationRowDataById( $applicationID ) {
		$query = "SELECT a.app_number, a.creation_date, a.description, ".
			"a.required_approval, a.status_change_date, a.update_date, o.*, " .
			"c.name as character_name, c.subtype as character_type, " .
			"v.venue, a.user_id, a.category, a.status, a.active, ".
			"a.org_id as a_org_id, u.org_id as u_org_id ".
			"FROM applications a ".
			"LEFT JOIN users u ON a.user_id=u.id ".
			"LEFT JOIN organizations o ON u.org_id = o.id " .
			"LEFT JOIN characters c ON a.character_id = c.id " .
			"LEFT JOIN venues v ON a.venue_id = v.id ".
			"WHERE a.id=?";
		$this->db->query( $query, [$applicationID] );
		$row = $this->db->nextRow();
		$organization = new Organization( $row['globe'], $row['nation'], $row['region'], $row['domain'], $row['chapter'], $row['city'], $row['state'], $row['country'], $row['admin_user_id'], $row['id']);
		$applicationRow = new ApplicationRow(
			$row['app_number'],
			$this->db->parseTime($row['creation_date']),
			$row['description'],
			$this->db->parseTime($row['update_date']),
			$row['required_approval'],
			$this->db->parseTime($row['status_change_date']),
			$organization,
			$row['character_name'],
			$row['character_type'],
			$row['venue'],
			$row['user_id'],
			$row['category'],
			$row['status'],
			$row['active'],
			$row['a_org_id'],
			$row['u_org_id'] );
		return $applicationRow;
	}

	function readApplicationUserId( $app_id ) {
		$query = "SELECT user_id FROM applications WHERE id=?";
		$result = $this->db->query($query, [$app_id]);
		if( $result->numRows() > 0 ) {
			$app_info = $result->nextRow();
			return $app_info['user_id'];
		} else {
			return 0;
		}
	}

	function readApplicationIDsByFilters( $filter, $user_id, $admin_org_venue_list, $admin_vss_list, $last_login_date, $org_list ) {
		$echoquery = false;
		$query = "SELECT a.id AS app_id FROM applications a ";

		if( $filter->needsUserData() || sizeof($admin_org_venue_list) > 0 ) {
			$query .= "LEFT JOIN users u ON a.user_id=u.id ";
		}
		if( $filter->needsOrganizationData() || sizeof($admin_org_venue_list) > 0 ) {
			$query .= "LEFT JOIN organizations o ON a.org_id = o.id ";
		}
		if( $filter->needsCommentData() ) {
			$query .= "LEFT JOIN comments com ON com.app_id=a.id ";
		}
		if( $filter->needsCharacterData() || sizeof($admin_vss_list) > 0 ) {
		  	$query .= "LEFT JOIN characters c ON a.character_id=c.id ";
		}
		if( $filter->sortorder == 'venue' ) {
			$query .= "LEFT JOIN venues v ON a.venue_id=v.id ";
		}
		if( $filter->needsCategoryData() ) {
			$query .= "LEFT JOIN categories cat ON a.category = cat.category ";
		}
		if( $filter->needsRevisionData() ) {
			$query .= "LEFT JOIN revisions r ON r.application_id = a.id ";
		}
		if( $filter->needsVSSData() ) {
			$query .= "LEFT JOIN vsss s ON s.id = c.vss_id ";
		}
		$where = Array();
		if(empty($_SESSION['super_user'])){
			$orgwhere= "( a.user_id=? ";
			$where_params = [$user_id];
			if ( sizeof($admin_org_venue_list) > 0 ) {
				foreach( $admin_org_venue_list as $venue_id => $admin_org_list ) {
					$venue_id = (int)$venue_id;
					$temp = "create temporary table user_orgids{$venue_id} (orgid int not null, PRIMARY KEY (`orgid`)) ENGINE=Memory";
					if($echoquery) echo $temp."\n";
					$this->db->query($temp);
					$placeholders = implode(",", array_fill(0, count($admin_org_list), "?"));
					$temp = "insert into user_orgids{$venue_id} (orgid) VALUES ";
					$value_strings = array_fill(0, count($admin_org_list), "(?)");
					$temp .= implode(",", $value_strings);
					if($echoquery) echo $temp."\n";
					$this->db->query($temp, $admin_org_list);
					$query .= "LEFT JOIN user_orgids{$venue_id} uo{$venue_id} on (o.id=uo{$venue_id}.orgid) ";
					if( $venue_id != 0 ) {
						$orgwhere .= " OR ( o.id = uo{$venue_id}.orgid AND a.venue_id = ? )";
						$where_params[] = $venue_id;
					} else {
						$orgwhere .= " OR o.id = uo{$venue_id}.orgid ";
					}
				}
			}
			if ( sizeof($admin_vss_list) > 0 ) {
				$temp = "create temporary table user_vsss (vss_id int not null, PRIMARY KEY (`vss_id`)) ENGINE=Memory";
				if($echoquery) echo $temp."\n";
				$this->db->query($temp);
				$value_strings = array_fill(0, count($admin_vss_list), "(?)");
				$temp = "insert into user_vsss (vss_id) VALUES " . implode(",", $value_strings);
				if($echoquery) echo $temp."\n";
				$this->db->query($temp, $admin_vss_list);
				$query .= "LEFT JOIN user_vsss uv on (c.vss_id=uv.vss_id) ";
				$orgwhere .= " OR (c.vss_id is not null and c.vss_id =uv.vss_id )";
			}
			$orgwhere.=")";
			$where[] = $orgwhere;
		}
	  if ( !is_null( $filter->status ) ) {
		if ( $filter->status == "open" ) {
	      $where[] = "a.status not in ('Approved','Denied','Removed')";
	    } elseif ( $filter->status != "unconstrained" ) {
	      $where[] = "a.status=?";
	      $where_params[] = $filter->status;
	    }
	  }
	  if ( !empty( $filter->search ) ) {
	    $search = $filter->search;
	    $where[]= "( lower(a.description) like ? OR lower(c.name) like ? OR lower(u.name) like ? OR lower(a.app_number) like ? )";
	    $like_search = "%" . strtolower($search) . "%";
	    $where_params[] = $like_search;
	    $where_params[] = $like_search;
	    $where_params[] = $like_search;
	    $where_params[] = $like_search;
	  }
	  if ( !empty( $filter->category ) ) {
	    $where[]= "cat.category=?";
	    $where_params[] = $filter->category;
	  }

	  if ( !empty( $filter->venue ) ) {
	    $where[] = "a.venue_id=?";
	    $where_params[] = $filter->venue;
	  }
	  if ( !empty( $filter->org_id ) && sizeof($org_list) > 0 ) {
		$temp = "create temporary table search_orgids (orgid int not null, PRIMARY KEY (`orgid`)) ENGINE=Memory";
		if($echoquery) echo $temp."\n";
		$this->db->query($temp);
		$value_strings = array_fill(0, count($org_list), "(?)");
		$temp = "insert into search_orgids (orgid) VALUES " . implode(",", $value_strings);
		if($echoquery) echo $temp."\n";
		$this->db->query($temp, $org_list);
		$query .= "LEFT JOIN search_orgids so on (o.id=so.orgid) ";
		$where[] .= "o.id = so.orgid ";
	  }
	  if ( !empty( $filter->vss_id ) ) {
	    $where[]= "s.id = ?";
	    $where_params[] = $filter->vss_id;
	  }
	  if ( !empty( $filter->modinlast ) ) {
	    $modinlast = intval($filter->modinlast);
	    $where[] = "( date_add(r.revision_date, INTERVAL $modinlast DAY) > Now() OR date_add(com.comment_date, INTERVAL $modinlast DAY ) > Now() ) ";
	  }
	  if ( !empty( $filter->recentmod ) ) {
	    $where[] = "( r.revision_date > from_unixtime(?) OR com.comment_date > from_unixtime(?) )";
	    $where_params[] = $last_login_date;
	    $where_params[] = $last_login_date;
	  }
	  if ( is_array( $filter->required_approval) &&
	       sizeof( $filter->required_approval ) > 0 )  {
		$placeholders = implode(",", array_fill(0, count($filter->required_approval), "?"));
	    $where[] = "a.required_approval in ($placeholders)";
	    $where_params = array_merge($where_params, $filter->required_approval);
	  }
	  if(count($where)) {
		if (isset($where_params) && count($where_params) > 0) {
			// The first where clause is actually part of a security check, not a bound param
			// For the initial orgwhere, we need to handle it differently
			$query_params = [];
			for ($i = 0; $i < count($where); $i++) {
				if ($i == 0 && !empty($_SESSION['super_user'])) {
					// Skip super user org where clauses
				}
			}
		}
		$query.= " WHERE ".implode(' AND ',$where);
	  }
	  $query .= " GROUP BY a.id ";
	  $query .= " ORDER BY ";
	  $maindir = $filter->inverted ? "desc" : "asc";
	  $backdir = $filter->inverted ? "asc" : "desc";
		switch( $filter->sortorder ) {
			case 'appnumber':
				$query .= "a.app_number $maindir";
				break;
			case 'item':
				$query .= "a.description $maindir";
				break;
			case 'character':
				$query .= "c.name $maindir";
				break;
			case 'venue':
				$query .= "v.venue $maindir";
				break;
			case 'category':
				$query .= "a.category $maindir";
				break;
			case 'affiliation':
				$query .= "o.nation $maindir, o.region $maindir, ".
					"o.domain $maindir, o.chapter $maindir";
				break;
			case 'update_date':
				$query .= "a.update_date $backdir";
				break;
			case 'required_approval':
				$query .= "a.required_approval $maindir";
				break;
			case 'status':
				$query .= "a.status $maindir, a.status_change_date $backdir";
				break;
			default:
				$query .= "a.creation_date $maindir";
		}
		if($echoquery) echo $query."\n";
		if (isset($where_params) && count($where_params) > 0) {
			$rs = $this->db->query( $query, $where_params );
		} else {
			$rs = $this->db->query( $query );
		}
		$app_ids = array();
		while ( $row = $rs->nextRow() ) {
			$app_ids[] = $row['app_id'];
	  }
		
		return $app_ids;
	}

	function readMyAppRowData( $userID, $showDeniedRemoved ) {
		$query = "SELECT a.id, a.app_number, a.description, c.name as char_name, ".
		           "v.venue, a.category, unix_timestamp(a.update_date) as ".
		           "updated_date, a.status " .
		           "FROM applications a " .
		           "LEFT JOIN characters c ON a.character_id = c.id ".
		           "LEFT JOIN venues v ON a.venue_id = v.id ".
		           "WHERE a.user_id=? ";
		if( $showDeniedRemoved ) {
			$query .= "AND status not in ('Removed', 'Denied') ";
		}
		$query.= "ORDER BY a.status, v.venue, c.name, a.creation_date";
		$r = $this->db->query( $query, [$userID] );
		$rows = array();
		while( $row = $r->nextRow() ) {
			$rows[] = $row;
		}
		return $rows;
	}

	function buildApplicationNumber( $application ) {
		$query =
			"SELECT o.nation, o.region, v.venue ".
			"FROM organizations o ".
			"CROSS JOIN venues v ".
			"WHERE o.id=? AND v.id=?";
		$this->db->query( $query, [$application->org_id, $application->venue_id] );
		$abbreviations = $this->db->nextRow();
		return sprintf("%s-%s-%s-%s-%06d",
			$abbreviations['nation'],
			$abbreviations['region'],
			strtoupper( substr( $abbreviations['venue'], 0,2 ) ),
			strftime( "%y%m" ),
			$application->id);
	}

	function updateApplicationNumber( &$application ) {
		$application->app_number = $this->buildApplicationNumber($application);
		$this->db->query(
			"UPDATE applications ".
			"SET app_number=? WHERE id=?",
			[$application->app_number, $application->id]
		);
	}


	function deleteByID( $id ) {
		$this->db->query("DELETE FROM revisions WHERE application_id=?", [$id]);
		$this->db->query("DELETE from comments where app_id=?", [$id]);
		$this->db->query("DELETE from applications where id=?", [$id]);
	}

	function updateStatus ( $applicationID, $status ) {
        $query="UPDATE applications SET status=?, ".
        	"status_change_date=now(), ".
            "update_date=now() WHERE id=?";
		$this->db->query($query, [$status, $applicationID]);
	}

}