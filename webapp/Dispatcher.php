<?php
namespace webapp;

require_once dirname(__FILE__) . '/core.php';

interface IDispatcher
{
	public function dispatch();
	public function setApplication(Application $app);
}

abstract class Dispatcher implements IDispatcher
{
	protected $app;

	public function __construct()
	{
	}

	public function dispatch()
	{
		return function() {};
	}

	public function setApplication(Application $app)
	{
		$this->app = $app;
	}
}
?>
