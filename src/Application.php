<?php

namespace FFan\Dop\Uis;

use ffan\dop\AutoLoader;
use FFan\Std\Common\Config as FFanConfig;
use FFan\Std\Common\Env as FFanEnv;
use FFan\Std\Common\Utils as FFanUtils;
use FFan\Std\Common\Str as FFanStr;
use FFan\Std\Console\Debug;
use FFan\Std\Event\EventDriver;
use FFan\Std\Event\EventManager;

/**
 * Class Application
 * @package FFan\Dop\Uis
 */
class Application
{
    /**
     * @var ServerHandler
     */
    private $server_info;

    /**
     * @var string app name
     */
    private $app_name;

    /**
     * @var Response 输出类
     */
    private $response;

    /**
     * @var ErrorHandler
     */
    private $error_handler;

    /**
     * @var self
     */
    private static $instance;

    /**
     * @var Filter[] 过滤器
     */
    private $filter_list;

    /**
     * @var string 应用的主命名空间
     */
    private $app_ns;

    /**
     * Application constructor.
     */
    public function __construct()
    {
        if (!defined('APP_PATH')) {
            define('APP_PATH', FFanEnv::getRootPath());
        }

        //这一步保证MainLogger初始化
        FFan::getLogger();
        Debug::init();
        ob_start();
        if (null !== self::$instance) {
            throw new \RuntimeException('Application is a singleton class');
        }
        self::$instance = $this;
        $this->server_info = ServerHandler::getInstance();
        $this->response = new Response();
        $this->init();
        $this->app_name = $this->server_info->getAppName();
    }

    /**
     * 初始化一个app，包括私有的config
     */
    private function initAppConfig()
    {
        //加载APP私有配置
        $config_file = APP_PATH . 'config.php';
        if (is_file($config_file)) {
            /** @noinspection PhpIncludeInspection */
            $app_config = require($config_file);
            FFanConfig::addArray($app_config);
        }
        $init_file = APP_PATH . 'init.php';
        if (is_file($init_file)) {
            /** @noinspection PhpIncludeInspection */
            require_once $init_file;
        }
    }

    /**
     * 运行
     */
    public function run()
    {
        $event_mrg = EventManager::instance();
        try {
            $this->initAppConfig();
            //内置mock处理 (生产环境不支持 mock)
            if ($this->server_info->isMockAction() && !FFanEnv::isProduct()) {
                $this->mockAction();
            } else {
                $this->actionDispatch();
                $event_mrg->trigger(EventDriver::EVENT_COMMIT);
            }
        } catch (ActionException $action_err) {
            $event_mrg->trigger(EventDriver::EVENT_ROLLBACK);
            $code = $action_err->getCode();
            $message = $action_err->getMessage();
            $this->response->setStatus($code, $message);
        } catch (\Exception $exception) {
            $event_mrg->trigger(EventDriver::EVENT_ROLLBACK);
            $this->error_handler->handleException($exception);
        }
        $this->renderView();
    }

    /**
     * view阶段
     */
    public function renderView()
    {
        $view = new View($this->response);
        $view->view();
        exit(0);
    }

    /**
     * 初始化
     */
    private function init()
    {
        if (!isset($config['extension'])) {
            $config['extension'] = [];
        }
        $charset = FFanEnv::getCharset();
        ini_set('default_charset', $charset);
        $timezone = FFanEnv::getTimezone();
        date_default_timezone_set($timezone);
        $this->error_handler = new ErrorHandler($this);
        $this->error_handler->register();
    }

    /**
     * 派发请求
     */
    public function actionDispatch()
    {
        $page_name = $this->server_info->getPageName();
        $action_name = $this->server_info->getActionName();
        $u_page_name = FFanStr::camelName($page_name);
        $class_name = $u_page_name . 'Page';
        if (!$this->requirePageFile($class_name)) {
            return;
        }
        //sdk dop
        $sdk_dop_file = APP_PATH . 'sdk/dop.php';
        if (is_file($sdk_dop_file)) {
            /** @noinspection PhpIncludeInspection */
            require_once $sdk_dop_file;
        }
        $page_obj = new $class_name($this);
        $call_func = 'action' . $action_name;
        if (!method_exists($page_obj, $call_func)) {
            $this->response->setStatus(Response::STATUS_ACTION_NOT_FOUND);
            return;
        }

        //过滤器
        if (null !== $this->filter_list) {
            /** @var Filter $filter */
            foreach ($this->filter_list as $filter) {
                $filter->call($u_page_name, $action_name);
                $err_code = $filter->getErrCode();
                if (0 !== $err_code) {
                    throw new ActionException($filter->getErrMsg(), $err_code);
                }
            }
        }

        $action_args = $this->getActionArgs($page_name, $action_name);
        //在获取参数的时候,参数 验证不通过
        if (null === $action_args && Response::STATUS_OK !== $this->response->getStatus()) {
            return;
        }

        //将get参数  和 post 改名，不允许直接调用
        if (!empty($_GET)) {
            $GLOBALS['__GET_'] = $_GET;
            $_GET = array();
        }
        if (!empty($_POST)) {
            $GLOBALS['__POST_'] = $_GET;
            $_POST = array();
        }
        call_user_func(array($page_obj, $call_func), $action_args);
    }

