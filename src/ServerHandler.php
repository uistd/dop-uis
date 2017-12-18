<?php
namespace UiStd\Uis\Base;

use UiStd\Common\Str as UisStr;

/**
 * Class ServerHandler
 * @package UiStd\Uis\Base
 */
class ServerHandler
{

    /**
     * @var string App目录
     */
    private $app_name;

    /**
     * @var string 页面名称
     */
    private $page_name;

    /**
     * @var string 方法名
     */
    private $action_name;

    /**
     * @var string path_info
     */
    private $path_info;

    /**
     * @var self
     */
    private static $instance;

    public function __construct()
    {
        if (null !== self::$instance) {
            throw new \RuntimeException('ServerHandler is a singleton class');
        }
        self::$instance = $this;
        self::$instance->parse();
    }

    /**
     * 解析pathInfo
     * @return void
     */
    public function parse()
    {
        $route_path = $this->getPathInfo();
        $app_name = 'app';
        $page_name = 'index';
        $action_name = 'main';
        //如果路径为空
        if (strlen($route_path) <= 1) {
            $this->app_name = $app_name;
            $this->page_name = $page_name;
            return;
        }
        $path_arr = UisStr::split($route_path, '/');
        $count = count($path_arr);
        //如果只有1级，route_path就表示 app_name
        if (1 === $count) {
            $app_name = $path_arr[0];
        } //如果有2级，表示app和page
        elseif (2 === $count) {
            $app_name = $path_arr[0];
            $page_name = $path_arr[1];
        } //如果有更多级，表示app/page/action
        else{
            $app_name = $path_arr[0];
            $page_name = $path_arr[1];
            if (3 === $count) {
                $action_name = $path_arr[2];
            }//超过3级
            else {
                array_shift($path_arr);
                array_shift($path_arr);
                $action_name = join('_', $path_arr);
            }
        }
        $this->app_name = $app_name;
        $this->page_name = $page_name;
        $this->action_name = UisStr::camelName($action_name);
        Uis::debug('App:'. $app_name . ' Page:'. $this->page_name . ' Action:'. $this->action_name);
    }

    /**
     * 设置path_info
     * @param string $path_info
     */
    public function setPathInfo($path_info)
    {
        if (!is_string($path_info)) {
            throw new \InvalidArgumentException('Invalid path_info');
        }
        $path_info = trim($path_info, ' /');
        if ('' !== $path_info && !$this->isValidPath($path_info)) {
            throw new \InvalidArgumentException('Invalid path_info ' . $path_info);
        }
        $this->path_info = $path_info;
    }

    /**
     * 检查path是否满足要求
     * @param string $path_str
     * @return bool
     */
    private function isValidPath($path_str)
    {
        return 0 !== preg_match('/^[a-zA-Z_][a-zA-Z0-9_/]+$/', $path_str);
    }

    /**
     * 获取AppName
     * @return string
     */
    public function getAppName()
    {
        return $this->app_name;
    }

    /**
     * 获取page名称
     * @return string
     */
    public function getPageName()
    {
        return $this->page_name;
    }

    /**
     * 获取Action名称
     * @return string
     */
    public function getActionName()
    {
        return $this->action_name;
    }

    /**
     * 获取app/page/action的目录
     */
    public function getFullPath()
    {
        return $this->app_name . '/' . $this->page_name . '/' . $this->action_name;
    }

    /**
     * 获取scriptFile
     * @return string
     */
    public function getScriptFile()
    {
        $script_file = $_SERVER['SCRIPT_FILENAME'];
        $script_name = basename($script_file);
        if (basename($_SERVER['SCRIPT_NAME']) === $script_name) {
            $script_file = $_SERVER['SCRIPT_NAME'];
        } elseif (basename($_SERVER['PHP_SELF']) === $script_name) {
            $script_file = $_SERVER['PHP_SELF'];
        } elseif (isset($_SERVER['ORIG_SCRIPT_NAME']) && basename($_SERVER['ORIG_SCRIPT_NAME']) === $script_name) {
            $script_file = $_SERVER['ORIG_SCRIPT_NAME'];
        } elseif (($pos = strpos($_SERVER['PHP_SELF'], '/' . $script_name)) !== false) {
            $script_file = substr($_SERVER['SCRIPT_NAME'], 0, $pos) . '/' . $script_name;
        } elseif (!empty($_SERVER['DOCUMENT_ROOT']) && strpos($script_file, $_SERVER['DOCUMENT_ROOT']) === 0) {
            $script_file = str_replace('\\', '/', str_replace($_SERVER['DOCUMENT_ROOT'], '', $script_file));
        } else {
            throw new \RuntimeException('Unable to determine the entry script URL.');
        }
        return $script_file;
    }

    /**
     * 返回pathInfo部分
     * @return string
     */
    public function getPathInfo()
    {
        if (null !== $this->path_info) {
            return $this->path_info;
        }
        if (!empty($_SERVER['PATH_INFO'])) {
            $this->path_info = $_SERVER['PATH_INFO'];
        } else {
            $path_info = $this->getRequestUri();
            //如果有?只取path_info的部分
            if (false !== ($pos = strpos($path_info, '?'))) {
                $path_info = substr($path_info, 0, $pos);
            }
            $script_file = $this->getScriptFile();
            if (0 === strpos($path_info, $script_file)) {
                $path_info = substr($path_info, strlen($script_file));
            }
            $this->path_info = $path_info;
        }
        return $this->path_info;
    }

    /**
     * 获取请求uri
     * @return string
     */
    public function getRequestUri()
    {
        if (isset($_SERVER['REQUEST_URI'])) {
            $request_uri = $_SERVER['REQUEST_URI'];
            if ($request_uri !== '' && $request_uri[0] !== '/') {
                $request_uri = preg_replace('/^(http|https):\/\/[^\/]+/i', '', $request_uri);
            }
        } elseif (isset($_SERVER['HTTP_X_REWRITE_URL'])) { // IIS
            $request_uri = $_SERVER['HTTP_X_REWRITE_URL'];
        } elseif (isset($_SERVER['ORIG_PATH_INFO'])) { // IIS 5.0 CGI
            $request_uri = $_SERVER['ORIG_PATH_INFO'];
            if (!empty($_SERVER['QUERY_STRING'])) {
                $request_uri .= '?' . $_SERVER['QUERY_STRING'];
            }
        } else {
            $request_uri = '/';
        }
        return $request_uri;
    }

    /**
     * 获取请求的方式
     * @return string
     */
    public function getMethod()
    {
        if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            return strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
        } else {
            return isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
        }
    }

    /**
     * 获取实例
     * @return self
     */
    public static function getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new self;
        }
        return self::$instance;
    }
}
