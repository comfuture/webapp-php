<?php
namespace webapp;

require_once dirname(__FILE__) . '/core.php';
require_once dirname(__FILE__) . '/Annotation.php';	// because annotation evaluates before Annotation is autoloaded

class Controller
{
	protected $name;
	protected $app;
	protected $route;
	protected $request;

	function __construct($name=null)
	{
		if (!$name)
			$name = get_class();
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
		$this->request = $app->request;
		$this->parseAnnotations();
	}

	protected function parseAnnotations()
	{
		$klass = new \ReflectionAnnotatedClass(get_class($this));

		// 1. determine base route
		if ($klass->hasAnnotation('Route')) {
			$annotation = $klass->getAnnotation('Route');
			if (!$annotation->methods)
				$annotation->methods = array('GET');
			$route = new Route($annotation->value, 
				array(&$this, 'index'), $annotation->methods);
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
				if (!$annotation->methods)
					$annotation->methods = array('GET');
				$route = new Route(
					($this->route ? $this->route->getPattern() : '') . $annotation->value,
					array(&$this, $method->name), $annotation->methods);
				$this->app->route($route);
			}
		}
	}

	protected function redirect($path, $code=302)
	{
		$response = new Response();
		$response->headers[] = array('Location: ' . $path, true, $code);
		return $response;
	}

	protected function render_template($tpl, $vars=null)
	{
		return $this->app->render_template($tpl, $vars);
	}

	protected function jsonify($var)
	{
		return json_encode($var); //, JSON_FORCE_OBJECT);
	}
}
?>
