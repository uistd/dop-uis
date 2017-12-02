<?php

namespace FFan\Uis\Work;

use FFan\Std\Console\Debug;
use FFan\Std\Logger\LogHelper;
use FFan\Std\Logger\LogRouter;

/**
 * Class Crontab 定时任务
 * @package FFan\Uis\Work
 */
abstract class Crontab
{
    /**
     * @var LogRouter
     */
    private $logger;

    /**
     * @var
     */
    private $app_name;

    /**
     * Crontab constructor.
     * @param string $app_name
     */
    public function __construct($app_name)
    {
        $this->app_name = $app_name;
        $this->logger = LogHelper::getLogRouter();
        $this->log('start');
        try {
            $this->action();
        } catch (\Exception $exception) {
            Debug::recordException($exception);
        }
    }

    /**
     * 析构
     */
    public function __destruct()
    {
        $this->log('exit');
    }

    /**
     * 运行
     */
    abstract public function action();

    /**
     * 记录日志
     * @param string $msg
     */
    protected function log($msg)
    {
        $this->logger->info($msg);
    }
}
