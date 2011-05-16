<?php
namespace webapp;

require_once dirname(__FILE__) . '/vendor/addendum/annotations.php';
require_once dirname(__FILE__) . '/vendor/doctrine-common/lib/Doctrine/Common/ClassLoader.php';
require_once dirname(__FILE__) . '/vendor/twig/lib/Twig/Autoloader.php';

class WebApp
{
	public static $g = array();

	public static function init()
	{
		define('__WEBAPP_AUTOLOAD', 1);
		ini_set('unserialize_callback_func', 'spl_autoload_call');

		//// register webapp autoloader
		spl_autoload_register(array(new self, 'autoload'));

		//// register doctrine autoloader
		// doctrine-common
		$doctrine_loader = new \Doctrine\Common\ClassLoader('Doctrine\Common', 
			dirname(__FILE__) . '/vendor/doctrine-common/lib');
		$doctrine_loader->register();

		// doctrine-dbal
		$doctrine_loader = new \Doctrine\Common\ClassLoader('Doctrine\DBAL',
			dirname(__FILE__) . '/vendor/doctrine-dbal/lib');
		$doctrine_loader->register();

		// doctrine-ORM
		$doctrine_loader = new \Doctrine\Common\ClassLoader('Doctrine\ORM',
			dirname(__FILE__) . '/vendor/doctrine/lib');
		$doctrine_loader->register();

		//// register twig autoloader
		\Twig_Autoloader::register();

		//// register int parameter converter
		Route::registerConvertFunction('int', function($v) {
			return intval($v);
		});    
	}

	public static function autoload($class)
	{
		if (0 !== strpos($class, 'webapp\\')) {
			return;
		}	 
		if (file_exists($file = dirname(__FILE__) . '/' . str_replace('webapp\\', '/', $class).'.php')) {
			require $file;
		}  
	}
}

if (!defined('__WEBAPP_AUTOLOAD')) {
	WebApp::init();	
}

class Request
{
	private $_param;

	public function __construct()
	{
		$this->_param = array_merge($_GET, $_POST);
	}

	public function __isset($key)
	{
		return in_array($key, array('host', 'path', 'param',
			'cookie', 'session', 'body'));
	}

	public function __get($key)
	{
		switch ($key) {
		case 'method':
			return $_SERVER['REQUEST_METHOD'];
		case 'host':
			return $_SERVER['HTTP_HOST'];
		case 'path':
			return $_SERVER['PATH_INFO'];
		case 'param':
			return $_param;
		case 'cookie':
			return $_COOKIE;
		case 'session':
			return $_SESSION;
		case 'body':
			return file_get_contents('php://input');
		}
	}
}

class Response
{
	public $headers = array();	// stupid php!
	private $body = '';

	function __construct($body='', $headers=array())
	{
		$this->body = $body;
		$this->headers = array_merge($this->headers, $headers);
	}

	public function __isset($key)
	{
		return in_array($key, array('headers', 'body'));
	}

	public function __get($key)
	{
		if (isset($this->{$key})) {
			return $this->{$key};
		}
	}

	public function __set($key, $value)
	{
		if (isset($this->{$key})) {
			$this->{$key} = $value;
		}
	}
}

// message flashing
// @see http://flask.pocoo.org/docs/api/#message-flashing

$flash = array();

function flash($message, $category='')
{
	global $flash;
	$flash[] = array($category, $message);
}

function get_flashed_messages($with_category=false)
{
	global $flash;
	if ($with_category) {
		usort($flash, function($a, $b) { 
			return strcmp($a[0], $b[0]);
		});
		array_walk($flash, function(&$value, $index) {
			$value = array_combine(array('category', 'message'), $value);
		});
	} else {
		array_walk($flash, function(&$value, $index) {
			$value = $value[1];
		});   
	}	 
	$ret = $flash;
	$flash = array();
	return $ret; 
}

function redirect($url, $code='302')
{
	header('Location: ' . $url, true, $code);
	exit;
}

function url_for($endpoint, $vars=null)
{
}

class WebApp_Twig_Loader implements \Twig_LoaderInterface
{
	private $tempalteDir;
	private $loaders = array();

	function __construct($templateDir='.')
	{
		$this->templateDir = $templateDir;
	}

	public function getSource($name)
	{
		list(,$path) = $this->parts($name);
		return $this->getLoader($name)->getSource($path);
	}

	public function getCacheKey($name)
	{
		list(,$path) = $this->parts($name);
		return $this->getLoader($name)->getCacheKey($path);
	}

	public function isFresh($name, $time)
	{
		list(,$path) = $this->parts($name);
		return $this->getLoader($name)->isFresh($path, $time);
	}

	private function parts($name)
	{
		list($module, $path) = explode(':', $name);
		if (!$path) {
			$path = $module;
			$module = null;
		}
		return array($module, $path);
	}

	private function getLoader($name)
	{
		list($module, $path) = $this->parts($name);
		if ($module) {
			$tpldir = './' . $module . '/' . $this->templateDir;
		} else {
			$tpldir = './' . $this->templateDir;
		}
		if (array_key_exists($tpldir, $this->loaders)) {
			$loader = $this->loaders[$tpldir];
		} else {
			$loader = new \Twig_Loader_Filesystem($tpldir);
			$this->loaders[$tpldir] = $loader;
		}
		return $loader;
	}
}

class WebApp_Twig_Extension extends \Twig_Extension
{
	public function getName()
	{
		return 'WebApp';
	}

	public function getGlobals()
	{
		return array();
		return array(
			'g' => WebApp::$g,
			'get_flashed_messages' => new \Twig_Function('webapp\get_flashed_messages'),
			'url_for' => new \Twig_Function('webapp\url_for')
		);
	}
}

?>
