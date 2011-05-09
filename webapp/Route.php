<?php
namespace webapp
{
	class Route
	{
		private $handler = null;
		private $pattern = '';
		private $regex = '';
		private $method = 'GET';

		function __construct($pattern, $handler, $method='GET')
		{
			if ($pattern{0} !== '/')
				throw new \Exception('pattern must starts with "/"');
			$this->pattern = $pattern;
			$this->handler = $handler;
			$this->method = $method;

			$reVars = '#{(?:(?P<converter>[^:}]+:)?(?P<variable>[^}]+))}#';
			$this->regex = '#^' . $this->pattern;
			if (preg_match_all($reVars, $pattern, &$matches, PREG_SET_ORDER)) {
				foreach ($matches as $match) {
					if ($match['converter']) {
						switch ($match['converter']) {
						case 'int:':
							$this->regex = str_replace($match[0], '([0-9]+)', $this->regex);
							break;
						case 'path:':
							$this->regex = str_replace($match[0], '(.+)', $this->regex);
							break;
						}
					} else {
						$this->regex = str_replace($match[0], '([^/$]+)', $this->regex);
					}

				}
			}
			$this->regex = str_replace('*', '.*', $this->regex);
			$this->regex .= '$#';
		}

		public function getPattern()
		{
			return $this->pattern;
		}

		public function getRegEx()
		{
			return $this->regex;
		}

		public function test($path)
		{
			if (preg_match_all($this->regex, $path,
				&$matches, PREG_SET_ORDER)) {
				$param = $matches[0];
				array_shift($param);
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

