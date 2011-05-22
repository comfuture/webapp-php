<?php
namespace webapp;

class YAMLDispatcher extends Dispatcher
{
	private $yaml;

	function __construct($file)
	{
		$this->yaml = \sfYaml::load($file);
	}

	public function dispatch()
	{
		if (!isset($this->yaml['handlers']))
			return;

		foreach ($this->yaml['handlers'] as $handler) {
			$pattern = '#^' . $handler['url'] . '#';
			$script = $this->app->config['system.basedir']
				. '/' . $handler['script'];
			$class = str_replace('/','\\', $handler['script']);
			$class = substr($class, 0, strpos($class, '.'));
			if (preg_match($pattern, $this->app->request->path) &&
				is_file($script)) {
				require_once $script;
				if (class_exists($class)) {
					$instance = new $class();
					$this->app->addController($instance);
				}
				break;
			}
		}
	}
}
