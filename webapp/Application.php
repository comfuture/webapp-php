<?php
namespace webapp;

require_once dirname(__FILE__) . '/core.php';

class Application
{
	private $config;
	private $dispatcher;
	private $controllers = array();
	private $routes = array();
	private $beforeRequest = array();
	private $templateEnv;
	private $cache;
	private $em;

	function __construct($config=null)
	{
		$this->config = array(
		);
		if (null != $config) {
			$this->config = array_merge($this->config, $config);
		}

		if (isset($this->config['system.template'])) {
			$templateLoader = new WebApp_Twig_Loader(
				$this->config['template.dir']);
			$this->templateEnv = new \Twig_Environment($templateLoader, array(
				'cache' => isset($this->config['template.cache.dir'])
					? $this->config['template.cache.dir']
					: null
			));
			$this->templateEnv->addExtension(new WebApp_Twig_Extension());
		}

		$_cache = $this->config['system.cache'];
		if (isset($_cache)) {
			$cacheEngins = array(
				'apc' => 'ApcCache',
				'memcache' => 'MemcacheCache',
				'xcache' => 'XcacheCache',
				'array' => 'ArrayCache'
			);
			if (in_array($_cache, $cacheEngines)) {
				$cacheEngine = '\Doctrine\Common\Cache\\' .
					$cacheEngines[$_cache];
				$this->cache = new $cacheEngine();
				if ($_cache == 'memcache' &&
						$_memcache = $this->cache['system.cache.memcache']) {
					$memcache = new \Memcache($_memcache['host'],
						$_memcache['port']);
				}
			}
		}

		if (isset($this->config['database'])) {
			// entity manager
			$ormconf = new \Doctrine\ORM\Configuration();
			if ($this->config['env'] == 'development') {
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

			$connection = $this->config['database'];

			$this->em = \Doctrine\ORM\EntityManager::create($connection, $ormconf);
		}

		$this->request = new Request();
	}

	public function __get($key)
	{
		switch ($key) {
		case 'config': case 'em': case 'cache': case 'templateEnv':
			return $this->{$key};
		}
	}

	public function run()
	{
		foreach ($this->beforeRequest as $handler) {
			call_user_func($handler);
		}

		$trailSlashes = array();
		foreach ($this->routes as $route) {
			$params = $route->test($this->request);
			if (false !== $params) {
				$response = $route->handle($params);
				if (is_string($response)) {
					echo $response;
				} else if (is_a($response, 'webapp\Response')) {
					foreach ($response->headers as $header) {
						call_user_func_array('header', (array) $header);
					}
					echo $response->body;
				}
				return;
			} else if (substr($route->getPattern(), -1) == '/') {
				$trailSlashes[] = $this->request->path . '/';
			}
		}
		print_r($this->request->method);
		return;
		// redirect with trail slashes
		if (in_array($this->request->path . '/', $trailSlashes)) {
			redirect($this->request->path . '/');
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

	public function static_dir($dir) 
	{
		$app = $this;
		$this->route('/' . $dir . '{path:path}', function($path) use ($app, $dir) {
			$file = $app->config['system.basedir'] . '/' . $dir . $path;
			if (is_file($file)) {
				$info = FileInfo::getType($file);
				header('Content-Type: ' . $info);
				return fpassthru(fopen($file, 'r'));
			}
		});
	}

}
?>
