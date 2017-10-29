<?php

namespace FFan\Dop\Uis;

/**
 * Class Tool
 * @package FFan\Dop\Uis
 */
abstract class Tool
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * Tool constructor.
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * 主执行函数
     */
    abstract function action();
}
