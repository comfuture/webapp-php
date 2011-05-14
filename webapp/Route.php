<?php
namespace webapp
{
	class Route
	{
		private static $convertFunctions = array();
		private $handler = null;
		private $pattern = '';
		private $regex = '';
		private $converters = array();
		private $methods = array('GET');

		function __construct($pattern, $handler, $methods=array('GET'))
		{
			if ($pattern{0} !== '/')
				throw new \Exception('pattern must starts with "/"');
			$this->pattern = $pattern;
			$this->handler = $handler;
			$this->methods = $methods;

			$reVars = '#{(?:(?P<converter>[^:}]+:)?(?P<variable>[^}]+))}#';
			$this->regex = '#^' . $this->pattern;
			if (preg_match_all($reVars, $pattern, $matches, PREG_SET_ORDER)) {
				foreach ($matches as $match) {
					if ($match['converter']) {
						switch ($match['converter']) {
						case 'int:':
							$this->regex = str_replace($match[0], '([0-9]+)', $this->regex);
							$this->converters[] = 'int';
							break;
						case 'path:':
							$this->regex = str_replace($match[0], '(.+)', $this->regex);
							$this->converters[] = 'path';
							break;
						}
					} else {
						$this->regex = str_replace($match[0], '([^/$]+)', $this->regex);
						$this->converters[] = 'str';
					}

				}
			}
			$this->regex = str_replace('*', '.*', $this->regex);
			$this->regex .= '$#';
		}

		public function registerConvertFunction($name, $func)
		{
			static::$convertFunctions[$name] = $func;
		}

		public function getPattern()
		{
			return $this->pattern;
		}

		public function getRegEx()
		{
			return $this->regex;
		}

		public function test($request)
		{
			$path = $request->path;
			$method = $request->method;
			if (preg_match_all($this->regex, $path, $matches, PREG_SET_ORDER) &&
				in_array($method, $this->methods)) {
				$param = $matches[0];
				array_shift($param);
				array_map(function($v, $c) {
					if (in_array($c, static::$convertFunctions))
						return static::$convertFunctions[$c]($v);
					return $v;
				}, $param, $this->converters);
				return $param;
			}
			return false;
		}

		public function handle($params=null)
		{
			if (null === $params)
				$params = array();
			foreach ($params as $param) {
				$converter = array_shift($this->converters);
			}
			return call_user_func_array($this->handler, $params);
		}
	}
}

?>
