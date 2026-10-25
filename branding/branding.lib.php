<?php
/* Copyright (C) 2026  Fusion theme
 *
 * Theme "fusion" — which of the two company logos (Setup > Company: "Logo" and
 * "Logo (squarred)") the sidebar shows expanded, collapsed, and as favicon.
 *
 * Shared by style.css.php (sidebar logos), branding/setup.php (the settings page)
 * and branding/favicon.php (the favicon endpoint). Three constants, all optional:
 *
 *   FUSION_LOGO_EXPANDED   ''|logo|squarred|custom|none       sidebar expanded
 *   FUSION_LOGO_COLLAPSED  ''|logo|squarred|custom|none       sidebar collapsed (icons only)
 *   FUSION_FAVICON         ''|logo|squarred|custom|dolibarr
 *
 * '' keeps the historical automatic choice: wide logo expanded when the
 * application name is empty, the squarred logo otherwise. 'none' shows the
 * initial letter of the brand label.
 *
 * 'custom' is an image of its own for that place, uploaded in branding/setup.php the
 * way Setup > Company does it: stored in <mycompany>/logos/fusion/ (thumbnails in
 * its thumbs/ subfolder), its file name in FUSION_<PLACE>_FILE.
 */

if (!function_exists('fz_fusion_logo_consts')) {
	/**
	 * Logo constants to try, in order, for a choice.
	 *
	 * @param	string	$choice		logo|squarred|wide|square
	 * @return	string[]			MAIN_INFO_SOCIETE_* constant names
	 */
	function fz_fusion_logo_consts($choice)
	{
		switch ($choice) {
			case 'logo':
				return array('MAIN_INFO_SOCIETE_LOGO');
			case 'squarred':
				return array('MAIN_INFO_SOCIETE_LOGO_SQUARRED');
			case 'wide':	// automatic mode: prefer the wordmark, fall back to the square icon
				return array('MAIN_INFO_SOCIETE_LOGO', 'MAIN_INFO_SOCIETE_LOGO_SQUARRED');
			default:		// 'square'
				return array('MAIN_INFO_SOCIETE_LOGO_SQUARRED', 'MAIN_INFO_SOCIETE_LOGO');
		}
	}
}

if (!function_exists('fz_fusion_logo_path')) {
	/**
	 * Relative path (under the mycompany directory) of the first readable logo for a
	 * choice, preferring the given Dolibarr thumbnail over the full-resolution file.
	 *
	 * @param	string	$choice		logo|squarred|wide|square
	 * @param	string	$thumb		Thumbnail suffix to prefer: '_small' or '_mini'
	 * @return	string				'logos/…' path, or '' when nothing is readable
	 */
	function fz_fusion_logo_path($choice, $thumb = '_small')
	{
		global $conf;

		$dirout = empty($conf->mycompany->dir_output) ? '' : $conf->mycompany->dir_output;
		if ($dirout === '') {
			return '';
		}

		foreach (fz_fusion_logo_consts($choice) as $const) {
			$file = getDolGlobalString($const);
			if ($file === '') {
				continue;
			}
			$dot = strrpos($file, '.');
			$thumbfile = ($dot !== false) ? substr($file, 0, $dot).$thumb.substr($file, $dot) : $file.$thumb;
			foreach (array('logos/thumbs/'.$thumbfile, 'logos/'.$file) as $relativePath) {
				if (is_readable($dirout.'/'.$relativePath)) {
					return $relativePath;
				}
			}
		}

		return '';
	}
}

if (!function_exists('fz_fusion_logo_url')) {
	/**
	 * Browser URL of the logo for a choice (see fz_fusion_logo_path()).
	 *
	 * @param	string	$choice		logo|squarred|wide|square
	 * @param	string	$thumb		Thumbnail suffix to prefer: '_small' or '_mini'
	 * @return	string				viewimage.php URL, or ''
	 */
	function fz_fusion_logo_url($choice, $thumb = '_small')
	{
		$relativePath = fz_fusion_logo_path($choice, $thumb);
		return $relativePath === '' ? '' : DOL_URL_ROOT.'/viewimage.php?cache=1&modulepart=mycompany&file='.urlencode($relativePath);
	}
}

