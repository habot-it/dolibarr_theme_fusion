<?php
/* Copyright (C) 2026  Fusion theme
 *
 * Theme "fusion" — settings page for the brand: application name, the two company
 * logos, and which image the sidebar (expanded / collapsed) and the favicon use.
 *
 *   theme/fusion/branding/setup.php        (admins only; tab added to Setup > Display)
 *
 * Two blocks:
 *   - Identity: MAIN_APPLICATION_TITLE and the company logos, edited exactly like
 *     Setup > Security > Miscellaneous and Setup > Company do (same constants,
 *     same thumbnails), so both pages stay in sync;
 *   - Usage: one source per place (see branding.lib.php), with a live preview and,
 *     for "own image", an upload of its own.
 *
 * The favicon goes through Dolibarr's own MAIN_FAVICON_URL constant, pointed at
 * branding/favicon.php, so it is applied on every page, login included, with no
 * core change.
 */

require __DIR__.'/../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once __DIR__.'/branding.lib.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

if (empty($user->admin)) {
	accessforbidden();
}

$langs->loadLangs(array('admin', 'companies', 'errors'));
$words = fz_fusion_branding_words();

$sidebarChoices = array('' => $words['auto'], 'logo' => $langs->trans('Logo'), 'squarred' => $langs->trans('LogoSquarred'), 'custom' => $words['custom'], 'none' => $words['none']);
$faviconChoices = array('' => $words['default'], 'logo' => $langs->trans('Logo'), 'squarred' => $langs->trans('LogoSquarred'), 'custom' => $words['custom'], 'dolibarr' => $words['dolibarr']);
$choicesByPlace = array('expanded' => $sidebarChoices, 'collapsed' => $sidebarChoices, 'favicon' => $faviconChoices);
$places = fz_fusion_branding_places();
$companyLogos = array('logo' => 'MAIN_INFO_SOCIETE_LOGO', 'logo_squarred' => 'MAIN_INFO_SOCIETE_LOGO_SQUARRED');
$faviconUrl = fz_fusion_favicon_url();
$dirforlogos = $conf->mycompany->dir_output.'/logos/';
$dirforimage = $dirforlogos.'fusion/';

$action = GETPOST('action', 'aZ09');

/**
 * Thumbnail file name of an image (Dolibarr's naming: name_small.ext).
 *
 * @param	string	$file		Image file name
 * @param	string	$suffix		'_small' or '_mini'
 * @return	string
 */
function fz_fusion_branding_thumbname($file, $suffix)
{
	$dot = strrpos($file, '.');
	return ($dot !== false) ? substr($file, 0, $dot).$suffix.substr($file, $dot) : $file.$suffix;
}

/**
 * Move an uploaded image into a directory and build its _small / _mini thumbnails.
 *
 * @param	string		$htmlname	Name of the file field
 * @param	string		$dir		Target directory, with a trailing slash
 * @param	string		$prefix		Prefix of the stored file name
 * @return	string|null				Stored file name, '' when nothing was uploaded, null on error
 */
function fz_fusion_branding_upload($htmlname, $dir, $prefix = '')
{
	global $langs;

	if (empty($_FILES[$htmlname]['name'])) {
		return '';
	}
	$reg = array();
	if (!preg_match('/([^\\/:]+)$/i', $_FILES[$htmlname]['name'], $reg) || image_format_supported($reg[1]) < 0) {
		$supportedextensions = preg_replace('/\\\./', '', getListOfPossibleImageExt());
		setEventMessages($langs->trans('ErrorBadImageFormat').' - '.$langs->trans('ErrorSupportedFormatAre', implode(', ', explode('|', $supportedextensions))), null, 'errors');
		return null;
	}
	$file = $prefix.dol_sanitizeFileName($reg[1]);
	if (!is_dir($dir)) {
		dol_mkdir($dir);
	}
	$result = dol_move_uploaded_file($_FILES[$htmlname]['tmp_name'], $dir.$file, 1, 0, $_FILES[$htmlname]['error']);
	if (!is_numeric($result) || $result <= 0) {
		setEventMessages($langs->trans('ErrorFailedToSaveFile'), null, 'errors');
		return null;
	}
	if (image_format_supported($file) > 0) {
		$sizes = getDefaultImageSizes();
		vignette($dir.$file, $sizes['maxwidthsmall'], $sizes['maxheightsmall'], '_small', $sizes['quality']);
		vignette($dir.$file, $sizes['maxwidthmini'], $sizes['maxheightmini'], '_mini', $sizes['quality']);
	}
	return $file;
}

