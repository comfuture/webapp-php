<?php
namespace
{
        require_once dirname(__FILE__) . '/vendor/addendum/annotations.php';

        class Route extends Annotation
        {     
                public $method = 'GET';
        }

	class BeforeRequest extends Annotation
	{
	}

	class Cached extends Annotation
	{
		public $timeout;
	}

	class Secured extends Annotation
	{
		public $role;
	}

	class Filter extends Annotation
	{
		public $param;
	}

	class Decorate extends Annotation
	{
		public $param;
	}

	// forms
	class Form extends Annotation
	{
		public $type;
		public $label;
		public $readonly;
	}

	class Column extends Annotation
	{
		public $length;
	}
}
?>
