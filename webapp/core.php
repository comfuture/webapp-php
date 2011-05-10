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
	private $_headers = array();
	private $_body = '';

	public function __isset($key)
	{
		return in_array($key, 'headers', 'body');
	}

	public function __get($key)
	{
		switch ($key) {
		case 'headers':
			return $this->_headers;
		case 'body':
			return $this->_body;
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

function url_for($endpoint, $vars=null)
{
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
