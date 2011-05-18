<?php
namespace webapp;

function _wa($path)
{
	list($head, $tail) = explode('/', $path, 2);
	list(,$ext) = substr($path, strrpos($path, '.') + 1);

	$response = new Response();
	switch ($head) {
	case 'datatables':
		$file = dirname(__FILE__) . '/vendor/DataTables/media/' . $tail;
		break;
	case 'tokeninput':
		$file = dirname(__FILE__) . '/vendor/jquery-tokeninput/' . $tail;
		break;
	case 'fileuploader.js': case 'fileuploader.css': case 'loading.gif':
		$file = dirname(__FILE__) . '/vendor/file-uploader/client/' . $path;
		break;
	case 'aristo':
		if ($tail == 'theme.css')
			$file = dirname(__FILE__) . '/vendor/aristo/css/Aristo/jquery-ui-1.8.7.custom.css';
		else
			$file = dirname(__FILE__) . '/vendor/aristo/css/Aristo/' . $tail;
		break;
	case 'ckeditor':
		$file = dirname(__FILE__) . '/vendor/ckeditor/' . $tail;
		break;
	}
	if (is_file($file)) {
		$info = FileInfo::getType($file);
		$response->headers[] = 'Content-Type: ' . $info;
		$response->body = file_get_contents($file);
		return $response;
	} else {
		$response->headers[] = array('Status: 404 Not Found', true, 404);
		return $response;
	}
}

function builtins()
{
	return new Route('/_wa/{path:path}', 'webapp\_wa');
}
?>
