<?php

spl_autoload_register(
	function ($className) {
		$paths = [
			NN_WWW . 'pages' . DS . 'install' . DS,
			NN_WWW . 'pages' . DS . 'admin' . DS,
			NN_WWW . 'pages' . DS,
		];

		foreach ($paths as $path) {
			$spec = str_replace('\\', DS, $path . $className . '.php');

			if (file_exists($spec)) {
				require_once $spec;
				break;
			} else if (NN_LOGAUTOLOADER) {
				var_dump($spec);
			}
		}
	}
);

?>