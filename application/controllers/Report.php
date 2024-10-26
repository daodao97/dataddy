<?php
use \GG\Cache\FileCache as FileCache;

class ReportController extends MY\Controller_Abstract
{

    public function init()
    {
        parent::init();

        $this->form_validation = new \GG\FormValidation();
    }

    public function alarmAction()
    {
        $this->indexAction();

        R('title', '报警作业:' . array_last(preg_split('@/@u', $this->data['info']['name'])));

        if (!isset($this->data['result'])) {
            echo "No alarm data\n";
        }

        $result = $this->data['result']['data'] ?? [];
        $alarm = d(@$this->data['options']['alarm'], []);

        if (!is_array($result)) {
            echo "Alarm result is empty\n";

            return FALSE;
        }

        foreach ($result as $item) {
            $title = $item['id'];
            $message = $item['message'];

            $message = is_array($message) ? implode("\n", $message) : $message;

            if (!$message) {
                echo $title,  ": Alarm data invalid, miss message!\n";

                continue;
            }

            $types = d(@$alarm['type'], '');
            $types = array_filter(explode(',', $types));

            if (count($types) == 0) {
                echo $title,  ": Alarm data invalid, miss type!\n";
                exit;
            }

            foreach ($types as $type) {
                $conf = \GG\Config::get("notify.{$type}");

                if (!$conf) {
                    echo "Alarm type:{$type} miss notify conf!\n";

                    return FALSE;
                }

                $sender =  MY\Notify_Maker::make($conf);

                if (!$sender) {
                    echo "Alarm type:{$type} error, not support!\n";

                    return FALSE;
                }

                $receiver = "@all";

                $return_content = $sender->sendMessage($title, $message, $receiver);

                // if ($type == 'mail') {
                //     //@todo
                //     $subject = isset($item['subject']) ? $item['subject'] : '';
                //     if (!$subject) {
                //         if (preg_match('@^\s*(\S+)@', strip_tags($message), $ma)) {
                //             $subject = str_truncate($ma[1], 30);
                //         }
                //     }

                //     send_mail($type_receiver, $subject, $message);
                // } else {
                //     $url = \GG\Config::get("notify.{$type}.url");

                //     if (!$url) {
                //         echo "Alarm type:{$type} miss notify url!\n";

                //         return FALSE;
                //     }

                //     $url = preg_replace('@\{receiver\}@', urlencode($type_receiver), $url);
                //     $url = preg_replace('@\{message\}@', urlencode($message), $url);

                //     $return_content = @file_get_contents($url);
                // }

                echo "----------------------------\n";
                echo "TYPE:{$type}\n";
                echo "TITLE:{$title}\n";
                echo "MESSAGE:{$message}\n";
                echo "RESPONSE:{$return_content}\n";
                echo "----------------------------\n";
            }
        }

        return FALSE;
    }

