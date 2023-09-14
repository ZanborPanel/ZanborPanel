<?php

class Hiddify {

    private $base_url;
    private $session;
    private $headers;
    
    public function __construct($base_url, $session) {
        $this->base_url = $base_url;
        $this->session = $session;
        $this->headers = [
            "Cookie: session=" . $this->session,
            "User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:103.0) Gecko/20100101 Firefox/103.0",
            "Conection: keep-alive",
            "Content-Type: application/x-www-form-urlencoded; charset=UTF-8",
            "Host: " . str_replace(['https://', 'http://'], ['', ''], $base_url),
            "Origin: " . $base_url,
            "Referer: " . $base_url . '/panel/inbounds',
            "X-Requested-With: XMLHttpRequest"
        ];
        
    }

}


?>