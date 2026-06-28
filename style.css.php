<?php
/* Copyright (C) 2026  Fusion theme
 *
 * Theme "fusion" — derived from eldy. It reuses every component style of eldy
 * (tables, forms, cards, badges, …) and only overrides the application shell so
 * that the two Dolibarr menus (horizontal top + vertical left) are merged into a
 * SINGLE responsive navigation:
 *   - landscape : one retractable left sidebar
 *   - portrait  : one top bar + slide-in drawer
 * The DOM merge itself is done by theme/fusion/fusion.js (auto-loaded when the
 * constant ALLOW_THEME_JS = 1).
 *
 * IMPORTANT: no output must be produced before eldy's style.css.php is included,
 * because it is the one that calls top_httphead('text/css') (HTTP headers).
 */

// Reuse the whole eldy stylesheet (this also bootstraps Dolibarr and sets the
// text/css content-type). __DIR__ inside eldy keeps resolving to the eldy dir.
require __DIR__.'/../eldy/style.css.php';
?>

/* =======================================================================
 *  FUSION — unified responsive shell overrides
 *  Everything is scoped under html.fusion (class added by fusion.js as soon
 *  as it runs, so the legacy menus are hidden before they can flash).
 * ======================================================================= */

<?php
if (!function_exists('fz_fusion_clean_label')) {
	function fz_fusion_clean_label($value)
	{
		$flags = ENT_QUOTES | (defined('ENT_HTML5') ? ENT_HTML5 : 0);
		$value = html_entity_decode((string) $value, $flags, 'UTF-8');
		$value = strip_tags($value);
		$value = preg_replace('/^\+/', '', $value);
		$value = preg_replace('/[\x{00A0}\s]+/u', ' ', $value);
		return trim($value);
	}
}
if (!function_exists('fz_fusion_mycompany_logo_url')) {
	function fz_fusion_mycompany_logo_url($shape = 'square')
	{
		global $conf;

		$squareCandidates = array(
			array('MAIN_INFO_SOCIETE_LOGO_SQUARRED', 'logos/'),
			array('MAIN_INFO_SOCIETE_LOGO_SQUARRED_SMALL', 'logos/thumbs/'),
			array('MAIN_INFO_SOCIETE_LOGO_SQUARRED_MINI', 'logos/thumbs/'),
		);
		$wideCandidates = array(
			array('MAIN_INFO_SOCIETE_LOGO', 'logos/'),
			array('MAIN_INFO_SOCIETE_LOGO_SMALL', 'logos/thumbs/'),
			array('MAIN_INFO_SOCIETE_LOGO_MINI', 'logos/thumbs/'),
		);
		$candidates = $shape === 'wide'
			? array_merge($wideCandidates, $squareCandidates)
			: array_merge($squareCandidates, $wideCandidates);

		foreach ($candidates as $candidate) {
			$file = getDolGlobalString($candidate[0]);
			if ($file === '') {
				continue;
			}
			$relativePath = $candidate[1].$file;
			$absolutePath = empty($conf->mycompany->dir_output) ? '' : $conf->mycompany->dir_output.'/'.$relativePath;
			if ($absolutePath && is_readable($absolutePath)) {
				return DOL_URL_ROOT.'/viewimage.php?cache=1&modulepart=mycompany&file='.urlencode($relativePath);
			}
		}

		return '';
	}
}
$fz_optioncss = function_exists('GETPOST') ? GETPOST('optioncss', 'aZ09') : (isset($_GET['optioncss']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $_GET['optioncss']) : '');
$fz_is_print = ($fz_optioncss === 'print');
if (isset($langs) && is_object($langs)) {
	$langs->loadLangs(array('main', 'admin'));
}
if (!function_exists('fz_fusion_trans')) {
	function fz_fusion_trans($key, $fallback)
	{
		global $langs;
		if (isset($langs) && is_object($langs)) {
			$value = $langs->transnoentitiesnoconv($key);
			if ($value !== '' && $value !== $key) {
				return $value;
			}
		}
		return $fallback;
	}
}
if (!function_exists('fz_fusion_css_string')) {
	function fz_fusion_css_string($value)
	{
		return '"'.str_replace(array('\\', '"', "\r", "\n"), array('\\\\', '\"', ' ', ' '), (string) $value).'"';
	}
}
$fz_companyname = fz_fusion_clean_label(getDolGlobalString('MAIN_INFO_SOCIETE_NOM'));
$fz_appname = fz_fusion_clean_label(getDolGlobalString('MAIN_APPLICATION_TITLE'));
$fz_is_empty_appname = ($fz_appname === '');
$fz_brand_label = $fz_appname !== '' ? $fz_appname : ($fz_companyname !== '' ? $fz_companyname : (defined('DOL_APPLICATION_TITLE') ? constant('DOL_APPLICATION_TITLE') : 'Dolibarr'));
$fz_logo_square_url = fz_fusion_mycompany_logo_url('square');
$fz_logo_wide_url = fz_fusion_mycompany_logo_url('wide');
$fz_logo_url = $fz_is_empty_appname ? $fz_logo_wide_url : $fz_logo_square_url;
$fz_border_radius = getDolGlobalString('THEME_ELDY_USEBORDERONTABLE') ? getDolGlobalInt('THEME_ELDY_BORDER_RADIUS', 6) : 0;
$fz_row_hover = (isset($colorbacklinepairhover) && $colorbacklinepairhover !== '') ? 'var(--colorbacklinepairhover)' : 'color-mix(in srgb, var(--fz-nav-fg) 14%, transparent)';
$fz_row_checked = (isset($colorbacklinepairchecked) && $colorbacklinepairchecked !== '') ? 'var(--colorbacklinepairchecked)' : 'color-mix(in srgb, var(--fz-nav-fg) 22%, transparent)';
$fz_sub_row_hover = (isset($colorbacklinepairhover) && $colorbacklinepairhover !== '') ? 'var(--colorbacklinepairhover)' : 'color-mix(in srgb, var(--fz-sub-fg) 14%, transparent)';
$fz_sub_row_checked = (isset($colorbacklinepairchecked) && $colorbacklinepairchecked !== '') ? 'var(--colorbacklinepairchecked)' : 'color-mix(in srgb, var(--fz-sub-fg) 22%, transparent)';
$fz_nav_hover_fg = (isset($colorbacklinepairhover) && $colorbacklinepairhover !== '') ? 'var(--fz-text)' : 'var(--fz-nav-fg)';
$fz_nav_active_fg = (isset($colorbacklinepairchecked) && $colorbacklinepairchecked !== '') ? 'var(--fz-text)' : 'var(--fz-nav-fg)';
$fz_langcode = '';
if (isset($langs) && is_object($langs) && !empty($langs->defaultlang)) {
	$fz_langcode = (string) $langs->defaultlang;
} elseif (function_exists('GETPOST')) {
	$fz_langcode = (string) GETPOST('lang', 'aZ09');
}
$fz_langprefix = substr(strtolower(str_replace('_', '-', $fz_langcode)), 0, 2);
$fz_mode_words = array(
	'en' => array('display' => 'Display mode', 'light' => 'Light', 'auto' => 'Auto', 'dark' => 'Dark'),
	'fr' => array('display' => 'Mode d\'affichage', 'light' => 'Clair', 'auto' => 'Auto', 'dark' => 'Sombre'),
	'es' => array('display' => 'Modo de visualización', 'light' => 'Claro', 'auto' => 'Auto', 'dark' => 'Oscuro'),
	'de' => array('display' => 'Anzeigemodus', 'light' => 'Hell', 'auto' => 'Auto', 'dark' => 'Dunkel'),
	'it' => array('display' => 'Modalità di visualizzazione', 'light' => 'Chiaro', 'auto' => 'Auto', 'dark' => 'Scuro'),
	'pt' => array('display' => 'Modo de visualização', 'light' => 'Claro', 'auto' => 'Auto', 'dark' => 'Escuro'),
	'nl' => array('display' => 'Weergavemodus', 'light' => 'Licht', 'auto' => 'Auto', 'dark' => 'Donker'),
);
$fz_mode_word = isset($fz_mode_words[$fz_langprefix]) ? $fz_mode_words[$fz_langprefix] : $fz_mode_words['en'];
$fz_i18n = array(
	'search' => fz_fusion_trans('Search', 'Search'),
	'menu' => fz_fusion_trans('Menu', 'Menu'),
	'user' => fz_fusion_trans('User', 'User'),
	'mode_display' => $fz_mode_word['display'],
	'mode_light' => $fz_mode_word['light'],
	'mode_auto' => $fz_mode_word['auto'],
	'mode_dark' => $fz_mode_word['dark'],
);
?>
html.fusion{
	--fz-appname: <?php echo fz_fusion_css_string($fz_appname); ?>;   /* read by fusion.js */
	--fz-brand-label: <?php echo fz_fusion_css_string($fz_brand_label); ?>;
	--fz-logo-url: <?php echo fz_fusion_css_string($fz_logo_url); ?>;
	--fz-logo-square-url: <?php echo fz_fusion_css_string($fz_logo_square_url); ?>;
	--fz-logo-wide: <?php echo $fz_is_empty_appname ? '1' : '0'; ?>;
	--fz-menu-colorlogo: <?php echo getDolGlobalString('THEME_MENU_COLORLOGO') ? '1' : '0'; ?>;
	--fz-t-search: <?php echo fz_fusion_css_string($fz_i18n['search']); ?>;
	--fz-t-menu: <?php echo fz_fusion_css_string($fz_i18n['menu']); ?>;
	--fz-t-user: <?php echo fz_fusion_css_string($fz_i18n['user']); ?>;
	--fz-t-mode-display: <?php echo fz_fusion_css_string($fz_i18n['mode_display']); ?>;
	--fz-t-mode-light: <?php echo fz_fusion_css_string($fz_i18n['mode_light']); ?>;
	--fz-t-mode-auto: <?php echo fz_fusion_css_string($fz_i18n['mode_auto']); ?>;
	--fz-t-mode-dark: <?php echo fz_fusion_css_string($fz_i18n['mode_dark']); ?>;
	/* ---- geometry ---- */
	--fz-sb-w: 268px;            /* expanded sidebar width            */
	--fz-sb-w-collapsed: 66px;   /* icons-only sidebar width          */
	--fz-topbar-h: 54px;         /* portrait top bar height           */
	--fz-radius: <?php echo ((int) $fz_border_radius); ?>px;

	/* Bridge Fusion shell colors to Dolibarr/Eldy theme options.
	   Dolibarr "top menu" color drives Fusion's main sidebar.
	   Dolibarr "left menu" color drives Fusion's opened submenus. */
	--fz-nav-bg: var(--colorbackhmenu1, #0f172a);
	--fz-nav-bg2: color-mix(in srgb, var(--fz-nav-bg) 82%, #000);
	--fz-nav-fg: var(--colortextbackhmenu, #dbe3ef);
	--fz-nav-fg-dim: color-mix(in srgb, var(--fz-nav-fg) 62%, transparent);
	--fz-nav-hover: <?php echo $fz_row_hover; ?>;
	--fz-nav-active-bg: <?php echo $fz_row_checked; ?>;
	--fz-nav-hover-fg: <?php echo $fz_nav_hover_fg; ?>;
	--fz-nav-active-fg: <?php echo $fz_nav_active_fg; ?>;
	--fz-menu-icon-normal: <?php echo getDolGlobalString('THEME_MENU_COLORLOGO') ? 'var(--fz-menu-icon-color, var(--fz-nav-fg-dim))' : 'var(--fz-nav-fg-dim)'; ?>;
	--fz-menu-icon-hover: <?php echo getDolGlobalString('THEME_MENU_COLORLOGO') ? 'var(--fz-menu-icon-color, var(--fz-nav-hover-fg))' : 'var(--fz-nav-hover-fg)'; ?>;
	--fz-menu-icon-active: <?php echo getDolGlobalString('THEME_MENU_COLORLOGO') ? 'var(--fz-menu-icon-color, var(--fz-nav-active-fg))' : 'var(--fz-nav-active-fg)'; ?>;
	--fz-sub-bg: var(--colorbackvmenu1, color-mix(in srgb, var(--fz-nav-bg) 92%, #fff));
	--fz-sub-fg: var(--colortextbackvmenu, var(--fz-nav-fg));
	--fz-sub-fg-dim: color-mix(in srgb, var(--fz-sub-fg) 64%, transparent);
	--fz-sub-border: color-mix(in srgb, var(--fz-sub-fg) 22%, transparent);
	--fz-sub-hover: <?php echo $fz_sub_row_hover; ?>;
	--fz-sub-active-bg: <?php echo $fz_sub_row_checked; ?>;
	--fz-accent: var(--butactionbg, var(--colortextlink, var(--colorbackhmenu1, #2563eb)));
	--fz-accent-fg: var(--textbutaction, #ffffff);
	--fz-star: #f5b301;

	--fz-content-bg: var(--colorbackbody, #f4f6fb);
	--fz-surface: var(--colorbacktabcard1, var(--colorbacklinepair1, #ffffff));
	--fz-border: var(--inputbordercolor, var(--colorboxstatsborder, #e6e9f0));
	--fz-text: var(--colortext, #1f2733);
	--fz-text-dim: color-mix(in srgb, var(--fz-text) 62%, transparent);
	--fz-row-hover: <?php echo $fz_row_hover; ?>;
	--fz-row-checked: <?php echo $fz_row_checked; ?>;
	--fz-topbar-bg: var(--colorbackhmenu1, var(--fz-surface));
	--fz-topbar-fg: var(--colortextbackhmenu, var(--fz-text));
}

/*
 * Dark palette — redefines BOTH the fusion shell vars (--fz-*) AND eldy's own
 * theme variables (--color*) so the WHOLE app switches, not just the sidebar.
 * Applied when the user forces dark, or chooses "auto" and the OS is dark.
 */
html.fusion[data-fz-mode="dark"]{
	/* shell (sidebar keeps a subtle tint of the configured brand color) */
	--fz-nav-bg: color-mix(in srgb, var(--colorbackhmenu1, #0a0f1a) 22%, #0a0c12);
	--fz-nav-bg2: color-mix(in srgb, var(--fz-nav-bg) 84%, #000);
	--fz-nav-hover: rgba(255,255,255,.08);
	--fz-sub-bg: color-mix(in srgb, var(--colorbackvmenu1, #2b2c2e) 92%, #000);
	--fz-sub-hover: rgba(255,255,255,.08);
	--fz-content-bg:#1d1e20; --fz-surface:#26272b; --fz-border:#3a3b3e;
	--fz-text:#dcdcdc; --fz-text-dim:#9aa0a8; --fz-topbar-bg:#3d3e40; --fz-topbar-fg:rgb(220,220,220);
	/* eldy core */
	--colorbackhmenu1:#3d3e40; --colorbackvmenu1:#2b2c2e; --colorbacktitle1:#3b3c3e;
	--colorbacktabcard1:#1d1e20; --colorbacktabactive:rgb(220,220,220);
	--colorbacklineimpair1:#38393d; --colorbacklineimpair2:#2b2d2f;
	--colorbacklinepair1:#38393d; --colorbacklinepair2:#2b2d2f;
	--colorbacklinepairhover:#2b2d2f; --colorbacklinepairchecked:#0e5ccd;
	--colorbackbody:#1d1e20; --colorbackmobilemenu:#080808; --colorbackgrey:#0f0f0f;
	--tooltipbgcolor:#2b2d2f; --colortexttitlenotab:rgb(220,220,220);
	--colortexttitlenotab2:rgb(220,220,220); --colortexttitle:rgb(220,220,220);
	--colortext:rgb(220,220,220); --colortextlink:#4390dc; --colortexttitlelink:#4390dc;
	--colortextbackhmenu:rgb(220,220,220); --colortextbackvmenu:rgb(220,220,220);
	--tooltipfontcolor:rgb(220,220,220); --listetotal:rgb(245,83,158);
	--inputbackgroundcolor:rgb(70,70,70); --inputbackgroundcolordisabled:rgb(60,60,60);
	--inputcolordisabled:rgb(140,140,140); --inputbordercolor:rgb(120,120,120);
	--oddevencolor:rgb(220,220,220); --colorboxstatsborder:rgb(65,100,138);
	--dolgraphbg:#1d1e20; --fieldrequiredcolor:rgb(250,183,59);
	--colortextbacktab:rgb(220,220,220); --colorboxiconbg:rgb(36,38,39);
	--refidnocolor:rgb(220,220,220); --tableforfieldcolor:rgb(220,220,220);
	--colorblack:#fff; --colorwhite:#000;
}
html.fusion[data-fz-mode="dark"] body,
html.fusion[data-fz-mode="dark"] button{ color:#bbb; }

<?php if (getDolGlobalInt('THEME_DARKMODEENABLED') != 0) { ?>
@media (prefers-color-scheme: dark){
	html.fusion[data-fz-mode="auto"]{
		--fz-nav-bg: color-mix(in srgb, var(--colorbackhmenu1, #0a0f1a) 22%, #0a0c12);
		--fz-nav-bg2: color-mix(in srgb, var(--fz-nav-bg) 84%, #000);
		--fz-nav-hover: rgba(255,255,255,.08);
		--fz-sub-bg: color-mix(in srgb, var(--colorbackvmenu1, #2b2c2e) 92%, #000);
		--fz-sub-hover: rgba(255,255,255,.08);
		--fz-content-bg:#1d1e20; --fz-surface:#26272b; --fz-border:#3a3b3e;
		--fz-text:#dcdcdc; --fz-text-dim:#9aa0a8; --fz-topbar-bg:#3d3e40; --fz-topbar-fg:rgb(220,220,220);
		--colorbackhmenu1:#3d3e40; --colorbackvmenu1:#2b2c2e; --colorbacktitle1:#3b3c3e;
		--colorbacktabcard1:#1d1e20; --colorbacktabactive:rgb(220,220,220);
		--colorbacklineimpair1:#38393d; --colorbacklineimpair2:#2b2d2f;
		--colorbacklinepair1:#38393d; --colorbacklinepair2:#2b2d2f;
		--colorbacklinepairhover:#2b2d2f; --colorbacklinepairchecked:#0e5ccd;
		--colorbackbody:#1d1e20; --colorbackmobilemenu:#080808; --colorbackgrey:#0f0f0f;
		--tooltipbgcolor:#2b2d2f; --colortexttitlenotab:rgb(220,220,220);
		--colortexttitlenotab2:rgb(220,220,220); --colortexttitle:rgb(220,220,220);
		--colortext:rgb(220,220,220); --colortextlink:#4390dc; --colortexttitlelink:#4390dc;
		--colortextbackhmenu:rgb(220,220,220); --colortextbackvmenu:rgb(220,220,220);
		--tooltipfontcolor:rgb(220,220,220); --listetotal:rgb(245,83,158);
		--inputbackgroundcolor:rgb(70,70,70); --inputbackgroundcolordisabled:rgb(60,60,60);
		--inputcolordisabled:rgb(140,140,140); --inputbordercolor:rgb(120,120,120);
		--oddevencolor:rgb(220,220,220); --colorboxstatsborder:rgb(65,100,138);
		--dolgraphbg:#1d1e20; --fieldrequiredcolor:rgb(250,183,59);
		--colortextbacktab:rgb(220,220,220); --colorboxiconbg:rgb(36,38,39);
		--refidnocolor:rgb(220,220,220); --tableforfieldcolor:rgb(220,220,220);
		--colorblack:#fff; --colorwhite:#000;
	}
	html.fusion[data-fz-mode="auto"] body,
	html.fusion[data-fz-mode="auto"] button{ color:#bbb; }
}
<?php } ?>

/* ---------------------------------------------------------------------- *
 *  Hide the legacy menus (their useful nodes are moved into the sidebar)  *
 * ---------------------------------------------------------------------- */
html.fusion header#id-top,
html.fusion .side-nav,
html.fusion #id-left{
	display: none !important;
}
/* In case some pages keep a clearer div between header and container */
html.fusion body#mainbody > div[style*="clear"]{ display:none !important; }

/* ---------------------------------------------------------------------- *
 *  Content area : leave room for the fixed sidebar (landscape)            *
 * ---------------------------------------------------------------------- */
/* Offset the container by the sidebar width and constrain its width.
   Force block layout: with the left-menu cell removed, #id-right (a lone
   display:table-cell) would otherwise shrink-to-fit and leave a void at right. */
html.fusion #id-container{
	display: block !important;
	margin-left: var(--fz-sb-w) !important;
	width: calc(100% - var(--fz-sb-w)) !important;
	box-sizing: border-box !important;
	transition: margin-left .22s ease, width .22s ease;
	background: var(--fz-content-bg);
}
html.fusion.fz-collapsed #id-container{
	margin-left: var(--fz-sb-w-collapsed) !important;
	width: calc(100% - var(--fz-sb-w-collapsed)) !important;
}
/* hover-peek : the .fz-collapsed class is momentarily lifted so the rail renders
   expanded, but the content must NOT reflow — keep it at the collapsed offset so the
   expanded rail OVERLAYS it (an elevated shadow makes the overlay read as floating). */
html.fusion.fz-peek #id-container{
	margin-left: var(--fz-sb-w-collapsed) !important;
	width: calc(100% - var(--fz-sb-w-collapsed)) !important;
}
html.fz-peek #fz-sidebar{ box-shadow:0 0 40px rgba(0,0,0,.45); }
html.fusion #id-right{
	display: block !important;
	width: auto !important;
	float: none !important;
	margin-left: 0 !important;
	box-sizing: border-box;
	min-height: 100vh;
}
html.fusion body#mainbody{ background: var(--fz-content-bg); }

/* Neutralize 3rd-party module layout hacks that reserve the legacy left-menu
   width (e.g. timemoto: "#id-container{padding-left:260px}" on .page-index /
   .page-manager). The 2-id selector (body#mainbody + #id-container) outranks
   their 1-id selectors. Only the padding is killed here; the margin/width offset
   stays in the orientation-aware rules so portrait (drawer) is not affected. */
html.fusion body#mainbody #id-container{ padding-left: 0 !important; padding-right: 0 !important; }
html.fusion body#mainbody #id-right{ width: auto !important; max-width: none !important; }

/* ====================================================================== *
 *  THE SIDEBAR (single unified menu)                                      *
 * ====================================================================== */
#fz-sidebar{
	position: fixed; top:0; left:0; bottom:0;
	width: var(--fz-sb-w);
	display: flex; flex-direction: column;
	background: var(--fz-nav-bg);
	color: var(--fz-nav-fg);
	z-index: 1200;
	box-shadow: 2px 0 14px rgba(10,16,32,.10);
	transition: width .22s ease, transform .22s ease;
	font-family: inherit;
}
html.fz-collapsed #fz-sidebar{ width: var(--fz-sb-w-collapsed); }
/* never underline links anywhere in the menu (eldy underlines on hover) */
html.fusion #fz-sidebar a,html.fusion #fz-sidebar a:link,html.fusion #fz-sidebar a:visited,
html.fusion #fz-sidebar a:hover,html.fusion #fz-sidebar a:focus,html.fusion #fz-sidebar a:active,
html.fusion #fz-sidebar a *,html.fusion #fz-sidebar a:hover *,
html.fusion #fz-sidebar .dropdown-toggle,html.fusion #fz-sidebar .dropdown-toggle:hover,
html.fusion #fz-topbar a,html.fusion #fz-topbar a:hover{
	text-decoration:none !important;text-decoration-line:none !important}
/* THE fix: eldy does ".alogin:hover,.atoplogin:hover{text-decoration:underline!important}".
   The underline is set on the CONTAINER (#topmenu-login-dropdown / search / +/star all have
   class .atoplogin) and PROPAGATES to child text — a child text-decoration:none can't undo it.
   So we must neutralize it on the .atoplogin/.alogin element itself. */
html.fusion #fz-sidebar .atoplogin:hover,html.fusion #fz-sidebar .alogin:hover,
html.fusion #fz-sidebar .atoplogin:focus,html.fusion #fz-topbar .atoplogin:hover{
	text-decoration:none !important;text-decoration-line:none !important}
/* ultra-specific safety net for the user toggle (3 ids) + kill any border underline */
html.fusion #fz-sidebar #fz-user a,html.fusion #fz-sidebar #fz-user a:hover,
html.fusion #fz-sidebar #fz-user a:focus,html.fusion #fz-sidebar #fz-user a:active,
html.fusion #fz-sidebar #fz-user a span,html.fusion #fz-sidebar #fz-user a:hover span,
html.fusion #fz-sidebar #fz-user .atoploginusername{
	text-decoration:none !important;text-decoration-line:none !important;border-bottom:0 !important}

/* Brand + collapse button */
#fz-brand{
	height: var(--fz-topbar-h); flex:0 0 auto;
	display:flex; align-items:center; gap:10px; padding:0 17px;
}
#fz-brand .fz-logo{
	width:32px;height:32px;border-radius:var(--fz-radius);flex:0 0 auto;
	display:flex;align-items:center;justify-content:center;overflow:hidden;
	background:linear-gradient(135deg,#3b82f6,#8b5cf6);color:#fff;font-weight:800;
}
#fz-brand .fz-logo img{display:block;width:100%;height:100%;object-fit:contain}
/* When a real company logo is configured, show it as-is (no gradient background) */
#fz-brand .fz-logo.has-logo{background:none;border-radius:0}
#fz-brand .fz-logo.is-wide-logo{width:124px;height:34px;justify-content:flex-start}
#fz-brand .fz-brand-name{font-weight:700;color:var(--fz-nav-fg);font-size:15px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex:0 1 auto;min-width:0}
/* Dolibarr version badge, pushed to the right edge (margin-left:auto), with room
   kept on the right for the floating collapse button */
#fz-brand .fz-version{flex:0 0 auto;align-self:center;margin-left:auto;margin-right:16px;
	transform:translateX(10px);
	color:var(--fz-nav-fg-dim);font-size:11px;font-weight:700;white-space:nowrap;letter-spacing:.02em}
/* Slide button = a small tab attached to the sidebar edge. The chevron points
   toward the action: left to retract, right to reopen. */
#fz-brand .fz-collapse{
	position:fixed;left:var(--fz-sb-w);top:10px;z-index:1300;
	border:0;cursor:pointer;color:var(--fz-nav-fg);
	background:var(--fz-nav-bg);
	width:18px;height:34px;border-radius:0 var(--fz-radius) var(--fz-radius) 0;font-size:11px;
	display:flex;align-items:center;justify-content:center;box-sizing:border-box;
	padding-left:1px;
	box-shadow:10px 0 18px -8px rgba(0,0,0,.28);
	clip-path:inset(-28px -28px -28px 0);
	transition:left .22s ease,background .15s ease,box-shadow .15s ease;
}
#fz-brand .fz-collapse:hover{
	background:color-mix(in srgb, var(--fz-nav-bg) 88%, #fff);
	box-shadow:12px 0 22px -8px rgba(0,0,0,.32);
}
html.fz-collapsed #fz-brand .fz-collapse{left:var(--fz-sb-w-collapsed)}
/* Double chevron left when expanded; double chevron right when retracted. */
#fz-brand .fz-collapse i{font-size:12px;line-height:1;transform:none;transition:opacity .18s ease}
#fz-brand .fz-collapse.is-unlocked i{font-size:13px}

/* Compact primary-menu tooltips shown only when the sidebar is retracted. */
div.ui-tooltip.mytooltip.fz-menu-tooltip{
	min-width:0 !important;
	max-width:190px !important;
	width:auto !important;
	padding:8px 11px !important;
	line-height:1.25 !important;
	white-space:normal;
}

/* collapsed brand: center the square logo in the 66px rail. The collapse handle
   is fixed/out of flow, so it must not influence logo alignment. */
html.fz-collapsed #fz-brand{padding:0;gap:0;justify-content:center}
html.fz-collapsed #fz-brand .fz-logo{margin:0}
html.fz-collapsed #fz-brand .fz-logo.has-logo{width:44px;height:40px}
html.fz-collapsed #fz-brand .fz-logo.is-wide-logo{width:40px;height:40px}


/* Tools row (search + quick add), reusing Dolibarr nodes */
#fz-tools{padding:10px 12px 6px;display:flex;flex-direction:column;gap:8px;align-items:stretch;flex:0 0 auto;position:relative}
#fz-tools .fz-tools-search{width:100%;min-width:0}
#fz-tools .fz-tools-search input,
#fz-tools .fz-tools-search .select2-container{width:100% !important}
/* ---- Native search (#blockvmenusearch): the select2 "Search into …" combo,
   or a plugin search (ATM) — themed to sit in the sidebar ------------------- */
#fz-tools #blockvmenusearch{position:relative;width:100% !important;box-sizing:border-box;
	background:none !important;border:0 !important;border-top:0 !important;padding:0 !important;margin:0 !important}
#fz-tools #blockvmenusearch::before{content:"\f002";font-family:"Font Awesome 5 Free";font-weight:900;
	position:absolute;left:11px;top:18px;transform:translateY(-50%);color:var(--fz-nav-fg-dim);font-size:13px;z-index:2;pointer-events:none}
#fz-tools #blockvmenusearch .select2-container,#fz-tools .vmenusearchselectcombo{width:100% !important;box-sizing:border-box}
#fz-tools .select2-container--default .select2-selection--single{
	height:36px !important;display:flex !important;align-items:center;border-radius:var(--fz-radius) !important;
	background:rgba(255,255,255,.10) !important;border:1px solid rgba(255,255,255,.10) !important}
#fz-tools .select2-container--default .select2-selection--single .select2-selection__rendered{
	color:var(--fz-nav-fg) !important;line-height:normal !important;padding-left:32px !important;padding-right:24px !important}
#fz-tools .select2-container--default .select2-selection--single .select2-selection__placeholder{color:var(--fz-nav-fg-dim) !important}
#fz-tools .select2-container--default .select2-selection--single .select2-selection__arrow{height:34px !important;right:6px}
/* old-search-form fallback (plain input) */
#fz-tools #blockvmenusearch input[type="text"]{width:100% !important;box-sizing:border-box;height:36px;
	border-radius:var(--fz-radius);padding:6px 10px 6px 32px;background:rgba(255,255,255,.10);border:1px solid rgba(255,255,255,.10);color:var(--fz-nav-fg)}
/* icon row sits BELOW the search field */
#fz-tools .fz-tools-extra{display:flex;flex-direction:row;gap:6px;align-items:center;justify-content:space-evenly}
#fz-tools .fz-tools-extra a,
#fz-tools .fz-tools-extra .login_block_elem{color:var(--fz-nav-fg) !important}
/* loupe button : only shown when the sidebar is collapsed */
#fz-search-toggle{display:none;flex:0 0 auto;width:40px;height:38px;border:0;border-radius:var(--fz-radius);
	background:rgba(255,255,255,.10);color:var(--fz-nav-fg);cursor:pointer;align-items:center;justify-content:center;font-size:15px}
#fz-search-toggle:hover{background:var(--fz-nav-hover);color:var(--fz-nav-hover-fg)}

/* Scrollable nav */
#fz-nav{flex:1 1 auto;overflow-y:auto;overflow-x:hidden;padding:6px 8px 14px}
.fz-sec-label{font-size:11px;letter-spacing:.07em;text-transform:uppercase;color:var(--fz-nav-fg-dim);
	padding:14px 12px 6px;white-space:nowrap;overflow:hidden}

/* A navigation group = one old top-menu section */
.fz-group{margin:1px 0}
.fz-head{
	display:flex;align-items:center;gap:12px;padding:9px 12px;border-radius:var(--fz-radius);
	color:var(--fz-nav-fg);text-decoration:none;white-space:nowrap;cursor:pointer;position:relative;
	transition:padding .22s ease;
}
.fz-head:hover{background:var(--fz-nav-hover);color:var(--fz-nav-hover-fg)}
.fz-head .fz-ic{width:20px;flex:0 0 20px;text-align:center;font-size:15px;line-height:1;
	display:flex;align-items:center;justify-content:center;transition:font-size .22s ease}
.fz-head .fz-label{flex:1;overflow:hidden;text-overflow:ellipsis}
.fz-head .fz-chev{
	width:26px;height:26px;flex:0 0 26px;display:flex;align-items:center;justify-content:center;
	margin:-4px -6px -4px 0;border-radius:var(--fz-radius);font-size:11px;color:var(--fz-nav-fg-dim);
	cursor:pointer;transition:transform .18s ease,background .15s ease,color .15s ease}
.fz-group:not(.fz-has-sub):not(.fz-loading) > .fz-head .fz-chev{display:none}
.fz-head .fz-chev:hover{background:var(--fz-nav-hover);color:var(--fz-nav-hover-fg)}
.fz-group.fz-open > .fz-head .fz-chev{transform:rotate(90deg)}
/* on-demand submenu fetch: pulse the chevron while loading */
.fz-group.fz-loading > .fz-head .fz-chev{animation:fz-pulse .8s ease-in-out infinite}
@keyframes fz-pulse{50%{opacity:.25}}
.fz-group.fz-active > .fz-head{background:var(--fz-nav-active-bg);color:var(--fz-nav-active-fg)}
.fz-group.fz-active > .fz-head::before{content:"";position:absolute;left:-8px;top:6px;bottom:6px;width:3px;
	border-radius:0 var(--fz-radius) var(--fz-radius) 0;background:var(--fz-nav-active-fg)}

/* Fallback colors for synthetic icons. Native Dolibarr icons keep their own
   inline/class colors when THEME_MENU_COLORLOGO is enabled. */
.fz-group[data-code="home"]{--fz-menu-icon-color:var(--fz-nav-fg)}
.fz-group[data-code="companies"],.fz-group[data-code="thirdparties"],.fz-group[data-code="societe"],.fz-group[data-code="fournisseur"]{--fz-menu-icon-color:#6c6aa8}
.fz-group[data-code="products"],.fz-group[data-code="product"],.fz-group[data-code="produit"],.fz-group[data-code="service"],.fz-group[data-code="stock"],.fz-group[data-code="mrp"]{--fz-menu-icon-color:#a69944}
.fz-group[data-code="commercial"],.fz-group[data-code="contrat"],.fz-group[data-code="ficheinter"],.fz-group[data-code="ticket"]{--fz-menu-icon-color:#3bbfa8}
.fz-group[data-code="billing"],.fz-group[data-code="facture"],.fz-group[data-code="propale"],.fz-group[data-code="commande"],.fz-group[data-code="don"]{--fz-menu-icon-color:#65953d}
.fz-group[data-code="bank"],.fz-group[data-code="banque"],.fz-group[data-code="accountancy"],.fz-group[data-code="compta"],.fz-group[data-code="accounting"],.fz-group[data-code="tax"]{--fz-menu-icon-color:#b0bb39}
.fz-group[data-code="project"],.fz-group[data-code="projet"]{--fz-menu-icon-color:#6c6aa8}
.fz-group[data-code="hrm"],.fz-group[data-code="members"],.fz-group[data-code="adherent"]{--fz-menu-icon-color:#79633f}
.fz-group[data-code="agenda"],.fz-group[data-code="eventorganization"]{--fz-menu-icon-color:#906080}
.fz-group[data-code="holiday"]{--fz-menu-icon-color:#755114}
.fz-group[data-code="tools"],.fz-group[data-code="import"],.fz-group[data-code="export"],.fz-group[data-code="mailing"],.fz-group[data-code="cron"]{--fz-menu-icon-color:#999}
.fz-group[data-code="website"],.fz-group[data-code="externalsite"]{--fz-menu-icon-color:#304}
.fz-group[data-code="knowledgemanagement"],.fz-group[data-code="ecm"]{--fz-menu-icon-color:#3bbfa8}
.fz-head .fz-ic .fz-native-menu-icon{
	display:inline-flex !important;align-items:center;justify-content:center;width:20px !important;min-width:20px !important;
	height:20px !important;margin:0 !important;padding:0 !important;line-height:20px !important;text-align:center !important;
	vertical-align:middle !important;background-repeat:no-repeat !important;background-position:center center !important;
	background-size:20px 20px !important;position:static !important;top:auto !important;left:auto !important}
.fz-head .fz-ic img.fz-native-menu-icon,.fz-head .fz-ic .fz-native-menu-icon img{
	display:block !important;max-width:20px !important;max-height:20px !important;width:auto !important;height:auto !important;
	object-fit:contain;margin:0 !important;padding:0 !important}
.fz-head .fz-ic .fz-native-menu-icon::before,
.fz-head .fz-ic .fz-native-menu-icon i::before,
.fz-head .fz-ic .fz-native-menu-icon span::before{line-height:20px !important;text-align:center !important}
<?php if (getDolGlobalString('THEME_MENU_COLORLOGO')) { ?>
.fz-head .fz-ic:not(.fz-ic-native),.fz-head .fz-ic:not(.fz-ic-native) i,.fz-head .fz-ic:not(.fz-ic-native) span,
.fz-head .fz-ic:not(.fz-ic-native) [class*="fa-"],.fz-head .fz-ic:not(.fz-ic-native)::before,
.fz-head .fz-ic:not(.fz-ic-native) i::before,.fz-head .fz-ic:not(.fz-ic-native) span::before{
	color:var(--fz-menu-icon-normal) !important}
.fz-head .fz-ic.fz-ic-native .fz-native-menu-icon{filter:none !important}
<?php } else { ?>
.fz-head .fz-ic,.fz-head .fz-ic i,.fz-head .fz-ic span,.fz-head .fz-ic [class*="fa-"],
.fz-head .fz-ic::before,.fz-head .fz-ic i::before,.fz-head .fz-ic span::before{
	color:var(--fz-menu-icon-normal) !important}
.fz-head .fz-ic.fz-ic-native .fz-native-menu-icon{filter:saturate(0) grayscale(1) !important}
<?php } ?>

/* Submenu (old left-menu of the active section, moved here) */
.fz-sub{max-height:0;opacity:0;overflow:hidden;margin:2px 14px 2px 14px;
	border-left:2px solid var(--fz-sub-border);padding-left:4px;color:var(--fz-sub-fg);
	transition:max-height .24s ease,opacity .2s ease}
/* The open height is driven from JS (element scrollHeight) so any submenu length
   folds/unfolds smoothly — CSS cannot animate to/from max-height:auto. */
.fz-group.fz-open > .fz-sub{opacity:1;background:var(--fz-sub-bg)}

/* Re-skin + compact the Dolibarr left-menu nodes moved inside .fz-sub.
   eldy emits: .blockvmenu > .menu_titre(a.vmenu) + .menu_top + .menu_contenu(&nbsp;* a.vsmenu <br>)* + .menu_end
   The legacy <br> and decorative spacers are removed; each .menu_contenu becomes a tight row. */
.fz-sub .blockvmenu,.fz-sub .menu_titre,.fz-sub .menu_contenu,
.fz-sub .blockvmenuend,.fz-sub .blockvmenupair,.fz-sub .blockvmenuimpair{
	background:none !important;border:0 !important;margin:0 !important;width:auto !important;
	min-height:0 !important;line-height:1.3 !important;color:var(--fz-sub-fg) !important}
.fz-sub .menu_top,.fz-sub .menu_end{display:none !important}       /* decorative spacers */
.fz-sub br{display:none !important}                                /* legacy line breaks */

/* Section heading (.menu_titre) */
/* pull the section sub-titles (Products, Services, Warehouses…) left, out of the
   submenu indent, so they sit under the main group icon while children stay indented */
.fz-sub .menu_titre{position:relative;padding:7px 12px !important;margin-left:-15px !important;border-radius:var(--fz-radius)}
/* highlight the whole section-title row on hover, like the child rows */
.fz-sub .menu_titre:hover{background:var(--fz-sub-hover)}
.fz-sub .menu_titre a.vmenu::after{content:"";position:absolute;inset:0}
.fz-sub .menu_titre a.vmenu,.fz-sub .menu_titre span.vmenu{
	display:flex;align-items:center;gap:7px;color:var(--fz-sub-fg) !important;font-weight:600 !important;
	text-decoration:none;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.fz-sub .menu_titre:hover a.vmenu,.fz-sub .menu_titre:hover .fas,
.fz-sub .menu_titre:hover .far,.fz-sub .menu_titre:hover .fa{color:var(--fz-sub-fg) !important}
.fz-sub .menu_titre .fas,.fz-sub .menu_titre .far,.fz-sub .menu_titre .fa{color:var(--fz-sub-fg-dim) !important}

/* Sub-item rows (.menu_contenu). a.vsmenu stays inline so the &nbsp; indentation
   keeps showing the hierarchy on the same line; ::after makes the whole row clickable. */
.fz-sub .menu_contenu{position:relative;display:block !important;padding:4px 10px 4px 30px !important;border-radius:var(--fz-radius)}
.fz-sub .menu_contenu:hover{background:var(--fz-sub-hover) !important}
.fz-sub .menu_contenu a.vsmenu,.fz-sub .menu_contenu span.vsmenu{
	display:inline !important;color:var(--fz-sub-fg-dim) !important;
	text-decoration:none;font-size:13px;font-weight:400 !important;white-space:nowrap}
.fz-sub .menu_contenu.fz-subhead a.vsmenu,.fz-sub .menu_contenu.fz-subhead span.vsmenu{
	color:var(--fz-sub-fg) !important;font-weight:600 !important}
.fz-sub .menu_contenu:hover a.vsmenu{color:var(--fz-sub-fg) !important}
.fz-sub .menu_contenu a.vsmenu::after{content:"";position:absolute;inset:0}
.fz-sub .vsmenudisabled{color:var(--fz-sub-fg-dim) !important;opacity:.6}
.fz-sub .menu_titre img,.fz-sub .menu_contenu img{filter:none}

/* Collapsible sub-menus (any depth) : fusion.js re-nests the left-menu rows so every
   node with deeper children becomes a .fz-subhead immediately followed by its
   .fz-subsub panel. The panel folds/unfolds (height + opacity) with the head's state;
   a chevron shows it. fz-subopen lives on the HEAD so nesting works. */
.fz-subsub{max-height:0;opacity:0;overflow:hidden;transition:max-height .24s ease,opacity .2s ease}
.fz-subhead.fz-subopen + .fz-subsub{opacity:1}
.fz-sub .fz-subhead{cursor:default}
.fz-sub .menu_titre.fz-subhead,.fz-sub .menu_contenu.fz-subhead{padding-right:30px !important}
.fz-subhead > .fz-subchev{
	position:absolute;right:4px;top:50%;z-index:2;display:flex;align-items:center;justify-content:center;
	width:24px;height:24px;border-radius:var(--fz-radius);transform:translateY(-50%);font-size:10px;
	color:var(--fz-sub-fg-dim);cursor:pointer;transition:transform .2s ease,background .15s ease,color .15s ease}
.fz-subhead > .fz-subchev:hover{background:var(--fz-sub-hover);color:var(--fz-sub-fg)}
.fz-subhead.fz-subopen > .fz-subchev{transform:translateY(-50%) rotate(90deg)}

/* Favorites (bookmark module) section */
#fz-fav .fz-head .fz-ic{color:var(--fz-star)}
#fz-fav a{display:flex;align-items:center;gap:10px;padding:7px 12px;border-radius:var(--fz-radius);
	color:var(--fz-nav-fg) !important;text-decoration:none;font-size:13px;white-space:nowrap;overflow:hidden}
#fz-fav a:hover{background:var(--fz-nav-hover);color:var(--fz-nav-hover-fg) !important}
#fz-fav a .fa-star,#fz-fav .fas{color:var(--fz-star) !important}

/* Footer (help/version + user) */
#fz-foot{flex:0 0 auto;padding:8px;position:relative}
#fz-foot .blockvmenuhelp,#fz-foot #blockvmenuhelp{background:none !important;border:0 !important;
	padding:0 6px 6px !important;font-size:11px}
#fz-foot a{color:var(--fz-nav-fg-dim) !important}
#fz-user{display:flex;align-items:center;gap:10px;padding:6px 0;border-radius:var(--fz-radius);min-width:0}
#fz-user .login_block_user,#fz-user .login_block_elem{
	display:flex;align-items:center;gap:10px;color:var(--fz-nav-fg) !important;
	width:100%;min-width:0;height:auto !important;line-height:normal !important;float:none !important}
/* toggle (on the sidebar colour) = white text, no underline */
#fz-user .login-dropdown-a,#fz-user .login-dropdown-a span,#fz-user .atoploginusername{
	color:var(--fz-nav-fg) !important;text-decoration:none !important}
/* dropdown popup (on a light surface) = dark text, including inner spans/labels */
#fz-user .dropdown-menu,#fz-user .dropdown-menu a,#fz-user .dropdown-menu span,
#fz-user .dropdown-menu b,#fz-user .dropdown-menu p,#fz-user .dropdown-menu small,
#fz-user .dropdown-menu .button-top-menu-dropdown{color:var(--fz-text) !important}
#fz-user img.photouserphoto,#fz-user .photologin{width:34px;height:34px;box-sizing:border-box;border-radius:50%;object-fit:cover}

/* ---------- collapsed (icons only) ---------- */
html.fz-collapsed #fz-brand .fz-brand-name,
html.fz-collapsed #fz-brand .fz-version,
html.fz-collapsed .fz-head .fz-label,
html.fz-collapsed .fz-head .fz-chev,
html.fz-collapsed .fz-sec-label,
html.fz-collapsed .fz-sub,
html.fz-collapsed #fz-fav a .fz-favlabel,
html.fz-collapsed #fz-foot .blockvmenuhelp,
html.fz-collapsed #fz-user .atoploginusername,
html.fz-collapsed #fz-user .hideonsmartphone{display:none !important}
/* symmetric padding centers the lone icon AND lets it slide there (animatable),
   instead of snapping via justify-content during the horizontal collapse */
html.fz-collapsed .fz-head{padding:11px 15px}
html.fz-collapsed .fz-head .fz-ic{font-size:17px}
/* user avatar : eldy forces `padding:0 3px 0 4px !important` on .login_block_elem,
   which shoved the avatar ~4px right in flex-start (expanded) while the collapsed
   centering hid it — that asymmetry was the jump. Neutralize that padding and the
   wrapper gaps so the avatar's left edge is the SAME in both modes: foot 8px + the
   <a> padding 8px = 16px → 16 + 34 + 16 = the 66px rail. So it is centered when
   collapsed AND figé (no jump) when expanding, with plain flex-start everywhere. */
/* Zero every eldy gap/padding in the chain so the avatar sits at a FIXED left edge
   (foot 8px + <a> padding 8px = 16px) in BOTH modes. 16 + 34 (avatar) + 16 = the
   66px rail → centered when collapsed AND, being flex-start (left-anchored), it
   never moves during the width animation. We deliberately do NOT use
   justify-content:center: that isn't animatable, so it would snap the avatar to the
   middle of the still-wide container at the start of the collapse and swing back. */
#fz-user,
#fz-user .login_block_user,
#fz-user .login_block_elem,
#fz-user #topmenu-login-dropdown{gap:0}
#fz-user .login_block_elem{padding:0 !important}
/* collapsed user : show ONLY the centered avatar (hide the name + any caret/arrow) */
html.fz-collapsed #fz-user .login-dropdown-a > *:not(img):not(.photo){display:none !important}
html.fz-collapsed #fz-user .dropdown-toggle::after,
html.fz-collapsed #fz-user .dropdown-toggle::before{content:none !important;display:none !important;border:0 !important}

/* collapsed tools : stack icons in the narrow rail; search becomes a flyout */
html.fz-collapsed #fz-tools{flex-direction:column;align-items:center;gap:6px;justify-content:center}
html.fz-collapsed #fz-search-toggle{display:flex}
html.fz-collapsed #fz-tools .fz-tools-extra{margin-left:0;flex-direction:column;gap:6px}
html.fz-collapsed #fz-tools .fz-tools-search{
	position:absolute;left:calc(100% + 6px);top:6px;width:0;opacity:0;overflow:visible;
	pointer-events:none;transition:width .22s ease,opacity .18s ease;z-index:1600}
html.fz-collapsed.fz-search-open #fz-tools .fz-tools-search{width:264px;opacity:1;pointer-events:auto}
/* search flyout and +/star dropdowns are mutually exclusive in collapsed mode */
html.fz-collapsed.fz-search-open #fz-tools .fz-tools-extra .dropdown-menu{display:none !important}
/* the flyout itself is the light card (content-agnostic: works for the select2
   combo or a plugin search) */
html.fz-collapsed.fz-search-open #fz-tools .fz-tools-search{
	background:var(--fz-surface);border:1px solid var(--fz-border);border-radius:var(--fz-radius);
	box-shadow:0 14px 36px rgba(0,0,0,.4);padding:8px;box-sizing:border-box;
	max-height:calc(100vh - var(--fz-topbar-h) - 20px);overflow:visible}
html.fz-collapsed #fz-tools .fz-tools-search #blockvmenusearch::before{color:var(--fz-text-dim)}
html.fz-collapsed #fz-tools .fz-tools-search .select2-selection--single,
html.fz-collapsed #fz-tools .fz-tools-search input{
	background:var(--fz-content-bg) !important;border:1px solid var(--fz-border) !important;color:var(--fz-text) !important}
html.fz-collapsed #fz-tools .fz-tools-search input::placeholder{color:var(--fz-text-dim) !important}
html.fz-collapsed #fz-tools .fz-tools-search .search-dropdown-header::before{color:var(--fz-text-dim) !important}
html.fz-collapsed #fz-tools .fz-tools-search .select2-selection__rendered{color:var(--fz-text) !important}
html.fz-collapsed #fz-tools .fz-tools-search .select2-selection__placeholder{color:var(--fz-text-dim) !important}
/* couple the input and its scope list as ONE panel inside the flyout card:
   keep the list ALWAYS in normal flow in collapsed mode (no display toggling), so
   the flyout's own opacity/width transition fades them in AND out TOGETHER — they
   never appear nor disappear one after the other. */
html.fz-collapsed #fz-tools .search-dropdown-body{
	display:block !important;position:static !important;left:auto !important;right:auto !important;top:auto !important;
	margin-top:6px !important;background:none !important;border:0 !important;box-shadow:none !important;
	padding:0 !important;max-height:50vh;overflow:auto}
/* +, star, import dropdowns fly out to the right when collapsed */
html.fz-collapsed #fz-tools .dropdown-menu{left:calc(100% + 6px) !important;right:auto !important;top:0 !important;width:260px !important}
/* ====================================================================== *
 *  PORTRAIT TOP BAR (only shown in narrow / portrait mode)               *
 * ====================================================================== */
#fz-topbar{
	display:none;
	position:fixed;top:0;left:0;right:0;height:var(--fz-topbar-h);z-index:1100;
	background:var(--fz-topbar-bg);border-bottom:1px solid var(--fz-border);
	align-items:center;gap:10px;padding:0 12px;color:var(--fz-topbar-fg);
}
#fz-topbar .fz-burger{background:none;border:0;font-size:18px;color:var(--fz-topbar-fg);cursor:pointer;
	width:38px;height:38px;border-radius:var(--fz-radius);display:flex;align-items:center;justify-content:center}
#fz-topbar .fz-burger:hover{background:rgba(127,127,127,.12)}
#fz-topbar .fz-tb-title{font-weight:700;color:var(--fz-topbar-fg);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex:1}
#fz-scrim{position:fixed;inset:0;background:rgba(7,12,25,.5);opacity:0;visibility:hidden;
	transition:opacity .2s ease;z-index:1150}

/* ---------------------------------------------------------------------- *
 *  RESPONSIVE SWITCH  →  narrow / portrait : sidebar becomes a drawer     *
 * ---------------------------------------------------------------------- */
@media only screen and (max-width: 920px){
	html.fusion #fz-topbar{display:flex}
	html.fusion #fz-sidebar{transform:translateX(-100%);width:var(--fz-sb-w);box-shadow:0 0 40px rgba(0,0,0,.45)}
	html.fusion.fz-drawer #fz-sidebar{transform:translateX(0)}
	html.fusion.fz-drawer #fz-scrim{opacity:1;visibility:visible}
	html.fusion #id-container{margin-left:0 !important;width:100% !important;padding-top:var(--fz-topbar-h) !important}
	html.fusion #id-right{margin-left:0 !important}
	/* never use the icons-only mode in portrait */
	html.fusion.fz-collapsed #fz-sidebar{width:var(--fz-sb-w)}
	html.fusion.fz-collapsed #fz-brand .fz-brand-name,
	html.fusion.fz-collapsed .fz-head .fz-label,
	html.fusion.fz-collapsed .fz-head .fz-chev,
	html.fusion.fz-collapsed .fz-sec-label,
	html.fusion.fz-collapsed .fz-sub,
	html.fusion.fz-collapsed #fz-tools .fz-tools-search{display:block !important}
	html.fusion.fz-collapsed #fz-user .atoploginusername,
	html.fusion.fz-collapsed #fz-user .hideonsmartphone{display:inline-block !important}
	html.fz-collapsed .fz-head{justify-content:flex-start;padding:9px 12px}
	html.fusion.fz-collapsed #fz-user{padding:6px;justify-content:flex-start}
	html.fusion.fz-collapsed #fz-user #topmenu-login-dropdown{width:100%;padding:0 5px}
	html.fusion.fz-collapsed #fz-user #topmenu-login-dropdown > a{
		width:100%;height:auto;justify-content:flex-start}
	html.fusion #fz-brand .fz-collapse{display:none}
	/* tools always inline in the drawer (no loupe / no flyout in portrait) */
	html.fusion #fz-search-toggle{display:none !important}
	html.fusion #fz-tools{flex-direction:column !important;align-items:stretch !important}
	html.fusion #fz-tools .fz-tools-search{position:static !important;width:100% !important;opacity:1 !important;pointer-events:auto !important}
	html.fusion #fz-tools .fz-tools-search #topmenu-global-search-dropdown{background:none !important;border:0 !important;
		box-shadow:none !important;padding:0 !important;width:100% !important}
	html.fusion #fz-tools .fz-tools-extra{flex-direction:row !important;justify-content:space-evenly !important}
	html.fusion #fz-tools .dropdown-menu{left:8px !important;right:8px !important;width:auto !important;top:calc(100% + 6px) !important}
}

/* On very small screens, drop the content side padding a bit */
@media only screen and (max-width: 480px){
	html.fusion #id-right{padding-left:6px !important;padding-right:6px !important}
}

/* ====================================================================== *
 *  Controls and dropdowns                                                *
 * ====================================================================== */

/* Contrast safety : force readable text on every nav entry */
.fz-head,.fz-head .fz-label{color:var(--fz-nav-fg) !important;opacity:1}
.fz-head .fz-ic{opacity:1}
<?php if (getDolGlobalString('THEME_MENU_COLORLOGO')) { ?>
.fz-head .fz-ic:not(.fz-ic-native),
.fz-head .fz-ic:not(.fz-ic-native) i,
.fz-head .fz-ic:not(.fz-ic-native) span,
.fz-head .fz-ic:not(.fz-ic-native) [class*="fa-"],
.fz-head .fz-ic:not(.fz-ic-native)::before,
.fz-head .fz-ic:not(.fz-ic-native) i::before,
.fz-head .fz-ic:not(.fz-ic-native) span::before{
	color:var(--fz-menu-icon-normal) !important;opacity:1}
.fz-group.fz-active > .fz-head,.fz-group.fz-active > .fz-head .fz-label{color:var(--fz-nav-active-fg) !important}
.fz-group.fz-active > .fz-head .fz-ic:not(.fz-ic-native),
.fz-group.fz-active > .fz-head .fz-ic:not(.fz-ic-native) i,
.fz-group.fz-active > .fz-head .fz-ic:not(.fz-ic-native) span,
.fz-group.fz-active > .fz-head .fz-ic:not(.fz-ic-native) [class*="fa-"],
.fz-group.fz-active > .fz-head .fz-ic:not(.fz-ic-native)::before,
.fz-group.fz-active > .fz-head .fz-ic:not(.fz-ic-native) i::before,
.fz-group.fz-active > .fz-head .fz-ic:not(.fz-ic-native) span::before{color:var(--fz-menu-icon-active) !important}
.fz-head:hover,.fz-head:hover .fz-label{color:var(--fz-nav-hover-fg) !important}
.fz-head:hover .fz-ic:not(.fz-ic-native),
.fz-head:hover .fz-ic:not(.fz-ic-native) i,
.fz-head:hover .fz-ic:not(.fz-ic-native) span,
.fz-head:hover .fz-ic:not(.fz-ic-native) [class*="fa-"],
.fz-head:hover .fz-ic:not(.fz-ic-native)::before,
.fz-head:hover .fz-ic:not(.fz-ic-native) i::before,
.fz-head:hover .fz-ic:not(.fz-ic-native) span::before{color:var(--fz-menu-icon-hover) !important}
<?php } else { ?>
.fz-head .fz-ic,.fz-head .fz-ic i,.fz-head .fz-ic span,.fz-head .fz-ic [class*="fa-"],
.fz-head .fz-ic::before,.fz-head .fz-ic i::before,.fz-head .fz-ic span::before{
	color:var(--fz-menu-icon-normal) !important;opacity:1}
.fz-group.fz-active > .fz-head,.fz-group.fz-active > .fz-head .fz-label{color:var(--fz-nav-active-fg) !important}
.fz-group.fz-active > .fz-head .fz-ic,
.fz-group.fz-active > .fz-head .fz-ic i,
.fz-group.fz-active > .fz-head .fz-ic span,
.fz-group.fz-active > .fz-head .fz-ic [class*="fa-"],
.fz-group.fz-active > .fz-head .fz-ic::before,
.fz-group.fz-active > .fz-head .fz-ic i::before,
.fz-group.fz-active > .fz-head .fz-ic span::before{color:var(--fz-menu-icon-active) !important}
.fz-head:hover,.fz-head:hover .fz-label{color:var(--fz-nav-hover-fg) !important}
.fz-head:hover .fz-ic,
.fz-head:hover .fz-ic i,
.fz-head:hover .fz-ic span,
.fz-head:hover .fz-ic [class*="fa-"],
.fz-head:hover .fz-ic::before,
.fz-head:hover .fz-ic i::before,
.fz-head:hover .fz-ic span::before{color:var(--fz-menu-icon-hover) !important}
<?php } ?>
.fz-group.fz-active > .fz-head .fz-chev{color:var(--fz-nav-active-fg) !important}
.fz-head:hover .fz-chev{color:var(--fz-nav-hover-fg) !important}

/* Color-mode segmented control */
.fz-modes{display:flex;gap:4px;margin:6px;padding:3px;border-radius:var(--fz-radius);background:rgba(255,255,255,.06)}
@media only screen and (min-width: 921px){
	html.fz-collapsed .fz-modes{
		display:block;position:relative;width:40px;height:40px;margin:4px auto 8px;padding:0;
		background:transparent;border-radius:var(--fz-radius);outline:none;overflow:visible;z-index:1510;cursor:pointer}
	html.fz-collapsed .fz-modes::before{
		content:"\f042";font-family:"Font Awesome 5 Free";font-weight:900;
		position:absolute;inset:0;display:grid;place-items:center;
		width:40px;height:40px;border-radius:var(--fz-radius);background:rgba(255,255,255,.10);
		color:var(--fz-nav-fg);font-size:16px;line-height:1;box-sizing:border-box}
	html.fz-collapsed[data-fz-mode="light"] .fz-modes::before{content:"\f185"}
	html.fz-collapsed[data-fz-mode="dark"] .fz-modes::before{content:"\f186"}
	html.fz-collapsed .fz-modes:hover::before,
	html.fz-collapsed .fz-modes:focus-visible::before,
	html.fz-collapsed .fz-modes.fz-modes-open::before{background:var(--fz-nav-hover);color:var(--fz-nav-hover-fg)}
	html.fz-collapsed .fz-modes::after{
		content:"";position:absolute;left:calc(100% + 8px);bottom:0;width:124px;height:44px;
		background:var(--fz-surface);border:1px solid var(--fz-border);border-radius:var(--fz-radius);
		box-shadow:0 12px 34px rgba(0,0,0,.4);opacity:0;transform:translateX(-4px);
		pointer-events:none;transition:opacity .15s ease,transform .15s ease}
	html.fz-collapsed .fz-modes.fz-modes-open::after{opacity:1;transform:translateX(0)}
	html.fz-collapsed .fz-modes .fz-mode-btn{
		position:absolute;top:0;left:calc(100% + 12px);z-index:1;
		width:36px;height:36px;min-width:36px;display:block;padding:0;border-radius:var(--fz-radius);
		background:transparent;color:var(--fz-text-dim);opacity:0;transform:translateX(-4px);
		pointer-events:none;line-height:1;box-sizing:border-box;
		transition:opacity .15s ease,transform .15s ease,background .15s ease,color .15s ease}
	html.fz-collapsed .fz-modes .fz-mode-btn::before{
		content:"\f185";font-family:"Font Awesome 5 Free";font-weight:900;
		position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);
		width:18px;height:18px;line-height:18px;text-align:center;font-size:16px}
	html.fz-collapsed .fz-modes .fz-mode-btn[data-mode="auto"]::before{content:"\f042"}
	html.fz-collapsed .fz-modes .fz-mode-btn[data-mode="dark"]::before{content:"\f186"}
	html.fz-collapsed .fz-modes .fz-mode-btn[data-mode="auto"]{left:calc(100% + 52px)}
	html.fz-collapsed .fz-modes .fz-mode-btn[data-mode="dark"]{left:calc(100% + 92px)}
	html.fz-collapsed .fz-modes.fz-modes-open .fz-mode-btn{opacity:1;transform:translateX(0);pointer-events:auto}
	html.fz-collapsed .fz-modes .fz-mode-btn:hover{background:var(--fz-row-hover);color:var(--fz-text)}
	html.fz-collapsed .fz-modes .fz-mode-btn.is-active{background:var(--fz-row-checked);color:var(--fz-text)}
	html.fz-collapsed .fz-modes .fz-mode-btn i{display:none !important}
	html.fz-collapsed .fz-modes .fz-mode-lbl{display:none}
}
.fz-mode-btn{flex:1;display:flex;align-items:center;justify-content:center;gap:6px;cursor:pointer;
	border:0;background:none;color:var(--fz-nav-fg-dim);padding:6px 4px;border-radius:var(--fz-radius);font-size:12px;
	font-family:inherit;transition:background .15s ease,color .15s ease}
.fz-mode-btn:hover{color:var(--fz-nav-fg)}
.fz-mode-btn.is-active{background:var(--fz-nav-active-bg);color:var(--fz-nav-active-fg)}
.fz-mode-btn .fz-mode-lbl{font-size:12px}

/* ---- User dropdown : make it open UPWARD and inside the sidebar ------- */
#fz-user{position:relative}
#fz-user #topmenu-login-dropdown{position:static !important;width:100%;padding:0}
#fz-user #topmenu-login-dropdown > a{
	display:flex;align-items:center;gap:10px;padding:6px 8px;border-radius:var(--fz-radius);width:100%;box-sizing:border-box;min-width:0;overflow:hidden;
	color:var(--fz-nav-fg) !important;text-decoration:none}
#fz-user #topmenu-login-dropdown > a:hover{background:var(--fz-nav-hover);color:var(--fz-nav-hover-fg) !important}
#fz-user #topmenu-login-dropdown > a:hover .atoploginusername{color:var(--fz-nav-hover-fg) !important}
#fz-user .photouserphoto,#fz-user .dropdown-user-image{width:34px !important;height:34px !important;
	box-sizing:border-box !important;border-radius:50% !important;object-fit:cover}
#fz-user .atoploginusername{
	color:var(--fz-nav-fg) !important;font-weight:600;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1 1 auto}
/* the panel itself */
#fz-user .dropdown-menu{
	position:absolute !important;left:0;right:0;bottom:54px;top:auto !important;box-sizing:border-box;
	width:auto !important;min-width:0 !important;max-height:70vh;overflow:auto;
	display:none;background:var(--fz-surface);color:var(--fz-text);
	border:1px solid var(--fz-border);border-radius:var(--fz-radius);box-shadow:0 12px 34px rgba(0,0,0,.45);
	padding:10px;z-index:1500;font-size:13px}
#fz-user #topmenu-login-dropdown.open .dropdown-menu{display:block !important}
#fz-user .dropdown-menu a,#fz-user .dropdown-menu .button-top-menu-dropdown{color:var(--fz-text) !important}
/* eldy hard-codes light #f9f9f9 / #f4f4f4 backgrounds + black text on these blocks,
   which breaks the themed (esp. dark) panel — re-skin them with theme variables. */
#fz-user .dropdown-menu .user-header{text-align:center;border-bottom:1px solid var(--fz-border);padding-bottom:8px;margin-bottom:8px;
	background:transparent !important;color:var(--fz-text) !important}
#fz-user .dropdown-menu .user-footer{display:flex;gap:8px;flex-wrap:wrap;border-top:1px solid var(--fz-border);
	padding-top:8px;margin-top:8px;background:transparent !important}
#fz-user .dropdown-menu .user-footer .button-top-menu-dropdown{border:1px solid var(--fz-border) !important;
	background:var(--fz-content-bg) !important;color:var(--fz-text) !important;
	border-radius:var(--fz-radius);height:36px;min-width:36px;box-sizing:border-box;padding:0 14px;text-decoration:none;
	display:inline-flex;align-items:center;justify-content:center;gap:6px;line-height:1}
#fz-user .dropdown-menu .user-footer .button-top-menu-dropdown:hover{background:var(--fz-row-hover) !important;color:var(--fz-text) !important}
#fz-user .dropdown-menu .pull-left,#fz-user .dropdown-menu .pull-right{float:none !important;display:flex}
html.fz-collapsed #fz-user .dropdown-menu{
	left:calc(100% + 8px) !important;right:auto !important;bottom:0;width:280px !important;
	max-width:calc(100vw - var(--fz-sb-w-collapsed) - 20px)}
