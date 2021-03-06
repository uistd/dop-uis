<?php

namespace UiStd\Uis\Base;

use UiStd\Common\Config as UisConfig;
use UiStd\Common\Env as UisEnv;
use UiStd\Common\Ip;
use UiStd\Common\Str as UisStr;
use UiStd\Console\Debug;
use UiStd\Event\EventDriver;
use UiStd\Event\EventManager;

/**
 * Class Application
 * @package UiStd\Uis\Base
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
     * @var string app name 的驼峰命名
     */
    private $camel_app_name;

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
     * @var View 视图对象
     */
    private $view;

    /**
     * Application constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        if (null !== self::$instance) {
            throw new \RuntimeException('Application is a singleton class');
        }
        UisConfig::init($config);
        Uis::getLogger();
        $this->init();
        $this->server_info = ServerHandler::getInstance();
        self::$instance = $this;
        $this->app_name = $this->server_info->getAppName();
        $this->camel_app_name = UisStr::camelName($this->app_name);
        $this->response = new Response();
        $this->view = new View($this->response);
        Debug::init();
    }

    /**
     * 运行
     */
    public function run()
    {
        $event_mrg = EventManager::instance();
        try {
            //加载 初始化文件
            $init_file = ROOT_PATH . 'apps/init.php';
            if (is_file($init_file)) {
                /** @noinspection PhpIncludeInspection */
                require_once $init_file;
            }
            //特殊请求， 仅内网生效
            if (isset($_GET['TOOL_REQUEST']) && Ip::isInternal(Ip::get())) {
                $this->toolAction();
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
        $charset = UisEnv::getCharset();
        ini_set('default_charset', $charset);
        $timezone = UisEnv::getTimezone();
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
        $u_page_name = UisStr::camelName($page_name);
        $class_name = $u_page_name . 'Page';
        $ns = 'Uis\Page\\' . $this->camel_app_name . '\\';
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
        call_user_func(array($page_obj, $call_func), $action_args);
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
        $dop_class = '\Uis\Protocol\\' . $this->camel_app_name . '\\' . $page_name . '\\' . $class_name;
        $action_args = null;
        if (!class_exists($dop_class)) {
            return null;
        }
        /** @var IRequest $request */
        $request = new $dop_class();
        //如果是Json post过来的数据
        if ($this->isJsonRequest()) {
            $tmp_post = json_decode(file_get_contents("php://input"), true);
            if (JSON_ERROR_NONE === json_last_error() && is_array($tmp_post)) {
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
     * 获取appName(驼峰命名)
     * @return string
     */
    public function getAppCamelName()
    {
        return $this->camel_app_name;
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
     * 内置工具加载
     */
    private function toolAction()
    {
        $tool = strtolower($_GET['TOOL_REQUEST']);
        $tool_class_name = '\Uis\Tool\\' . UisStr::camelName($tool) . 'Tool';
        if (!class_exists($tool_class_name)) {
            $this->response->setStatus(500, 'tool not found');
            return;
        }
        $tool_obj = new $tool_class_name($this);
        if ($tool_obj instanceof Tool) {
            $tool_obj->action();
        } else {
            $this->response->setStatus(500, $tool_class_name . ' is not instance of Tool');
        }
    }

    /**
     * 获取实例
     * @return Application
     */
    public static function getInstance()
    {
        return self::$instance;
    }
}