if (!function_exists('fz_fusion_branding_places')) {
	/**
	 * The three places, with the constant holding the choice (its own image: same name + _FILE).
	 *
	 * @return	array<string,string>	place => constant
	 */
	function fz_fusion_branding_places()
	{
		return array('expanded' => 'FUSION_LOGO_EXPANDED', 'collapsed' => 'FUSION_LOGO_COLLAPSED', 'favicon' => 'FUSION_FAVICON');
	}
}

if (!function_exists('fz_fusion_custom_path')) {
	/**
	 * Relative path (under the mycompany directory) of the image uploaded for a place.
	 *
	 * @param	string	$place		expanded|collapsed|favicon
	 * @param	string	$thumb		Thumbnail suffix to prefer ('_small', '_mini'), '' for the original
	 * @return	string				'logos/fusion/…' path, or '' when none is uploaded
	 */
	function fz_fusion_custom_path($place, $thumb = '_small')
	{
		global $conf;

		$places = fz_fusion_branding_places();
		$file = isset($places[$place]) ? getDolGlobalString($places[$place].'_FILE') : '';
		if ($file === '' || empty($conf->mycompany->dir_output)) {
			return '';
		}
		$candidates = array('logos/fusion/'.$file);
		if ($thumb !== '') {
			$dot = strrpos($file, '.');
			$thumbfile = ($dot !== false) ? substr($file, 0, $dot).$thumb.substr($file, $dot) : $file.$thumb;
			array_unshift($candidates, 'logos/fusion/thumbs/'.$thumbfile);
		}
		foreach ($candidates as $relativePath) {
			if (is_readable($conf->mycompany->dir_output.'/'.$relativePath)) {
				return $relativePath;
			}
		}
		return '';
	}
}

if (!function_exists('fz_fusion_sidebar_logo_urls')) {
	/**
	 * Sidebar logo URLs, expanded and collapsed, from the FUSION_LOGO_* settings.
	 *
	 * @param	bool	$emptyAppname	True when MAIN_APPLICATION_TITLE is empty (automatic mode)
	 * @return	array{expanded:string,collapsed:string}	'' = show the initial letter
	 */
	function fz_fusion_sidebar_logo_urls($emptyAppname)
	{
		$urls = array();
		$auto = array(
			'expanded' => $emptyAppname ? 'wide' : 'square',
			'collapsed' => 'square',
		);
		foreach (array('expanded' => 'FUSION_LOGO_EXPANDED', 'collapsed' => 'FUSION_LOGO_COLLAPSED') as $mode => $const) {
			$choice = getDolGlobalString($const);
			if ($choice === 'custom') {
				$custom = fz_fusion_custom_path($mode);
				$urls[$mode] = ($custom === '') ? '' : DOL_URL_ROOT.'/viewimage.php?cache=1&modulepart=mycompany&file='.urlencode($custom);
			} elseif ($choice === 'none') {
				$urls[$mode] = '';
			} elseif ($choice === 'logo' || $choice === 'squarred') {
				$urls[$mode] = fz_fusion_logo_url($choice);
			} else {
				$urls[$mode] = fz_fusion_logo_url($auto[$mode]);
			}
		}
		return $urls;
	}
}

if (!function_exists('fz_fusion_favicon_url')) {
	/**
	 * URL of the favicon endpoint, the value branding/setup.php stores in MAIN_FAVICON_URL.
	 *
	 * @return	string
	 */
	function fz_fusion_favicon_url()
	{
		return DOL_URL_ROOT.'/theme/fusion/branding/favicon.php';
	}
}

