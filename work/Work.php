<?php

namespace FFan\Uis\Work;

use FFan\Std\Console\Debug;
use FFan\Std\Logger\FileLogger;
use FFan\Std\Logger\LogHelper;
use FFan\Std\Logger\LogRouter;

/**
 * Class Work 任务进程
 * @package FFan\Uis\Work
 */
class Work
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
    protected static $log_path = 'work';

    /**
     * Work constructor.
     * @param int $sleep_time 休息时间 毫秒
     */
    public function __construct($sleep_time = 1000)
    {
        $process_count = Manager::processCount($this->workName());
        if ($process_count > 0) {
            $this->logMsg('exist. quit.');
            return;
        }
        $this->sleep_time = $sleep_time * 1000;
        $this->initLogger();
        $this->logMsg('start');
        $this->loop();
    }

    /**
     * 初始化日志
     */
    private function initLogger()
    {
        $this->logger = LogHelper::getLogRouter();
        $class_name = basename($this->workName());
        new FileLogger(self::$log_path, $class_name);
    }

    /**
     * @return string
     */
    private function workName()
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
     * 主循环
     */
    private function loop()
    {
        while ($this->isContinueLoop()) {
            $start_time = microtime(true);
            try {
                $this->action();
            } catch (\Exception $exception) {
                Debug::recordException($exception);
                $job_list = null;
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

    /**
     * 运行
     */
    protected function action()
    {

    }

    /**
     * 是否继续
     */
    protected function isContinueLoop()
    {
        return true;
    }
}
