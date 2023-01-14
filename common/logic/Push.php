<?php

namespace wokmanchat\common\logic;

use wokmanchat\common\model;
use wokmanchat\common\Module;

class Push
{
    /**
     * Undocumented function
     *
     * @param model\WokChatUser $user
     * @param array $request
     * @param mixed $response
     * @return array
     */
    public function trigger($user, $request = [], $response = [])
    {
        $app = model\WokChatApp::where('id', $user['app_id'])->cache(600)->find();

        if (!$app || !$app['push_url']) {
            return ['code' => 0, 'msg' => '无push_url'];
        }

        $data = [
            'user' => $user,
            'request' => $request,
            'response' => $response,
        ];

        $this->curl($app['push_url'], $app, $data);
    }

    protected function curl($url, $app, $data = [])
    {
        try {
            $url = trim($url);
            $time = time();
            $sign = md5($app['secret'] . $time);

            $cafile = Module::getInstance()->getRoot() . 'data' . DIRECTORY_SEPARATOR . 'cacert.pem';

            $header = [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Encoding: gzip, deflate, br',
                'Accept-Language: zh-CN,en-US;q=0.7,en;q=0.3',
                'Content-Type: application/json; charset=UTF-8',
                'Connection: close',
                'User-Agent: Mozilla/5.0 (Linux) Gecko/20100101 Firefox/99.0 Chrome/99.0 Wokmanchat/1.0.2',
                'Referer: ' . preg_replace('/^(https?:\/\/[^\/]+).*$/', '$1', $url) . '/',
                'Host: ' . preg_replace('/^https?:\/\/([^\/]+).*$/', '$1', $url),
                'appid: ' . $app['id'],
                'time: ' . $time,
                'sign: ' . $sign,
            ];

            $options = [
                'http' => [
                    'method' => 'POST',
                    'ssl' => [
                        'cafile' => $cafile,
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                    ],
                    'header' => implode("\r\n", $header),
                    'timeout' => 10, // 超时时间（单位:s）
                    'content' => json_encode($data)
                ]
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
}
