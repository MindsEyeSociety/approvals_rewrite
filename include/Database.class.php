<?php

include_once("include/ResultSet.class.php");
include_once("include/settings.inc");

class Database {
    var $db_host;
    var $db_user;
    var $db_pass;
    var $db_name;
    var $dbh;
    var $lastResult;


    public function __construct( $host, $user, $pass, $name ) {
    $this->db_host = $host;
    $this->db_user = $user;
    $this->db_pass = $pass;
    $this->db_name = $name;

    $this->dbh = mysqli_connect(
        $this->db_host,
        $this->db_user,
        $this->db_pass,
        $this->db_name
    );
    if (!$this->dbh) {
        $errMsg = "CRITICAL: mysqli_connect() failed: " . mysqli_connect_error() . " (errno " . mysqli_connect_errno() . ")";
        error_log("Database::__construct – " . $errMsg);
        die("<pre>" . $errMsg . "</pre>");
    }

    // Force explicit select (old mysqli client sometimes ignores db param in connect)
    if (!mysqli_select_db($this->dbh, $this->db_name)) {
        $errMsg = "CRITICAL: mysqli_select_db() failed: " . mysqli_error($this->dbh) . " (errno " . mysqli_errno($this->dbh) . ")";
        error_log("Database::__construct – " . $errMsg);
        die("<pre>" . $errMsg . "</pre>");
    }

    // Verify it really stuck
    $verify = mysqli_query($this->dbh, "SELECT DATABASE() AS db");
    if (!$verify) {
        $errMsg = "CRITICAL: Verification query failed: " . mysqli_error($this->dbh);
        error_log("Database::__construct – " . $errMsg);
        die("<pre>" . $errMsg . "</pre>");
    }
    $row = mysqli_fetch_assoc($verify);
    $current_db = $row ? $row['db'] : 'unknown';

    if ($current_db !== $this->db_name) {
        $errMsg = "CRITICAL: Connected to wrong DB: '$current_db' (expected '$this->db_name')";
        error_log("Database::__construct – " . $errMsg);
        die("<pre>" . $errMsg . "</pre>");
    }

    // Force utf8mb4 charset to match database collation (all tables migrated
    // from latin1 2026-09-01; must stay in lockstep with the schema charset —
    // see the utf8mb4 migration in sql/ for context).
    if (!mysqli_set_charset($this->dbh, 'utf8mb4')) {
        $errMsg = "CRITICAL: mysqli_set_charset('utf8mb4') failed: " . mysqli_error($this->dbh);
        error_log("Database::__construct – " . $errMsg);
        die("<pre>" . $errMsg . "</pre>");
    }

    $this->affectedRows = null;
    $this->lastResult = null;
}

    function parseTime( $timeString ) {
        list ( $year, $month, $day, $hour, $minute, $second ) = sscanf( $timeString, "%04d-%02d-%02d %02d:%02d:%02d" );
        $phpTime = mktime( $hour, $minute, $second, $month, $day, $year );
        return $phpTime;
    }

    //function escape( $source ) { # pass a string and returns a string that is safe to write to the database
    //    if(get_magic_quotes_gpc()) $source = stripslashes($source);
    //    $result = mysqli_real_escape_string($this->dbh, $source);
    //    return $result;
    //}
    function escape( $source ) {
	    return $this->dbh->real_escape_string($source);  // object-style call
	}

    function query(string $query, array $params = []) {
    // If params are provided, use the new secure execute_query (PHP 8.2+)
    if (!empty($params)) {
        $rh = mysqli_execute_query($this->dbh, $query, $params);
    } else {
        // Fallback for old concatenated strings (Legacy)
        $rh = mysqli_query($this->dbh, $query);
    }

    if (!$rh) {
        // For debugging, but consider logging instead of echoing in production
        echo(htmlspecialchars($query) . "<br>\n");
        $this->throwError();
    }

    // Since execute_query returns a result object for SELECT
    // and true for UPDATE/INSERT, your ResultSet needs to handle both.
    $this->lastResult = new ResultSet($rh, $this->dbh);
    return $this->lastResult;
}

    function throwError() { # print some debugging info if we encounter an error
        echo( "<pre>" );
        echo( mysqli_connect_error($this->dbh) );  // Use mysqli_ version
        echo( "</pre>" );
        echo( "<div class=\"sql-error\">".mysqli_errno($this->dbh).":".mysqli_error($this->dbh)."</div>" );
        echo( "<div>\$this->db_host = ".$this->db_host."</div>\n");
        echo( "<div>\$this->db_user = ".$this->db_user."</div>\n");
        echo( "<div>\$this->db_name = ".$this->db_name."</div>\n");
        echo( "<div>MySQL Client Version:".mysqli_get_client_info()."</div>\n");  // mysqli_get_client_info (no param)
        echo( "<div>MySQL Server Version:".mysqli_get_server_info($this->dbh)."</div>\n");
        echo( "<div>MySQL Protocol Version:".mysqli_get_proto_info($this->dbh)."</div>\n");
        echo( "<div>MySQL Host Info:".mysqli_get_host_info($this->dbh)."</div>\n");
        // Do NOT exit here — let the script continue for troubleshooting
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
        return mysqli_insert_id($this->dbh);  // Switch to mysqli_insert_id
    }
}