/**
 * Delete a company logo like admin/company.php does: its files only when the
 * other logo does not use the same ones, then its three constants.
 *
 * @param	string	$const		MAIN_INFO_SOCIETE_LOGO or MAIN_INFO_SOCIETE_LOGO_SQUARRED
 * @return	void
 */
function fz_fusion_branding_remove_company_logo($const)
{
	global $db, $conf, $dirforlogos;

	$other = ($const === 'MAIN_INFO_SOCIETE_LOGO') ? 'MAIN_INFO_SOCIETE_LOGO_SQUARRED' : 'MAIN_INFO_SOCIETE_LOGO';
	foreach (array('' => '', '_SMALL' => 'thumbs/', '_MINI' => 'thumbs/') as $suffix => $subdir) {
		$file = getDolGlobalString($const.$suffix);
		if ($file !== '' && $file !== getDolGlobalString($other.$suffix)) {
			dol_delete_file($dirforlogos.$subdir.$file);
		}
		dolibarr_del_const($db, $const.$suffix, $conf->entity);
	}
}

/**
 * Delete an own image file of the theme, with its thumbnails.
 *
 * @param	string	$file		File name in logos/fusion/
 * @return	void
 */
function fz_fusion_branding_delete_custom_files($file)
{
	global $dirforimage;

	if ($file === '') {
		return;
	}
	foreach (array($file, 'thumbs/'.fz_fusion_branding_thumbname($file, '_small'), 'thumbs/'.fz_fusion_branding_thumbname($file, '_mini')) as $path) {
		if (file_exists($dirforimage.$path)) {
			dol_delete_file($dirforimage.$path);
		}
	}
}

/**
 * After a change: point MAIN_FAVICON_URL at our endpoint when the favicon is ours,
 * give it back when it was ours and no longer is (a value set by someone else is
 * only replaced on an explicit choice), and bump the stylesheet revision, which
 * carries the sidebar logo URLs.
 *
 * @return	int		<0 on error
 */
function fz_fusion_branding_saved()
{
	global $db, $conf, $faviconUrl;

	if (getDolGlobalString('FUSION_FAVICON') !== '') {
		$res = dolibarr_set_const($db, 'MAIN_FAVICON_URL', $faviconUrl, 'chaine', 0, 'Fusion theme: branding/favicon.php', $conf->entity);
	} elseif (getDolGlobalString('MAIN_FAVICON_URL') === $faviconUrl) {
		$res = dolibarr_del_const($db, 'MAIN_FAVICON_URL', $conf->entity);
	} else {
		$res = 1;
	}
	dolibarr_set_const($db, 'MAIN_IHM_PARAMS_REV', getDolGlobalInt('MAIN_IHM_PARAMS_REV') + 1, 'chaine', 0, '', $conf->entity);
	return $res;
}