    public function widgetAction()
    {
        $this->indexAction();

        $result = $this->data['result']['data'];

        $widgets = [];

        foreach ($result as $report_id => $report) {
            $widget_title = $this->data['info']['name'];
            if (count($result) > 1) {
                $widget_title .= '#' . $report_id;
            }
            if (empty($report['rows'])) {
                $widgets[] = [
                    'title' => $widget_title,
                    'id' => $report_id,
                ];
                continue;
            }

            $sum = $report['rows']['sum'] ?? $report['rows'][0];
            unset($report['rows']['sum']);
            unset($report['rows']['avg']);
            $rows = array_values($report['rows']);
            $chart_options = $report['chart_options'] ?? '';

            $widget = [
                'title' => $widget_title,
                'id' => $report_id,
                'sum' => $sum,
            ];
            if ($chart_options) {
                $chart_options_list = is_array($chart_options) && isset($chart_options[0]) ? $chart_options : [$chart_options];
                $category_axis = array_column($rows, array_keys($rows[0])[0]);
                $chart = [
                    'category_axis' => $category_axis,
                    'values' => [],
                ];
                $data_cols = [];
                foreach ($chart_options_list as $chart_options_item) {
                    if (!is_array($chart_options_item)) {
                        $data_cols = array_merge($data_cols, preg_split('@,@u', $chart_options_item));
                    } elseif (isset($chart_options_item['fields'])) {
                        $data_cols = array_merge($data_cols, preg_split('@,@u', $chart_options_item['fields']));
                    } elseif (isset($chart_options_item['graphs'])) {
                        $data_cols = array_merge($data_cols, array_column($chart_options_item['graphs'], 'valueField'));
                    }
                }
                $data_cols = array_values(array_unique($data_cols));
                $new_sum = [];
                foreach ($data_cols as $data_col) {
                    $new_sum[$data_col] = $widget['sum'][$data_col];
                    $chart['values'][$data_col] = array_map(function ($val) {
                        return $val;
                    }, array_column($rows, $data_col));
                }
                $widget['sum'] = $new_sum;
                $widget['chart'] = [
                    'options' => $chart_options,
                    'data' => $chart,
                ];
            } else {
                $widget['table'] = $report['rows'];
            }
            $widget['cols'] = array_keys($widget['sum']);
            $widgets[] = $widget;
        }

        return response([
            'title' => $this->data['info']['name'],
            'widgets' => $widgets,
        ]);
    }

    public function mailAction()
    {
        $this->send_by_mail = true;

        param_request([
            'receiver' => 'STRING',
            'subject' => 'STRING'
        ]);

        $request_options = array();

        if (!empty($GLOBALS['req_receiver'])) {
            $request_options['receiver'] = @$GLOBALS['req_receiver'];
        }
        if (!empty($GLOBALS['req_subject'])) {
            $request_options['subject'] = @$GLOBALS['req_subject'];
        }

        ob_start();
        $this->indexAction();
        ob_end_clean();

        R('title', '邮件:' . array_last(preg_split('@/@u', $this->data['info']['name'])));

        if (isset($this->data['result']['options']['mail'])) {
            $this->mail_config = array_merge($this->mail_config, $this->data['result']['options']['mail']);
        }

        if (!empty($request_options)) {
            $this->mail_config = array_merge($this->mail_config, $request_options);
        }

        if (isset($this->mail_config['enable']) && !$this->mail_config['enable']) {
            $this->send_by_mail = false;
            return response_error(CODE_ERR_PARAM, 'mail is disabled.');
        }

        $subject = $this->mail_config['subject'] ?? '{name}';
        $subject = preg_replace('@\{name\}@', $this->data['subject'], $subject);
        if (preg_match('@\{date\|(.+)\}@', $subject, $ma)) {
            $subject = preg_replace('@\{date\|(.+)\}@', date('Y-m-d', strtotime(@$ma[1])), $subject);
        }

        $this->mail_config['subject'] = $subject;

        $this->data['subject'] = d(@$this->mail_config['subject'], $this->data['subject']);
    }


