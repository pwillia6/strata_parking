<?php

class OAuth {

    var $type = 'unknown';
    
    /**
     * Sets the OAuth provider in the session.
     * @param string $provider The name of the provider (e.g., 'google', 'microsoft').
     */
    static function setProvider($provider) {
        $_SESSION['oauth_provider'] = $provider;
    }

    /**
     * Initializes and returns the OAuth object from the session.
     * If no provider is set in the session, it returns false.
     * @param string $returnURL The URL to return to after authentication.
     * @return OAuth|false The OAuth object or false if initialization fails.
     */
    static function initialize($returnURL) {
        if (isset($_SESSION["OAuth"])) {
            return $_SESSION["OAuth"];
        }

        if (!isset($_SESSION['oauth_provider'])) {
            return false;
        }

        $provider = $_SESSION['oauth_provider'];
        $redirectUri = 'https://' . $_SERVER['HTTP_HOST'] . "/login.php";

        if ($provider === 'microsoft') {
            $oauth = new OAuth(
                'Microsoft', 
                OAUTH_MICROSOFT_CLIENT_ID, 
                OAUTH_MICROSOFT_CLIENT_SECRET, 
                $redirectUri, $returnURL, 
                array('tenant' => OAUTH_MICROSOFT_TENANT));
        } else { // Default to Google
            $oauth = new OAuth(
                'Google', 
                OAUTH_GOOGLE_CLIENT_ID, 
                OAUTH_GOOGLE_CLIENT_SECRET, 
                $redirectUri, 
                $returnURL);
        }
        $_SESSION["OAuth"] = $oauth;

        return $_SESSION["OAuth"];
    }
    
    static function reset() {
        unset($_SESSION["OAuth"]);
    }
    
    function __construct($Type, $ClientID, $ClientSecret, $RedirectURL, $ReturnURL, $options = array()) {
        if ($Type=='Google') {
            $this->config = (object) array(
                'type' => 'Google',
                'client_id' => $ClientID,
                'client_secret' => $ClientSecret,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $RedirectURL,
                'TokenURL' => 'https://www.googleapis.com/oauth2/v4/token',
                'AuthURL' => 'https://accounts.google.com/o/oauth2/v2/auth',
                'apiURL' => 'https://people.googleapis.com',
                'scope' => 'openid email profile',
            );
        } elseif ($Type == 'Microsoft') {
            $tenant = isset($options['tenant']) ? $options['tenant'] : 'common';
            $this->config = (object) array(
                'type' => 'Microsoft',
                'client_id' => $ClientID,
                'client_secret' => $ClientSecret,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $RedirectURL,
                'TokenURL' => "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token",
                'AuthURL' => "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/authorize",
                'apiURL' => 'https://graph.microsoft.com/v1.0/',
                'scope' => 'openid profile email User.Read',
                'tenant' => $tenant,
            );
        }
        $this->type = $Type;
        $this->returnURL  = $ReturnURL;
    }

    /*
     * WARNING: For security reasons avoid logging this on production servers.
     *
     * For debug and test, put suitable error_log() here to see internal OAuth process.
     */
    function log($s) {
        error_log("OAuth: " . $s);
        return;
    }

    /* Create a new taken from the refresh token if possible */
    function newToken() {
        if (!isset($this->refreshToken)) {
            return FALSE;
        }
        
        $config = $this->config;
        $params = array(
            'refresh_token' => $this->refreshToken,
            'client_id'     => $config->ClientID,
            'client_secret' => $config->ClientSecret,
            'grant_type'    => 'refresh_token'
        );
        
        $result = $this->_api($config->TokenURL, $params, 'POST');
        
        if (isset($result->access_token)) {
            $this->token = $result->access_token;
            $this->expires = time() +  $result->expires_in;
            $this->offset = 0;
            return true;
        }
        
        return false;
    }