@media only screen and (max-width: 920px){
	html.fusion.fz-collapsed #fz-user .dropdown-menu{
		left:6px !important;right:6px !important;bottom:54px;width:auto !important;max-width:none}
}

/* ---- Native global search, shown inline in the sidebar --------------- */
#fz-tools #topmenu-global-search-dropdown{position:static;width:100%;min-width:0;display:block}
#fz-tools #topmenu-global-search-dropdown > a.dropdown-toggle{display:none} /* hide magnifier toggle */
#fz-tools #topmenu-global-search-dropdown .dropdown-menu.dropdown-search{
	display:block !important;position:static !important;background:none !important;border:0 !important;
	box-shadow:none !important;padding:0 !important;margin:0 !important;width:auto !important;min-width:0 !important}
#fz-tools .search-dropdown-header{position:relative;padding:0}
#fz-tools .search-dropdown-header::before{content:"\f002";font-family:"Font Awesome 5 Free";font-weight:900;
	position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--fz-nav-fg-dim);font-size:13px;pointer-events:none}
#fz-tools #top-global-search-input{
	width:100%;box-sizing:border-box;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.06);
	border-radius:var(--fz-radius);padding:8px 10px 8px 32px;color:var(--fz-nav-fg);font-size:13px;outline:none}
#fz-tools #top-global-search-input::placeholder{color:var(--fz-nav-fg-dim)}
/* force every inner wrapper of the native search to span its full slot width
   (.fz-tools-search itself is excluded: its width is flex/flyout-controlled) */
