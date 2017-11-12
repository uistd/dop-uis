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
     * @var int 休眠时间
     */
    private $sleep_time;

    /**
     * @var LogRouter
     */
    private $logger;

    /**
     * @var string 日志目录
     */
    protected static $log_path = 'crontab';

    /**
     * @var bool 是否循环执行
     */
    protected $is_loop = false;

    /**
     * @var int 如果是循环执行，每次执行的间隔
     */
    protected $loop_sleep = 1000;

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
        if ($this->is_loop) {
            $this->loop();
        } else {
            try {
                $this->action();
            } catch (\Exception $exception) {
                Debug::recordException($exception);
            }
        }
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
    protected function action()
    {

    }

    /**
     * 主循环
     */
    private function loop()
    {
        while ($this->is_loop) {
            $start_time = microtime(true);
            try {
                $this->action();
            } catch (\Exception $exception) {
                Debug::recordException($exception);
            }
            $now_time = microtime(true);
            $cost_time = $now_time - $start_time;
            //转成 微秒
            $cost_time *= 1000;
            $sleep_time = $this->sleep_time - $cost_time;
            if ($sleep_time > 0) {
                usleep($sleep_time);
            }
        }
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
