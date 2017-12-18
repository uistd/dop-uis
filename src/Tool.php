<?php

namespace UiStd\Uis\Base;

use UiStd\Common\Config;
use UiStd\Tpl\Tpl;

/**
 * Class Tool
 * @package UiStd\Uis\Base
 */
abstract class Tool
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * @var array
     */
    private $tpl_data = [];

    /**
     * Tool constructor.
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        Config::add('ffan-tpl', array(
            'tpl_dir' => 'tool/views'
        ));
        header('Access-Control-Allow-Origin:*');
    }

    /**
     * 主执行函数
     */
    abstract public function action();

    /**
     * 模板显示
     * @param string $tpl
     */
    public function tpl($tpl)
    {
        Tpl::run($tpl, $this->tpl_data);
        exit(0);
    }

    /**
     * 设置模板变量
     * @param string $key
     * @param mixed $value
     */
    public function assign($key, $value)
    {
        $this->tpl_data[$key] = $value;
    }

    /**
     * 以json方式输出
     */
    public function json()
    {
        echo json_encode($this->tpl_data, JSON_UNESCAPED_UNICODE);
        exit(0);
    }
}
