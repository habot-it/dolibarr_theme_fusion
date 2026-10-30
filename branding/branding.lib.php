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

if (!function_exists('fz_fusion_branding_head')) {
	/**
	 * Tabs of Setup > Display, plus the theme's own two (settings, changelog). fusion.js
	 * adds the same two to the core Display pages, which a theme cannot extend server-side.
	 *
	 * @return	array	Tabs, as dol_get_fiche_head() takes them
	 */
	function fz_fusion_branding_head()
	{
		require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
		$words = fz_fusion_branding_words();
		$head = ihm_prepare_head();
		$head[] = array(DOL_URL_ROOT.'/theme/fusion/branding/setup.php', $words['tab'], 'fusionbranding');
		$head[] = array(DOL_URL_ROOT.'/theme/fusion/branding/changelog.php', $words['changelog'], 'fusionchangelog');
		return $head;
	}
}

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

if (!function_exists('fz_fusion_required_consts')) {
	/**
	 * Dolibarr constants the theme cannot work without. Choosing the theme in Setup >
	 * Display only sets MAIN_THEME: a theme is no module, core gives it no activation
	 * step. sql/activate_fusion.sql sets these, and so does the settings page.
	 *   ALLOW_THEME_JS                       core loads fusion.js, which builds the shell
	 *   MAIN_USE_TOP_MENU_SEARCH_DROPDOWN    core renders the search node fusion.js moves
	 *   MAIN_USE_TOP_MENU_QUICKADD_DROPDOWN  same for the quick-add node
	 *
	 * @return	array<string,string>	Name => required value
	 */
	function fz_fusion_required_consts()
	{
		return array('ALLOW_THEME_JS' => '1', 'MAIN_USE_TOP_MENU_SEARCH_DROPDOWN' => '1', 'MAIN_USE_TOP_MENU_QUICKADD_DROPDOWN' => '1');
	}
}

