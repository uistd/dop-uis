<?php

namespace UiStd\Uis\Base;

use UiStd\Console\Debug;
use UiStd\Common\Env;
use UiStd\Event\EventManager;

/**
 * Class ErrorHandle
 * @package UiStd\Uis\Base
 */
class ErrorHandler
{
    /**
     * @var Application
     */
    protected $application;

    /**
     * @var string 申请内存，如果发生内存不足导致的Fatal error时，可释放内存显示错误
     */
    private $reserve_mem;

    /**
     * @var array fatal error
     */
    private static $fatal_error_map = array(
        E_ERROR => true,
        E_PARSE => true,
        E_CORE_ERROR => true,
        E_CORE_WARNING => true,
        E_COMPILE_ERROR => true,
        E_COMPILE_WARNING => true,
        E_RECOVERABLE_ERROR => true,
        E_USER_ERROR => true,
    );

    /**
     * ErrorHandler constructor.
     * @param Application $application
     */
    public function __construct(Application $application)
    {
        $this->application = $application;
        //分配16K内存，避免处理 内存 不足错误时，没有足够内存
        $this->reserve_mem = str_repeat('********************************', 512);
    }

    /**
     * 注册错误处理方式
     */
    public function register()
    {
        //错误显示关闭，交给handleError函数处理
        ini_set('display_errors', false);
        //报告所有的错误
        error_reporting(E_ALL);
        //未被try起来的exception处理
        set_exception_handler([$this, 'handleException']);
        //php代码级错处处理
        set_error_handler([$this, 'handleError']);
        //fatal error处理
        EventManager::instance()->attach(EventManager::SHUTDOWN_EVENT, [$this, 'handleFatalError'], 10000);
    }

    /**
     * 处理未被catch的异常
     * @param \Throwable $exception
     */
    public function handleException($exception)
    {
        //重置，避免在处理的过程中又发生异常而死循环
        restore_exception_handler();
        try {
            $error_content = Debug::recordException($exception);
            $this->displayException($error_content);
        } catch (\Throwable $except) {
            $this->finalErrorHandle($except, $exception->getMessage());
        }
    }

    /**
     * 如果处理错误的时候又出错，最终回调
     * @param \Throwable $except
     * @param string $last_error_msg 之前的错误消息
     */
    private function finalErrorHandle(\Throwable $except, $last_error_msg = '')
    {
        $msg = 'An Error occurred while handling another error:' . PHP_EOL;
        $msg .= '[' . $except->getCode() . ']' . $except->getMessage() . PHP_EOL;
        $msg .= $except->getFile() . ' line:' . $except->getLine();
        $msg .= $last_error_msg;
        error_log($msg);
    }

    /**
     * 处理 PHP 执行错误.
     * @param integer $code 错误级别
     * @param string $message 错误消息.
     * @param string $file 错误文件.
     * @param integer $line 错误行号.
     * @return boolean
     */
    public function handleError($code, $message, $file, $line)
    {
        //重置，避免在处理的过程中又发生错误而死循环
        restore_error_handler();
        //记录错误信息
        Debug::recordError($code, $message, $file, $line);
        set_error_handler([$this, 'handleError']);
        return false;
    }

    /**
     * 处理Fatal error
     */
    public function handleFatalError()
    {
        unset($this->reserve_mem);
        $error = error_get_last();
        if (!isset($error['type']) || !isset(self::$fatal_error_map[$error['type']])) {
            return;
        }
        Uis::getLogger()->emergency('PHP FATAL ERROR');
        $this->handleError($error['type'], $error['message'], $error['file'], $error['line']);
        $this->displayException('Fatal error handler');
    }

    /**
     * 显示异常.
     * @param string $content 详细内容
     */
    protected function displayException($content)
    {
        if (!Env::isProduct()) {
            echo $content;
        }
        $response = $this->application->getResponse();
        $response->setStatus(Response::STATUS_INTERNAL_ERROR, 'Server internal error');
        //哪怕出错了， 也要返回200
        http_response_code(200);
        $this->application->renderView();
    }
}
