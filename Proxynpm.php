<?php

namespace MarioFlores\Proxynpm;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use MarioFlores\Proxynpm\Database;
use Exception; 

class Proxynpm {

    public $output = false;
    public $path;

    function __construct($path) {
        $this->path = $path; 
        if (empty($this->path)) {
            throw new Exception("You did not set the proxy file path");
        }
        if(!is_file(FCPATH.$this->path.'.json')){
            $file = fopen(FCPATH.$this->path.'.json'); 
            fwrite($file, ''); 
            fclose($file); 
        }
    }

    public function getProxy() {

        $tested = false;
        while ($tested == false) {
            $this->checkStock();
            $proxy = Database::orderByRaw("RAND()")->first();
            $proxy = $proxy;
            $tested = $this->test($proxy);
            if ($tested == false) {
                Database::destroy($proxy->id);
                $this->output('Deleted proxy ');
            } else {
                return [
                    'ip' => $proxy->ip,
                    'port' => $proxy->port,
                ];
            }
        }
    }

    public function checkStock() {
        $stock = Database::all()->count();
        if ($stock < 50) {

            $this->refresh();
            $proxys = $this->readFreshList();
            Database::insert($proxys);
        }
    }

    function test($proxy) {
        $client = new Client([
            'headers' => [
                'User-Agent' => "Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:47.0) Gecko/20100101 Firefox/47.0",
                'Accept-Language' => "en-US,en;q=0.5",
            ],
            'timeout' => 60,
            'cookies' => new \GuzzleHttp\Cookie\CookieJar,
            'http_errors' => false,
            'allow_redirects' => true,
        ]);
        try {
            $response = $client->request('GET', 'http://agency-systems.com/home/proxys/', [
                'proxy' => 'tcp://' . $proxy->ip . ':' . $proxy->port,
                'timeout' => 60,
                'http_errors' => false,
            ]);
            if ($response->getStatusCode() == 200) {
                $this->output('response was 200 ');
                $header = $response->getHeader('Content-Type');
                $this->output($header[0]);
                if (strpos($header[0], 'application/json') === false) {
                    $this->output('Response was not json ');
                    return false;
                } else {
                    $resposta = $response->getBody()->getContents();
                    if (empty($resposta)) {
                        $this->output('Response was empty ');
                        return false;
                    } else {
                        $this->output('response was not empty ');
                        $json = $resposta;
                        $resposta = json_decode($json);
                        if ($resposta->ip == '62.28.58.182') {
                            $this->output('Proxy is transparent ');
                            return false;
                        } else {
                            $this->output('the proxy is ok');
                            return true;
                        }
                    }
                }
            } else {
                $this->output('Response was different then 200');
                return false;
            }
        } catch (RequestException $ex) {
            $this->output($ex->getMessage());
            return false;
        }
    }

    function output($message) {
        if ($this->output === true) {
            echo $message . "<br /> \n";
        }
    }

    function refresh() {
        exec('proxy-lists getProxies --output-format="json" --output-file="' . $this->path . '"');
    }

    function readFreshList() {
        $http_list = array();
        $proxys = file_get_contents(FCPATH.$this->path . '.json');
        $proxys = json_decode($proxys);
        if (!empty($proxys)) {
            foreach ($proxys as $proxy) {
                if (in_array('http', $proxy->protocols)) {
                    $http_list[] = [
                        'ip' => $proxy->ipAddress,
                        'port' => $proxy->port,
                    ];
                }
            }
        }
        return $http_list;
    }

}
