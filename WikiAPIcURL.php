<?php
class WikiAPIcURL {

	public $ch;
	public $url;
	public $login;
	public $errno;
	public $error;
	public $user_agent = "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/29.0.1547.66 Safari/537.36";
	
	public function __construct ($url, $domain) {
	
		$this->ch = curl_init();
		$this->url = $url;
		$this->domain = $domain;
		
	}
	
	public function exe () {
	
		// Thanks to: http://www.tunnelsup.com/using-the-sharepoint-2013-wiki-api
		curl_setopt($this->ch, CURLOPT_HEADER, 0);
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($this->ch, CURLOPT_URL, $this->url);
		curl_setopt($this->ch, CURLOPT_HTTPAUTH, CURLAUTH_NTLM); // Required if the sharepoint requires authentication
		curl_setopt($this->ch, CURLOPT_USERPWD, $this->login);   // Required if the sharepoint requires authentication
		curl_setopt($this->ch, CURLOPT_USERAGENT,$this->user_agent);
		curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, FALSE);
	
		try {
			$ret = curl_exec($this->ch);
		} catch (Exception $e) {
			die("Curl failed: " . $e->getMessage() );
		}
		
		$this->errno = curl_errno($this->ch);
		$this->error = curl_error($this->ch);
		
		curl_close($this->ch);
		
		return $ret;
	
	}
		
	public function getCredentials () {
		echo "Please enter your username (no domain): ";
		$handle = fopen ("php://stdin","r");
		$this->username = trim( fgets($handle) );

		echo "Please enter your password (note this is EXTREMELY INSECURE so do it on your own computer only): ";
		$handle = fopen ("php://stdin","r");
		$this->password = trim( fgets($handle) );
		
		$this->login = $this->domain . '/' . $this->username . ':' . $this->password;
		
	}

}