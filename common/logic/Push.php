<?php

namespace wokmanchat\common\logic;

use GuzzleHttp\Client;
use wokmanchat\common\model;
use wokmanchat\common\Module;
use GuzzleHttp\Exception\RequestException;
use Workerman\Http\Client as HttpClient;

class Push
{
    protected static $httpClient = null;

    /**
     * Undocumented function
     *
     * @param model\WokChatUser $user
     * @param array $request
     * @param mixed $response
     * @return void
     */
    public function trigger($user, $request = [], $response = [])
    {
        if (!$user) {
            return;
        }
        $app = model\WokChatApp::where('id', $user['app_id'])->find();

        if (!$app || !$app['push_url']) {
            return;
        }

        $data = [
            'user' => $user,
            'request' => $request,
            'response' => $response,
        ];

        $guzzleHttp = class_exists(Client::class);
        $httpClient = class_exists(HttpClient::class);

        if ($httpClient) {
            $this->httpClientPost($app['push_url'], $app, $data);
        } else if ($guzzleHttp) {
            $res = $this->guzzleHttpPost($app['push_url'], $app, $data);
        } else {
            $res = $this->curlPost($app['push_url'], $app, $data);
        }
    }

    /**
     * Undocumented function
     *
     * @param string $url
     * @param array|mixed $app
     * @param array $data
     * @return array
     */
    protected function curlPost($url, $app, $data = [])
    {
        try {
            $url = trim($url);
            $time = time();
            $sign = md5($app['secret'] . $time);

            $cafile = Module::getInstance()->getRoot() . 'data' . DIRECTORY_SEPARATOR . 'cacert.pem';

            $header = [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Content-Type: application/json; charset=UTF-8',
                'User-Agent: Mozilla/5.0 (Linux) Gecko/20100101 Firefox/99.0 Chrome/99.0 Wokmanchat/' . Module::getInstance()->getVersion(),
                'Referer: ' . preg_replace('/^(https?:\/\/[^\/]+).*$/', '$1', $url) . '/',
                'Host: ' . preg_replace('/^https?:\/\/([^\/]+).*$/', '$1', $url),
                'appid: ' . $app['id'],
                'time: ' . $time,
                'sign: ' . $sign,
            ];

            $options = [
                'http' => [
                    'method' => 'POST',
                    'header' => implode("\r\n", $header),
                    'timeout' => 10, // 超时时间（单位:s）
                    'content' => json_encode($data)
                ],
                'ssl' => [
                    'cafile' => $cafile,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ];
            $context = stream_context_create($options);

            $result = file_get_contents($url, false, $context);

            if (!$result) {
                return [200, '无返回内容'];
            }

            return [200, mb_substr($result, 0, 100)];
        } catch (\Exception $e) {
            return [500, mb_substr($e->getMessage(), 0, 100)];
        }
    }

    /**
     * Undocumented function
     *
     * @param string $url
     * @param array|mixed $app
     * @param array $data
     * @return array
     */
    protected function guzzleHttpPost($url, $app, $data = [])
    {
        try {
            $url = trim($url);
            $time = time();
            $sign = md5($app['secret'] . $time);

            $headers = [
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Content-Type' => 'application/json; charset=UTF-8',
                'User-Agent' => 'Mozilla/5.0 (Linux) Gecko/20100101 Firefox/99.0 Chrome/99.0 Wokmanchat/' . Module::getInstance()->getVersion(),
                'Referer' =>  preg_replace('/^(https?:\/\/[^\/]+).*$/', '$1', $url) . '/',
                'Host' => preg_replace('/^https?:\/\/([^\/]+).*$/', '$1', $url),
                'appid' => $app['id'],
                'time' => $time,
                'sign' => $sign,
            ];

            $client = new Client([
                'verify' => false, //不验证https
                'timeout' => 10, // 超时时间（单位:s）
                'headers' => $headers,
                'http_errors' => false,
            ]);

            $response = $client->request('POST', $url, [
                'json' => $data
            ]);
            if ($response->getStatusCode() == '200') {
                $content = (string)$response->getBody();
                if (!$content) {
                    return [200, '无返回内容'];
                }
                return [200, mb_substr($content, 0, 100)];
            } else {
                return [$response->getStatusCode(), ''];
            }
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $content = (string)$response->getBody();
                return [500, mb_substr($content, 0, 100)];
            }
            return [500, mb_substr($e->getMessage(), 0, 100)];
        } catch (\Throwable $e) {
            return [500, mb_substr($e->getMessage(), 0, 100)];
        }
    }

    /**
     * Undocumented function
     *
     * @param string $url
     * @param array|mixed $app
     * @param array $data
     * @return void
     */
    protected function httpClientPost($url, $app, $data = [])
    {
        $url = trim($url);
        $time = time();
        $sign = md5($app['secret'] . $time);

        if (!self::$httpClient) {
            $options = [
                'max_conn_per_addr' => 128, // 每个域名最多维持多少并发连接
                'keepalive_timeout' => 15,  // 连接多长时间不通讯就关闭
                'connect_timeout'   => 30,  // 连接超时时间
                'timeout'           => 10,  // 请求发出后等待响应的超时时间
            ];
            self::$httpClient = new HttpClient($options);
        }

        $headers = [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Content-Type' => 'application/json; charset=UTF-8',
            'User-Agent' => 'Mozilla/5.0 (Linux) Gecko/20100101 Firefox/99.0 Chrome/99.0 Wokcrontab/' . Module::getInstance()->getVersion(),
            'Referer' =>  preg_replace('/^(https?:\/\/[^\/]+).*$/', '$1', $url) . '/',
            'Host' => preg_replace('/^https?:\/\/([^\/]+).*$/', '$1', $url),
            'appid' => $app['id'],
            'time' => $time,
            'sign' => $sign,
        ];

        self::$httpClient->request($url, [
            'method' => 'POST',
            'headers' => $headers,
            'data' => json_encode($data),
            'success' => function ($response) {
                //
            },
            'error' => function ($e) {
                //
            }
        ]);
    }
}
