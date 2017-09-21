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
     * 显示
     */
    public function view()
    {
        $this->clearOutputBuffer();
        $data = $this->viewData();
        //调试模式下，显示 控制 台
        if (Debug::isDebugMode()) {
            Debug::displayDebugMessage($data);
        } elseif (self::VIEW_TYPE_JSON === $this->view_type) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode($data, JSON_UNESCAPED_UNICODE);
        } elseif (self::VIEW_TYPE_TPL === $this->view_type) {
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
            //如果 数组只有一层, 并且key 也是 data
            if (isset($result_data['data']) && 1 === count($result_data)) {
                $result_data = $result_data['data'];
            }
        }
        //附加数据输出， 但是不会覆盖主体 status message data 字段
        $append_data = $this->response->getAppendData();
        if (0 === $status && !empty($append_data)) {
            $result += $append_data;
        }
        $result['data'] = $result_data;
        return $result;
    }
}
