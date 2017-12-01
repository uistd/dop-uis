<?php

namespace FFan\Uis\Work;

use FFan\Std\Common\Config;
use FFan\Std\Common\Env;
use FFan\Std\Common\InvalidConfigException;
use FFan\Std\Logger\FileLogger;
use FFan\Std\Logger\LogHelper;
use FFan\Std\Logger\LogLevel;
use FFan\Std\Logger\LogRouter;

/**
 * Class Manager
 * @package FFan\Uis\Work
 */
class Manager
{
    /**
     * 每次循环休息时间（1秒）
     */
    const SLEEP_TIME = 1000000;

    /**
     * @var LogRouter
     */
    private $logger;

    /**
     * @var string
     */
    private $app_name;

    /**
     * @var Task[]
     */
    private $task_list = array();

    /**
     * @var bool 继续的标志
     */
    private $continue_flag = true;

    /**
     * Manager constructor.
     * @param string $app_name
     * @throws InvalidConfigException
     */
    public function __construct($app_name)
    {
        $this->initLogger();
        $this->logger->info('Task manager start!');
        $this->parseMainConfig();
        pcntl_signal(SIGTERM, array($this, 'quitBySignal'));
        pcntl_signal(SIGQUIT, array($this, 'quitBySignal'));
        pcntl_signal(SIGINT, array($this, 'quitBySignal'));
        pcntl_signal(SIGHUP, array($this, 'quitBySignal'));
        pcntl_signal(SIGHUP, array($this, 'ignoreSignal'));
    }

    /**
     * 监听中止进程信号
     * @param int $signal
     */
    public function quitBySignal($signal)
    {
        $this->logger->info('Catch signal ' . $signal . ', quit.');
        $this->continue_flag = false;
    }

    /**
     * 忽略信号
     * @param int $signal
     */
    public function ignoreSignal($signal)
    {
        $this->logger->info('Catch signal ' . $signal . ', ignore.');
    }

    /**
     * 解析主配置
     */
    private function parseMainConfig()
    {
        $work_config = Config::get('ffan-work');
        if (!is_array($work_config)) {
            return;
        }
        foreach ($work_config as $each_conf) {
            $tmp_task = new Task($this->app_name);
            if (!$tmp_task->parse($each_conf)) {
                $this->logger->error($each_conf . ' 无法解析');
                continue;
            }
            $this->task_list[] = $tmp_task;
        }
    }

    /**
     * 主循环函数
     */
    public function loop()
    {
        $last_minute = 0;
        while ($this->continue_flag) {
            //监听信号
            pcntl_signal_dispatch();
            $sleep_time = self::SLEEP_TIME;
            $start_time = microtime(true);
            $this_minute = (int)floor(time() / 60);
            if ($this_minute !== $last_minute) {
                $last_minute = $this_minute;
                $now_time = microtime(true);
                $this->taskWakeUp();
                $cost_time = $now_time - $start_time;
                //转成 微秒
                $cost_time *= 1000;
                $sleep_time -= $cost_time;
            }
            //休眠时间
            usleep($sleep_time);
        }
        $this->logger->info('Task manager exit!');
    }

    /**
     * 唤醒进程
     */
    private function taskWakeUp()
    {
        foreach ($this->task_list as $task) {
            if ($task->isRunning()) {
                continue;
            }
            if (!$task->isWakeUp()) {
                continue;
            }
            $task->start();
        }
    }

    /**
     * 初始化日志
     */
    private function initLogger()
    {
        $log_level = 0xffff;
        if (Env::isProduct()) {
            $log_level ^= LogLevel::DEBUG;
        }
        new FileLogger('crontab/' . $this->app_name, 'main', $log_level, FileLogger::OPT_SPLIT_BY_DAY);
        $this->logger = LogHelper::getLogRouter();
    }
}
