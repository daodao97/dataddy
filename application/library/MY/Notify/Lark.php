<?php

namespace MY;

class Notify_Lark implements Notify_Interface
{
    private $webhook_url = '';
    private $secret = '';
    private $keyword = '';

    public function __construct($conf = [])
    {
        $this->webhook_url = $conf['url'] ?? '';
        $this->secret = $conf['secret'] ?? '';
        $this->keyword = $conf['keyword'] ?? '';
    }

    public function sendMessage($title, $content, $receiver)
    {
        try {
            if ($title && $content && $receiver) {
                return $this->sendRequest($title, $content, $receiver);
            } else {
                return false;
            }
        } catch (\Throwable $e) {
            return false;
        }
    }


    /**
     * Notes: 发送飞书富文本消息 
     * function: sendRequest
     * @param $title
     * @param $content
     * @param $developer
     * @return mixed
     * @static
     */
    private function sendRequest($title, $content, $developer)
    {
        $timestamp = time();
        $data = [
            'timestamp' => $timestamp,
            'msg_type' => 'post',
            'content' => [
                'post' => [
                    'zh_cn' => [
                        'title' => $this->keyword . " 通知：{$title}",
                        'content' => [
                            [
                                ['tag' => 'text', 'text' => $content]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        if ($this->secret) {
            $data['sign'] = self::makeSign();
        }
        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        $header = ['Content-Type: application/json; charset=utf-8'];

        $options = [
            'headers' => $header,
            'body' => $data
        ];

        $client = new \GuzzleHttp\Client(['verify' => false]);
        $response = $client->request('POST', $this->webhook_url, $options);
        return $response->getBody()->getContents();
    }


    /**
     * Notes: HmacSHA256 算法计算签名
     * function: makeSign
     * @param string $time
     * @return string
     * @static
     */
    private function makeSign($time = '')
    {
        $timestamp = $time ? $time : time();
        $secret = $this->secret;
        $string = "{$timestamp}\n{$secret}";
        return base64_encode(hash_hmac('sha256', "", $string, true));
    }
}