#fz-tools #topmenu-global-search-dropdown,
#fz-tools #topmenu-global-search-dropdown .dropdown-menu.dropdown-search,
#fz-tools #top-menu-action-search,
#fz-tools .search-dropdown-header,
#fz-tools #top-global-search-input{width:100% !important;max-width:none !important;min-width:0 !important;box-sizing:border-box !important}
#fz-tools #top-menu-action-search,
#fz-tools .search-dropdown-header{margin:0 !important;padding:0 !important;display:block !important}
#fz-tools .fz-fallback-search{
	display:flex;align-items:center;gap:8px;background:rgba(255,255,255,.07);
	border:1px solid rgba(255,255,255,.06);border-radius:var(--fz-radius);padding:7px 10px;box-sizing:border-box}
#fz-tools .fz-fallback-search-icon{color:var(--fz-nav-fg-dim);opacity:.65}
#fz-tools .fz-fallback-search input{
	flex:1;min-width:0;background:none;border:0;outline:0;color:var(--fz-nav-fg);font-size:13px;font-family:inherit}
html.fz-collapsed #fz-tools .fz-tools-search .fz-fallback-search{
	width:264px;background:var(--fz-surface);border-color:var(--fz-border);
	box-shadow:0 14px 36px rgba(0,0,0,.4);padding:8px}
