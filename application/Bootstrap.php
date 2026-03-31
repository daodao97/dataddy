<?php
require dirname(__FILE__) . '/helpers/common.php';
require dirname(__FILE__) . '/helpers/parameter.php';
require dirname(__FILE__) . '/helpers/array.php';
require dirname(__FILE__) . '/helpers/date.php';
require dirname(__FILE__) . '/helpers/string.php';
require dirname(__FILE__) . '/helpers/http.php';
require dirname(__FILE__) . '/helpers/dataddy.php';

class Bootstrap extends Yaf\Bootstrap_Abstract {
    public function _initLocal($dispatcher)
    {
        $bootstrap_start = microtime(TRUE);
        $loader = Yaf\Loader::getInstance();
        $loader->registerLocalNamespace(array('MY', 'GG', 'PL'));

        GG\Db\Model\Base::setForceReadOnMater();

        class_alias('\MY\Config', 'Config');

        R('starttime', microtime(TRUE));

        $view = new MY\View_Simple(__dir__ . '/views');

        $dispatcher->setView($view);

        $stage_start = microtime(TRUE);
        Config::init();
        $this->logProfile('bootstrap.config_init', $stage_start);

        $stage_start = microtime(TRUE);
        $uid = GG\Session::getInstance()->getUserID();
        $this->logProfile('bootstrap.session_get_uid', $stage_start, [
            'uid' => (int)$uid,
        ]);

        //if (!$uid) {
            $ticket = null;
            if (isset($_GET['ticket'])) {
                $ticket = $_GET['ticket'];
            } elseif (!empty($_SERVER['HTTP_REFERER']) && preg_match('@ticket=(\w+)@i', $_SERVER['HTTP_REFERER'], $ma)) {
                $ticket = $ma[1];
            }
            if ($ticket) {
                $ticket_info = \TrustTicketModel::getInstance()->getTicketInfo($ticket, get_client_ip());
                $uid = $ticket_info['uid'] ?? 0;
                R('frame_mode', true);
            }
        //}
        $roles = '';
        $is_admin = FALSE;


        $stage_start = microtime(TRUE);
        if ($uid && ($user = M('user')->find($uid))) {
            unset($user['password']);
            if (!empty($user['config'])) {
                $user['config'] = my_json_decode($user['config']);
            }
            R('user', $user);
            $roles = $user['roles'];
            $is_admin = $user['is_admin'];
        }
        $this->logProfile('bootstrap.load_user', $stage_start, [
            'uid' => (int)$uid,
            'found' => isset($user) ? 1 : 0,
        ]);

        if ($this->isCli()){
            $roles = '1';
            $is_admin = True;
            R('is_cli', TRUE);
        }

        $stage_start = microtime(TRUE);
        R('permission', new MY\Permission($roles, $is_admin));
        $this->logProfile('bootstrap.permission_init', $stage_start, [
            'roles' => is_string($roles) ? $roles : count((array)$roles),
            'is_admin' => $is_admin ? 1 : 0,
        ]);

        MY\PluginManager::getInstance()
            ->setDispatcher($dispatcher);

        $dispatcher->registerPlugin(new DataddyPlugin());

        error_reporting(E_ALL ^ E_NOTICE ^ E_DEPRECATED ^ E_STRICT);
        ini_set('display_errors', '1');

        $this->logProfile('bootstrap.init_total', $bootstrap_start, [
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
        ]);
    }

    private function isCli()
    {
        return (php_sapi_name() === 'cli') ? true : false;
    }

    private function logProfile($stage, $start, $context = [])
    {
        $cost = round((microtime(TRUE) - $start) * 1000, 2);
        $parts = [ "{$stage} cost={$cost}ms" ];
        foreach ($context as $key => $value) {
            $parts[] = "{$key}={$value}";
        }
        log_message(implode(' ', $parts), LOG_INFO);
    }
}
/* End of file <`2:filename`>.php */
