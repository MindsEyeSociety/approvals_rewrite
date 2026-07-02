<?php
/**
 * GoogleSheetsService.php
 *
 * Pushes the organization list into the Google Sheet that backs the public
 * "domains" map (Google My Maps reads from that Sheet). Authenticates to the
 * Google Sheets API v4 with a service account, using a self-signed RS256 JWT
 * exchanged for an access token — so it needs no Composer packages, only PHP's
 * built-in openssl, curl, and json extensions.
 *
 * @see EmailService for the sibling "side-effect on an event" pattern.
 */
class GoogleSheetsService {

	/** @var bool Whether the sync is turned on ($SETTINGS["GOOGLE_MAP_SYNC_ENABLED"]). */
	private $enabled;
	/** @var string Absolute path to the service-account JSON key file (kept outside the web root). */
	private $keyFile;
	/** @var string The target spreadsheet's ID. */
	private $sheetId;
	/** @var string The tab (sheet) name the sync owns, e.g. "Sheet1". */
	private $tab;

	/** Google OAuth2 token endpoint for the JWT-bearer grant. */
	const TOKEN_URL = "https://oauth2.googleapis.com/token";
	/** Sheets API base for a spreadsheet's value ranges. */
	const SHEETS_BASE = "https://sheets.googleapis.com/v4/spreadsheets";
	/** The columns this sync owns/overwrites in the target tab. */
	const COLUMNS = "A:C";

	/**
	 * Build the service from the global $SETTINGS (loaded by db.inc).
	 *
	 * Reads GOOGLE_MAP_SYNC_ENABLED, GOOGLE_SA_KEY_FILE, GOOGLE_MAP_SHEET_ID and
	 * GOOGLE_MAP_SHEET_TAB. Missing keys degrade to safe defaults (disabled / empty tab).
	 */
	public function __construct() {
		global $SETTINGS;
		$this->enabled = !empty( $SETTINGS["GOOGLE_MAP_SYNC_ENABLED"] );
		$this->keyFile = $SETTINGS["GOOGLE_SA_KEY_FILE"]   ?? "";
		$this->sheetId = $SETTINGS["GOOGLE_MAP_SHEET_ID"]  ?? "";
		$this->tab     = $SETTINGS["GOOGLE_MAP_SHEET_TAB"] ?? "";
	}

	/**
	 * Best-effort entry point for the org-mutation handlers.
	 *
	 * Rebuilds the whole Sheet from the current active organizations. Never throws
	 * and never blocks the caller: if the sync is disabled or anything fails, it
	 * simply logs and returns, so an org add/edit/delete always completes. Because
	 * every call is a full refresh, a skipped/failed call self-heals on the next one.
	 *
	 * Example:
	 *   $orgDAO->insert( $organization );
	 *   GoogleSheetsService::syncOrgMap(); // push the new roster to the map
	 */
	public static function syncOrgMap() {
		try {
			$svc = new self();
			if( !$svc->enabled ) {
				return;
			}
			$dao  = DAOFactory::getInstance()->getOrganizationDAO();
			$rows = $dao->getMapRows();
			$svc->syncOrganizations( $rows );
		} catch ( \Throwable $e ) {
			error_log( "GoogleSheetsService::syncOrgMap failed: " . $e->getMessage() );
		}
	}

	/**
	 * Overwrite the target tab with a header row plus one row per organization.
	 *
	 * Clears the owned columns (A:C) then writes the fresh matrix, so removed orgs
	 * disappear and the Sheet always matches the DB. The org values are the raw
	 * UTF-8 bytes stored in the (latin1-declared) columns; json_encode accepts them
	 * as valid UTF-8.
	 *
	 * @param array $rows Rows of ['org_name','city','state'] (associative) from OrganizationDAO::getMapRows().
	 * @return bool True on success; false (and error_log) if any API call or JSON encode fails.
	 * @throws \RuntimeException If required settings are missing or the access token cannot be obtained.
	 */
	public function syncOrganizations( array $rows ) {
		if( $this->sheetId === "" || $this->keyFile === "" ) {
			throw new \RuntimeException( "GoogleSheetsService: GOOGLE_MAP_SHEET_ID and GOOGLE_SA_KEY_FILE must be set" );
		}
		$token = $this->getAccessToken();

		$values = array( array( "Name", "City", "State" ) );
		foreach( $rows as $r ) {
			$values[] = array(
				(string)( $r["org_name"] ?? "" ),
				(string)( $r["city"] ?? "" ),
				(string)( $r["state"] ?? "" ),
			);
		}

		$range = $this->range( self::COLUMNS );
		// 1) Clear the columns we own so stale/removed rows are dropped.
		$this->apiRequest( "POST", self::SHEETS_BASE . "/{$this->sheetId}/values/{$range}:clear", $token, array() );
		// 2) Write the fresh matrix starting at the top-left of our range.
		$writeRange = $this->range( "A1" );
		$this->apiRequest(
			"PUT",
			self::SHEETS_BASE . "/{$this->sheetId}/values/{$writeRange}?valueInputOption=RAW",
			$token,
			array( "values" => $values )
		);
		return true;
	}

