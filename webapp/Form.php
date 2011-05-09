<?php
namespace webapp;

// XXX: in development

require_once dirname(__FILE__) . '/Annotation.php';

class Form
{
	function __construct()
	{
	}

	public function setMeta($meta)
	{
	}

	public static function fromMeta($meta)
	{
		return new Form();
	}

	public function html()
	{
	}
}

?>