if (!function_exists('fz_fusion_branding_words')) {
	/**
	 * Labels of the settings page, one table per language. English is the fallback.
	 *
	 * @return	array<string,string>
	 */
	function fz_fusion_branding_words()
	{
		global $langs;

		$words = array(
			'en' => array('title' => 'Fusion theme — logos & favicon', 'tab' => 'Logos & favicon', 'identity' => 'Identity', 'usage' => 'Where the logos are used', 'appname' => 'Application name', 'appnote' => 'Shown next to the sidebar logo. Left empty, no name is shown and the automatic choice puts the wide logo in the expanded menu. Same setting as Setup > Security > Miscellaneous.', 'place' => 'Place', 'source' => 'Source', 'preview' => 'Preview', 'expanded' => 'Menu expanded', 'collapsed' => 'Menu collapsed', 'favicon' => 'Favicon', 'auto' => 'Automatic', 'none' => 'Initial letter', 'custom' => 'Own image', 'dolibarr' => 'Dolibarr icon', 'default' => 'Dolibarr default (squarred logo)', 'favnote' => 'The favicon is served by the theme through the MAIN_FAVICON_URL constant. If you leave the Fusion theme, set it back to "Dolibarr default" first.', 'favother' => 'MAIN_FAVICON_URL currently holds another value (%s); it is replaced as soon as another source than "Dolibarr default" is chosen.'),
			'fr' => array('title' => 'Thème Fusion — logos et favicon', 'tab' => 'Logos et favicon', 'identity' => 'Identité', 'usage' => 'Utilisation des logos', 'appname' => 'Nom de l\'application', 'appnote' => 'Affiché à côté du logo de la barre latérale. Laissé vide, aucun nom n\'est affiché et le choix automatique met le grand logo dans le menu ouvert. Même réglage que Configuration > Sécurité > Divers.', 'place' => 'Emplacement', 'source' => 'Source', 'preview' => 'Aperçu', 'expanded' => 'Menu ouvert', 'collapsed' => 'Menu fermé', 'favicon' => 'Favicon', 'auto' => 'Automatique', 'none' => 'Lettre initiale', 'custom' => 'Image dédiée', 'dolibarr' => 'Icône Dolibarr', 'default' => 'Défaut Dolibarr (logo carré)', 'favnote' => 'Le favicon est servi par le thème via la constante MAIN_FAVICON_URL. Si vous quittez le thème Fusion, remettez d\'abord « Défaut Dolibarr ».', 'favother' => 'MAIN_FAVICON_URL contient actuellement une autre valeur (%s) ; elle sera remplacée dès qu\'une autre source que « Défaut Dolibarr » sera choisie.'),
			'es' => array('title' => 'Tema Fusion — logotipos y favicon', 'tab' => 'Logotipos y favicon', 'identity' => 'Identidad', 'usage' => 'Uso de los logotipos', 'appname' => 'Nombre de la aplicación', 'appnote' => 'Se muestra junto al logotipo de la barra lateral. Vacío, no se muestra ningún nombre y la opción automática pone el logotipo ancho en el menú abierto. Mismo ajuste que Configuración > Seguridad > Varios.', 'place' => 'Ubicación', 'source' => 'Origen', 'preview' => 'Vista previa', 'expanded' => 'Menú abierto', 'collapsed' => 'Menú cerrado', 'favicon' => 'Favicon', 'auto' => 'Automático', 'none' => 'Letra inicial', 'custom' => 'Imagen propia', 'dolibarr' => 'Icono de Dolibarr', 'default' => 'Predeterminado de Dolibarr (logotipo cuadrado)', 'favnote' => 'El tema sirve el favicon mediante la constante MAIN_FAVICON_URL. Si deja el tema Fusion, vuelva antes a «Predeterminado de Dolibarr».', 'favother' => 'MAIN_FAVICON_URL contiene otro valor (%s); se reemplazará en cuanto se elija otro origen que «Predeterminado de Dolibarr».'),
			'de' => array('title' => 'Fusion-Theme — Logos & Favicon', 'tab' => 'Logos & Favicon', 'identity' => 'Identität', 'usage' => 'Verwendung der Logos', 'appname' => 'Anwendungsname', 'appnote' => 'Wird neben dem Logo der Seitenleiste angezeigt. Leer wird kein Name angezeigt und die automatische Wahl zeigt das breite Logo im ausgeklappten Menü. Dieselbe Einstellung wie Einstellungen > Sicherheit > Sonstiges.', 'place' => 'Ort', 'source' => 'Quelle', 'preview' => 'Vorschau', 'expanded' => 'Menü ausgeklappt', 'collapsed' => 'Menü eingeklappt', 'favicon' => 'Favicon', 'auto' => 'Automatisch', 'none' => 'Anfangsbuchstabe', 'custom' => 'Eigenes Bild', 'dolibarr' => 'Dolibarr-Symbol', 'default' => 'Dolibarr-Standard (quadratisches Logo)', 'favnote' => 'Das Favicon wird vom Theme über die Konstante MAIN_FAVICON_URL ausgeliefert. Vor einem Wechsel des Themes bitte zuerst „Dolibarr-Standard" wählen.', 'favother' => 'MAIN_FAVICON_URL enthält derzeit einen anderen Wert (%s); er wird ersetzt, sobald eine andere Quelle als „Dolibarr-Standard" gewählt wird.'),
			'it' => array('title' => 'Tema Fusion — loghi e favicon', 'tab' => 'Loghi e favicon', 'identity' => 'Identità', 'usage' => 'Uso dei loghi', 'appname' => 'Nome dell\'applicazione', 'appnote' => 'Mostrato accanto al logo della barra laterale. Se vuoto, nessun nome è mostrato e la scelta automatica mette il logo largo nel menu aperto. Stessa impostazione di Impostazioni > Sicurezza > Varie.', 'place' => 'Posizione', 'source' => 'Origine', 'preview' => 'Anteprima', 'expanded' => 'Menu aperto', 'collapsed' => 'Menu chiuso', 'favicon' => 'Favicon', 'auto' => 'Automatico', 'none' => 'Lettera iniziale', 'custom' => 'Immagine dedicata', 'dolibarr' => 'Icona Dolibarr', 'default' => 'Predefinito Dolibarr (logo quadrato)', 'favnote' => 'La favicon è servita dal tema tramite la costante MAIN_FAVICON_URL. Se abbandoni il tema Fusion, rimetti prima «Predefinito Dolibarr».', 'favother' => 'MAIN_FAVICON_URL contiene un altro valore (%s); sarà sostituito appena si sceglie un\'origine diversa da «Predefinito Dolibarr».'),
			'pt' => array('title' => 'Tema Fusion — logótipos e favicon', 'tab' => 'Logótipos e favicon', 'identity' => 'Identidade', 'usage' => 'Utilização dos logótipos', 'appname' => 'Nome da aplicação', 'appnote' => 'Mostrado ao lado do logótipo da barra lateral. Vazio, nenhum nome é mostrado e a escolha automática coloca o logótipo largo no menu aberto. Mesma definição que Configuração > Segurança > Diversos.', 'place' => 'Local', 'source' => 'Origem', 'preview' => 'Pré-visualização', 'expanded' => 'Menu aberto', 'collapsed' => 'Menu fechado', 'favicon' => 'Favicon', 'auto' => 'Automático', 'none' => 'Letra inicial', 'custom' => 'Imagem própria', 'dolibarr' => 'Ícone Dolibarr', 'default' => 'Predefinido Dolibarr (logótipo quadrado)', 'favnote' => 'O favicon é servido pelo tema através da constante MAIN_FAVICON_URL. Se deixar o tema Fusion, reponha primeiro «Predefinido Dolibarr».', 'favother' => 'MAIN_FAVICON_URL contém outro valor (%s); será substituído quando for escolhida outra origem que «Predefinido Dolibarr».'),
			'nl' => array('title' => 'Fusion-thema — logo\'s & favicon', 'tab' => 'Logo\'s & favicon', 'identity' => 'Identiteit', 'usage' => 'Gebruik van de logo\'s', 'appname' => 'Naam van de applicatie', 'appnote' => 'Getoond naast het logo van de zijbalk. Leeg wordt geen naam getoond en zet de automatische keuze het brede logo in het uitgeklapte menu. Dezelfde instelling als Instellingen > Beveiliging > Diversen.', 'place' => 'Plaats', 'source' => 'Bron', 'preview' => 'Voorbeeld', 'expanded' => 'Menu uitgeklapt', 'collapsed' => 'Menu ingeklapt', 'favicon' => 'Favicon', 'auto' => 'Automatisch', 'none' => 'Beginletter', 'custom' => 'Eigen afbeelding', 'dolibarr' => 'Dolibarr-pictogram', 'default' => 'Dolibarr-standaard (vierkant logo)', 'favnote' => 'Het thema levert de favicon via de constante MAIN_FAVICON_URL. Kies eerst "Dolibarr-standaard" voordat u het Fusion-thema verlaat.', 'favother' => 'MAIN_FAVICON_URL bevat nu een andere waarde (%s); die wordt vervangen zodra een andere bron dan "Dolibarr-standaard" wordt gekozen.'),
		);
		$code = (isset($langs) && is_object($langs) && !empty($langs->defaultlang)) ? (string) $langs->defaultlang : '';
		$prefix = substr(strtolower($code), 0, 2);
		return isset($words[$prefix]) ? $words[$prefix] : $words['en'];
	}
}
