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
     * @var bool 是否是循环模式
     */
    private $is_loop = false;

    /**
     * @var int 每次循环后sleep时间（仅 循环模式 有效）[毫秒]
     */
    private $sleep_time = 1000;

    /**
     * Crontab constructor.
     * @param string $app_name
     */
    public function __construct($app_name)
    {
        $this->app_name = $app_name;
        $this->logger = LogHelper::getLogRouter();
        $this->log('start');
    }

    /**
     * init
     */
    abstract public function init();

    /**
     * 记录日志
     * @param string $msg
     */
    protected function log($msg)
    {
        $this->logger->info($msg);
    }

    /**
     * 执行方法
     */
    protected function action()
    {

    }

    /**
     * 设置进程loop方式执行
     * @param int $loop_sleep
     */
    protected function setLoop($loop_sleep)
    {
        $loop_sleep = (int)$loop_sleep;
        //至少休息1毫秒
        if ($loop_sleep < 1) {
            $loop_sleep = 1;
        }
        $this->log('Set loop '. $loop_sleep .'ms');
        //转成微秒
        $this->sleep_time = $loop_sleep * 1000;
        $this->is_loop = true;
        pcntl_signal(SIGTERM, array($this, 'quitBySignal'));
        pcntl_signal(SIGINT, array($this, 'quitBySignal'));
        $this->loop();
    }

    /**
     * 监听中止进程信号
     * @param int $signal
     */
    public function quitBySignal($signal)
    {
        $this->logger->info('Catch signal ' . $signal . ', quit.');
        $this->is_loop = false;
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
            //监听信号
            pcntl_signal_dispatch();
            if ($this->is_loop && $sleep_time > 0) {
                usleep($sleep_time);
            }
        }
    }
}
