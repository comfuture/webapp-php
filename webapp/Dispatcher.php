<?php
namespace webapp;

require_once dirname(__FILE__) . '/core.php';

interface IDispatcher
{
	public function dispatch();
	public function setApplication(Application $app);
}

abstract class Dispatcher implements IDispatcher
{
	protected $app;

	public function __construct()
	{
	}

	public function dispatch()
	{
		return function() {};
	}

	public function setApplication(Application $app)
	{
		$this->app = $app;
	}
}

/**
 * first level path as controller
 */
class DirectoryDispatcher extends Dispatcher
{
	public function dispatch()
	{
		if (null == $this->app)
			throw new \Exception("dispatcher requires valid Application.\ntry Application::setDispatcher");
		$path = explode('/', $this->app->request->path);
		if (count($path) < 2)
			return;

		$ns = $path[1];
		$dir = $this->app->config['system.basedir'] . '/' . $ns;
		if (is_dir($dir) && is_file($file = $dir . '/controller.php')) {
			require_once $file;
			$klass = $ns . '\Controller';
			$instance = new $klass();
			$this->app->addController($instance);

			// TODO: implement Controller::resolve() method
			//return $instance->resolve($app->request->path);
		}
		//return function() {};
	}
}

class ClassDispatcher extends Dispatcher
{
	public function dispatch()
	{
		try {
			$path = explode('/', $app->request->path);
			if (count($path) < 3)
				return;

			$klass = $path[1];
			$instance = new $klass;
			return array(&$instance, $path[2]);

		} catch (Exception $e) {
		}
		return function() {};
	}
}

?>