    public function indexAction()
    {
        param_request([
            'id' => 'UINT'
        ]);

        $id = @$GLOBALS['req_id'];

        if (!$id || !($info = M('menuItem')->find($id)) || $info['type'] == 'folder') {
            $this->error('404');

            return FALSE;
        }

        $this->data['info'] = $info;

        R('title', '报表查询:' . array_last(preg_split('@/@u', $this->data['info']['name'])));

        if ($info['type'] == 'link') {
            $this->redirect($info['uri']);

            return FALSE;
        }

        $options = my_json_decode($info['settings']);


        $options['dsn'] = $info['dsn'];

        $template_data = [
            '$root.$state.current.data.pageTitle' => array_last(preg_split('@/@u', $info['name'])),
            'writeable' => P("report.{$id}", 'w'),
            'form_enable' => false,
            'autorefresh' => d(@$options['auto_refresh'], 0),
            'subject' => $info['name'],
            'receiver' => d(@$this->mail_config['receiver'], ''),
        ];

        if (R('is_cli') || isset($_GET['_disable_cache'])) {
            $options['disable_cache'] = TRUE;
        }

        $this->data['subject'] = $info['name'];

        $use_dev_content = !R('is_cli') && R('permission')->check('menu.' . $id, 'w');
        $content = $use_dev_content && $info['dev_content'] ? $info['dev_content'] : $info['content'];
        $safe_code_field = $use_dev_content && $info['dev_content'] ? 'dev_safe_code' : 'safe_code';

        $me = R('user');
        $sql_log = new SqlLogModel([
            'report_id' => $id,
            'uid' => $me['id'] ?? 0,
            'nick' => $me['nick'] ?? ''
        ]);

        $engine = new \MY\Data_Template(
            $content,
            $options,
            $_GET,
            R('permission'),
            $info[$safe_code_field],
            $this->filecache,
            //null
            $sql_log
        );

        try {
            if (count($engine->getForms()) > 0) {
                $template_data['form_enable'] = true;
            }
            
            $result = $engine->run();

        } catch (Exception $e) {

            return $this->error($e, template_cb($template_data));
        }

        if ($result == FALSE) {

            return $this->error('报表解析失败', template_cb($template_data));
        }

        if ($engine->is_safe_code != !!$info[$safe_code_field]) {
            M('menuItem')->update(['id' => $id], [$safe_code_field => 1 * $engine->is_safe_code]);
        }

        $this->data['result'] = &$result;

        if ($result['type'] == 'html') {
            $this->data['template_data'] = template_cb($template_data);
            // echo $result['data'] . template_cb($template_data);
            return true;
        }

        foreach ($result['data'] as $report_id => &$report) {
            $chart_options = isset($options['chart']) ? $options['chart'] : [];

            if (isset($options['charts'][$report_id])) {
                $chart_options = $options['charts'][$report_id];
            }

            if (isset($report['options']['chart'])) {
                $chart_options =  $report['options']['chart'];
                
                // 尝试解析为 JSON
                if (is_string($chart_options) && substr(trim($chart_options), 0, 1) === '{') {
                    $parsed_options = json_decode($chart_options, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $chart_options = $parsed_options;
                    }
                }
            }

            if (isset($GLOBALS['ddy_chart_options']) && isset($GLOBALS['ddy_chart_options'][$report_id])) {
                if (is_array($GLOBALS['ddy_chart_options'][$report_id])) {
                    $chart_options = array_merge($chart_options, $GLOBALS['ddy_chart_options'][$report_id]);
                } else {
                    $chart_options = $GLOBALS['ddy_chart_options'][$report_id];
                }
            }

            if (!isset($report['chart_options'])) {
                $report['chart_options'] = $chart_options;
            }

            if (isset($report['options']['edit'])) {
                $report['options']['edit']['dataddy_state'] = get_state_string($report_id);
            }
            unset($report);
        }

        $this->data['options'] = $result['options'];

        $this->data['error'] = $engine->getErrors();

        if ($template_data['writeable']) {
            $template_data['sql'] = @$result['sql'];
        }

        if ($use_dev_content) {
            $template_data['dev_info'] = [
                'warning' => $info['dev_version_time'] != $info['release_version_time'],
                'dev_version_time' => $info['dev_version_time'],
            ];
        }
        $this->data['template_data'] = template_cb($template_data);

        $format = $_GET['format'];
        if ($format == 'json') {
            $rowData = $result['data'];
            //可能有多个报表
            $outputDataJson = array();
            if ($rowData) {
                foreach ($rowData as $data) {
                    $outputDataJson[] = $data['rows'];
                }
            }
            echo json_encode($outputDataJson);
            Yaf\Dispatcher::getInstance()->disableView();
        }

        if ($result['type'] == 'raw' || $format == 'raw') {
            echo $result['data'];
            Yaf\Dispatcher::getInstance()->disableView();
        }

        if (R('frame_mode')) {
            $this->data['frame_mode'] = true;
            $this->display('../open/index', $this->data);
            return FALSE;
        }
    }

