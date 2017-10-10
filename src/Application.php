<?php

namespace FFan\Dop\Uis;

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
     * @var View 视图对象
     */
    private $view;

    /**
     * Application constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        FFanConfig::init($config);
        //这一步保证MainLogger初始化
        FFan::getLogger();
        Debug::init();
        ob_start();
        if (null !== self::$instance) {
            throw new \RuntimeException('Application is a singleton class');
        }
        self::$instance = $this;
        $this->server_info = ServerHandler::getInstance();
        $this->app_name = $this->server_info->getAppName();
        define('APP_PATH', ROOT_PATH . 'apps/' . $this->app_name . '/');
        $this->response = new Response();
        $this->view = new View($this->response);
        $this->init();
        spl_autoload_register([$this, 'autoLoader']);
    }

    /**
     * 运行
     */
    public function run()
    {
        $event_mrg = EventManager::instance();
        try {
            $init_file = APP_PATH . 'init.php';
            if (is_file($init_file)) {
                /** @noinspection PhpIncludeInspection */
                require_once $init_file;
            }
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
        $this->view->view();
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
        $ns = $this->getAppNameSpace() . '\\Page\\';
        $class_name = $ns . $class_name;
        if (!class_exists($class_name)) {
            $this->response->setStatus(Response::STATUS_PAGE_NOT_FOUND);
            return;
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

        $action_args = $this->getActionArgs($u_page_name, $action_name);
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
        $mock_path = ROOT_PATH . 'protocol/plugin_mock/';
        $include_file = FFanUtils::joinFilePath($mock_path, 'include.php');
        if (!is_file($include_file)) {
            $this->response->setStatus(Response::STATUS_PAGE_NOT_FOUND, 'Mock plugin error');
            return;
        }
        /** @noinspection PhpIncludeInspection */
        require_once $include_file;
        $page_name = $this->server_info->getPageName();
        $u_page_name = FFanStr::camelName($page_name);
        $action_name = $this->server_info->getActionName();
        $this->getActionArgs($u_page_name, $action_name);
        if (Response::STATUS_OK !== $this->response->getStatus()) {
            return;
        }
        $u_app_name = FFanStr::camelName($this->app_name);
        $mock_class = '\\Protocol\\Plugin\\Mock\\' . $u_app_name . '\\Mock' . $u_app_name . $u_page_name;
        if (!class_exists($mock_class)) {
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
        $class_name = $action_name . 'Request';
        $dop_class = '\\Protocol\\' . FFanStr::camelName($this->app_name) . '\\' . $page_name . '\\' . $class_name;
        $action_args = null;
        if (!class_exists($dop_class)) {
            return null;
        }
        /** @var IRequest $request */
        $request = new $dop_class();
        //如果是Json post过来的数据
        if ($this->isJsonRequest()) {
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
     * 判断header里是否 指定是json请求
     * @return bool
     */
    private function isJsonRequest()
    {
        if (isset($_SERVER['HTTP_CONTENT_TYPE'])) {
            return strpos($_SERVER['HTTP_CONTENT_TYPE'], '/json') !== false || strpos($_SERVER['HTTP_CONTENT_TYPE'], '+json') !== false;
        }

        return false;
    }

    /**
     * 获取应用的主命名空间
     * @return string
     */
    private function getAppNameSpace()
    {
        if (null === $this->app_ns) {
            $u_app_name = FFanStr::camelName($this->app_name);
            $this->app_ns = 'Uis\\' . $u_app_name;
        }
        return $this->app_ns;
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
     * 获取视图对象
     * @return View
     */
    public function getView()
    {
        return $this->view;
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
        return self::$instance;
    }

    /**
     * 一些内置的自动加载
     * @param string $class_name
     */
    public function autoLoader($class_name)
    {
        //以Uis\App开始的
        $main_ns = $this->getAppNameSpace();
        if (0 !== strpos($class_name, $main_ns)) {
            return;
        }
        $sub_name = substr($class_name, strlen($main_ns) + 1);
        $path_name = str_replace('\\', '/', $sub_name);
        $file = FFanEnv::getRootPath() . 'apps/' . $this->app_name . '/' . $path_name . '.php';
        if (!is_file($file)) {
            return;
        }
        /** @noinspection PhpIncludeInspection */
        require_once $file;
    }
}
