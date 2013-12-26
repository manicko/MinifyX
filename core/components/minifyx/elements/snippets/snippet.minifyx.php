<?php
/** @var array $scriptProperties */
if (!$modx->getService('minifyx','MinifyX', MODX_CORE_PATH.'components/minifyx/model/minifyx/')) {return;}
/** @var MinifyX $MinifyX */
$MinifyX = new MinifyX($modx, $scriptProperties);
if (!$MinifyX->prepareCacheFolder()) {
	$modx->log(modX::LOG_LEVEL_ERROR, '[MinifyX] Could not create cache dir "'.$MinifyX->config['cacheFolder'].'"');
	return;
}

$time = time();
$cacheFolderUrl = MODX_BASE_URL . str_replace(MODX_BASE_PATH, '', $MinifyX->config['cacheFolder']);

$array = array(
	'js' => trim($modx->getOption('jsSources', $scriptProperties, '', true)),
	'css' => trim($modx->getOption('cssSources', $scriptProperties, '', true)),
);

foreach ($array as $type => $value) {
	if (empty($value)) {continue;}
	$filename = $MinifyX->config[$type.'Filename'];
	$extension = $MinifyX->config[$type.'Ext'];
	$register = $MinifyX->config['register'.ucfirst($type)];
	$placeholder = !empty($MinifyX->config[$type.'Placeholder'])
		? $MinifyX->config[$type.'Placeholder']
		: '';

	$files = $MinifyX->prepareFiles($value);
	$properties = array(
		'minify' => $MinifyX->config['minify'.ucfirst($type)]
				? 'true'
				: 'false',
	);
	$cached = $MinifyX->getCachedFiles($filename, $extension);

	$result = $MinifyX->Munee($files, $properties, $cached);
	// Create new cache file
	if (!empty($result)) {
		$file = $filename . '_' . $time . $extension;
		file_put_contents($MinifyX->config['cacheFolder'] . $file, $result);
	}
	// File already in cache
	elseif (!empty($cached)) {
		$file = array_pop($cached);
	}
	// Empty source files or some error
	else {
		$file = '';
	}

	// Register file on frontend
	if (!empty($file) && file_exists($MinifyX->config['cacheFolder'] . $file)) {
		if ($register == 'placeholder' && $placeholder) {
			$tag = $type == 'css'
				? '<link rel="stylesheet" href="' . $cacheFolderUrl .  $file . '" type="text/css" />'
				: '<script type="text/javascript" src="' . $cacheFolderUrl . $file . '"></script>';
			$modx->setPlaceholder($placeholder, $tag);
		}
		else {
			if ($type == 'css') {
				$modx->regClientCSS($cacheFolderUrl . $file);
			}
			else {
				if ($register == 'startup') {
					$modx->regClientStartupScript($cacheFolderUrl . $file);
				}
				else {
					$modx->regClientScript($cacheFolderUrl . $file);
				}
			}
		}
	}

	// Remove old cache files
	foreach ($cached as $old) {
		unlink($MinifyX->config['cacheFolder'] . $old);
	}
}