    /**
     * mock方法
     */
    private function mockAction()
    {
        $mock_path = APP_PATH . 'protocol/plugin_mock/';
        $include_file = FFanUtils::joinFilePath($mock_path, 'include.php');
        if (!is_file($include_file)) {
            $this->response->setStatus(Response::STATUS_PAGE_NOT_FOUND, 'Mock plugin error');
            return;
        }
        /** @noinspection PhpIncludeInspection */
        require_once $include_file;
        $u_app_name = FFanStr::camelName($this->app_name);
        $page_name = $this->server_info->getPageName();
        $u_page_name = FFanStr::camelName($page_name);
        $action_name = $this->server_info->getActionName();
        $class_name = $u_app_name . $u_page_name . 'Page';
        $this->requirePageFile($class_name, false);
        $this->getActionArgs($page_name, $action_name);
        if (Response::STATUS_OK !== $this->response->getStatus()) {
            return;
        }
        $ns = $this->getAppNameSpace();
        $mock_class = $ns . '\\plugin\\mock\\Mock' . $u_app_name . $u_page_name;
        if (!AutoLoader::dopExist($mock_class)) {
            $this->response->setStatus(Response::STATUS_PAGE_NOT_FOUND, 'Mock class ' . $mock_class . ' not found');
            return;
        }
        $method = 'mock' . $action_name . 'Response';
        $ref = new \ReflectionClass($mock_class);
        if (!$ref->hasMethod($method)) {
            $this->response->setStatus(Response::STATUS_PAGE_NOT_FOUND, 'Mock action ' . $mock_class . '::' . $method . ' not found');
            return;
        }
        /** @var IResponse $data */
        $data = call_user_func(array($mock_class, $method));
        $this->response->setResponse($data);
    }

    /**
     * 实例化参数为对象
     * @param string $page_name
     * @param string $action_name
     * @return IRequest|null
     */
    private function getActionArgs($page_name, $action_name)
    {
        //dop protocol 文件是强制加载的
        /** @noinspection PhpIncludeInspection */
        require_once APP_PATH . 'protocol/dop.php';
        $class_name = $action_name . 'Request';
        $ns = $this->getAppNameSpace();
        $dop_class = $ns . '\\' . $page_name . '\\' . $class_name;
        $action_args = null;
        if (!AutoLoader::dopExist($dop_class)) {
            return null;
        }
        /** @var IRequest $request */
        $request = new $dop_class();
        //如果是Json post过来的数据
        if (isset($_SERVER['HTTP_CONTENT_TYPE']) && 'application/json' === $_SERVER['HTTP_CONTENT_TYPE']) {
            $tmp_post = json_decode(file_get_contents("php://input"), true);
            if (JSON_ERROR_NONE === json_last_error()) {
                $_POST = $tmp_post;
            }
        }
        $request->arrayUnpack($_GET + $_POST);
        //数据有效性验证
        if (!$request->validateCheck()) {
            $this->response->setStatus(Response::STATUS_PARAM_INVALID, $request->getValidateErrorMsg());
            return null;
        }
        return $request;
    }

    /**
     * 获取应用的主命名空间
     * @return string
     */
    private function getAppNameSpace()
    {
        if (null === $this->app_ns) {
            $this->app_ns = FFanConfig::getString('app_namespace');
            if (null === $this->app_ns) {
                $this->app_ns = 'FFan\Dop';
            }
        }
        return $this->app_ns;
    }

    /**
     * 加载 page 类(controller)
     * @param string $file_name 类名
     * @param bool $set_error 如果类不存在,是否要set error
     * @return bool
     */
    private function requirePageFile($file_name, $set_error = true)
    {
        $page_file = APP_PATH . 'page/' . $file_name . '.php';
        //如果没有文件
        if (!is_file($page_file)) {
            FFan::debug('Page file:' . $page_file . ' not found');
            if ($set_error) {
                $this->response->setStatus(Response::STATUS_PAGE_NOT_FOUND);
            }
            return false;
        }
        /** @noinspection PhpIncludeInspection */
        require_once $page_file;
        return true;
    }

    /**
     * 获取pathInfo
     * @return ServerHandler
     */
    public function getServerInfo()
    {
        return $this->server_info;
    }

    /**
     * 获取一个数据返回
     * @return Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * 获取appName
     * @return string
     */
    public function getAppName()
    {
        return $this->app_name;
    }

    /**
     * 增加一个过滤器
     * @param Filter $filter
     */
    public function addFilter(Filter $filter)
    {
        $this->filter_list[] = $filter;
    }

    /**
     * 获取实例
     * @return Application
     */
    public static function getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
