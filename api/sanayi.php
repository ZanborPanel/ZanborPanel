<?php

class Sanayi{
    
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
    
    private function convertToBytes($from) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $number = substr($from, 0, -2);
        $suffix = strtoupper(substr($from,-2));
    
        if(is_numeric(substr($suffix, 0, 1))) {
            return preg_replace('/[^\d]/', '', $from);
        }
    
        $exponent = array_flip($units)[$suffix] ?? null;
        if($exponent === null) {
            return null;
        }
    
        return $number * (1024 ** $exponent);
    }
    
    private function getH($url) {
        $curl = curl_init();
        curl_setopt_array($curl, array(
          CURLOPT_URL => $url,
          CURLOPT_RETURNTRANSFER => true,
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        return json_decode( $response , true );      
    }
    
    private function gen_uuid() {
        return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0x0fff ) | 0x4000,
            mt_rand( 0, 0x3fff ) | 0x8000,
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
        );
    }
    
    private function CreateI2D() {
      $id = substr(sha1(microtime() . rand(111111, 999999)), 0, 10);
      return $id;
    }
    
    private function request($url, $method = false, array $headers = null, $data = null) {
      $curl = curl_init();
  
      $options = [
          CURLOPT_URL => $url,
          CURLOPT_POST => $method,
          CURLOPT_POSTFIELDS => $data,
          CURLOPT_HEADER => false,
          CURLOPT_HTTPHEADER => $headers,
          CURLOPT_RETURNTRANSFER => true
      ];
  
      curl_setopt_array($curl, $options);
      $result = curl_exec($curl);
      curl_close($curl);
      return $result;
  }


    public function generateRandomString($length = 8) {
        $bytes = random_bytes($length);
        return bin2hex($bytes);
    }
    
    public function checkId($inbound_id) {
        $url = $this->base_url . '/panel/inbound/list';
        $curl = curl_init();
        curl_setopt_array($curl, array(
          CURLOPT_URL => $url,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_HTTPHEADER => $this->headers
        ));
        $result = json_decode(curl_exec($curl), true)['obj'];
        $status = false;
        foreach ($result as $value) {
            if ($value['id'] == $inbound_id) {
                $status = true;
            }
        }
        return json_encode(['status' => $status]);
    }
    
    public function Status(){
        $url = $this->base_url . '/server/status';
        $result = self::request($url, true, $this->headers);
        return json_encode($result);
    }
    
    public function getSubPort() {
        $url = $this->base_url . '/panel/setting/all';
        $curl = curl_init();
        curl_setopt_array($curl, array(
          CURLOPT_URL => $url,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_HTTPHEADER => $this->headers
        ));
        $result = json_decode(curl_exec($curl), true);
        return $result;
    }

    public function getPortById($id) {
        $url = $this->base_url . '/panel/inbound/list';
        $curl = curl_init();
        curl_setopt_array($curl, array(
          CURLOPT_URL => $url,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_HTTPHEADER => $this->headers
        ));
        $result = json_decode(curl_exec($curl), true)['obj'];
        foreach ($result as $value) {
            if ($value['id'] == $id) {
                return json_encode(['status' => true, 'port' => $value['port'], 448]);
            }
        }
        return json_encode(['status' => false, 'msg' => 'id is not found !', 'status_code' => 404]);
    }
    
    public function addClient($name, $id, $date, $limit){
        
        $url = $this->base_url . '/panel/inbound/addClient';
        
        $total = self::convertToBytes($limit.'GB');
        $expiryTimePlus = $date * 86400;
        $date = intval((time( ) + $expiryTimePlus) * 1000);
        $randomKey = self::gen_uuid();
        $subid = self::generateRandomString();
        $subPort = self::getSubPort()['obj']['subPort'];
        $parts = parse_url($this->base_url);
        
        $settings = json_encode([
            "clients" => [[
                "id" => $randomKey,
                "flow" => "",
                "email" => $name,
                "limitIp" => 1,
                "totalGB" => $total,
                "expiryTime" => $date,
                "enable" => true,
                "tgId" => "",
                "subId" => $subid
            ]]
        ]);
        
        $data = "id=$id&settings=$settings";
        
        # -------------- [ curl ] -------------- #

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        $resp = json_decode(curl_exec($curl), true);
        curl_close($curl);
        
        # -------------- [ curl ] -------------- #
        
        if($resp['success'] == true){
            return json_encode(['status' => true, 'msg' => 'successful', 'results' => ['subscribe' => str_replace($parts['port'], $subPort, $this->base_url) . '/sub/' . $subid, 'id' => $randomKey, 'remark' => $name, 'limitIp' => 1, 'totalGB' => $total, 'expiryTime' => $date, 'subId' => $subid, 'subPort' => $subPort]], 448);
        } else {
            return json_encode(['status' => false, 'msg' => 'unsuccessful', 'results' => ['id' => $randomKey, 'remark' => $name, 'limitIp' => 1, 'totalGB' => $total, 'expiryTime' => $date, 'subId' => $subid]], 448);
        }
        
    }

    public function getUserInfo($name, $id) {
        $url = $this->base_url . '/panel/inbound/list';
        $curl = curl_init();
        curl_setopt_array($curl, array(
          CURLOPT_URL => $url,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_HTTPHEADER => $this->headers
        ));
        $result = json_decode(curl_exec($curl), true)['obj'];

        foreach ($result as $value) {
            if ($value['id'] == $id) {
                $clients = $value['clientStats'];
                foreach ($clients as $client) {
                    if ($client['email'] == $name) {
                        return json_encode(['status' => true, 'result' => $client], 448);
                    }
                }
            }
        }
        return json_encode(['status' => false, 'msg' => 'not found', 'status_code' => 404], 448);
    }

    public function addExpire($remark, $date, $id) {
        $url = $this->base_url . '/panel/inbound/list';
        $curl = curl_init();
        curl_setopt_array($curl, array(
          CURLOPT_URL => $url,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_HTTPHEADER => $this->headers
        ));
        $result = json_decode(curl_exec($curl), true)['obj'];

        $inbound_key = 0;
        $client_key = 0;
        foreach ($result as $i => $value) {
            if ($value['id'] == $id) {
                $inbound_key = $i;
                $clients = $value['clientStats'];
                foreach ($clients as $client) {
                    if ($client['email'] == $remark) {
                        $client_key = $$client['id'];
                    }
                }
            }
        }
        
        $client_uuids = json_decode($result[$inbound_key]['settings'], true)['clients'];
        foreach ($client_uuids as $client_uuid) {
            if ($client_uuid['email'] == $remark) {
                $uuid = $client_uuid['id'];
                $expiryTime = $client_uuid['expiryTime'];
                $total = $client_uuid['totalGB'];
                $subId = $client_uuid['subId'];
            }
        }

        $update_url = $this->base_url . '/panel/inbound/updateClient/' . $uuid;

        $settings = json_encode([
            "clients" => [[
                "id" => $uuid,
                "flow" => "",
                "email" => $remark,
                "limitIp" => 1,
                "totalGB" => $total,
                "expiryTime" => $expiryTime + (86400 * 1000 * $date),
                "enable" => true,
                "tgId" => "",
                "subId" => $subId
            ]]
        ]);
        
        $data = "id=$id&settings=$settings";
        
        # -------------- [ curl ] -------------- #

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $update_url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        $resp = json_decode(curl_exec($curl), true);
        curl_close($curl);
        
        # -------------- [ curl ] -------------- #
        
        if($resp['success'] == true){
            return json_encode(['status' => true, 'msg' => 'successful'], 448);
        } else {
            return json_encode(['status' => false, 'msg' => 'unsuccessful'], 448);
        }
    }

    public function addVolume($remark, $limit, $id) {
        $url = $this->base_url . '/panel/inbound/list';
        $curl = curl_init();
        curl_setopt_array($curl, array(
          CURLOPT_URL => $url,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_HTTPHEADER => $this->headers
        ));
        $result = json_decode(curl_exec($curl), true)['obj'];

        $inbound_key = 0;
        $client_key = 0;
        foreach ($result as $i => $value) {
            if ($value['id'] == $id) {
                $inbound_key = $i;
                $clients = $value['clientStats'];
                foreach ($clients as $client) {
                    if ($client['email'] == $remark) {
                        $client_key = $$client['id'];
                    }
                }
            }
        }
        
        $client_uuids = json_decode($result[$inbound_key]['settings'], true)['clients'];
        foreach ($client_uuids as $client_uuid) {
            if ($client_uuid['email'] == $remark) {
                $uuid = $client_uuid['id'];
                $expiryTime = $client_uuid['expiryTime'];
                $total = $client_uuid['totalGB'];
                $subId = $client_uuid['subId'];
            }
        }

        $update_url = $this->base_url . '/panel/inbound/updateClient/' . $uuid;

        $settings = json_encode([
            "clients" => [[
                "id" => $uuid,
                "flow" => "",
                "email" => $remark,
                "limitIp" => 1,
                "totalGB" => $total + ($limit * pow(1024, 3)),
                "expiryTime" => $expiryTime,
                "enable" => true,
                "tgId" => "",
                "subId" => $subId
            ]]
        ]);
        
        $data = "id=$id&settings=$settings";
        
        # -------------- [ curl ] -------------- #

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $update_url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        $resp = json_decode(curl_exec($curl), true);
        curl_close($curl);
        
        # -------------- [ curl ] -------------- #
        
        if($resp['success'] == true){
            return json_encode(['status' => true, 'msg' => 'successful'], 448);
        } else {
            return json_encode(['status' => false, 'msg' => 'unsuccessful'], 448);
        }
    }
    
}

