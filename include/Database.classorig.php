<?php

class Database {
	var $db_host;
	var $db_user;
	var $db_pass;
	var $db_name;
	var $dbh;
	var $lastResult;


	function Database( $host, $user, $pass, $name ) {
		$this->db_host = $host;
		$this->db_user = $user;
		$this->db_pass = $pass;
		$this->db_name = $name;
		$this->dbh = mysqli_connect(
			$this->db_host,
			$this->db_user,
			$this->db_pass
		);
		$this->affectedRows = NULL;
		if( !$this->dbh ) {
			$this->throwError();
		} else {
			$status = mysql_select_db( $this->db_name, $this->dbh );
			if ( !$status ) {
				$this->throwError();
			}
		}
	}

	function parseTime( $timeString ) {
		list ( $year, $month, $day, $hour, $minute, $second ) = sscanf( $timeString, "%04d-%02d-%02d %02d:%02d:%02d" );
		$phpTime = mktime( $hour, $minute, $second, $month, $day, $year );
		return $phpTime;
	}

	function escape( $source ) {
		if(get_magic_quotes_gpc()) $source = stripslashes($source);
		$result = mysql_escape_string( $source );
		return $result;
	}

	function query( $query ) {
		$status = mysql_select_db( $this->db_name, $this->dbh );
		if ( !$status ) {
			$this->throwError();
		}
		$rh = mysql_query ( $query, $this->dbh );
		if ( ! $rh ) {
			echo( "$query<br>\n" );
			$this->throwError();
		}
		$this->lastResult = new ResultSet( $rh );
		return $this->lastResult;
	}

	function throwError() {
		echo( "<pre>" );
		var_dump( debug_backtrace());
		echo( "</pre>" );
		echo( "<div class=\"sql-error\">".mysql_errno($this->dbh).":".mysql_error($this->dbh)."</div>" );
		echo( "<div>\$this->db_host = ".$this->db_host."</div>\n");
		echo( "<div>\$this->db_user = ".$this->db_user."</div>\n");
		echo( "<div>\$this->db_name = ".$this->db_name."</div>\n");
		echo( "<div>MySQL Client Version:".mysql_get_client_info()."</div>\n");
		echo( "<div>MySQL Server Version:".mysql_get_server_info($this->dbh)."</div>\n");
		echo( "<div>MySQL Protocol Version:".mysql_get_proto_info($this->dbh)."</div>\n");
		echo( "<div>MySQL Host Info:".mysql_get_host_info($this->dbh)."</div>\n");
		exit;
	}

	function getAllRows() {
		return $this->lastResult->getAllRows();
	}

	function getFieldCount() {
		return $this->lastResult->getFieldCount();
	}

	function getFieldNames() {
		return $this->lastResult->getFieldNames();
	}

	function nextRow() {
		return $this->lastResult->nextRow();
	}

	function numRows() {
		return $this->lastResult->numRows();
	}

	function affectedRows() {
		return $this->lastResult->affectedRows();
	}

	function getField( $field ) {
		return $this->lastResult->getField( $field );
	}

	function getInsertId() {
		return( mysql_insert_id() );
	}
}
