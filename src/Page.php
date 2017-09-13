<?php

namespace FFan\Dop\Uis;
use FFan\Std\Http\ApiResult;

/**
 * Class Page
 * @package FFan\Dop\Uis
 */
class Page
{
    /**
     * @var array 代码和消息映射关系
     */
    protected static $return_code;

    /**
     * @var Response
     */
    protected $response;

    /**
     * @var Application
     */
    protected $app;

    /**
     * Page constructor.
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->response = $app->getResponse();
        $this->app = $app;
    }

    /**
     * 中止代码执行
     * @param int $code
     * @throws ActionException
     */
    public function quit($code = 10000)
    {
        $msg = isset(static::$return_code[$code]) ? static::$return_code[$code] : '操作失败';
        throw new ActionException($msg, $code);
    }

    /**
     * 终止代码执行
     * @param ApiResult $result
     * @throws ActionException
     */
    public function response(ApiResult $result)
    {
        throw new ActionException($result->message, $result->status);
    }
}
