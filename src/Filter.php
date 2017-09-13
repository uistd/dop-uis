<?php

namespace FFan\Dop\Uis;

/**
 * Class Filter 过滤器
 * @package FFan\Dop\Uis
 */
abstract class Filter
{
    /**
     * @var int 错误码
     */
    private $err_code = 0;

    /**
     * @var string 错误消息
     */
    private $err_msg;

    /**
     * Filter constructor.
     */
    public function __construct()
    {
        Application::getInstance()->addFilter($this);
    }

    /**
     * 执行filter
     * @param string $page
     * @param string $action
     */
    abstract public function call($page, $action);

    /**
     * 获取err_code
     * @return int
     */
    public function getErrCode()
    {
        return $this->err_code;
    }

    /**
     * 获取错误消息
     * @return string
     */
    public function getErrMsg()
    {
        return $this->err_msg;
    }

    /**
     * 设置错误
     * @param int $code
     * @param string $msg
     */
    public function setError($code, $msg = 'filter error')
    {
        $this->err_code = $code;
        $this->err_msg = $msg;
    }
}
