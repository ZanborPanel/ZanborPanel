<?php

class Hiddify
{

    private $base_url;
    private $complate_url;
    private $headers;

    public function __construct($base_url, $complate_url)
    {
        $this->base_url = $base_url;
        $this->complate_url = $complate_url;
        $this->headers = [
            "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7",
            "Accept-Encoding: gzip, deflate, br",
            "Accept-Language: en-US,en;q=0.9,fa;q=0.8,zh-CN;q=0.7,zh;q=0.6",
            "Cache-Control: max-age=0",
            "Sec-Ch-Ua: " . '"Chromium";v="116", "Not)A;Brand";v="24", "Google Chrome";v="116""',
            "Content-Type: multipart/form-data; boundary=----WebKitFormBoundaryT81SOPQBvEMwZ6sv",
            "Origin: " . $base_url,
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

    public function getCookie($url = null)
    {
        $url = is_null($url) ? $this->complate_url . "/user/new/?url=%2F8itQkDU30qCOwzUkK3LnMf58qfna%2F276dbb23-95d8-4802-a741-bb0474bd9b71%2Fadmin%2Fuser%2F" : $url;
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, 1);
        $response = curl_exec($curl);
        curl_close($curl);
        preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $response, $matches);
        return $matches[1][0];
    }

    public function getCsrfToken($url = null)
    {
        $url = is_null($url) ? $this->complate_url . "/user/new/?url=%2F8itQkDU30qCOwzUkK3LnMf58qfna%2F276dbb23-95d8-4802-a741-bb0474bd9b71%2Fadmin%2Fuser%2F" : $url;
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl);
        curl_close($curl);
        preg_match('#<input type="hidden" name="csrf_token" value="(.*)"\/>#', $response, $matches);
        return $matches[1];
    }

    public function createUser($name, $limit, $date)
    {
        $url = $this->complate_url . '/user/new/?url=%2F8itQkDU30qCOwzUkK3LnMf58qfna%2F276dbb23-95d8-4802-a741-bb0474bd9b71%2Fadmin%2Fuser%2F';
        $uuid = self::gen_uuid();
        $getCookie = self::getCookie();
        $getCsrfToken = self::getCsrfToken();

        $pyload = [
            'csrf_token' => $getCsrfToken,
            'uuid' => $uuid,
            'name' => $name,
            'usage_limit_GB' => $limit,
            'package_days' => $date,
            'mode' => 'no_reset',
            'comment' => '',
            'enable' => 'y'
        ];

        $this->headers[] = "Cookie: " . $getCookie;
        $this->headers[] = "Referer: " . $this->complate_url . '/user/new/?url=%2F8itQkDU30qCOwzUkK3LnMf58qfna%2F276dbb23-95d8-4802-a741-bb0474bd9b71%2Fadmin%2Fuser%2F';

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($pyload));
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        $response = json_decode(curl_exec($curl), true);
        curl_close($curl);

        return json_encode(['status' => true, 'results' => $pyload], 448);
    }

    public function createUser2($name, $limit, $date)
    {
        $url = str_replace('/admin', '', $this->complate_url) . '/api/v1/user/';
        $pyload = '
        {
            "uuid": "6ebd2ea8-4d41-48b7-8fc2-7d6570da30a9",
            "name": "Test",
            "added_by_uuid": "abb4bdb8-732c-4fa6-92e0-6b3fd4bb8450",
            "current_usage_GB": 0,
            "usage_limit_GB": 1,
            "package_days": 900,
            "start_date": null,
            "comment": null,
            "last_online": "1-01-01 00:00:00",
            "last_reset_time": "2023-05-21",
            "mode": "no_reset",
            "telegram_id": null
        }';

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $pyload);
        $response = json_decode(curl_exec($curl), true);
        curl_close($curl);

        return json_encode(['status' => true, 'results' => $response], 448);
    }
}


?>