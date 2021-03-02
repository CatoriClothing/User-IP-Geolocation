<?php 
header('Content-type: application/json');
header('Access-Control-Allow-Credentials: true');

if($_GET['id'] == ''){
// Credit to Tomoli75 for the session rate limiting https://gist.github.com/Tomoli75/394a47e391b966f5061dfa37b8633e44
    session_start();
    class RateExceededException extends Exception {}
    
    class RateLimiter {
        private $prefix;
        public function __construct($token, $prefix = "rate") {
            $this->prefix = md5($prefix . $token);
    
            if( !isset($_SESSION["cache"]) ){
                $_SESSION["cache"] = array();
            }
    
            if( !isset($_SESSION["expiries"]) ){
                $_SESSION["expiries"] = array();
            }else{
                $this->expireSessionKeys();
            }
        }
    
        public function limitRequestsInMinutes($allowedRequests, $minutes) {
            $this->expireSessionKeys();
            $requests = 0;
    
            foreach ($this->getKeys($minutes) as $key) {
                $requestsInCurrentMinute = $this->getSessionKey($key);
                if (false !== $requestsInCurrentMinute) $requests += $requestsInCurrentMinute;
            }
    
            if (false === $requestsInCurrentMinute) {
                $this->setSessionKey( $key, 1, ($minutes * 60 + 1) );
            } else {
                $this->increment($key, 1);
            }
            if ($requests > $allowedRequests) throw new RateExceededException;
        }
    
        private function getKeys($minutes) {
            $keys = array();
            $now = time();
            for ($time = $now - $minutes * 60; $time <= $now; $time += 60) {
                $keys[] = $this->prefix . date("dHi", $time);
            }
            return $keys;
        }
    
        private function increment( $key, $inc){
            $cnt = 0;
            if( isset($_SESSION['cache'][$key]) ){
                $cnt = $_SESSION['cache'][$key];
            }
            $_SESSION['cache'][$key] = $cnt + $inc;
        }
    
        private function setSessionKey( $key, $val, $expiry ){
            $_SESSION["expiries"][$key] = time() + $expiry;
            $_SESSION['cache'][$key] = $val;
        }
        
        private function getSessionKey( $key ){
            return isset($_SESSION['cache'][$key]) ? $_SESSION['cache'][$key] : false;
        }
    
        private function expireSessionKeys() {
            foreach ($_SESSION["expiries"] as $key => $value) {
                if (time() > $value) { 
                    unset($_SESSION['cache'][$key]);
                    unset($_SESSION["expiries"][$key]);
                }
            }
        }
    }
    
    // Support Cloudflare Passthrough
    if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
        $rateLimiter = new RateLimiter($_SERVER["HTTP_CF_CONNECTING_IP"]);
        // This IP used for search if cloudflare is in use otherwise normal header
        $_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
      }
      else{
        $rateLimiter = new RateLimiter($_SERVER["REMOTE_ADDR"]);
      }
    
    
    $limit = 200;				//	number of connections to limit user to per $minutes
    $minutes = 1;				//	number of $minutes to check for.
    $seconds = floor($minutes * 60);	//	retry after $minutes in seconds.
    
    try {
        $rateLimiter->limitRequestsInMinutes($limit, $minutes);
    } catch (RateExceededException $e) {
        header("HTTP/2 429 Too Many Requests");
        header(sprintf("Retry-After: %d", $seconds));
        $data = 'Rate Limit Exceeded ';
        die (json_encode($data));
    }
    // END RATE LIMITING 

  $sql_con = new mysqli('host', 'username', 'password', 'db');
  $stmt = $sql_con->prepare("SELECT countrycode FROM ip WHERE (INET_ATON(?) BETWEEN INET_ATON(ipfrom) AND INET_ATON(ipto)) LIMIT 1");

   $stmt->bind_param("s", $_SERVER['REMOTE_ADDR']); 
   $stmt->execute(); 
   $stmt->bind_result($countryCode);


   while ($stmt->fetch()) {
       $ipdata = array("ip"=>$_SERVER['REMOTE_ADDR'], "country"=>$countryCode);
   }

   $stmt->close();

   echo json_encode($ipdata);
}
else {
die();
}
  ?>
