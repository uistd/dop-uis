<?php

namespace FFan\Dop\Uis;

use FFan\Std\Common\Config;
use FFan\Std\Common\Env as FFanEnv;
use FFan\Std\Console\Debug;
use FFan\Std\Tpl\Tpl;

/**
 * Class View 显示类
 * @package FFan\Dop\Uis
 */
class View
{
    /**
     * 数据显示方式
     */
    const VIEW_TYPE_JSON = 1;
    const VIEW_TYPE_TPL = 2;
    const VIEW_TYPE_ECHO = 3;

    /**
     * @var bool 是否已经显示 过了
     */
    private $is_display = false;

    /**
     * @var Response
     */
    private $response;

    /**
     * @var int 显示方式
     */
    private $view_type = self::VIEW_TYPE_JSON;

    /**
     * @var mixed 模板参数
     */
    private $view_tpl;

    /**
     * @var string echo 输出的时候的header
     */
    private $content_type;

    /**
     * @var string 输出的内容
     */
    private $echo_content;

    /**
     * View constructor.
     * @param Response $response
     */
    public function __construct(Response $response)
    {
        $this->response = $response;
    }

    /**
     * 设置以模板方式显示
     * @param string $tpl_name
     * @throws ActionException
     */
    public function setViewTpl($tpl_name)
    {
        if (!is_string($tpl_name) || empty($tpl_name)) {
            throw new \InvalidArgumentException('Invalid tpl_name');
        }
        $app = Application::getInstance();
        //设置tpl的路径
        Config::add('ffan-tpl', array('tpl_dir' => 'apps/' . $app->getAppName() . '/view'));
        if (!Tpl::hasTpl($tpl_name, $tpl_file)) {
            throw new ActionException("Tpl '" . $tpl_file . "' not found", '105');
        }
        $this->view_type = self::VIEW_TYPE_TPL;
        $this->view_tpl = $tpl_name;
    }

    /**
     * 直接输出内容
     * @param string $echo_str 需要显示的内容
     * @param string $content_type
     */
    public function setViewEcho($echo_str, $content_type = 'text/html')
    {
        if (!is_string($echo_str)) {
            throw new \InvalidArgumentException('Invalud echo_str');
        }
        $this->view_type = self::VIEW_TYPE_ECHO;
        if (null === $echo_str) {
            $this->echo_content = $echo_str;
        } else {
            $this->echo_content .= $echo_str;
        }
        $this->content_type = $content_type;
    }

    /**
     * 显示
     */
    public function view()
    {
        $this->clearOutputBuffer();
        $data = $this->viewData();
        //防止在 shutdown function里出现错误，然后调用错误输出
        if ($this->is_display) {
            return;
        }
        $this->is_display = true;
        //调试模式下，显示 控制 台
        if (Debug::isDebugMode()) {
            Debug::displayDebugMessage($data);
            return;
        }
        //json输出
        if (self::VIEW_TYPE_JSON === $this->view_type) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($data, JSON_UNESCAPED_UNICODE);
            return;
        }
        //其它方式
        if (Response::STATUS_OK !== $data['status']) {
            echo $data['message'];
            return;
        }
        //echo输出
        if (self::VIEW_TYPE_ECHO === $this->view_type) {
            if (is_string($this->content_type) && !empty($this->content_type)) {
                header('Content-Type: ' . $this->content_type);
            }
            echo $this->echo_content;
        } //模板方式
        elseif (self::VIEW_TYPE_TPL === $this->view_type) {
            echo Tpl::get($this->view_tpl, $data);
        }
    }

    /**
     * 清理之前的输出buffer
     */
    private function clearOutputBuffer()
    {
        //如果是开发模式，将输出以日志形式记录
        if (FFanEnv::isDev()) {
            for ($level = ob_get_level(); $level > 0; --$level) {
                $tmp = ob_get_clean();
                if (false !== $tmp && strlen($tmp) > 0) {
                    FFan::debug($tmp);
                }
            }
        }//正式环境将之前的所有输出全部clean
        else {
            for ($level = ob_get_level(); $level > 0; --$level) {
                ob_end_clean() || ob_clean();
            }
        }
    }

    /**
     * 获取输出数据
     * @return array
     */
    private function viewData()
    {
        $status = $this->response->getStatus();
        $message = $this->response->getMessage();
        $result = array(
            'status' => $status,
            'message' => $message,
        );
        $result_data = null;
        if (Response::STATUS_OK === $status && $this->response) {
            $result_data = $this->response->getOutput();
            $result += $result_data;
        }
        //附加数据输出， 但是不会覆盖主体 status message data 字段
        $append_data = $this->response->getAppendData();
        if (0 === $status && !empty($append_data)) {
            $result += $append_data;
        }
        return $result;
    }
}
