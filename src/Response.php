<?php

namespace FFan\Dop\Uis;

use FFan\Std\Common\Config;

/**
 * Class Response
 * @package ffan\php\base
 */
class Response
{
    //数据输出格式 json
    const TYPE_JSON = 1;
    //二进制base64
    const TYPE_BINARY = 2;

    //内置状态码 ok
    const STATUS_OK = 0;
    //page不存在
    const STATUS_PAGE_NOT_FOUND = 101;
    //action方法不存在
    const STATUS_ACTION_NOT_FOUND = 102;
    //参数错误
    const STATUS_PARAM_INVALID = 103;
    //内部错误
    const STATUS_INTERNAL_ERROR = 104;

    /**
     * @var IResponse 数据输出
     */
    private $response_data;

    /** @var int 状态码
     */
    private $status = self::STATUS_OK;

    /**
     * @var string 消息内容
     */
    private $message;

    /**
     * @var array 附加数据
     */
    private $append_data;

    /**
     * 设置状态码
     * @param int $status_code
     * @param null|string $message
     */
    public function setStatus($status_code, $message = null)
    {
        if (!is_int($status_code) || $status_code < 0) {
            throw new \InvalidArgumentException('Invalid status code');
        }
        $this->status = $status_code;
        if (is_string($message)) {
            $this->message = $message;
        }
    }

    /**
     * 设置数据
     * @param IResponse $data
     */
    public function setResponse(IResponse $data)
    {
        $this->response_data = $data;
    }

    /**
     * 设置和 status message data 平级的数组
     * @param string $key
     * @param mixed $value
     */
    public function appendData($key, $value)
    {
        if (!is_string($key) || empty($key)) {
            throw new \InvalidArgumentException('Invalid append data key');
        }
        //如果 key 已经存在了， 自动重命名
        if (isset($this->append_data[$key])) {
            //如果 key 后面 带 数字
            if (preg_match('/[a-zA-Z_]([0-9]+$)/', $key, $match) > 0) {
                $num = (int)$match[1] + 1;
                $key .= '_' . $num;
            } else {
                $key .= '1';
            }
            $this->appendData($key, $value);
        } else {
            $this->append_data[$key] = $value;
        }
    }

    /**
     * 获取输出数据
     * @return mixed
     */
    public function getOutput()
    {
        if (null === $this->response_data) {
            return $this->response_data;
        }
        $output_type = $this->getOutputType();
        if (self::TYPE_JSON === $output_type) {
            $result = $this->response_data->arrayPack(true);
        } else {
            $app = Application::getInstance();
            $app->getResponse()->appendData('dopBinary', true);
            $mask_key = $this->getBinaryMaskKey();
            $bin_str = $this->response_data->binaryEncode(false, true, $mask_key);
            $result = base64_encode($bin_str);
        }
        return $result;
    }

    /**
     * 获取二进制输出的加密key
     * @return string|null
     */
    private function getBinaryMaskKey()
    {
        //二进制输出时，对内容的加密串
        $mask_config = Config::get('dop_binary_mask');
        if (is_array($mask_config)) {
            $server_info = Application::getInstance()->getServerInfo();
            $app_name = $server_info->getAppName();
            $page_name = $server_info->getPageName();
            $action_name = $server_info->getActionName();
            $full_key = $app_name . '/' . $page_name . '/' . $action_name;
            if (isset($mask_config[$full_key])) {
                return $mask_config[$full_key];
            }
            $page_key = $app_name . '/' . $page_name . '/*';
            if (isset($mask_config[$page_key])) {
                return $mask_config[$page_key];
            }
            $app_key = $app_name . '/*';
            if (isset($mask_config[$app_key])) {
                return $mask_config[$app_key];
            }
        }
        return null;
    }

    /**
     * 获取返回值类型
     * @return int
     */
    private function getOutputType()
    {
        //如果 是在header中指定二进制
        if (isset($_SERVER['HTTP_ACCEPT_BINARY']) && 'dop' === $_SERVER['HTTP_ACCEPT_BINARY']) {
            return self::TYPE_BINARY;
        }
        //在Request参数中指定
        if (isset($_REQUEST['RESPONSE_TYPE'])) {
            if ('binary' === $_REQUEST['RESPONSE_TYPE']) {
                return self::TYPE_BINARY;
            } elseif ('json' === $_REQUEST['RESPONSE_TYPE']) {
                return self::TYPE_JSON;
            }
        }
        //配置中指定
        $output_type_conf = Config::getString('response_type');
        if ('binary' === $output_type_conf) {
            return self::TYPE_BINARY;
        }
        return self::TYPE_JSON;
    }

    /**
     * 获取状态
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * 获取返回消息
     * @return string
     */
    public function getMessage()
    {
        if (null !== $this->message) {
            return $this->message;
        }
        switch ($this->status) {
            case self::STATUS_OK:
                return 'success';
            case self::STATUS_ACTION_NOT_FOUND:
                return 'action not found';
            case self::STATUS_PAGE_NOT_FOUND:
                return 'page not found';
            default:
                return 'unknown';
        }
    }

    /**
     * 获取 附加数据
     * @return null|array
     */
    public function getAppendData()
    {
        return $this->append_data;
    }
}
