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
		$this->handler = $server;
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
	action: '{$this->handler}',
	onComplete: function(id, fileName, resp) {
		if (typeof jQuery == 'undefined') return;
		var form = jQuery('#file-uploader').closest('form');
		$('<input type="hidden" name="uploaded_files[]"/>')
			.attr('data-fileid', id)
			.val(resp['fileid'])
			.appendTo(form);
	}
}); 
</script>
__EOF__;
		return $code;
	}

	public function upload($path)
	{
		if ($_GET['qqfile']) {
			$file = UploadedFile::fromStream();
		} else if ($_FILES) {
			$file = UploadedFile::fromRequest($_FILES[0]);
		}
		if ($file->save($path))
			return basename($path);
		return false;
	}
}

class UploadedFile
{
	private $isStream = false;

	public function __isset($key) {
		return in_array($key, array('name', 'size'));
	}

	public function __get($key)
	{
		switch ($key) {
		case 'name':
			if ($this->isStream)
				return $_GET['qqfile'];
			else if ($this->file)
				return $this->file['name'];
		case 'size':
			if ($this->isStream && $_SERVER['CONTENT_LENGTH']) {
				return (int) $_SERVER['CONTENT_LENGTH'];
			} else if ($this->file) {
				return filesize($this->file['tmp_name']);
			}
			return 0;
		case 'isStream':
			return $this->{$key};
		}
	}

	public function read()
	{
		if ($this->isStream) {
			$in = fopen('php://input', 'r');
			return stream_get_contents($in);
		}
		else if ($this->file)
			return file_get_contents($this->file['tmp_name']);
		return null;
	}

	public function save($path)
	{
		return file_put_contents($path, $this->read());
	}

	public static function fromRequest($item)
	{
		$file = new UploadedFile();
		$file->file = $item;
		$file->isStream = false;
		return $file;
	}

	public static function fromStream()
	{
		$file = new UploadedFile();
		$file->isStream = true;
		return $file;
	}
}
?>