html.fz-collapsed #fz-tools .fz-fallback-search input{color:var(--fz-text)}
@media only screen and (max-width: 920px){
	html.fusion.fz-collapsed #fz-tools #top-global-search-input{
		background:rgba(255,255,255,.07);color:var(--fz-nav-fg);border-color:rgba(255,255,255,.06)}
	html.fusion.fz-collapsed #fz-tools #top-global-search-input::placeholder{color:var(--fz-nav-fg-dim)}
	html.fusion.fz-collapsed #fz-tools .fz-tools-search .fz-fallback-search{
		width:100%;background:rgba(255,255,255,.07);border-color:rgba(255,255,255,.06);
		box-shadow:none;padding:7px 10px}
	html.fusion.fz-collapsed #fz-tools .fz-fallback-search input{color:var(--fz-nav-fg)}
}
/* the per-type scope list appears only while the field is focused.
   It anchors to #fz-tools (position:relative): the inner search wrappers collapse
   to ~0 height (Bootstrap's .dropdown-menu is out of flow) so % offsets are
   unreliable. top is therefore a fixed offset = #fz-tools padding-top (10px) +
   search field height (~36px) + 6px gap = the field's bottom + 6px. It overlays
   the +/star row below the field, which is the wanted "just under the field" look. */
#fz-tools .search-dropdown-body{display:none}
#fz-tools #topmenu-global-search-dropdown:focus-within .search-dropdown-body{
	display:block;position:absolute;left:8px;right:8px;top:52px;z-index:1500;padding:12px 14px;
	background:var(--fz-surface);color:var(--fz-text);border:1px solid var(--fz-border);border-radius:var(--fz-radius);
	box-shadow:0 12px 34px rgba(0,0,0,.4);max-height:60vh;overflow:auto;width:auto !important;box-sizing:border-box}