    public function saveAction()
    {
        param_request([
            'id' => 'UINT',
            'dataddy_state' => 'STRING',
        ]);

        $id = @$GLOBALS['req_id'];
        $state = @$GLOBALS['req_dataddy_state'];

        if (!$id || !($info = M('menuItem')->find($id)) || $info['type'] == 'folder') {

            return response_error(CODE_ERR_PARAM, 'id');
        }

        R('title', '报表保存:' . $info['name']);

        if (!$state) {

            return response_error(CODE_ERR_PARAM, 'miss state');
        }


        $options = my_json_decode($info['settings']);

        $this->data['subject'] = $info['name'];

        $options['dsn'] = $info['dsn'];

        $use_dev_content = !R('is_cli') && R('permission')->check('menu.' . $id, 'w');
        $content = $use_dev_content && $info['dev_content'] ? $info['dev_content'] : $info['content'];
        $safe_code_field = $use_dev_content && $info['dev_content'] ? 'dev_safe_code' : 'safe_code';

        $engine = new \MY\Data_Template($content, $options, $_GET, R('permission'), $info[$safe_code_field], $this->filecache);

        try {
            $engine->ignore_data = TRUE;
            $result = $engine->run();

            $handler = ddy_get_form_handler($state);

            if (!$handler) {

                return response_error(CODE_ERR_PARAM, 'invalid state');
            }

            $columns = NULL;
            foreach ($result['data'] as $report_id => $report) {
                if (get_state_string($report_id) != $state) {
                    continue;
                }

                $options = $report['options'];
                $columns = @$options['edit']['columns'];
                break;
            }

            if (!$columns) {
                return response_error(CODE_ERR_PARAM, "columns not config");
            }

            $error = '';
            $rules = [];
            foreach ($columns as $label => $column) {
                $field = d(@$column['name'], $label);
                if (!isset($_POST[$field])) {
                    continue;
                }

                $value = $_POST[$field];
                $rules["{$field}|{$label}"] = isset($column['rule']) ? $column['rule'] : 'any';

                if (isset($column['type']) && $column['type'] == 'select') {
                    #@todo
                }
            }

            $data = $this->form_validation->check($rules, $this);

            if ($data === FALSE) {
                return response_error(CODE_ERR_PARAM, $this->form_validation->errors());
            }

            if (empty($data)) {
                return response_error(CODE_ERR_PARAM, "no data");
            }

            $result = call_user_func_array($handler, [&$error, @$_POST['row_id'], $data]);

            if ($result) {
                return response($result);
            } else {
                return response_error(CODE_ERR_SYSTEM, $error);
            }

        } catch (Exception $e) {

            return response_error(CODE_ERR_SYSTEM, "$e");
        }
    }


    public function syntaxCheckAction()
    {
        param_request([
            'id' => 'UINT',
            'code' => 'STRING'
        ]);
        $id = $GLOBALS['req_id'];
        $code = $GLOBALS['req_code'];
        $options = [];

        $engine = new \MY\Data_Template(
            $code,
            $options,
            $_GET,
            R('permission'),
            false,
            null,
            null
        );

        try {
            $engine->validate();
            $errors = $engine->getErrors();
            if ($errors) {
                var_dump($errors);
            }
            return response([]);
        } catch (\PHPSandbox\Error $exception) {
            $msg = ($exception->getPrevious() ?? $exception)->getMessage();
            #var_dump($exception->getPrevious());
            #var_dump($exception->getNode()->getLine());
            #var_dump($exception);
            #var_dump($exception->getNode());
            #var_dump($exception->getData());
            $parser_node = $exception->getNode();
            #var_dump($exception->getPrevious());
            #var_dump($exception);
            $line = 0;
            if ($parser_node) {
                $line = $parser_node->getLine();
            } else {
                $traces = $exception->getTrace();
                foreach ($traces as $trace) {
                    if ($trace['args'] ?? false) {
                        foreach ($trace['args'] as $arg) {
                            if (is_object($arg) && $arg instanceof \PhpParser\Node) {
                                $line = $arg->getLine();
                                break 2;
                            }
                        }
                    }
                }
                if (!$line) {
                    preg_match('/.+on line (\d+)/ms', $msg, $m);
                    $line = $m[1] ?? 0;
                }
            }
            $error = preg_replace('/on line (\d)+/', 'on line ' . $line, $msg);
            return response(['msg' => $msg, 'error' => $error, 'line' => $line], CODE_ERR_SYSTEM);
        }
    }

