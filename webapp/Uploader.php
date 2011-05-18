<?php
namespace webapp;

class Uploader
{
	private $handler;
	
	function __construct($handler=null)
	{
		$this->handler = $handler;
	}
	
	public function getHTML($server)
	{
		$code = <<<__EOF__
<div id="file-uploader">       
    <noscript>          
        <p>Please enable JavaScript to use file uploader.</p>
        <!-- or put a simple form for upload here -->
    </noscript>         
</div>
<script type="text/javascript">
var uploader = new qq.FileUploader({
    element: document.getElementById('file-uploader'),
    action: '{$this->handler}'
}); 
</script>
__EOF__;
		return $code;
	}

	public function upload()
	{
		return array('success' => true);
	}
}

class UploadedFile
{
	private $isStream;

	function __construct()
	{
	}

	public function __isset($key) {
		return in_array($key, array('name', 'size'));
	}

	public function __get($key)
	{
		switch ($key) {
		case 'name':
			return 'filename';
		case 'size':
			return 0;
		}
	}

	public function save($path)
	{
	}

	public static function fromRequest($file)
	{
	}

	public static function fromStream()
	{
	}
}
?>
