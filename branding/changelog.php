<?php
/* Copyright (C) 2026  Fusion theme
 *
 * Theme "fusion" — the theme's CHANGELOG.md, shown as a tab of Setup > Display.
 *
 *   theme/fusion/branding/changelog.php    (admins only, like the settings tab)
 *
 * The changelog is the theme's one record of its releases, and so of its version
 * (Dolibarr has no versioning for themes). It is rendered by Dolibarr's own Markdown
 * parser in safe mode, which escapes any raw HTML.
 */

require __DIR__.'/../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/parsemd.lib.php';
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

$langs->loadLangs(array('admin'));
$words = fz_fusion_branding_words();
$content = @file_get_contents(__DIR__.'/../CHANGELOG.md');

llxHeader('', $words['changelog'], '', '', 0, 0, '', '', '', 'mod-admin page-fusion-changelog');

print load_fiche_titre($langs->trans('GUISetup'), '', 'title_setup');
print dol_get_fiche_head(fz_fusion_branding_head(), 'fusionchangelog', '', -1, '');

print '<style>
.fz-changelog{line-height:1.55}
.fz-changelog h1{display:none}	/* the tab already names the page */
.fz-changelog h2{font-size:1.25em;margin:28px 0 8px;padding-bottom:6px;border-bottom:1px solid var(--colortopbordertitle1)}
.fz-changelog h2:first-of-type{margin-top:8px}
.fz-changelog h3{font-size:1.02em;margin:16px 0 6px;color:var(--fz-text-dim,#64748b);text-transform:uppercase;letter-spacing:.03em}
.fz-changelog ul{margin:0 0 8px;padding-left:22px}
.fz-changelog li{margin:3px 0}
.fz-changelog code{font-size:.92em;padding:1px 5px;border-radius:4px;background:color-mix(in srgb,var(--fz-text-dim,#64748b) 12%,transparent)}
</style>';

if ($content === false || trim($content) === '') {
	print '<span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span>';
} else {
	print '<div class="fz-changelog">'.dolMd2Html($content, 'parsedown').'</div>';
}

print dol_get_fiche_end();

llxFooter();
$db->close();
