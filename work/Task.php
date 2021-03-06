<?php

namespace UiStd\Uis\Work;

use UiStd\Common\Config;
use UiStd\Common\Str;
use UiStd\Common\Utils;
use UiStd\Logger\LogHelper;

class Task
{
    const GREP_TYPE_COUNT = 1;
    const GREP_TYPE_PID = 2;

    /**
     * @var CrontabConfig
     */
    private $time;

    /**
     * @var string
     */
    private $class_name;

    /**
     * @var string
     */
    private $args;

    /**
     * @var string
     */
    private $app_name;

    /**
     * @var string php命令
     */
    private $php_bin;

    /**
     * Task constructor.
     * @param string $app_name
     */
    public function __construct($app_name)
    {
        $this->app_name = $app_name;
        $this->php_bin = Config::get('php_bin_path', 'php');
    }

    /**
     * 解析配置
     * @param string $task_config
     * @return bool
     */
    public function parse($task_config)
    {
        $tmp_arr = Str::split($task_config, ' ');
        LogHelper::getLogRouter()->info(print_r($tmp_arr, true));
        if (count($tmp_arr) < 6) {
            return false;
        }
        //前面5项 表示 crontab
        $crontab_str = array_shift($tmp_arr) . ' ' . array_shift($tmp_arr) . ' ' . array_shift($tmp_arr) . ' ' . array_shift($tmp_arr) . ' ' . array_shift($tmp_arr);
        $crontab_config = new CrontabConfig($crontab_str);
        if (!$crontab_config->isValid()) {
            return false;
        }
        //类名
        $class_name = str_replace('.php', '', array_shift($tmp_arr));
        LogHelper::getLogRouter()->info($class_name);
        //是否是合法的类名
        if (!Str::isValidClassName($class_name)) {
            return false;
        }
        $run_arg = '';
        //如果还有，剩下的就是运行参数
        if (!empty($tmp_arr)) {
            $run_arg = join(' ', $tmp_arr);
        }
        $this->class_name = $class_name;
        $this->time = $crontab_config;
        $this->args = $run_arg;
        return true;
    }

    /**
     * 是否该执行了
     * @return bool
     */
    public function isWakeUp()
    {
        return $this->time->isWakeUp();
    }

    /**
     * 进程是否在执行
     * @return bool
     */
    public function isRunning()
    {
        $result = $this->grep();
        return !empty($result);
    }

    /**
     * 启动进程
     */
    public function start()
    {
        $cmd = $this->getCmd();
        $this->log('Start ' . $cmd);
        exec($cmd . ' >> /dev/null 2>&1 &');
    }

    /**
     * 向进程发送信息
     * @param int $signal
     */
    public function kill($signal = 15)
    {
        $process_id = $this->grep(self::GREP_TYPE_PID);
        if (empty($process_id)) {
            $this->log('Process not exist!');
            return;
        }
        $cmd = 'kill -' . $signal . ' ' . $process_id;
        $this->log('execute ' . $cmd);
        exec($cmd);
    }

    /**
     * 获取执行脚本
     * @return string
     */
    private function getCmd()
    {
        $file = Utils::fixWithRootPath('work') .'task.php';
        $cmd = $this->php_bin . ' ' . $file . ' ' . $this->app_name . ' ' . $this->class_name . '.php';
        if (!empty($this->args)) {
            $cmd .= ' ' . $this->args;
        }
        return $cmd;
    }

    /**
     * 获取grep执行结果
     * @param int $type
     * @return string
     */
    private function grep($type = self::GREP_TYPE_COUNT)
    {
        $cmd = $this->getCmd();
        $grep_cmd = 'ps -efww | grep "' . addcslashes($cmd, '"') . '"|grep -v grep|';
        if (self::GREP_TYPE_COUNT === $type) {
            $grep_cmd .= 'wc -l';
        } else {
            $grep_cmd .= 'awk \'{ print $2 }\'';
        }
        exec($grep_cmd, $out);
        return (isset($out[0])) ? $out[0] : '';
    }

    /**
     * 日志消息
     * @param string $msg
     */
    private function log($msg)
    {
        $logger = LogHelper::getLogRouter();
        $logger->info($msg);
    }
}