    function getLoginUrl($RedirectURL = null, $Permissions = null) {
        if ($this->config->type=='Token') {
            $params = array(
                'check_token' => $this->config->token,
                'email' => $this->config->email,
            );
            return $this->config->AuthURL . '?' . http_build_query($params);
        }
    
        if ($this->config->type=='Facebook') {
            $params = array(
                'client_id' => $this->config->client_id,
                'state' => md5(session_id()),
                'response_type' => 'code',
                'redirect_uri' => $this->config->redirect_uri,
                'scope' => $this->config->scope,
            );
            return $this->config->AuthURL . '?' . http_build_query($params);
        }
        if ($this->config->type=='Google') {
            $params = array(
                'client_id' => $this->config->client_id,
                'state' => md5(session_id()),
                'response_type' => 'code',
                'redirect_uri' => $this->config->redirect_uri,
                'scope' => $this->config->scope
            );
            return $this->config->AuthURL . '?' . http_build_query($params);
        }
        if ($this->config->type=='Microsoft') {
            $params = array(
                'client_id' => $this->config->client_id,
                'state' => md5(session_id()),
                'response_type' => 'code',
                'redirect_uri' => $this->config->redirect_uri,
                'scope' => $this->config->scope,
            );
            return $this->config->AuthURL . '?' . http_build_query($params);
        }
    }

    function loggedIn() {
        return isset($this->token) &&  $this->checkExpiredToken();
    }
    


    /* Checks for expired token and refreshes if required */
    private function checkExpiredToken()
    {
        return true;
        if (isset($this->token) && isset($this->expires)) {
            $minutesLeft = ($this->expires - time())/60 ;
            if ($minutesLeft < 10) {
                unset($this->token);
                unset($this->expires);
                unset($this->offset);
                return $this->newToken();
            }
        }
        return true;
    }
    
    
    /* This call ensures we are logged in - token stored in $this->token */
    function login() {
        
       
        /* So we don't re-process the same code */
        if (!isset($this->codes)) {
            $this->codes = array();
        }

        if (isset($_GET['code']) && !isset($this->codes[$_GET['code']])){
            $params = array(
                'code' => $_GET['code'],
                'client_id' => $this->config->client_id,
                'client_secret' => $this->config->client_secret,
                'redirect_uri' => $this->config->redirect_uri,
                'response_type'   => 'code',
                'grant_type' => 'authorization_code'
            );
            $this->codes[$_GET['code']] = TRUE;
      
            $this->result = $result = $this->_api($this->config->TokenURL, $params, 'POST');
            if (isset($result->access_token)) {
                $this->result = $result;
                $this->token  = $result->access_token;
                $this->expires =  $result->expires_in;
                header( 'Location: ' . $this->returnURL);
                exit;
            } else {
                return false;
            }
        }

        if (!$this->loggedIn()) {
            $loginUrl = $this->getLoginUrl();
            header("Location: $loginUrl");
            exit;
        }

        return true;
    }

    /*
     * Logout of OAuth
     */
    function logout() {
			
        $instance = Instance::get(); // global $instance;
        $config = $this->config;

        if (isset($instance->LogoutURL) && !is_null($instance->LogoutURL) && $instance->LogoutURL != '') {
            $url = $instance->LogoutURL;
        } else {
            $url = preg_replace('@authorize[/]*$@','revoke', $instance->OAuthURL);
        }

        $parms = array(
            'token' => $this->token->token,
            'client_id' => $config->ClientID,
            'client_secret' => $config->ClientSecret,
        );

        $r = $this->_api($url, $parms, 'POST');

        return $r;
    }
    
    function api($url, $parms = array(), $fromethod = 'GET') {
        if (!preg_match('/^https:/', $url)) {
            $url = $this->config->apiURL . '/' . $url;
        }
        return $this->_api($url, $parms, $fromethod);
    }
    
