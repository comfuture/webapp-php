<?php
namespace webapp
{
	class Route
	{
		protected static $convertFunctions = array();
		private $handler = null;
		private $pattern = '';
		private $regex = '';
		private $converters = array();
		private $methods = array('GET');
		private $options;

		function __construct($pattern, $handler, $methods=null, $options=null)
		{
			if ($pattern{0} !== '/')
				throw new \Exception('pattern must starts with "/"');
			$this->pattern = $pattern;
			$this->handler = $handler;
			if (null != $methods)
				$this->methods = $methods;
			if (null != $options)
				$this->options = $options;

			$reVars = '#{(?:(?P<converter>[^:}]+:)?(?P<variable>[^}]+))}#';
			// + * ? [ ^ ] $ ( ) { } = ! < > | : -
			$_pattern = preg_replace('/[\+\*\[\]\$\(\)\.]/', '\\\$0', $this->pattern);
			$this->regex = '#^' . $_pattern;
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
			$this->regex = str_replace('%', '.*', $this->regex);
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

		public function getOptions()
		{
			return $this->options;
		}

		public function test($request)
		{
			$path = $request->path;
			$method = $request->method;
			if (preg_match_all($this->regex, $path, $matches, PREG_SET_ORDER) &&
				in_array($method, $this->methods)) {
				$param = $matches[0];
				array_shift($param);
				$fn = self::$convertFunctions;
				$param = array_map(function($v, $c) use($fn) {
					if (in_array($c, array_keys($fn))) {
						return $fn[$c]($v);
					}
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
			return call_user_func_array($this->handler, $params);
		}
	}
}

?>