	/**
	 * Build a URL-encoded A1 range, prefixed with the configured tab when set.
	 *
	 * @param string $a1 The A1 fragment, e.g. "A:C" or "A1".
	 * @return string e.g. "Sheet1%21A%3AC" (tab set) or "A1" (no tab → first sheet).
	 */
	private function range( $a1 ) {
		$range = ( $this->tab !== "" ) ? ( $this->tab . "!" . $a1 ) : $a1;
		return rawurlencode( $range );
	}

	/**
	 * Mint (and per-request cache) a Google API access token via the service-account JWT-bearer flow.
	 *
	 * @return string A bearer access token valid for ~1 hour.
	 * @throws \RuntimeException If the key file is unreadable/invalid, signing fails, or the token request fails.
	 */
	public function getAccessToken() {
		static $cached = null;
		if( $cached !== null ) {
			return $cached;
		}
		$raw = @file_get_contents( $this->keyFile );
		if( $raw === false ) {
			throw new \RuntimeException( "GoogleSheetsService: cannot read key file {$this->keyFile}" );
		}
		$key = json_decode( $raw, true );
		if( !is_array( $key ) || empty( $key["client_email"] ) || empty( $key["private_key"] ) ) {
			throw new \RuntimeException( "GoogleSheetsService: key file missing client_email/private_key" );
		}

		$now = time();
		$header = array( "alg" => "RS256", "typ" => "JWT" );
		$claims = array(
			"iss"   => $key["client_email"],
			"scope" => "https://www.googleapis.com/auth/spreadsheets",
			"aud"   => self::TOKEN_URL,
			"iat"   => $now,
			"exp"   => $now + 3600,
		);
		$signingInput = self::b64url( json_encode( $header ) ) . "." . self::b64url( json_encode( $claims ) );
		$signature = "";
		if( !openssl_sign( $signingInput, $signature, $key["private_key"], OPENSSL_ALGO_SHA256 ) ) {
			throw new \RuntimeException( "GoogleSheetsService: openssl_sign failed" );
		}
		$jwt = $signingInput . "." . self::b64url( $signature );

		$ch = curl_init( self::TOKEN_URL );
		curl_setopt_array( $ch, array(
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => http_build_query( array(
				"grant_type" => "urn:ietf:params:oauth:grant-type:jwt-bearer",
				"assertion"  => $jwt,
			) ),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 30,
		) );
		$response = curl_exec( $ch );
		$httpCode = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$curlErr  = curl_error( $ch );
		curl_close( $ch );

		if( $curlErr ) {
			throw new \RuntimeException( "GoogleSheetsService: token cURL error: $curlErr" );
		}
		$data = json_decode( $response, true );
		if( $httpCode !== 200 || empty( $data["access_token"] ) ) {
			throw new \RuntimeException( "GoogleSheetsService: token request failed (HTTP $httpCode): $response" );
		}
		$cached = $data["access_token"];
		return $cached;
	}

	/**
	 * Issue a Sheets API request with a Bearer token and a JSON body.
	 *
	 * @param string $method HTTP method ("POST" or "PUT").
	 * @param string $url    Full request URL.
	 * @param string $token  Bearer access token.
	 * @param array  $body   Body to JSON-encode (may be empty for :clear).
	 * @return array The decoded JSON response.
	 * @throws \RuntimeException On JSON-encode failure, cURL error, or a non-200 response.
	 */
	private function apiRequest( $method, $url, $token, array $body ) {
		$payload = json_encode( $body );
		if( $payload === false ) {
			throw new \RuntimeException( "GoogleSheetsService: json_encode failed (" . json_last_error_msg() . ")" );
		}
		$ch = curl_init( $url );
		curl_setopt_array( $ch, array(
			CURLOPT_CUSTOMREQUEST  => $method,
			CURLOPT_POSTFIELDS     => $payload,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_HTTPHEADER     => array(
				"Authorization: Bearer $token",
				"Content-Type: application/json",
			),
		) );
		$response = curl_exec( $ch );
		$httpCode = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$curlErr  = curl_error( $ch );
		curl_close( $ch );

		if( $curlErr ) {
			throw new \RuntimeException( "GoogleSheetsService: $method cURL error: $curlErr" );
		}
		if( $httpCode !== 200 ) {
			throw new \RuntimeException( "GoogleSheetsService: $method $url failed (HTTP $httpCode): $response" );
		}
		return json_decode( $response, true );
	}

	/**
	 * Base64url-encode (RFC 7515): standard base64 with +/ → -_ and no padding.
	 *
	 * @param string $data Raw bytes.
	 * @return string URL-safe base64 without "=" padding.
	 */
	private static function b64url( $data ) {
		return rtrim( strtr( base64_encode( $data ), "+/", "-_" ), "=" );
	}
}
