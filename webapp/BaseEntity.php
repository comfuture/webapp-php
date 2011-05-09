<?php
namespace webapp;

/**
 * base Doctrine entity for convinent orm association
 */
class BaseEntity
{
	protected $_properties;

	function __construct($initValues=null)
	{
		if (is_array($initValues)) {
			foreach ($initValues as $name=>$value) {
				$this->{$name} = $value;
			}
		}
	}

	protected function properties()
	{
		if (null === $this->_properties)
			$this->_properties = array_keys(get_class_vars(get_class($this)));
		return $this->_properties;
	}

	public function __isset($key)
	{
		return in_array($key, $this->properties());
	}

	public function __get($key)
	{
		if (in_array($key, $this->properties()))
			return $this->{$key};
		return null;
	}

	public function __set($key, $value)
	{
		if (in_array($key, $this->properties())) {
			$this->{$key} = $value;
		}
	}
}
?>
