<?php
namespace webapp;

require_once dirname(__FILE__) . '/core.php';
require_once dirname(__FILE__) . '/builtins.php';

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

		$this->route(builtins());

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
				if (is_string($response) || is_numeric($response)) {
					echo $response;
				} else if (is_a($response, 'webapp\Response')) {
					foreach ($response->headers as $header) {
						call_user_func_array('header', (array) $header);
					}
					echo $response->body;
				} else {
					$format = best_match($this->request->accept);
					$options = $route->getOptions();
					$availTypes = array_keys($options['template']);
					if (in_array($format[1], $availTypes)) {
						$tpl = $options['template'][$format[1]];
						echo $this->render_template($tpl, $response);
					}
					echo $this->jsonify($response);
				}
				return;
			} else if (substr($route->request->path, -1) == '/') {
				$trailSlashes[] = $this->request->path . '/';
			} 
		}

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

	public function route($route, $handler=null, $methods=null, $options=null)
	{
		if (is_a($route, 'webapp\Route')) {
			if (null != $handler) {
				// TODO: implement Route::setHandler()
				//$route->setHandler($handler);
			}
			if (null != $methods) {
				// TODO: implement Route::setMethod()
				//$route->setMethod($method);
			}
		} else if (is_string($route) && null != $handler) {
			$route = new Route($route, $handler, $methods, $options);
		} else {
			throw new \Exception('$route must be one of webapp\Route or string');
		}
		array_unshift($this->routes, $route);
		// TODO: sort?
	}

	protected function redirect($path, $code=302)
	{
		$response = new Response();
		$response->headers[] = array('Location: ' . $path, true, $code);
		return $response;
	}

        public function render_template($tpl, $vars=null)
        {     
                if (!$vars)
                        $vars = array();
                $template = $this->templateEnv->loadTemplate($tpl);
                return $template->render($vars);
	}

	public function jsonify($obj)
	{
		return json_encode($obj);
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

	public function register_package($ns)
	{
		$app = $this;
		$loader = function($class) use ($ns, $app) {
			if (0 !== strpos($class, $ns . '\\')) {
				return;
			}
			$file = $app->config['system.basedir'] . '/' . str_replace('\\', '/', $class).'.php';
			if (file_exists($file)) {
				require $file;
			}     
		};    
		      
		spl_autoload_register($loader);
	}

}
?>
