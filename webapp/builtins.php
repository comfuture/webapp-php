<?php
namespace webapp;

function _wa($path)
{
	list($key,) = explode('/', $path, 2);
	list(,$ext) = substr($path, strrpos($path, '.') + 1);

	$response = new Response();
	$response->headers[] = '';
	switch ($key) {
	case 'datatables':
		$file = dirname(__FILE__) . '/vendor/DataTables/media/' . $path;
		if (is_file($file)) {
			$info = FileInfo::getType($file);
			$response->headers[] = 'Content-Type: ' . $info;
			$response->body = file_get_contents($file);
			return $response;
		}
	case 'tokeninput':
		break;
	}
}

function bulitins()
{
	return new Route('/_wa/{path:path}', 'webapp\_wa');
}
?>
