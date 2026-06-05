<?php

# This contains the LegacyAuth class for use on legacy.modernenigmasociety.org
# First, the json_decoder, since PHP4 doesn't have one:

include_once("JSON.php");

/**
 * Revised LegacyAuth class for OAuth2.0.
 * 
 * @author Ephraim Gregor <ephraimgregor@gmail.com>
 */
class LegacyAuth 
{
    /**
     * Gets authentication from the OAuth 2.0 server.
     * 
     * @param string $id The client ID.
     * @param string $secret The client secret.
     * @param string $redirect The redirect URL.
     * @return void|array Array if result is valid, void otherwise.
     */
	public static function getAuth($id, $secret, $redirect) 
	{
	    // Various APIs.
	    $AUTHORIZATION_ENDPOINT = 'https://portal.modernenigmasociety.org/oauth/v2/auth';
        $TOKEN_ENDPOINT         = 'https://portal.modernenigmasociety.org/oauth/v2/token';
        $API_ENDPOINT           = 'https://portal.modernenigmasociety.org/api/authorized/user.json';
           
        // Can haz cookie?
        if(isset($_COOKIE['mes_login'])) 
        {
            // Builds the URL.           
            $url = LegacyAuth::buildQuery($API_ENDPOINT, array( 'access_token' => $_COOKIE['mes_login'] ));
        
            // Returns the array o' info.
            return LegacyAuth::authorize($url);
        }   
            
        // Have an auth code, then? Mmmm?         
        if(isset($_GET['code'])) 
        {
            // Build the token generation URL.            
            $params = array(
                'grant_type'    => 'authorization_code',
                'code'          => $_GET['code'],
                'redirect_uri'  => $redirect,
                'client_id'     => $id,
                'client_secret' => $secret
            );
            
            $url = LegacyAuth::buildQuery($TOKEN_ENDPOINT, $params);
            
            $token_json = @LegacyAuth::curl_load($url);

            // Make sure we didn't get a 404.
            if($token_json !== FALSE)
            {
                // Got it? Groovy- decode that sucker.   
                $token = json_decode($token_json);
                
                // Make sure we have a legit token.                        
                if(isset($token->access_token))
                {
                    // Sets a cookie to store all our stuff.
                    setcookie('mes_login', $token->access_token, time()+$token->expires_in, '/', '.modernenigmasociety.org' );
                      
                    // Builds the URL.           
                    $url = LegacyAuth::buildQuery($API_ENDPOINT, array( 'access_token' => $token->access_token ));
                
                    // Returns the array o' info.
                    return LegacyAuth::authorize($url);
                }
            }          
        }  
        
        // Well screw you guys. Sending you to get something, then!
        $params = array(
            'client_id'     => $id,
            'response_type' => 'code',
            'redirect_uri'  => $redirect
        );
        
        $url = LegacyAuth::buildQuery($AUTHORIZATION_ENDPOINT, $params);
        
        header("Location: $url");
        echo "<a href=\"{$url}\">{$url}</a>";
	}


    /**
     * Authorizes a user.
     * 
     * @param string $url The authorization URL.
     * @return array The successfully authorized user info.
     */
    function authorize($url)
    {
        // Suppress the errors in case of 404.
        $user_json = @LegacyAuth::curl_load($url);
        
        // Got nothing? Send 'em back!
        if($user_json === FALSE)
        {
            echo "There's a problem reaching the user account server. Please try again later.";
            exit;
        }
        
        $user = json_decode($user_json);
        
        $array = array( 
            'auth' => 'auth'
        );
        
        if (isset($user->error)) $array['error'] = $user->error;
        if (isset($user->status)) $array['status'] = $user->status;
        if (isset($user->membershipNumber)) $array['member_number'] = $user->membershipNumber;
        if (isset($user->membershipExpiration)) $array['membership_expiration'] = $user->membershipExpiration;
        if (isset($user->firstName)) $array['first_name'] = $user->firstName;
        if (isset($user->lastName)) $array['last_name'] = $user->lastName;
        
        return $array;
    }


    /**
     * Builds a query, since we don't have http_build_query in PHP4.
     *  
     * @param string $url The base URL.
     * @param array $params Array of GET parameters.
     * @return string 
     */
    static function buildQuery($url, $params)
    {
        //array_walk($params, create_function('&$val,$key', '$val = $key."=".urlencode($val);'));
	array_walk($params, function (&$val, $key) {
  	  $val = $key . '=' . urlencode($val);
	});
        return $url . '?' . implode('&', $params);
    }
    function curl_load($url){
        curl_setopt($ch=curl_init(), CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        /* INSECURE HACK - added by Andrew B on 2021-09-30 to work around new SSL validation issues */
        //curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        /* END INSECURE HACK */
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
}
