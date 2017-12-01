<?php

namespace FFan\Uis\Work;

use FFan\Std\Common\Config;
use FFan\Std\Common\Str;
use FFan\Std\Logger\LogHelper;

class Task
{
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
     * @var int 进程ID
     */
    private $process_id = 0;

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
        if (count($tmp_arr) < 6) {
            return false;
        }
        //前面5项 表示 crontab
        $crontab_str = array_shift($tmp_arr) . ' ' . array_shift($tmp_arr) . ' ' . array_shift($tmp_arr) . ' ' . array_shift($tmp_arr) . array_shift($tmp_arr);
        $crontab_config = new CrontabConfig($crontab_str);
        if (!$crontab_config->isValid()) {
            return false;
        }
        //类名
        $class_name = str_replace('.php', ' ', array_shift($tmp_arr));
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
        if (0 === $this->process_id) {
            return false;
        }
        $cmd = 'kill -0 '. $this->process_id;
        exec($cmd, $out);
        //没有输出, 表示进程存在
        if (empty($out[0])) {
            return true;
        }
        $this->process_id = 0;
        return false;
    }

    /**
     * 启动进程
     */
    public function start()
    {
        $logger = LogHelper::getLogRouter();
        $cmd = $this->getCmd();
        $logger->info('Start '. $cmd);
        exec($cmd .' >> /dev/null 2>&1 &');
        $process_cmd = 'ps -efww | grep "' . addcslashes($cmd, '"') . '"|grep -v grep|awk \'{ print $2 }\'';
        exec($process_cmd, $out);
        $this->process_id = isset($out[0]) ? $out[0] : 0;
        $logger->info('done, process_id ', $this->process_id);
    }

    /**
     * 向进程发送信息
     * @param int $signal
     */
    public function kill($signal = 15)
    {

    }

    /**
     * 获取执行脚本
     */
    private function getCmd()
    {
        $cmd = $this->php_bin.' task.php '. $this->app_name .' '. $this->class_name;
        if (!empty($this->args)) {
            $cmd .= ' '. $this->args;
        }
        $cmd .= ' fin';
        return $cmd;
    }
}