@media only screen and (min-width: 921px){
	html.fz-collapsed #fz-tools #topmenu-global-search-dropdown:focus-within .search-dropdown-body{
		position:static !important;left:auto;right:auto;top:auto;margin-top:8px;padding:4px 0 0;
		background:transparent;border:0;border-radius:0;box-shadow:none;max-height:none;overflow:visible}
}
#fz-tools .global-search-item{display:flex !important;align-items:center;gap:8px;width:100%;text-align:left;
	background:none;border:0;cursor:pointer;padding:7px 10px;margin:0;border-radius:var(--fz-radius);color:var(--fz-text);font-size:13px}
#fz-tools .global-search-item .pictofixedwidth{
	flex:0 0 28px;width:28px !important;min-width:28px;max-width:28px;
	display:inline-flex !important;align-items:center;justify-content:center;text-align:center !important;
	margin:0 !important;padding:0 !important;line-height:1 !important}
#fz-tools .global-search-item img.pictofixedwidth,
#fz-tools .global-search-item svg.pictofixedwidth{
	height:20px !important;object-fit:contain}
#fz-tools .global-search-item:hover,#fz-tools .global-search-item:focus{background:var(--fz-row-hover)}

/* ---- Tool icons (quick-add +, bookmark star, module builder, …) --------
   eldy gives these wrappers assorted paddings (.atoplogin → 4px, .login_block_elem
   → 3/4px + inline style) and nests some one level deeper, so the buttons came out
   different widths and off-centre. Reset all that, keep every direct child shrink-
   wrapped, and make the clickable element a uniform 34x34 centred button — at any
   nesting depth. */
