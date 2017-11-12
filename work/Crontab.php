<?php

namespace FFan\Uis\Work;

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
     * @var string 日志目录
     */
    protected static $log_path = 'crontab';

    /**
     * Crontab constructor.
     */
    public function __construct()
    {
        $this->initLogger();
        $process_count = Manager::processCount($this->crontabName());
        if ($process_count > 0) {
            $this->logMsg('exist. quit.');
            return;
        }
        $this->logMsg('start');
    }

    /**
     * 初始化日志
     */
    private function initLogger()
    {
        $this->logger = LogHelper::getLogRouter();

        new FileLogger(self::$log_path, $this->crontabName());
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
    public function action()
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
