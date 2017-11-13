<?php

namespace FFan\Dop\Uis;

use FFan\Std\Common\Config;
use FFan\Std\Common\InvalidConfigException;
use FFan\Std\Common\Str;

/**
 * Class ActivityManager 活动管理器
 * @package FFan\Dop\Uis
 */
class ActivityManager
{
    /**
     * @var callable[]
     */
    private $activity_event = [];

    /**
     * @var Activity[]
     */
    private $activity_list;

    /**
     * @var array 配置
     */
    private $activity_conf;

    /**
     * @var bool 是否初化完成
     */
    private $is_init = false;

    /**
     * @var self
     */
    private static $instance;

    /**
     * Activity constructor.
     */
    public function __construct()
    {
        if (null !== self::$instance) {
            throw new \RuntimeException('ActivityManager is a singleton class');
        }
        self::$instance = $this;
    }

    /**
     * 获取总配置
     * @return array
     */
    private function getConfig()
    {
        if (null !== $this->activity_conf) {
            return $this->activity_conf;
        }
        $config_arr = Config::get('uis-activity');
        if (!is_array($config_arr)) {
            $config_arr = array();
        }
        $new_conf = array();
        $now = time();
        foreach ($config_arr as $name => $each_conf) {
            $start_time = 0;
            $end_time = 0;
            $is_active = true;
            //如果指定了开始时间
            if (isset($each_conf['start_time'])) {
                $start_time = strtotime($each_conf['start_time']);
            }
            if (isset($each_conf['end_time'])) {
                $end_time = strtotime($each_conf['end_time']);
            }
            $each_conf['start_time'] = $start_time;
            $each_conf['end_time'] = $end_time;
            if ($now < $start_time) {
                $is_active = false;
            }
            if ($end_time > 0 && $now > $end_time) {
                $is_active = false;
            }
            $each_conf['_IS_ACTIVE_'] = $is_active;
            $new_conf[Str::camelName($name)] = $each_conf;
        }
        $this->activity_conf = $new_conf;
        return $this->activity_conf;
    }

    /**
     * 配置初始化
     */
    private function init()
    {
        if ($this->is_init) {
            return;
        }
        $this->is_init = true;
        $config_arr = $this->getConfig();
        foreach ($config_arr as $name => $each_conf) {
            $u_name = Str::camelName($name);
            //已经初始化过了
            if (isset($this->activity_list[$u_name])) {
                continue;
            }
            //不在活动时间
            if (!$each_conf['_IS_ACTIVE_']) {
                continue;
            }
            $this->getActiveInstance($name);
        }
        $this->activity_conf = $config_arr;
    }

    /**
     * 数据
     * @param string $event
     * @param mixed $data
     */
    public function trigger($event, $data)
    {
        $this->init();
        if (!isset($this->activity_event[$event])) {
            return;
        }
        foreach ($this->activity_event[$event] as $call) {
            call_user_func($call, $data);
        }
    }

    /**
     * 设置事件监听
     * @param string $event
     * @param callable $callback
     */
    public function attach($event, callable $callback)
    {
        if (!isset($this->activity_event[$event])) {
            $this->activity_event[$event] = array();
        } else {
            //检查相同回调相同事件多次设置
            foreach ($this->activity_event[$event] as $call) {
                if ($callback === $call) {
                    return;
                }
            }
        }
        $this->activity_event[$event][] = $callback;
    }

    /**
     * 获取一个实例
     * @param string $name
     * @return Activity|null
     */
    public function getActiveInstance($name)
    {
        $u_name = Str::camelName($name);
        if (!isset($this->activity_list[$u_name])) {
            $this->initActiveInstance($name);
        }
        return isset($this->activity_list[$u_name]) ? $this->activity_list[$u_name] : null;
    }

    /**
     * 初始化活动实例
     * @param string $name
     * @throws InvalidConfigException
     */
    private function initActiveInstance($name)
    {
        $config_arr = $this->getConfig();
        $u_name = Str::camelName($name);
        if (!is_array($config_arr[$u_name])) {
            $config_arr[$u_name] = array();
        }
        $app_name = Str::camelName(Application::getInstance()->getAppName());
        $each_conf = $config_arr[$u_name];
        $class_name = '\\Uis\\' . $app_name . '\\Activity\\' . $u_name.'Activity';
        if (!class_exists($class_name)) {
            throw new InvalidConfigException('uis-activity:' . $name . ' class not found');
        }
        /** @var Activity $active */
        $active = new $class_name($name, $each_conf);
        //如果 活动在生效中
        if ($this->isActive($u_name)) {
            //事件监听
            $active->attach();
        }
        $this->activity_list[$u_name] = $active;
    }

    /**
     * 某个活动是否还在继续
     * @param string $name
     * @return bool
     */
    public function isActive($name)
    {
        $config = $this->getConfig();
        $name = Str::camelName($name);
        if (!isset($config[$name])) {
            return false;
        }
        return $config[$name]['_IS_ACTIVE_'];
    }

    /**
     * 获取实例
     * @return ActivityManager
     */
    public static function getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}