if (!function_exists('fz_fusion_missing_consts')) {
	/**
	 * The required constants that do not hold their value in the current entity.
	 *
	 * @return	array<string,string>	Name => required value
	 */
	function fz_fusion_missing_consts()
	{
		$missing = array();
		foreach (fz_fusion_required_consts() as $name => $value) {
			if (getDolGlobalString($name) !== $value) {
				$missing[$name] = $value;
			}
		}
		return $missing;
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
			'en' => array('changelog' => 'Fusion theme changelog', 'hideworkboard' => 'Hide “%s”', 'title' => 'Fusion theme', 'tab' => 'Fusion theme', 'identity' => 'Identity', 'usage' => 'Where the logos are used', 'appname' => 'Application name', 'appnote' => 'Shown next to the sidebar logo. Left empty, no name is shown and the automatic choice puts the wide logo in the expanded menu. Same setting as Setup > Security > Miscellaneous.', 'place' => 'Place', 'source' => 'Source', 'preview' => 'Preview', 'expanded' => 'Menu expanded', 'collapsed' => 'Menu collapsed', 'favicon' => 'Favicon', 'auto' => 'Automatic', 'none' => 'Initial letter', 'custom' => 'Own image', 'dolibarr' => 'Dolibarr icon', 'default' => 'Dolibarr default (squarred logo)', 'favnote' => 'The favicon is served by the theme through the MAIN_FAVICON_URL constant. If you leave the Fusion theme, set it back to "Dolibarr default" first.', 'favother' => 'MAIN_FAVICON_URL currently holds another value (%s); it is replaced as soon as another source than "Dolibarr default" is chosen.', 'history' => 'Navigation history', 'histenabled' => 'Show the history', 'histnote' => 'The last elements opened (third parties, products, invoices…), shown above the page like a breadcrumb. Kept per user: the history follows the user from one browser to another and survives a new login.', 'histsize' => 'Elements shown', 'histkeep' => 'Elements kept', 'reqmissing' => 'Settings the Fusion theme needs are missing for this entity: %s. Without them the theme cannot build its menu, dashboard, history and search; Dolibarr\'s display setup does not set them when the theme is chosen.', 'reqapply' => 'Apply the required settings'),
			'fr' => array('changelog' => 'Changelog Thème Fusion', 'hideworkboard' => 'Masquer « %s »', 'title' => 'Thème Fusion', 'tab' => 'Thème Fusion', 'identity' => 'Identité', 'usage' => 'Utilisation des logos', 'appname' => 'Nom de l\'application', 'appnote' => 'Affiché à côté du logo de la barre latérale. Laissé vide, aucun nom n\'est affiché et le choix automatique met le grand logo dans le menu ouvert. Même réglage que Configuration > Sécurité > Divers.', 'place' => 'Emplacement', 'source' => 'Source', 'preview' => 'Aperçu', 'expanded' => 'Menu ouvert', 'collapsed' => 'Menu fermé', 'favicon' => 'Favicon', 'auto' => 'Automatique', 'none' => 'Lettre initiale', 'custom' => 'Image dédiée', 'dolibarr' => 'Icône Dolibarr', 'default' => 'Défaut Dolibarr (logo carré)', 'favnote' => 'Le favicon est servi par le thème via la constante MAIN_FAVICON_URL. Si vous quittez le thème Fusion, remettez d\'abord « Défaut Dolibarr ».', 'favother' => 'MAIN_FAVICON_URL contient actuellement une autre valeur (%s) ; elle sera remplacée dès qu\'une autre source que « Défaut Dolibarr » sera choisie.', 'history' => 'Historique de navigation', 'histenabled' => 'Afficher l\'historique', 'histnote' => 'Les derniers éléments ouverts (tiers, produits, factures…), affichés au-dessus de la page comme un fil d\'Ariane. Conservé par utilisateur : l\'historique suit l\'utilisateur d\'un navigateur à l\'autre et se retrouve à la reconnexion.', 'histsize' => 'Éléments affichés', 'histkeep' => 'Éléments mémorisés', 'reqmissing' => 'Des réglages nécessaires au thème Fusion manquent pour cette entité : %s. Sans eux, le thème ne peut pas construire son menu, son tableau de bord, son historique ni sa recherche ; la configuration de l\'affichage de Dolibarr ne les pose pas quand on choisit le thème.', 'reqapply' => 'Appliquer la configuration requise'),
			'es' => array('changelog' => 'Cambios del tema Fusion', 'hideworkboard' => 'Ocultar «%s»', 'title' => 'Tema Fusion', 'tab' => 'Tema Fusion', 'identity' => 'Identidad', 'usage' => 'Uso de los logotipos', 'appname' => 'Nombre de la aplicación', 'appnote' => 'Se muestra junto al logotipo de la barra lateral. Vacío, no se muestra ningún nombre y la opción automática pone el logotipo ancho en el menú abierto. Mismo ajuste que Configuración > Seguridad > Varios.', 'place' => 'Ubicación', 'source' => 'Origen', 'preview' => 'Vista previa', 'expanded' => 'Menú abierto', 'collapsed' => 'Menú cerrado', 'favicon' => 'Favicon', 'auto' => 'Automático', 'none' => 'Letra inicial', 'custom' => 'Imagen propia', 'dolibarr' => 'Icono de Dolibarr', 'default' => 'Predeterminado de Dolibarr (logotipo cuadrado)', 'favnote' => 'El tema sirve el favicon mediante la constante MAIN_FAVICON_URL. Si deja el tema Fusion, vuelva antes a «Predeterminado de Dolibarr».', 'favother' => 'MAIN_FAVICON_URL contiene otro valor (%s); se reemplazará en cuanto se elija otro origen que «Predeterminado de Dolibarr».', 'history' => 'Historial de navegación', 'histenabled' => 'Mostrar el historial', 'histnote' => 'Los últimos elementos abiertos (terceros, productos, facturas…), mostrados encima de la página como una ruta de navegación. Guardado por usuario: el historial sigue al usuario de un navegador a otro y se conserva al volver a conectarse.', 'histsize' => 'Elementos mostrados', 'histkeep' => 'Elementos guardados', 'reqmissing' => 'Faltan ajustes que el tema Fusion necesita para esta entidad: %s. Sin ellos el tema no puede construir su menú, su panel, su historial ni su búsqueda; la configuración de visualización de Dolibarr no los define al elegir el tema.', 'reqapply' => 'Aplicar la configuración necesaria'),
			'de' => array('changelog' => 'Fusion-Theme Änderungen', 'hideworkboard' => '„%s“ ausblenden', 'title' => 'Fusion-Theme', 'tab' => 'Fusion-Theme', 'identity' => 'Identität', 'usage' => 'Verwendung der Logos', 'appname' => 'Anwendungsname', 'appnote' => 'Wird neben dem Logo der Seitenleiste angezeigt. Leer wird kein Name angezeigt und die automatische Wahl zeigt das breite Logo im ausgeklappten Menü. Dieselbe Einstellung wie Einstellungen > Sicherheit > Sonstiges.', 'place' => 'Ort', 'source' => 'Quelle', 'preview' => 'Vorschau', 'expanded' => 'Menü ausgeklappt', 'collapsed' => 'Menü eingeklappt', 'favicon' => 'Favicon', 'auto' => 'Automatisch', 'none' => 'Anfangsbuchstabe', 'custom' => 'Eigenes Bild', 'dolibarr' => 'Dolibarr-Symbol', 'default' => 'Dolibarr-Standard (quadratisches Logo)', 'favnote' => 'Das Favicon wird vom Theme über die Konstante MAIN_FAVICON_URL ausgeliefert. Vor einem Wechsel des Themes bitte zuerst „Dolibarr-Standard" wählen.', 'favother' => 'MAIN_FAVICON_URL enthält derzeit einen anderen Wert (%s); er wird ersetzt, sobald eine andere Quelle als „Dolibarr-Standard" gewählt wird.', 'history' => 'Navigationsverlauf', 'histenabled' => 'Verlauf anzeigen', 'histnote' => 'Die zuletzt geöffneten Elemente (Partner, Produkte, Rechnungen…), über der Seite wie eine Brotkrümelnavigation angezeigt. Pro Benutzer gespeichert: der Verlauf folgt dem Benutzer von einem Browser zum anderen und bleibt nach einer neuen Anmeldung erhalten.', 'histsize' => 'Angezeigte Elemente', 'histkeep' => 'Gespeicherte Elemente', 'reqmissing' => 'Für diese Entität fehlen Einstellungen, die das Fusion-Theme benötigt: %s. Ohne sie kann das Theme sein Menü, sein Dashboard, seinen Verlauf und seine Suche nicht aufbauen; die Anzeigeeinstellungen von Dolibarr setzen sie bei der Wahl des Themes nicht.', 'reqapply' => 'Erforderliche Einstellungen anwenden'),
			'it' => array('changelog' => 'Changelog tema Fusion', 'hideworkboard' => 'Nascondi «%s»', 'title' => 'Tema Fusion', 'tab' => 'Tema Fusion', 'identity' => 'Identità', 'usage' => 'Uso dei loghi', 'appname' => 'Nome dell\'applicazione', 'appnote' => 'Mostrato accanto al logo della barra laterale. Se vuoto, nessun nome è mostrato e la scelta automatica mette il logo largo nel menu aperto. Stessa impostazione di Impostazioni > Sicurezza > Varie.', 'place' => 'Posizione', 'source' => 'Origine', 'preview' => 'Anteprima', 'expanded' => 'Menu aperto', 'collapsed' => 'Menu chiuso', 'favicon' => 'Favicon', 'auto' => 'Automatico', 'none' => 'Lettera iniziale', 'custom' => 'Immagine dedicata', 'dolibarr' => 'Icona Dolibarr', 'default' => 'Predefinito Dolibarr (logo quadrato)', 'favnote' => 'La favicon è servita dal tema tramite la costante MAIN_FAVICON_URL. Se abbandoni il tema Fusion, rimetti prima «Predefinito Dolibarr».', 'favother' => 'MAIN_FAVICON_URL contiene un altro valore (%s); sarà sostituito appena si sceglie un\'origine diversa da «Predefinito Dolibarr».', 'history' => 'Cronologia di navigazione', 'histenabled' => 'Mostra la cronologia', 'histnote' => 'Gli ultimi elementi aperti (soggetti terzi, prodotti, fatture…), mostrati sopra la pagina come un percorso. Conservata per utente: la cronologia segue l\'utente da un browser all\'altro e si ritrova al nuovo accesso.', 'histsize' => 'Elementi mostrati', 'histkeep' => 'Elementi conservati', 'reqmissing' => 'Mancano impostazioni necessarie al tema Fusion per questa entità: %s. Senza di esse il tema non può costruire il menu, la dashboard, la cronologia e la ricerca; la configurazione della visualizzazione di Dolibarr non le imposta quando si sceglie il tema.', 'reqapply' => 'Applica la configurazione richiesta'),
			'pt' => array('changelog' => 'Alterações do tema Fusion', 'hideworkboard' => 'Ocultar «%s»', 'title' => 'Tema Fusion', 'tab' => 'Tema Fusion', 'identity' => 'Identidade', 'usage' => 'Utilização dos logótipos', 'appname' => 'Nome da aplicação', 'appnote' => 'Mostrado ao lado do logótipo da barra lateral. Vazio, nenhum nome é mostrado e a escolha automática coloca o logótipo largo no menu aberto. Mesma definição que Configuração > Segurança > Diversos.', 'place' => 'Local', 'source' => 'Origem', 'preview' => 'Pré-visualização', 'expanded' => 'Menu aberto', 'collapsed' => 'Menu fechado', 'favicon' => 'Favicon', 'auto' => 'Automático', 'none' => 'Letra inicial', 'custom' => 'Imagem própria', 'dolibarr' => 'Ícone Dolibarr', 'default' => 'Predefinido Dolibarr (logótipo quadrado)', 'favnote' => 'O favicon é servido pelo tema através da constante MAIN_FAVICON_URL. Se deixar o tema Fusion, reponha primeiro «Predefinido Dolibarr».', 'favother' => 'MAIN_FAVICON_URL contém outro valor (%s); será substituído quando for escolhida outra origem que «Predefinido Dolibarr».', 'history' => 'Histórico de navegação', 'histenabled' => 'Mostrar o histórico', 'histnote' => 'Os últimos elementos abertos (terceiros, produtos, faturas…), mostrados acima da página como um trilho de navegação. Guardado por utilizador: o histórico acompanha o utilizador de um navegador para outro e mantém-se num novo início de sessão.', 'histsize' => 'Elementos mostrados', 'histkeep' => 'Elementos guardados', 'reqmissing' => 'Faltam definições necessárias ao tema Fusion para esta entidade: %s. Sem elas o tema não consegue construir o menu, o painel, o histórico nem a pesquisa; a configuração da visualização do Dolibarr não as define ao escolher o tema.', 'reqapply' => 'Aplicar a configuração necessária'),
			'nl' => array('changelog' => 'Fusion-thema wijzigingen', 'hideworkboard' => '„%s” verbergen', 'title' => 'Fusion-thema', 'tab' => 'Fusion-thema', 'identity' => 'Identiteit', 'usage' => 'Gebruik van de logo\'s', 'appname' => 'Naam van de applicatie', 'appnote' => 'Getoond naast het logo van de zijbalk. Leeg wordt geen naam getoond en zet de automatische keuze het brede logo in het uitgeklapte menu. Dezelfde instelling als Instellingen > Beveiliging > Diversen.', 'place' => 'Plaats', 'source' => 'Bron', 'preview' => 'Voorbeeld', 'expanded' => 'Menu uitgeklapt', 'collapsed' => 'Menu ingeklapt', 'favicon' => 'Favicon', 'auto' => 'Automatisch', 'none' => 'Beginletter', 'custom' => 'Eigen afbeelding', 'dolibarr' => 'Dolibarr-pictogram', 'default' => 'Dolibarr-standaard (vierkant logo)', 'favnote' => 'Het thema levert de favicon via de constante MAIN_FAVICON_URL. Kies eerst "Dolibarr-standaard" voordat u het Fusion-thema verlaat.', 'favother' => 'MAIN_FAVICON_URL bevat nu een andere waarde (%s); die wordt vervangen zodra een andere bron dan "Dolibarr-standaard" wordt gekozen.', 'history' => 'Navigatiegeschiedenis', 'histenabled' => 'Geschiedenis tonen', 'histnote' => 'De laatst geopende elementen (relaties, producten, facturen…), boven de pagina getoond als een kruimelpad. Per gebruiker bewaard: de geschiedenis volgt de gebruiker van de ene browser naar de andere en blijft na opnieuw inloggen.', 'histsize' => 'Getoonde elementen', 'histkeep' => 'Bewaarde elementen', 'reqmissing' => 'Instellingen die het Fusion-thema nodig heeft ontbreken voor deze entiteit: %s. Zonder deze kan het thema zijn menu, dashboard, geschiedenis en zoekfunctie niet opbouwen; de weergave-instellingen van Dolibarr zetten ze niet bij het kiezen van het thema.', 'reqapply' => 'Vereiste instellingen toepassen'),
		);
		$code = (isset($langs) && is_object($langs) && !empty($langs->defaultlang)) ? (string) $langs->defaultlang : '';
		$prefix = substr(strtolower($code), 0, 2);
		return isset($words[$prefix]) ? $words[$prefix] : $words['en'];
	}
}
