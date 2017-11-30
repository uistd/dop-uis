<?php

namespace FFan\Uis\Work;

use FFan\Std\Common\Config;
use FFan\Std\Common\InvalidConfigException;
use FFan\Std\Logger\FileLogger;
use FFan\Std\Logger\LogHelper;
use FFan\Std\Logger\LogRouter;

/**
 * Class Manager
 * @package FFan\Uis\Work
 */
class Manager
{
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
     * Manager constructor.
     * @param string $app_name
     * @throws InvalidConfigException
     */
    public function __construct($app_name)
    {
        $this->initLogger();
        $this->parseMainConfig();
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
                $this->logger->error($each_conf .' 无法解析');
                continue;
            }
            $this->task_list[] = $tmp_task;
        }
    }

    /**
     * grep的标志
     * @param string $file
     * @param int $pid
     * @return string
     */
    private static function grepFlag($file, $pid)
    {
        $greg_flag = 'php ' . $file . '.php';
        if (isset($GLOBALS['APP_NAME'])) {
            $greg_flag .= ' ' . $GLOBALS['APP_NAME'];
        }
        if ($pid > 0) {
            $greg_flag .= ' ' . $pid;
        }
        return $greg_flag;
    }

    /**
     * 取得该任务在运行的进程数
     * @param string $name 任务文件名
     * @param int $pid 进程ID
     * @return int
     */
    public static function processCount($name, $pid = 0)
    {
        $greg_flag = self::grepFlag($name, $pid);
        $cmd = 'ps -efww | grep "' . $greg_flag . '"|grep -v grep|wc -l';
        exec($cmd, $out);
        $exec_num = isset($out[0]) ? $out[0] : 0;
        return (int)$exec_num;
    }

    /**
     * 给某个进程传递信号
     * @param string $file 任务文件名
     * @param int $pid 子进程ID
     * @param int $signal 信号
     * @return void
     */
    function processKill($file, $pid = -1, $signal = SIGTERM)
    {
        $grep_flag = self::grepFlag($file, $pid);
        $cmd = 'ps -efww | grep "' . $grep_flag . '"|grep -v grep|awk \'{ print $2 }\'|xargs --no-run-if-empty kill -' . $signal;
        exec($cmd);
    }

    /**
     * 初始化日志
     */
    private function initLogger()
    {
        new FileLogger('crontab/'. $this->app_name, 'main', 0, FileLogger::OPT_SPLIT_BY_DAY);
        $this->logger = LogHelper::getLogRouter();
    }
}