    public function buildCronAction() {
        $result = \MY\Crontab::build();

        if (!$result) {
            return response_error(CODE_ERR_SYSTEM, 'CRON build error');
        }
        return response("crontab build success");
    }

    public function formAction() {
        param_request([
            'id' => 'UINT',
        ]);
        $id = $GLOBALS['req_id'];
        if (!$id || !($info = M('menuItem')->find($id)) || $info['type'] == 'folder') {
            $this->error('404');

            return FALSE;
        }
        $this->data['subject'] = $info['name'];

        $use_dev_content = !R('is_cli') && R('permission')->check('menu.' . $id, 'w');
        $content = $use_dev_content && $info['dev_content'] ? $info['dev_content'] : $info['content'];
        $safe_code_field = $use_dev_content && $info['dev_content'] ? 'dev_safe_code' : 'safe_code';

        $me = R('user');
        $sql_log = new SqlLogModel([
            'report_id' => $id,
            'uid' => $me['id'] ?? 0,
            'nick' => $me['nick'] ?? ''
        ]);

        $engine = new \MY\Data_Template(
            $content,
            $options,
            $_GET,
            R('permission'),
            $info[$safe_code_field],
            $this->filecache,
            //null
            $sql_log
        );
         
        $this->data['id'] = $id;
        $this->data['forms'] = $engine->getForms();

        $this->display('../report/form', $this->data);
        return FALSE;
    }

    public function saveformAction() {
        param_request([
            'id' => 'UINT',
        ]);
        $id = $GLOBALS['req_id'];
        $data = $_POST;

        if (!$id || !($info = M('menuItem')->find($id)) || $info['type'] == 'folder') {
            return response_error(CODE_ERR_PARAM, 'id');
        }
        $this->data['subject'] = $info['name'];

        $use_dev_content = !R('is_cli') && R('permission')->check('menu.' . $id, 'w');
        $content = $use_dev_content && $info['dev_content'] ? $info['dev_content'] : $info['content'];
        $safe_code_field = $use_dev_content && $info['dev_content'] ? 'dev_safe_code' : 'safe_code';

        $me = R('user');
        $sql_log = new SqlLogModel([
            'report_id' => $id,
            'uid' => $me['id'] ?? 0,
            'nick' => $me['nick'] ?? ''
        ]);

        try {
            $engine = new \MY\Data_Template(
                $content,
                $options,
                $_GET,
                R('permission'),
                $info[$safe_code_field],
                $this->filecache,
                //null
                $sql_log
            );

            $this->data['forms'] = $engine->getForms();

            $check = is_callable("ddy_save_form_handler");
            if (!$check) {
                return response_error(CODE_ERR_PARAM, 'save form handler not defined');
            }
    
            try {
                $result = call_user_func_array("ddy_save_form_handler", [$id, $data]);
                return response($result);
            } catch (Exception $e) {
                return response_error(CODE_ERR_SYSTEM, $e->getMessage());
            }
    
        } catch (Exception $e) {
            return response_error(CODE_ERR_SYSTEM, "$e");
        }

    }
}
/* End of file Index.php */
