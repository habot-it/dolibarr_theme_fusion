<?php
/* Copyright (C) 2026  Fusion theme
 *
 * Theme "fusion" — storage endpoint for the navigation history.
 *
 * fusion.js shows, above the page, the last elements (third parties, products,
 * invoices…) the user opened, like a breadcrumb. The list is kept in the user's
 * own parameters (llx_user_param, FUSION_HISTORY), so it follows the user from
 * one browser to another and survives a new login — Dolibarr core is left
 * untouched. One request per page:
 *
 *   GET  theme/fusion/history/history.php?action=load
 *        -> {"items":[…]}
 *   POST theme/fusion/history/history.php?action=push&token=<anti-csrf-newtoken>
 *        body = the element of the current page, as JSON
 *        -> {"items":[…]}   (the list with that element moved to its end)
 *
 * An item: {"u": url (path inside Dolibarr), "r": ref, "t": tooltip,
 *           "c": picto classes, "k": picto color, "i": picto image path}.
 * Nothing is stored or returned as received: fz_fusion_history_item() rebuilds
 * every item from whitelisted keys and checked values, and fusion.js only ever
 * writes them as text and attributes.
 */

if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', '1'); // A background call: leave the page's token valid
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
if (!defined('NOREQUIRESOC')) {
	define('NOREQUIRESOC', '1');
}

require __DIR__.'/../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';	// dol_set_user_param()

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var User $user
 */

if (!function_exists('fz_fusion_history_path')) {
	/**
	 * A path inside Dolibarr ("/product/card.php?id=3"), or '' when it is anything else
	 * (another site, a scheme, a protocol-relative URL, control characters…).
	 *
	 * @param	mixed	$value	Untrusted value
	 * @return	string
	 */
	function fz_fusion_history_path($value)
	{
		$value = is_string($value) ? trim($value) : '';
		if ($value === '' || strlen($value) > 600 || !preg_match('#^/[^/\\\\]#', $value) || preg_match('/[\x00-\x1F\x7F"<>`]/', $value)) {
			return '';
		}
		$parts = parse_url($value);
		if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
			return '';
		}
		return $value;
	}
}

if (!function_exists('fz_fusion_history_text')) {
	/**
	 * Plain text, on one line, cut to a length.
	 *
	 * @param	mixed	$value	Untrusted value
	 * @param	int		$max	Maximum length, in characters
	 * @return	string
	 */
	function fz_fusion_history_text($value, $max)
	{
		$value = is_string($value) ? strip_tags($value) : '';
		$value = trim(preg_replace('/[\x00-\x1F\x7F\s]+/u', ' ', $value));
		return dol_trunc($value, $max, 'right', 'UTF-8');
	}
}

if (!function_exists('fz_fusion_history_item')) {
	/**
	 * Rebuild one history item from untrusted input.
	 *
	 * @param	mixed		$data	Decoded JSON
	 * @return	array|null			Clean item, or null when it has no usable url or ref
	 */
	function fz_fusion_history_item($data)
	{
		if (!is_array($data)) {
			return null;
		}
		$item = array(
			'u' => fz_fusion_history_path(isset($data['u']) ? $data['u'] : ''),
			'r' => fz_fusion_history_text(isset($data['r']) ? $data['r'] : '', 60),
		);
		if ($item['u'] === '' || $item['r'] === '') {
			return null;
		}
		$tooltip = fz_fusion_history_text(isset($data['t']) ? $data['t'] : '', 200);
		if ($tooltip !== '') {
			$item['t'] = $tooltip;
		}
		if (isset($data['c']) && is_string($data['c']) && preg_match('/^[a-zA-Z0-9 _-]{1,160}$/', $data['c'])) {
			$item['c'] = $data['c'];
		}
		if (isset($data['k']) && is_string($data['k']) && preg_match('/^(#[0-9a-fA-F]{3,8}|rgba?\([0-9., %]{1,40}\))$/', $data['k'])) {
			$item['k'] = $data['k'];
		}
		$image = fz_fusion_history_path(isset($data['i']) ? $data['i'] : '');
		if ($image !== '') {
			$item['i'] = $image;
		}
		return $item;
	}
}

if (!function_exists('fz_fusion_history_list')) {
	/**
	 * Rebuild a stored list, oldest first, keeping its last $keep items.
	 *
	 * @param	mixed	$data	Decoded JSON
	 * @param	int		$keep	Number of items kept
	 * @return	array
	 */
	function fz_fusion_history_list($data, $keep)
	{
		$items = array();
		$seen = array();
		foreach (is_array($data) ? array_reverse($data) : array() as $raw) {	// newest first, so a duplicate keeps its latest visit
			$item = fz_fusion_history_item($raw);
			if ($item === null || isset($seen[$item['u']])) {
				continue;
			}
			$seen[$item['u']] = true;
			$items[] = $item;
			if (count($items) >= $keep) {
				break;
			}
		}
		return array_reverse($items);
	}
}

$action = GETPOST('action', 'aZ09');
$param = 'FUSION_HISTORY';
$keep = max(1, min(100, getDolGlobalInt('FUSION_HISTORY_KEEP', 30)));

// main.inc.php already refuses anonymous access, but the history is strictly personal.
if (empty($user->id)) {
	httponly_accessforbidden('Not logged');
}
if (getDolGlobalString('FUSION_HISTORY_DISABLED')) {
	httponly_accessforbidden('Disabled', 404);
}

// Refuse before top_httphead(): httponly_accessforbidden() emits its own headers.
if ($action != 'load' && $action != 'push') {
	httponly_accessforbidden('Unknown action', 400);
}
if ($action == 'push') {
	// An invalid token makes main.inc.php empty $_POST and carry on, so refuse explicitly.
	// The token is the one Dolibarr publishes in <meta name="anti-csrf-newtoken">.
	if (empty($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] != 'POST') {
		httponly_accessforbidden('Method not allowed', 405);
	}
	if (GETPOST('token', 'alpha') !== currentToken()) {
		httponly_accessforbidden('Bad token', 403);
	}
}

top_httphead('application/json', 1);

$stored = (isset($user->conf->$param) && $user->conf->$param !== '') ? json_decode($user->conf->$param, true) : null;
$items = fz_fusion_history_list($stored, $keep);

if ($action == 'push') {
	// The payload is read raw: GETPOST's sanitizers would mangle the JSON. It is
	// rebuilt by fz_fusion_history_item(), so nothing unchecked reaches the database.
	$body = file_get_contents('php://input');
	$item = (strlen($body) > 4000) ? null : fz_fusion_history_item(json_decode($body, true));
	if ($item === null) {
		http_response_code(400);
		print json_encode(array('items' => $items, 'error' => 'Invalid item'));
		$db->close();
		exit;
	}
	$items[] = $item;
	$items = fz_fusion_history_list($items, $keep);
	$encoded = json_encode($items);
	if ($encoded !== (isset($user->conf->$param) ? $user->conf->$param : '')) {
		dol_set_user_param($db, $conf, $user, array($param => $encoded));
	}
}

print json_encode(array('items' => $items));

$db->close();
