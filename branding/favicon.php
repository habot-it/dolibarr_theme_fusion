<?php
/* Copyright (C) 2026  Fusion theme
 *
 * Theme "fusion" — favicon endpoint.
 *
 * Dolibarr prints the favicon itself (main.inc.php, top_htmlhead()): the squarred
 * logo's mini thumbnail, unless the constant MAIN_FAVICON_URL holds a URL.
 * branding/setup.php stores this script's URL there, and the script serves the
 * image uploaded for it, or the logo picked in FUSION_FAVICON. Resolving the file
 * on each request (rather than storing its viewimage.php URL) keeps the favicon
 * right when a logo is uploaded again under another name.
 *
 * Served without login, like the company logo shown on the login page.
 */

if (!defined('NOLOGIN')) {
	define('NOLOGIN', '1');
}
if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', '1');
}
if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}
if (!defined('NOREQUIRETRAN')) {
	define('NOREQUIRETRAN', '1');
}
if (!defined('NOSESSION')) {
	define('NOSESSION', '1');
}

require __DIR__.'/../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once __DIR__.'/branding.lib.php';

/**
 * @var Conf $conf
 */

// An own image is served as uploaded (it is made for it); a company logo through
// its mini thumbnail, as Dolibarr does.
$choice = getDolGlobalString('FUSION_FAVICON');
$relativePath = '';
if ($choice === 'custom') {
	$relativePath = fz_fusion_custom_path('favicon', '');
} elseif ($choice === 'logo' || $choice === 'squarred') {
	$relativePath = fz_fusion_logo_path($choice, '_mini');
}
$file = ($relativePath !== '') ? $conf->mycompany->dir_output.'/'.$relativePath : '';

if ($file === '' || !is_readable($file)) {
	// 'dolibarr', an unset choice or a logo that is not uploaded: Dolibarr's own icon
	header('Location: '.DOL_URL_ROOT.'/theme/dolibarr_256x256_color.png', true, 302);
	exit;
}

$mtime = (int) filemtime($file);
$etag = '"'.md5($relativePath.'|'.$mtime).'"';
header('Cache-Control: public, max-age=3600');
header('ETag: '.$etag);
header('Last-Modified: '.gmdate('D, d M Y H:i:s', $mtime).' GMT');
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
	http_response_code(304);
	exit;
}
header('Content-Type: '.dol_mimetype($file));
header('Content-Length: '.filesize($file));
header('X-Content-Type-Options: nosniff');
readfile($file);
