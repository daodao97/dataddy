<?php
namespace MY;

const LOGO = <<<EOR
<svg height="32" aria-hidden="true" viewBox="0 0 16 16" version="1.1" width="32" data-view-component="true" class="octicon octicon-mark-github v-align-middle color-fg-default">
    <path d="M8 0c4.42 0 8 3.58 8 8a8.013 8.013 0 0 1-5.45 7.59c-.4.08-.55-.17-.55-.38 0-.27.01-1.13.01-2.2 0-.75-.25-1.23-.54-1.48 1.78-.2 3.65-.88 3.65-3.95 0-.88-.31-1.59-.82-2.15.08-.2.36-1.02-.08-2.12 0 0-.67-.22-2.2.82-.64-.18-1.32-.27-2-.27-.68 0-1.36.09-2 .27-1.53-1.03-2.2-.82-2.2-.82-.44 1.1-.16 1.92-.08 2.12-.51.56-.82 1.28-.82 2.15 0 3.06 1.86 3.75 3.64 3.95-.23.2-.44.55-.51 1.07-.46.21-1.61.55-2.33-.66-.15-.24-.6-.83-1.23-.82-.67.01-.27.38.01.53.34.19.73.9.82 1.13.16.45.68 1.31 2.69.94 0 .67.01 1.3.01 1.49 0 .21-.15.45-.55.38A7.995 7.995 0 0 1 0 8c0-4.42 3.58-8 8-8Z"></path>
</svg>
EOR;
class Sso_GithubSso extends \MY\Plugin_Abstract implements Sso_Interface 
{

    private $client;

    public function pluginInit($dispatcher, $manager){
        $this->client = Config::get("sso.github");
        if (!isset($this->client['client_id'])) {
            throw new \Exception('application.ino need sso.github.client_id config');
        }
        if (!isset($this->client['client_secret'])) {
            throw new \Exception('application.ino need sso.github.client_secret config');
        }
    }

    // 当 cookie 中已经标记过使用的三方登录是, 构造二次登录链接
    public function getLoginUrl($redirectUri)
    {
        $client_id = $this->client['client_id'];
        return "https://github.com/login/oauth/authorize?client_id=$client_id&redirect_uri=$redirectUri";
    }

    // 通过回跳地址中的 ticket 等, 进行三方验证, 返回用户信息
    public function auth()
    {
        $code = $_GET['code'] ?? '';
        if ($code == '') {
            return false;
        }
        $client = $this->client;
        $client['code'] = $code;

        $res = ddy_http_request("POST", 'https://github.com/login/oauth/access_token', [
            'query' => $client,
            'headers' => [
                "Accept" => "application/json",
                "Accept-Encoding" => "application/json",
            ]
        ]);

        $res = json_decode($res, true);


        if (isset($res['error'])) {
            throw new \Exception("Github SSO 认证失败: " . $res['error_description']);
        }

        if (!isset($res['access_token'])) {
            return false;
        }

        $user = ddy_http_request('GET','https://api.github.com/user', [
            'headers' => [
                "Accept" => "application/vnd.github+json",
                "X-GitHub-Api-Version" => "2022-11-28",
                'Authorization' => "Bearer " . $res["access_token"]
            ]
        ]);

        $user = json_decode($user, true);
        if (!isset($user['login'])) {
            return false;
        }

        $ddyUser = [
            'username' => $user['login'], 
            'nick' => $user['name'], 
            'email' => '', 
            'mobile' => '', 
            'avatar' => $user['avatar_url']
        ];

        return $ddyUser;
    }

    // 返回 html 片段, 显示在 登录页面, 登录框下方
    public function getLink()
    {
        $redirect_uri = get_current_protocol() . "://" . $_SERVER['HTTP_HOST'] . '/login/auth';
        $link = $this->getLoginUrl($redirect_uri);

        return "<a href='$link'>" . LOGO . "</a>";
    }

    /**
     *
     * 通过第三方获取当前用户信息
     * @return mixed array("username", "nickname", "email")
     */
    public function getUserInfo()
    {
    }

    public function getLogoutUrl($redirectUri)
    {
        \GG\Session::deleteCookie("use_system_login", $_SERVER['HTTP_HOST']);
        return '/';
    }
}