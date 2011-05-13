<?php
namespace
{
        require_once dirname(__FILE__) . '/vendor/addendum/annotations.php';

        class Route extends Annotation
        {     
                public $methods = array('GET');
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
		public $action;
		public $method;
	}

	class FormItem extends Annotation
	{
		public $type;
		public $name;
		public $label;
		public $options;
		public $readonly;
		public $layout;
	}

	class Column extends Annotation
	{
		public $length;
		public $type;

		public $unique;
	}

	class Id extends Annotation
	{
	}
}
?>