#fz-tools .fz-tools-extra > *{flex:0 0 auto}
#fz-tools .fz-tools-extra .dropdown{position:static}
#fz-tools .fz-tools-extra .atoplogin,
#fz-tools .fz-tools-extra .login_block_elem,
#fz-tools .fz-tools-extra .inline-block,
#fz-tools .fz-tools-extra .dropdown{margin:0 !important;padding:0 !important}
#fz-tools .fz-tools-extra a.dropdown-toggle,
#fz-tools .fz-tools-extra .login_block_elem > a{color:var(--fz-nav-fg) !important;font-size:15px;
	width:34px;height:34px;display:flex;align-items:center;justify-content:center;
	border-radius:var(--fz-radius);text-decoration:none;box-sizing:border-box}
#fz-tools .fz-tools-extra a.dropdown-toggle:hover,
#fz-tools .fz-tools-extra .login_block_elem > a:hover{background:var(--fz-nav-hover);color:var(--fz-nav-hover-fg) !important}
#fz-tools .fz-tools-extra .open > a.dropdown-toggle{background:var(--fz-nav-hover);color:var(--fz-nav-hover-fg) !important}
/* Some top-right tools keep eldy's .atoplogin color, computed for the old top bar.
   Once moved into Fusion's sidebar they must follow the sidebar foreground. */
