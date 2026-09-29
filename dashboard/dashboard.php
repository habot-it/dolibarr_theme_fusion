<?php
/* Copyright (C) 2026  Fusion theme
 *
 * Theme "fusion" — storage endpoint for the configurable dashboard grid.
 *
 * fusion-dashboard.js rebuilds the widget area (Dolibarr's two hard-coded box
 * columns) into one board on which every widget is explicitly placed, so it needs
 * to persist a layout that llx_boxes cannot express: llx_boxes.box_order only knows about column "A" and
 * column "B" (A01, B02, …). The layout therefore lives beside it, in the user's
 * own parameters (llx_user_param, one row per zone), which keeps it per user and
 * available from any browser — Dolibarr core is left untouched.
 *
 * The theme stylesheet cannot serve that value the way it serves every other
 * server-side value (theme/fusion/base/base.css.php defines NOLOGIN, because the CSS is
 * also served to the login page, so $user is not authenticated there). Hence this
 * small endpoint: GET action=load returns the layout, POST action=save stores it.
 *
 *   GET  theme/fusion/dashboard/dashboard.php?action=load&zone=0
 *        -> {"layout":{"v":5,"items":[{"id","x","y","w","h"}…]},"token":"…"}
 *           (layout is null when nothing is stored)
 *   POST theme/fusion/dashboard/dashboard.php?action=save&zone=0&token=<anti-csrf-newtoken>
 *        body = the layout as JSON
 *        -> {"ok":true}
 *
 * The layout is never stored as received: fz_fusion_dashboard_sanitize() rebuilds it from
 * whitelisted keys and integers only, so nothing a client sends can ever come back
 * out as markup.
 */

if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', '1'); // Saves happen while the page stays open: leave its token valid
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
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

if (!function_exists('fz_fusion_dashboard_sanitize')) {
	/**
	 * Rebuild a layout from untrusted input, keeping only what the grid understands:
	 * widgets carrying their own horizontal and vertical grid coordinates.
	 *
	 * An intermediate version wrapped widgets in explicit columns (rows[].cols[]);
	 * those are flattened here, each widget inheriting its column's width, which draws
	 * the same picture without the extra object.
	 *
	 * @param	mixed		$data	Decoded JSON payload
	 * @return	array|null			Canonical layout, or null when there is nothing usable
	 */
	function fz_fusion_dashboard_sanitize($data)
	{
		// v4 used 24 horizontal tracks; v5 uses 192 so the horizontal resize step is
		// roughly as fine as the eight-pixel vertical one. Both are accepted because the
		// client migrates v4 by an exact factor of eight. Earlier row layouts still arrive
		// in the shape below and are also converted client-side.
		if (is_array($data) && !empty($data['items']) && is_array($data['items'])) {
			$version = (isset($data['v']) && (int) $data['v'] >= 5) ? 5 : 4;
			$columns = ($version === 5) ? 192 : 24;
			$minwidth = ($version === 5) ? 8 : 1;
			$defaultwidth = (int) ($columns / 2);
			$items = array();
			foreach (array_slice($data['items'], 0, 120) as $item) {
				if (!is_array($item) || !isset($item['id'])) {
					continue;
				}
				$id = (string) $item['id'];
				if (!preg_match('/^[a-zA-Z0-9_]{1,32}$/', $id)) {
					continue;
				}
				$w = isset($item['w']) ? (int) $item['w'] : $defaultwidth;
				$x = isset($item['x']) ? (int) $item['x'] : 0;
				$w = max($minwidth, min($columns, $w));
				$x = max(0, min($columns - $w, $x));
				$items[] = array(
					'id' => $id,
					'x' => $x,
					'y' => max(0, min(4000, isset($item['y']) ? (int) $item['y'] : 0)),
					'w' => $w,
					'h' => max(8, min(500, isset($item['h']) ? (int) $item['h'] : 40)),
				);
			}

			return count($items) ? array('v' => $version, 'items' => $items) : null;
		}

		if (!is_array($data) || empty($data['rows']) || !is_array($data['rows'])) {
			return null;
		}

		$rows = array();
		foreach (array_slice($data['rows'], 0, 40) as $row) {
			if (!is_array($row)) {
				continue;
			}

			$rawitems = array();
			if (!empty($row['items']) && is_array($row['items'])) {
				$rawitems = $row['items'];
			} elseif (!empty($row['cols']) && is_array($row['cols'])) {
				foreach ($row['cols'] as $col) {
					if (!is_array($col) || empty($col['items']) || !is_array($col['items'])) {
						continue;
					}
					foreach ($col['items'] as $item) {
						if (is_array($item)) {
							$item['w'] = isset($col['w']) ? $col['w'] : 6;
							$rawitems[] = $item;
						}
					}
				}
			}

			$items = array();
			foreach (array_slice($rawitems, 0, 60) as $item) {
				if (!is_array($item) || !isset($item['id'])) {
					continue;
				}
				// A widget id is a box_id, or one of the synthetic ids the script gives
				// to the non-draggable blocks of the area (the working board).
				$id = (string) $item['id'];
				if (!preg_match('/^[a-zA-Z0-9_]{1,32}$/', $id)) {
					continue;
				}
				$width = isset($item['w']) ? (int) $item['w'] : 6;
				$height = isset($item['h']) ? (int) $item['h'] : 0;
				$items[] = array(
					'id' => $id,
					'w' => max(1, min(12, $width)),		// width, in twelfths of a row
					'h' => ($height > 0 ? max(120, min(4000, $height)) : 0),	// 0 = automatic height
				);
			}

			if (count($items)) {
				$rows[] = array('items' => $items);
			}
		}

		if (!count($rows)) {
			return null;
		}

		return array('v' => 3, 'rows' => $rows);
	}
}

