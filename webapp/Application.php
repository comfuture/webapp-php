<?php
namespace webapp;

require_once dirname(__FILE__) . '/core.php';

class Application
{
	private $_config;
	private $dispatcher;
	private $controllers = array();
	private $routes = array();
	private $beforeRequest = array();
	private $templateEnv;
	private $_em;

	function __construct($config=null)
	{
		$this->_config = array(
		);
		if (null != $config) {
			$this->_config = array_merge($this->_config, $config);
		}
		$filesystemLoader = new \Twig_Loader_Filesystem('templates');
		$this->templateEnv = new \Twig_Environment($filesystemLoader, array(
			'cache' => isset($this->_config['template.cache.dir'])
				? $this->_config['template.cache.dir']
				: null
		));
		$this->templateEnv->addExtension(new WebApp_Twig_Extension());

		if (isset($this->_config['database'])) {
			// entity manager
			$ormconf = new \Doctrine\ORM\Configuration();
			if ($config['env'] == 'development') {
				$ormcache = new \Doctrine\Common\Cache\ArrayCache();
				$ormconf->setMetadataCacheImpl($ormcache);
				$ormconf->setQueryCacheImpl($ormcache);
				$ormconf->setAutoGenerateProxyClasses(true);
			} else {
				// TODO: available cache environment?
				$ormcache = new \Doctrine\Common\Cache\ArrayCache();
				$ormconf->setMetadataCacheImpl($ormcache);
				$ormconf->setQueryCacheImpl($ormcache);
				$ormconf->setAutoGenerateProxyClasses(false);
			}
			$driverImpl = $ormconf->newDefaultAnnotationDriver('model');
			$ormconf->setMetadataDriverImpl($driverImpl);
			$ormconf->setProxyDir('model/proxy');
			$ormconf->setProxyNamespace('model\proxy');

			$connection = $config['database'];

			$this->_em = \Doctrine\ORM\EntityManager::create($connection, $ormconf);
		}

		$this->request = new Request();
	}

	public function __get($key)
	{
		switch ($key) {
		case 'config': case 'em':
			return $this->{'_'.$key};
		}
	}

	public function run()
	{
		foreach ($this->beforeRequest as $handler) {
			call_user_func($handler);
		}
		foreach ($this->routes as $route) {
			$params = $route->test($this->request->path);
			if (false !== $params) {
				$response = $route->handle($params);
				if (is_string($response)) {
					echo $response;
				}
				return;
			}
		}
	}

	public function setDispatcher($dispatcher)
	{
		$this->dispatcher = $dispatcher;
		$dispatcher->setApplication($this);
		$dispatcher->dispatch();
	}

	public function addController($controller)
	{
		$controller->setApplication($this);
		array_push($this->controllers, $controller);
	}

	public function addBeforeRequest($handler)
	{
		$this->beforeRequest[] = $handler;
	}

	public function route($route, $handler=null, $method=null)
	{
		if (is_a($route, 'webapp\Route')) {
			if (null != $handler) {
				// TODO: implement Route::setHandler()
				//$route->setHandler($handler);
			}
			if (null != $method) {
				// TODO: implement Route::setMethod()
				//$route->setMethod($method);
			}
		} else if (is_string($route) && null != $handler) {
			$route = new Route($route, $handler, $method);
		} else {
			throw new \Exception('$route must be one of webapp\Route or string');
		}
		array_unshift($this->routes, $route);
		// TODO: sort?
	}
}
?>