#fz-tools .fz-tools-extra .atoplogin,
#fz-tools .fz-tools-extra .atoplogin:hover,
#fz-tools .fz-tools-extra .open > a.dropdown-toggle [class*="fa"],
#fz-tools .fz-tools-extra a.dropdown-toggle [class*="fa"],
#fz-tools .fz-tools-extra .login_block_elem > a [class*="fa"]{
	color:inherit !important;opacity:1;text-decoration:none !important}
/* neutralize the per-glyph padding/margin eldy puts on the icon spans */
#fz-tools .fz-tools-extra a.dropdown-toggle > [class*="fa-"],
#fz-tools .fz-tools-extra .login_block_elem > a > [class*="fa-"]{margin:0 !important;padding:0 !important;line-height:1}
#fz-tools .fz-tools-extra .helppresentcircle{display:none !important} /* stray help arrow overlay */
#fz-tools .dropdown-menu{
	position:absolute !important;left:8px;right:8px;top:calc(100% + 6px) !important;bottom:auto;box-sizing:border-box;
	width:auto !important;min-width:0 !important;max-height:62vh;overflow:auto;padding:6px;
	background:var(--fz-surface);color:var(--fz-text);border:1px solid var(--fz-border);
	border-radius:var(--fz-radius);box-shadow:0 12px 34px rgba(0,0,0,.4);z-index:1500}