// Constants the theme needs and Setup > Display does not set (see fz_fusion_required_consts())
if ($action == 'applyrequired') {
	$error = 0;
	foreach (fz_fusion_missing_consts() as $name => $value) {
		if (dolibarr_set_const($db, $name, $value, 'chaine', 0, 'Fusion theme: required', $conf->entity) < 0) {
			$error++;
		}
	}
	dolibarr_set_const($db, 'MAIN_IHM_PARAMS_REV', getDolGlobalInt('MAIN_IHM_PARAMS_REV') + 1, 'chaine', 0, '', $conf->entity);
	if ($error) {
		setEventMessages($langs->trans('Error'), null, 'errors');
	} else {
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

if ($action == 'update') {
	$error = 0;

	// Application name: same constant and filter as Setup > Security > Miscellaneous
	if (GETPOSTISSET('MAIN_APPLICATION_TITLE')) {
		if (dolibarr_set_const($db, 'MAIN_APPLICATION_TITLE', GETPOST('MAIN_APPLICATION_TITLE', 'alphanohtml'), 'chaine', 0, '', $conf->entity) < 0) {
			$error++;
		}
	}

	// Navigation history: on by default, 10 elements shown out of 30 kept
	$res = GETPOST('FUSION_HISTORY_ENABLED', 'int') ? dolibarr_del_const($db, 'FUSION_HISTORY_DISABLED', $conf->entity) : dolibarr_set_const($db, 'FUSION_HISTORY_DISABLED', '1', 'chaine', 0, 'Fusion theme', $conf->entity);
	$historysize = max(1, min(30, GETPOSTINT('FUSION_HISTORY_SIZE') ?: 10));
	$historykeep = max($historysize, min(100, GETPOSTINT('FUSION_HISTORY_KEEP') ?: 30));
	foreach (array('FUSION_HISTORY_SIZE' => array($historysize, 10), 'FUSION_HISTORY_KEEP' => array($historykeep, 30)) as $const => $values) {
		$res = min($res, ($values[0] == $values[1]) ? dolibarr_del_const($db, $const, $conf->entity) : dolibarr_set_const($db, $const, (string) $values[0], 'chaine', 0, 'Fusion theme', $conf->entity));
	}
	if ($res < 0) {
		$error++;
	}

	// Home page workboard (the "open elements" tiles): Dolibarr's own constant, the one
	// of Setup > Display > Dashboard. Stored as '0' when shown, so that this explicit
	// choice survives sql/activate_fusion.sql, which hides the tiles by default.
	if (dolibarr_set_const($db, 'MAIN_DISABLE_GLOBAL_WORKBOARD', GETPOST('MAIN_DISABLE_GLOBAL_WORKBOARD', 'int') ? '1' : '0', 'chaine', 0, '', $conf->entity) < 0) {
		$error++;
	}

	// Company logos: same files and constants as Setup > Company
	foreach ($companyLogos as $htmlname => $const) {
		$file = fz_fusion_branding_upload($htmlname, $dirforlogos);
		if ($file === null) {
			$error++;
		} elseif ($file !== '') {
			dolibarr_set_const($db, $const, $file, 'chaine', 0, '', $conf->entity);
			foreach (array('_SMALL' => '_small', '_MINI' => '_mini') as $constsuffix => $suffix) {
				$thumb = fz_fusion_branding_thumbname($file, $suffix);
				if (file_exists($dirforlogos.'thumbs/'.$thumb)) {
					dolibarr_set_const($db, $const.$constsuffix, $thumb, 'chaine', 0, '', $conf->entity);
				}
			}
		}
	}

	// Source of each place, and its own image
	foreach ($places as $place => $const) {
		$value = GETPOST($const, 'aZ09');
		if (!array_key_exists($value, $choicesByPlace[$place])) {
			$value = '';
		}
		// Prefixed with the place, so two places sharing a file name never delete each other's
		$previous = getDolGlobalString($const.'_FILE');
		$file = fz_fusion_branding_upload('fusion_'.$place, $dirforimage, $place.'-');
		if ($file === null) {
			$error++;
		} elseif ($file !== '') {
			if ($previous !== $file) {
				fz_fusion_branding_delete_custom_files($previous);
			}
			dolibarr_set_const($db, $const.'_FILE', $file, 'chaine', 0, 'Fusion theme', $conf->entity);
			$value = 'custom';	// an image was just given for this place: use it
		}
		$res = ($value === '') ? dolibarr_del_const($db, $const, $conf->entity) : dolibarr_set_const($db, $const, $value, 'chaine', 0, 'Fusion theme', $conf->entity);
		if ($res < 0) {
			$error++;
		}
	}

	if (fz_fusion_branding_saved() < 0) {
		$error++;
	}
	if (!$error) {
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

// Trash icons of showImageToEdit(): action=remove<htmlname>
foreach ($companyLogos as $htmlname => $const) {
	if ($action == 'remove'.$htmlname) {
		fz_fusion_branding_remove_company_logo($const);
		fz_fusion_branding_saved();
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	}
}
foreach ($places as $place => $const) {
	if ($action == 'removefusion_'.$place) {
		fz_fusion_branding_delete_custom_files(getDolGlobalString($const.'_FILE'));
		dolibarr_del_const($db, $const.'_FILE', $conf->entity);
		fz_fusion_branding_saved();
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	}
}


/*
 * View
 */

$form = new Form($db);
$formfile = new FormFile($db);

// What each source shows, per place, for the live preview ('' = initial letter)
$dolibarrIcon = DOL_URL_ROOT.'/theme/dolibarr_256x256_color.png';
$previews = array();
foreach ($places as $place => $const) {
	$custom = fz_fusion_custom_path($place);
	$previews[$place] = array(
		'' => ($place === 'favicon') ? (fz_fusion_logo_url('squarred', '_mini') ?: $dolibarrIcon) : fz_fusion_logo_url('square'),
		'logo' => fz_fusion_logo_url('logo'),
		'squarred' => fz_fusion_logo_url('squarred'),
		'custom' => ($custom === '') ? '' : DOL_URL_ROOT.'/viewimage.php?cache=1&modulepart=mycompany&file='.urlencode($custom),
		'none' => '',
		'dolibarr' => $dolibarrIcon,
	);
}

// Automatic, menu expanded: the wide logo when there is no application name
$autoNonameUrl = fz_fusion_logo_url('wide');

/**
 * Source <select> of a place; each option carries the image it would show.
 *
 * @param	string					$name			Constant name, also the field name
 * @param	array<string,string>	$choices		value => label
 * @param	array<string,string>	$preview		value => image URL
 * @param	string					$autoNoname		Image of the automatic choice when the application name is empty ('' = same)
 * @return	string
 */
function fz_fusion_branding_select($name, $choices, $preview, $autoNoname = '')
{
	$current = getDolGlobalString($name);
	$out = '<select class="flat minwidth250 fz-branding-source" name="'.$name.'" id="'.$name.'">';
	foreach ($choices as $value => $label) {
		$out .= '<option value="'.dol_escape_htmltag($value).'" data-preview="'.dol_escape_htmltag($preview[$value]).'"';
		if ($value === '' && $autoNoname !== '') {
			$out .= ' data-preview-noname="'.dol_escape_htmltag($autoNoname).'"';
		}
		$out .= ($value === $current ? ' selected' : '').'>'.dol_escape_htmltag($label).'</option>';
	}
	$out .= '</select>';
	return $out;
}

/**
 * Preview box of the right column.
 *
 * @param	string	$shape		wide|square
 * @param	string	$look		menu (sidebar background) | checker (transparency checkerboard)
 * @param	string	$src		Image URL for a static preview ('' = filled by the script / empty)
 * @param	bool	$withName	Add the application name, as next to the expanded sidebar logo
 * @return	string
 */
function fz_fusion_branding_preview($shape, $look, $src = '', $withName = false)
{
	$out = '<span class="fz-branding-preview is-'.$shape.' is-'.$look.'"><span class="fz-branding-mark">';
	if ($src !== '') {
		$out .= '<img src="'.dol_escape_htmltag($src).'" alt="">';
	}
	$out .= '</span>';
	if ($withName) {
		$out .= '<span class="fz-branding-name"></span>';
	}
	return $out.'</span>';
}

llxHeader('', $words['title'], '', '', 0, 0, '', '', '', 'mod-admin page-fusion-branding');

// Same frame as Setup > Display, with our own tab active (fusion.js adds that tab
// to the Display pages, which a theme cannot extend server-side).
print load_fiche_titre($langs->trans('GUISetup'), '', 'title_setup');
print dol_get_fiche_head(fz_fusion_branding_head(), 'fusionbranding', '', -1, '');

print '<style>
/* One preview system for the whole page, in the right column: two shapes of the same
   height (wide: logo, menu expanded; square: squarred logo, menu collapsed, favicon)
   and one image height, so every logo is shown at the same scale. */
/* first column a little wider than the core titlefieldcreate (20 to 30%), so the longer
   labels (the workboard one) stay on one line; same width in every table, so they align */
.fz-branding-table td.titlefieldcreate{width:36% !important}
/* every row centred vertically, whatever the height of its tallest cell; specific
   enough to beat the core alignment rules (div .tdtop:not(.tagtdnote)…) */
html body div.fiche table.fz-branding-table > tbody > tr > td{vertical-align:middle !important}
.fz-branding-table td.fz-branding-previewcell{width:260px;text-align:right;vertical-align:middle}
.fz-branding-preview{display:inline-flex;align-items:center;justify-content:center;gap:10px;box-sizing:border-box;
	height:72px;padding:0 12px;border-radius:var(--fz-radius,8px);vertical-align:middle;overflow:hidden}
.fz-branding-preview.is-wide{width:240px}
.fz-branding-preview.is-square{width:72px;padding:0}
.fz-branding-preview.is-menu{background:var(--fz-nav-bg,#1e293b)}
.fz-branding-preview.is-menu.is-wide{justify-content:flex-start}
/* Transparent images (a white logo on nothing, say) stay visible on a checkerboard */
.fz-branding-preview.is-checker{
	background-color:#fff;
	background-image:conic-gradient(#d7dce3 25%,transparent 0 50%,#d7dce3 0 75%,transparent 0);
	background-size:16px 16px;box-shadow:inset 0 0 0 1px rgba(0,0,0,.08)}
.fz-branding-mark{display:flex;align-items:center;justify-content:center;min-width:0}
.fz-branding-mark img{display:block;height:48px;width:auto;max-width:216px;object-fit:contain}
.fz-branding-preview.is-square .fz-branding-mark img{width:48px;max-width:48px}
.fz-branding-name{color:var(--fz-nav-fg,#fff);font-weight:700;font-size:15px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-width:0}
.fz-branding-name:empty{display:none}
.fz-branding-letter{width:48px;height:48px;border-radius:var(--fz-radius,6px);display:flex;align-items:center;justify-content:center;
	background:color-mix(in srgb,var(--fz-nav-fg,#dbe3ef) 16%,transparent);color:var(--fz-nav-fg,#dbe3ef);font-weight:800;font-size:22px}
/* The Preview column shows every image: no second thumbnail in the upload blocks */
.fz-branding-table .fz-branding-upload img[id]{display:none}
.fz-branding-custom{margin-top:8px}
/* Phone: each row stacks (label, field, preview) instead of three columns that do not fit */
@media only screen and (max-width: 720px){
	.fz-branding-table,.fz-branding-table tbody,.fz-branding-table tr,.fz-branding-table td{display:block;width:auto !important;height:auto !important;box-sizing:border-box}
	.fz-branding-table tr.liste_titre td:not(:first-child){display:none}
	.fz-branding-table tr.oddeven{border-bottom:1px solid var(--colortopbordertitle1,#d7d7d7)}
	.fz-branding-table tr.oddeven td{padding:4px 12px;border:0 !important}
	.fz-branding-table tr.oddeven td:empty{display:none}
	.fz-branding-table tr.oddeven td:first-child{padding-top:12px;font-weight:600}
	.fz-branding-table tr.oddeven td:last-child{padding-bottom:12px}
	.fz-branding-table td.fz-branding-previewcell{text-align:left}
	.fz-branding-table input[type=text],.fz-branding-table select{min-width:0 !important;width:100%;max-width:100%}
	.fz-branding-preview.is-wide{width:100%;max-width:240px}
}
</style>';

$missingConsts = fz_fusion_missing_consts();
if ($missingConsts) {
	print '<div class="warning">'.dol_escape_htmltag(sprintf($words['reqmissing'], implode(', ', array_keys($missingConsts))));
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" class="margintoponly">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="applyrequired">';
	print '<input type="submit" class="button small" value="'.dol_escape_htmltag($words['reqapply']).'">';
	print '</form></div>';
}

print '<form enctype="multipart/form-data" method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

// Same tooltip as the company logos
$maxfilesizearray = getMaxFileSizeArray();
$maxmin = $maxfilesizearray['maxmin'];
$tooltiplogo = $langs->trans('AvailableFormats').' : png, jpg, jpeg';
$tooltiplogo .= ($maxmin > 0) ? '<br>'.$langs->trans('MaxSize').' : '.$maxmin.' '.$langs->trans('Kb') : '';

// ---- Home page ------------------------------------------------------------------
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent fz-branding-table">';
print '<tr class="liste_titre"><td class="titlefieldcreate">'.$langs->trans('Home').'</td><td></td><td></td></tr>';
// Named after the block itself ("Open Items", core's DolibarrWorkBoard); core's setup label
// speaks of "thumbs", which says little. Its explanation stays in the tooltip.
$workboardlabel = sprintf($words['hideworkboard'], $langs->transnoentitiesnoconv('DolibarrWorkBoard'));
print '<tr class="oddeven"><td><label for="MAIN_DISABLE_GLOBAL_WORKBOARD">'.$form->textwithpicto(dol_escape_htmltag($workboardlabel), dol_escape_htmltag($langs->transnoentitiesnoconv('DashboardDisableBlocks').' : '.$langs->transnoentitiesnoconv('GlobalOpenedElemView').', '.$langs->transnoentitiesnoconv('Agenda').', '.$langs->transnoentitiesnoconv('Projects').'…')).'</label></td><td>';
print '<input type="checkbox" name="MAIN_DISABLE_GLOBAL_WORKBOARD" id="MAIN_DISABLE_GLOBAL_WORKBOARD" value="1"'.(getDolGlobalString('MAIN_DISABLE_GLOBAL_WORKBOARD') ? ' checked' : '').'>';
print '</td><td></td></tr>';
print '</table></div>';

// ---- Identity ---------------------------------------------------------------
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent fz-branding-table">';
print '<tr class="liste_titre"><td class="titlefieldcreate">'.dol_escape_htmltag($words['identity']).'</td><td></td><td class="right">'.dol_escape_htmltag($words['preview']).'</td></tr>';

print '<tr class="oddeven"><td><label for="MAIN_APPLICATION_TITLE">'.$form->textwithpicto(dol_escape_htmltag($words['appname']), dol_escape_htmltag($words['appnote']).'<br><br>'.$langs->trans('MAIN_APPLICATION_TITLE')).'</label></td><td>';
print '<input type="text" class="flat minwidth300" name="MAIN_APPLICATION_TITLE" id="MAIN_APPLICATION_TITLE" value="'.dol_escape_htmltag(getDolGlobalString('MAIN_APPLICATION_TITLE')).'">';
print '</td><td></td></tr>';

foreach ($companyLogos as $htmlname => $const) {
	print '<tr class="oddeven"><td><label for="'.$htmlname.'">'.$form->textwithpicto($langs->trans($htmlname === 'logo' ? 'Logo' : 'LogoSquarred'), $tooltiplogo).'</label></td><td class="fz-branding-upload">';
	print $formfile->showImageToEdit($htmlname, 'mycompany', $conf->mycompany->dir_output, 'logos/', getDolGlobalString($const));
	print '</td><td class="fz-branding-previewcell">'.fz_fusion_branding_preview($htmlname === 'logo' ? 'wide' : 'square', 'checker', fz_fusion_logo_url($htmlname === 'logo' ? 'logo' : 'squarred')).'</td></tr>';
}
print '</table></div>';

// ---- Usage ------------------------------------------------------------------
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent fz-branding-table">';
print '<tr class="liste_titre"><td class="titlefieldcreate">'.dol_escape_htmltag($words['usage']).'</td><td>'.dol_escape_htmltag($words['source']).'</td><td class="right">'.dol_escape_htmltag($words['preview']).'</td></tr>';

foreach ($places as $place => $const) {
	$label = dol_escape_htmltag($words[$place]);
	if ($place === 'favicon') {
		$label = $form->textwithpicto($label, dol_escape_htmltag($words['favnote']));
	}
	$current = getDolGlobalString($const);
	print '<tr class="oddeven fz-branding-row"><td><label for="'.$const.'">'.$label.'</label></td><td>';
	print fz_fusion_branding_select($const, $choicesByPlace[$place], $previews[$place], $place === 'expanded' ? $autoNonameUrl : '');
	print '<div class="fz-branding-custom fz-branding-upload"'.($current === 'custom' ? '' : ' style="display:none"').'>';
	print $formfile->showImageToEdit('fusion_'.$place, 'mycompany', $conf->mycompany->dir_output, 'logos/fusion/', getDolGlobalString($const.'_FILE'));
	print '</div>';
	if ($place === 'favicon') {
		$currentFavicon = getDolGlobalString('MAIN_FAVICON_URL');
		if ($currentFavicon !== '' && $currentFavicon !== $faviconUrl) {
			print '<div class="warning small margintoponly">'.dol_escape_htmltag(sprintf($words['favother'], $currentFavicon)).'</div>';
		}
	}
	print '</td><td class="fz-branding-previewcell">';
	print fz_fusion_branding_preview($place === 'expanded' ? 'wide' : 'square', $place === 'favicon' ? 'checker' : 'menu', '', $place === 'expanded');
	print '</td></tr>';
}
print '</table></div>';

// ---- Navigation history -------------------------------------------------------
print '<div class="div-table-responsive-no-min"><table class="noborder centpercent fz-branding-table">';
print '<tr class="liste_titre"><td class="titlefieldcreate">'.$form->textwithpicto(dol_escape_htmltag($words['history']), dol_escape_htmltag($words['histnote'])).'</td><td></td><td></td></tr>';
print '<tr class="oddeven"><td><label for="FUSION_HISTORY_ENABLED">'.dol_escape_htmltag($words['histenabled']).'</label></td><td>';
print '<input type="checkbox" name="FUSION_HISTORY_ENABLED" id="FUSION_HISTORY_ENABLED" value="1"'.(getDolGlobalString('FUSION_HISTORY_DISABLED') ? '' : ' checked').'>';
print '</td><td></td></tr>';
print '<tr class="oddeven"><td><label for="FUSION_HISTORY_SIZE">'.dol_escape_htmltag($words['histsize']).'</label></td><td>';
print '<input type="number" class="flat width75" min="1" max="30" name="FUSION_HISTORY_SIZE" id="FUSION_HISTORY_SIZE" value="'.max(1, min(30, getDolGlobalInt('FUSION_HISTORY_SIZE', 10))).'">';
print '</td><td></td></tr>';
print '<tr class="oddeven"><td><label for="FUSION_HISTORY_KEEP">'.dol_escape_htmltag($words['histkeep']).'</label></td><td>';
print '<input type="number" class="flat width75" min="1" max="100" name="FUSION_HISTORY_KEEP" id="FUSION_HISTORY_KEEP" value="'.max(1, min(100, getDolGlobalInt('FUSION_HISTORY_KEEP', 30))).'">';
print '</td><td></td></tr>';
print '</table></div>';

print '<div class="center"><input type="submit" class="button button-save" value="'.dol_escape_htmltag($langs->trans('Save')).'"></div>';
print '</form>';

print dol_get_fiche_end();

// Live preview, and the upload of an own image shown only when that source is picked
print '<script>
(function () {
	var nameInput = document.getElementById("MAIN_APPLICATION_TITLE");
	var fallbackLabel = '.json_encode((string) (getDolGlobalString('MAIN_INFO_SOCIETE_NOM') ?: 'Dolibarr')).';
	// The name as the sidebar shows it: entities decoded (&nbsp; hides it), trimmed
	function appName() {
		var box = document.createElement("textarea");
		box.innerHTML = nameInput ? nameInput.value : "";
		return box.value.replace(/<[^>]*>/g, "").replace(/[\s\u00a0]+/g, " ").trim();
	}
	var rows = document.querySelectorAll(".fz-branding-row");
	function sync(row) {
		var select = row.querySelector(".fz-branding-source");
		var custom = row.querySelector(".fz-branding-custom");
		var mark = row.querySelector(".fz-branding-mark");
		var nameEl = row.querySelector(".fz-branding-name");
		var name = appName();
		var option = select.options[select.selectedIndex];
		var src = option ? option.getAttribute("data-preview") : "";
		if (option && !name && option.hasAttribute("data-preview-noname")) src = option.getAttribute("data-preview-noname");
		custom.style.display = select.value === "custom" ? "" : "none";
		mark.textContent = "";
		if (src) {
			var img = new Image();
			img.alt = "";
			img.src = src;
			mark.appendChild(img);
		} else {
			var badge = document.createElement("span");
			badge.className = "fz-branding-letter";
			badge.textContent = (name || fallbackLabel).charAt(0).toUpperCase();
			mark.appendChild(badge);
		}
		if (nameEl) nameEl.textContent = name;
	}
	rows.forEach(function (row) {
		row.querySelector(".fz-branding-source").addEventListener("change", function () { sync(row); });
		sync(row);
	});
	// A file just picked shows in the preview before saving: in its own row for the
	// company logos, as the "own image" source of its place for the others.
	document.querySelectorAll(".fz-branding-upload input[type=file]").forEach(function (input) {
		input.addEventListener("change", function () {
			var file = input.files && input.files[0];
			if (!file || !window.URL || !URL.createObjectURL) return;
			var url = URL.createObjectURL(file);
			var row = input.closest("tr");
			if (row.classList.contains("fz-branding-row")) {
				var option = row.querySelector(".fz-branding-source option[value=custom]");
				option.setAttribute("data-preview", url);
				sync(row);
			} else {
				var mark = row.querySelector(".fz-branding-mark");
				mark.textContent = "";
				var img = new Image();
				img.alt = "";
				img.src = url;
				mark.appendChild(img);
			}
		});
	});
	if (nameInput) nameInput.addEventListener("input", function () { rows.forEach(sync); });
})();
</script>';

llxFooter();
$db->close();
