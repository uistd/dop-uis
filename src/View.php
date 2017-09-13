<?php

namespace FFan\Dop\Uis;

use FFan\Std\Common\Env as FFanEnv;
use FFan\Std\Console\Debug;

/**
 * Class View 显示类
 * @package FFan\Dop\Uis
 */
class View
{
    /**
     * @var Response
     */
    private $response;

    /**
     * View constructor.
     * @param Response $response
     */
    public function __construct(Response $response)
    {
        $this->response = $response;
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
        } else {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode($data, JSON_UNESCAPED_UNICODE);
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
            'data' => null
        );
        //附加数据输出， 但是不会覆盖主体 status message data 字段
        $append_data = $this->response->getAppendData();
        if (0 === $status && !empty($append_data)) {
            $result += $append_data;
        }
        if (Response::STATUS_OK === $status && $this->response) {
            $data = $this->response->getOutput();
            //如果 数组只有一层, 并且key 也是 data
            if (isset($data['data']) && 1 === count($data)) {
                $data = $data['data'];
            }
            $result['data'] = $data;
        }
        return $result;
    }
}
