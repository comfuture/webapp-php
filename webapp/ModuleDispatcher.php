<?php
namespace webapp;

/**
 * first level path as module
 */
class ModuleDispatcher extends Dispatcher
{
	public function dispatch()
	{
		if (null == $this->app)
			throw new \Exception("dispatcher requires valid Application. try Application::setDispatcher");
		$path = explode('/', $this->app->request->path);
		if (count($path) < 2)
			return;

		$ns = $path[1];
		$dir = $this->app->config['system.basedir'] . '/' . $ns;
		if (is_dir($dir) && is_file($file = $dir . '/Controller.php')) {
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
?>