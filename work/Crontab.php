<?php

namespace FFan\Uis\Work;

use FFan\Std\Console\Debug;
use FFan\Std\Logger\FileLogger;
use FFan\Std\Logger\LogHelper;
use FFan\Std\Logger\LogRouter;

/**
 * Class Crontab 定时任务
 * @package FFan\Uis\Work
 */
class Crontab
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
        $this->initLogger();
        $process_count = Manager::processCount($this->crontabName());
        if ($process_count > 0) {
            $this->logMsg('exist. quit.');
            return;
        }
        $this->logMsg('start');
        try {
            $this->action();
        } catch (\Exception $exception) {
            Debug::recordException($exception);
        }
    }

    /**
     * 初始化日志
     */
    private function initLogger()
    {
        $this->logger = LogHelper::getLogRouter();
        $log_path = 'crontab/' . $this->app_name;
        new FileLogger($log_path, $this->crontabName());
    }

    /**
     * @return string
     */
    private function crontabName()
    {
        return basename(str_replace('\\', '/', static::class));
    }

    /**
     * 析构
     */
    public function __destruct()
    {
        $this->logMsg('exit');
    }

    /**
     * 运行
     */
    protected function action()
    {

    }

    /**
     * 记录日志
     * @param string $msg
     */
    protected function logMsg($msg)
    {
        $this->logger->info($msg);
    }
}
