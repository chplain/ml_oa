<?php
/**
 * 与公有库通讯 , 专供公有库请求使用
 */

define('DIR_DATA', dirname(__DIR__) . '/storage/app/private/v3OpenLibs/');

class appApiForCloud
{
    const CLOUD_ADDRESS_API_KEY = 'KEU8#.*&%vda';

    function checkSign()
    {
        $sign = md5(self::CLOUD_ADDRESS_API_KEY . http_build_query($_POST));
        $this->quitOn($_GET['sign'] != $sign, 1, '认证失败');
    }

    /**
     * 公有库配置更新同步
     */
    function onUpdateSetting()
    {
        switch ($_POST['name']) {
            case 'tags':
                $code = $_POST['code'];
                file_put_contents(DIR_DATA . 'tags.inc.php', $code);
                break;
            case 'default_exclude_tags':
                $value = $_POST['value'];
                $code = '<?php return "' . addslashes($value) . '";';
                file_put_contents(DIR_DATA . 'default_exclude_tags.inc.php', $code);
                break;
            case 'group_tags':
                $code = $_POST['code'];
                file_put_contents(DIR_DATA . 'group_tags.inc.php', $code);
                break;
            default:
                $this->quitOn(true, 1, '失败:不能识别的name属性. ' . $_POST['name'], null);
                break;
        }
        $this->quitOn(true, 0, 'succ', null);
    }

    function quitOn($b, $code, $message = '', $data = array())
    {
        if (!$b) return;
        $result = array('code' => $code, 'message' => $message, 'data' => $data);
        echo json_encode($result);
        exit;
    }

    function run()
    {
        $method_pre = 'do';
        if ('POST' == $_SERVER['REQUEST_METHOD']) {
            $method_pre = 'on';
        }
        $method = $method_pre . (('' != $_GET['do'] && method_exists($this, $method_pre . $_GET['do'])) ? $_GET['do'] : 'Default');
        call_user_func_array(array($this, $method), $this->callParams());
    }

    function callParams()
    {
        $r = $_GET;
        unset($r['do']);
        return $r;
    }
}

$o = new appApiForCloud();
$o->checkSign();
$o->run();
	