$action = GETPOST('action', 'aZ09');
$zone = GETPOST('zone', 'aZ09');
if (!preg_match('/^[a-zA-Z0-9_]{1,32}$/', $zone)) {
	$zone = '0'; // Same default as FormOther::getBoxesArea()
}
$param = 'FUSION_DASHBOARD_'.$zone;

// main.inc.php already refuses anonymous access, but the layout is strictly personal.
if (empty($user->id)) {
	httponly_accessforbidden('Not logged');
}

// Refuse before top_httphead(): httponly_accessforbidden() emits its own headers.
if ($action != 'load' && $action != 'save') {
	httponly_accessforbidden('Unknown action', 400);
}
if ($action == 'save') {
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

if ($action == 'load') {
	$stored = (isset($user->conf->$param) && $user->conf->$param !== '') ? json_decode($user->conf->$param, true) : null;

	// The token is handed back so a save whose token expired (Dolibarr rolls it on
	// every page load, so a second tab invalidates ours) can be retried. Reading it
	// requires a same-origin authenticated request: a cross-origin caller can issue
	// this GET but cannot read its response.
	print json_encode(array('layout' => fz_fusion_dashboard_sanitize($stored), 'token' => newToken()));
} else {
	// The payload is read raw: GETPOST's sanitizers would mangle the JSON. It is
	// parsed and rebuilt below, so nothing unchecked reaches the database.
	$body = file_get_contents('php://input');
	$toolarge = (strlen($body) > 65000);
	$layout = $toolarge ? null : fz_fusion_dashboard_sanitize(json_decode($body, true));
	if ($layout === null) {
		http_response_code($toolarge ? 413 : 400);
		print json_encode(array('ok' => false, 'error' => ($toolarge ? 'Payload too large' : 'Invalid layout')));
	} else {
		$result = dol_set_user_param($db, $conf, $user, array($param => json_encode($layout)));
		print json_encode(array('ok' => ($result >= 0)));
	}
}

$db->close();
