<?php
namespace webapp;

require_once dirname(__FILE__) . '/core.php';
require_once dirname(__FILE__) . '/Annotation.php';	// because annotation evaluates before Annotation is autoloaded

class Controller
{
	protected $name;
	protected $app;
	protected $route;

	function __construct($name)
	{
		$this->name = $name;
	}

	public function index()
	{
		return 'index';
	}

	public function setApplication($app)
	{
		if (!is_a($app, 'webapp\Application'))
			throw new \Exception("not valid application");
		$this->app = $app;
		$this->parseAnnotations();
	}

	protected function parseAnnotations()
	{
		$klass = new \ReflectionAnnotatedClass(get_class($this));

		// 1. determine base route
		if ($klass->hasAnnotation('Route')) {
			$annotation = $klass->getAnnotation('Route');
			$route = new Route($annotation->value, 
				array(&$this, 'index'), $annotation->method || 'GET');
			$this->route = $route;
			$this->app->route($route);
		}

		// 2. determine method routes and decorators
		foreach ($klass->getMethods() as $method) {
			if ($method->hasAnnotation('BeforeRequest')) {
				$this->app->addBeforeRequest(array(&$this, $method->name));
			}

			if ($method->hasAnnotation('Route')) {
				$annotation = $method->getAnnotation('Route');
				$route = new Route(
					($this->route ? $this->route->getPattern() : '') . $annotation->value,
					array(&$this, $method->name), $annotation->method || 'GET');
				$this->app->route($route);
			}
		}
	}

	protected function redirect($path)
	{
		header('Location: ' . $path);
	}

	protected function render_template($tpl, $vars)
	{
		if (!$this->application)
			throw new Exception("application is not set");
		
		if (false !== strpos($tpl, ':')) {
			//each($name, $path) 
			$arr = explode(':', $tpl, 2);
			$name = $arr[0]; $path = $arr[1];
			$ctrl = $this->application->getController($name);
			$tpl = $ctrl->dirname() . '/' . $path;
		}
		$template = $this->app->templateEnv->load($tpl);
		return $template->render($vars);
	}

	protected function jsonify($var)
	{
		return json_encode($var); //, JSON_FORCE_OBJECT);
	}
}
?>