    /* Basic api call */
    function _api($url, $parms = array(), $fromethod = 'GET')
    {
        $ch = curl_init();
        curl_setopt ($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.6) Gecko/20070725 Firefox/2.0.0.6");
        curl_setopt ($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, false);
        // curl_setopt ($ch, CURLOPT_VERBOSE, 1);
        curl_setopt ($ch, CURLOPT_HEADER, 1);

        if (isset($this->token)) {
            $headers = array();
            $headers[] = 'Authorization: Bearer ' . $this->token;
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        if ($fromethod=='GET') {
            if (count($parms)>0) {
                $url = $url . '?' . http_build_query($parms);
            }
        } else {
            curl_setopt ($ch, CURLOPT_POST, 0);
            $postdata = http_build_query($parms);
            curl_setopt ($ch, CURLOPT_URL, $url);
            curl_setopt ($ch, CURLOPT_POSTFIELDS, $postdata);
            curl_setopt ($ch, CURLOPT_POST, 1);
        }

        curl_setopt ($ch, CURLOPT_URL, $url);
        self::log("API: $url");
        self::log("API PARMS: " . json_encode($parms));

        $r = curl_exec ($ch);

        if ($r===FALSE) {
            $resp = (object) array(
              'error' => 'CURL - ' . curl_error($ch),
              'url' => $url,
              'r' => $r,
            );
            self::log(get_class() . '::_api failure', 'ERROR', $url);
            self::log(get_class() . '::_api failure', 'DEBUG', array('parms' => $parms, 'error' => curl_error($ch)));
            return $resp;
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($status!=200) {
            $resp = (object) array(
              'error' => "Error status returned $status",
              'status' => $status,
              'url' => $url,
              'r' => $r,
            );
            self::log(get_class() . '::_api bad status', 'ERROR', $url);
            self::log(get_class() . '::_api bad status', 'DEBUG', array('parms' => $parms, 'status' => $status));
            return $resp;
        }

        $rheaders = explode("\r\n\r\n", $r);
        while (substr($head=array_shift($rheaders),0,4)=='HTTP') {
            self::log("API HEADER:" . $head);
        }
        array_unshift($rheaders, $head);
        $resp = implode("\r\n\r\n", $rheaders);
        
        self::log("API RESPONSE:" . $resp);
        
        $results = curl_getinfo($ch);
        $results['curl_error'] = curl_error($ch);

        self::log("API RESULT:" . var_export($results, true));
        
        if (substr($resp, 0, 1) == '{' || substr($resp, 0, 1) == '[') {
            self::log("API CONVERTED JSON");
            $resp = json_decode($resp, FALSE);
        } else {
            self::log("API CONVERTED STRING");
            $resp = curl_error($ch) . ' ' . $resp;
        }
        
        return $resp;
    }


    
    function decodeJwtToken($jwt) {

        function base64UrlDecode($input) {
           $remainder = strlen($input) % 4;
           if ($remainder) {
               $input .= str_repeat('=', 4 - $remainder);
           }
           return base64_decode(strtr($input, '-_', '+/'));
        }
    
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return array('error' => 'Invalid token format');
        }
    
        list($headerB64, $payloadB64, $signatureB64) = $parts;
    
        // Decode header and payload
        $header = json_decode(base64UrlDecode($headerB64), true);
        $payload = json_decode(base64UrlDecode($payloadB64), true);
    
        if (!$header || !$payload) {
            return array('error' => 'Invalid header or payload encoding');
        }
    
        // No signature verification
        return (object) $payload; // Return the decoded payload as an associative array
    }  

    function user($user=null) {

        if (isset($this->user)) {
           return $this->user;
        }

        if (isset($this->result->id_token)) {
            $decoded_token = $this->decodeJwtToken($this->result->id_token);
            if (isset($decoded_token->email) || isset($decoded_token->preferred_username)) {
                 $decoded_token->email = isset($decoded_token->email) ? $decoded_token->email : $decoded_token->preferred_username;
                 $this->user = $decoded_token;
            }
        };

        if (!isset($this->user)) {
            if ($this->type=='Google') {
                $r = $this->api('v1/people/me?personFields=names,emailAddresses');
                
                $user = (object) array(
                    'given_name' => isset($r->names[0]->givenName) ? $r->names[0]->givenName : '',
                    'last_name' => isset($r->names[0]->familyName) ? $r->names[0]->familyName : '',
                    'email'     => isset($r->emailAddresses[0]->value) ? $r->emailAddresses[0]->value : '',
                    'id'        => isset($r->names[0]->metadata->source->id) ? $r->names[0]->metadata->source->id : '',
                );
                $user->id_field = 'GOOGLEID';
                $this->user = $user;
            }

            if ($this->type=='Microsoft') {
                $r = $this->api('me');
                
                $user = (object) array(
                    'given_name' => $r->givenName,
                    'last_name' => $r->surname,
                    'email'     => isset($r->mail) ? $r->mail : $r->userPrincipalName,
                    'id'        => $r->id,
                );
                $this->user = $user;
            }
        }

        return $this->user;
    }
       
}
    
