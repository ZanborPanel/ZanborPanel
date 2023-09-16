<?php

class Hiddify
{

    private $base_url;
    private $complate_url;
    private $session;
    private $headers;

    public function __construct($base_url, $complate_url, $session)
    {
        $this->base_url = $base_url;
        $this->complate_url = $complate_url;
        $this->headers = [
            "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7",
            "Accept-Encoding: gzip, deflate, br",
            "Accept-Language: en-US,en;q=0.9,fa;q=0.8,zh-CN;q=0.7,zh;q=0.6",
            "Cache-Control: max-age=0",
            "Content-Type: multipart/form-data; boundary=----WebKitFormBoundaryT81SOPQBvEMwZ6sv",
            "Origin: " . $base_url,
            "Referer: " . $complate_url . "/user/new/?url=%2F8itQkDU30qCOwzUkK3LnMf58qfna%2F276dbb23-95d8-4802-a741-bb0474bd9b71%2Fadmin%2Fuser%2F",
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36"
        ];

    }

    private function gen_uuid()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    private function getCookie($url = null)
    {
        $url = is_null($url) ? $this->complate_url . "/user/new/?url=%2F8itQkDU30qCOwzUkK3LnMf58qfna%2F276dbb23-95d8-4802-a741-bb0474bd9b71%2Fadmin%2Fuser%2F" : $url;
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }

}


?>