#fz-tools #topmenu-quickadd-dropdown .dropdown-menu{padding:0 !important}
/* compact every inner row */
#fz-tools .dropdown-menu br{display:none !important}
#fz-tools .dropdown-menu .dropdown-body,
#fz-tools .dropdown-menu .quickadd-body,
#fz-tools .dropdown-menu .bookmark-body{
	border-top:0 !important;border-bottom:0 !important}
/* eldy hard-codes light #f9f9f9 backgrounds on the bookmark header/footer, which
   breaks the themed (esp. dark) panel — blend them into the panel surface. */
#fz-tools .dropdown-menu .bookmark-header,
#fz-tools .dropdown-menu .dropdown-header,
#fz-tools .dropdown-menu .bookmark-footer{background:transparent !important;color:var(--fz-text) !important;border-color:var(--fz-border) !important}
#fz-tools #topmenu-quickadd-dropdown .dropdown-body,
#fz-tools #topmenu-quickadd-dropdown .quickadd-body,
#fz-tools #topmenu-quickadd-dropdown .dropdown-quickadd-list{
	border:0 !important;box-shadow:none !important;outline:0 !important}
#fz-tools #topmenu-quickadd-dropdown .quickadd-body{padding:12px 14px !important;box-sizing:border-box}
#fz-tools .dropdown-menu a,#fz-tools .dropdown-menu .top-menu-dropdown-link,
#fz-tools .dropdown-menu .dropdown-item,#fz-tools .dropdown-menu button.dropdown-item{
	display:flex !important;align-items:center;gap:8px;width:100%;box-sizing:border-box;text-align:left;
	padding:7px 10px !important;margin:0 !important;border:0;background:none;border-radius:var(--fz-radius);
	color:var(--fz-text) !important;text-decoration:none;font-size:13px;line-height:1.3 !important;cursor:pointer}
#fz-tools .dropdown-menu a:hover,#fz-tools .dropdown-menu .dropdown-item:hover{background:var(--fz-row-hover)}
#fz-tools .dropdown-menu .dropdown-search-input{width:100%;box-sizing:border-box;padding:8px 10px;margin:0 0 6px;
	border:1px solid var(--fz-border);border-radius:var(--fz-radius);background:var(--fz-surface);color:var(--fz-text);font-size:13px}
#fz-tools .dropdown-menu hr,#fz-tools .dropdown-menu .divider{margin:6px 0;border-color:var(--fz-border)}

/* Favorites links inherit readable color */
#blockvmenubookmarks a,#blockvmenubookmarks span{color:var(--fz-nav-fg) !important}

/* ====================================================================== *
 *  Custom thin scrollbars (sidebar, dropdowns, and whole app)            *
 * ====================================================================== */
/* Sidebar : light thumb on the dark navy */
#fz-nav,#fz-tools .dropdown-menu,#fz-tools .search-dropdown-body,#fz-user .dropdown-menu{
	scrollbar-width:thin;scrollbar-color:rgba(255,255,255,.22) transparent}
#fz-nav::-webkit-scrollbar,#fz-tools .dropdown-menu::-webkit-scrollbar,
#fz-tools .search-dropdown-body::-webkit-scrollbar,#fz-user .dropdown-menu::-webkit-scrollbar{width:8px;height:8px}
#fz-nav::-webkit-scrollbar-track{background:transparent}
#fz-nav::-webkit-scrollbar-thumb{background:rgba(255,255,255,.18);border-radius:var(--fz-radius);border:2px solid transparent;background-clip:padding-box}
#fz-nav::-webkit-scrollbar-thumb:hover{background:rgba(255,255,255,.34);background-clip:padding-box;border:2px solid transparent}

/* Whole application content : themed thin scrollbars */
html.fusion #id-right,html.fusion #id-container,html.fusion body#mainbody,
html.fusion .div-table-responsive,html.fusion .div-table-responsive-no-min{
	scrollbar-width:thin;scrollbar-color:var(--fz-text-dim) transparent}
html.fusion ::-webkit-scrollbar{width:11px;height:11px}
html.fusion ::-webkit-scrollbar-track{background:transparent}
html.fusion ::-webkit-scrollbar-thumb{background:color-mix(in srgb,var(--fz-text-dim) 55%,transparent);
	border-radius:var(--fz-radius);border:3px solid transparent;background-clip:padding-box}
html.fusion ::-webkit-scrollbar-thumb:hover{background:var(--fz-text-dim);background-clip:padding-box;border:3px solid transparent}
/* keep the navy sidebar's own scrollbars on the light-thumb rule above */
#fz-sidebar ::-webkit-scrollbar-thumb{background:rgba(255,255,255,.2);background-clip:padding-box;border:2px solid transparent}

<?php if ($fz_is_print) { ?>
/* Fusion print mode: optioncss=print must be content-only. */
header#id-top,
#id-top,
#id-left,
.side-nav,
.vmenu,
.tmenu,
#tmenu_tooltip,
#fz-sidebar,
#fz-topbar,
#fz-scrim,
body#mainbody > div[style*="clear"]{
	display:none !important;
}

body#mainbody{
	background:#fff !important;
}

#id-container,
body#mainbody #id-container{
	display:block !important;
	margin-left:0 !important;
	width:100% !important;
	max-width:none !important;
	padding-left:0 !important;
	padding-right:0 !important;
	padding-top:0 !important;
	background:#fff !important;
	box-sizing:border-box !important;
}

#id-right,
body#mainbody #id-right{
	display:block !important;
	float:none !important;
	margin-left:0 !important;
	width:auto !important;
	max-width:none !important;
	min-height:0 !important;
	box-sizing:border-box !important;
}
<?php } ?>
