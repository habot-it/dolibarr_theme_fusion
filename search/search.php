<?php
/* Copyright (C) 2026  Fusion theme
 *
 * Theme "fusion" — the "all types" search page.
 *
 * Dolibarr's own search only asks *where* to search ("Search into third parties",
 * "Search into invoices"…) and then opens that one list. Fusion hides that choice:
 * Enter in the search field opens this page, which lists the matches of every type
 * at once, each group linking to its own list filtered on the same text.
 *
 *   GET  theme/fusion/search/search.php?q=<text>
 *
 * The types are exactly the entries core's search box offers to this user
 * (core/ajax/selectsearchbox.php: enabled modules, read permissions, the
 * MAIN_SEARCHFORM_*_DISABLED options, entries added by modules through the
 * addSearchEntry hook) — that include is the permission gate: a type core does not
 * offer is never queried. On top of that, every query applies the restrictions of
 * the matching list page: entity, external user, sale representative, private
 * contacts, product/service rights, authorized projects, subordinates for expense
 * reports and leaves. An entry Fusion has no query for (a module's own) is still
 * listed, with the link to its list. Read-only: nothing is stored.
 */

require __DIR__.'/../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

if (!function_exists('fz_fusion_search_types')) {
	/**
	 * What to query for each of core's search entries (keys of selectsearchbox.php).
	 *
	 * table/alias	the table and its alias in the queries ('join': extra joined tables)
	 * entity		element name given to getEntity()
	 * fields		columns matched by natural_search()
	 * ref, sub		columns shown on the first and second line (sub '' = the third party of 'soc',
	 *				'au' = the user joined as au, by name)
	 * person		ref is built from firstname + lastname instead
	 * soc			column holding the third party, for the external user and sale
	 *				representative restrictions ('' = not linked to a third party)
	 * socvia		same, for a row linked to its third parties through other rows:
	 *				a subquery where %s is the condition on their f.fk_soc
	 * salerep		false: only the external user restriction applies (users)
	 * url			card page, the row id is appended
	 *
	 * @return array<string,array<string,mixed>>
	 */
	function fz_fusion_search_types()
	{
		$p = MAIN_DB_PREFIX;
		return array(
			'searchintomember' => array('table' => 'adherent', 'entity' => 'adherent', 'fields' => array('t.ref', 't.lastname', 't.firstname', 't.societe', 't.email'),
				'person' => true, 'sub' => 't.societe', 'url' => '/adherents/card.php?rowid='),
			'searchintothirdparty' => array('table' => 'societe', 'entity' => 'societe', 'fields' => array('t.nom', 't.name_alias', 't.code_client', 't.code_fournisseur', 't.email', 't.tva_intra'),
				'ref' => 't.nom', 'sub' => 't.name_alias', 'soc' => 't.rowid', 'url' => '/societe/card.php?socid='),
			'searchintocontact' => array('table' => 'socpeople', 'entity' => 'contact', 'fields' => array('t.lastname', 't.firstname', 't.email'),
				'person' => true, 'sub' => '', 'soc' => 't.fk_soc', 'url' => '/contact/card.php?id='),
			'searchintoproduct' => array('table' => 'product', 'entity' => 'product', 'fields' => array('t.ref', 't.label', 't.barcode'),
				'ref' => 't.ref', 'sub' => 't.label', 'url' => '/product/card.php?id='),
			'searchintobatch' => array('table' => 'product_lot', 'entity' => 'productlot', 'join' => ' INNER JOIN '.$p.'product as pr ON pr.rowid = t.fk_product',
				'fields' => array('t.batch', 'pr.ref'), 'ref' => 't.batch', 'sub' => 'pr.ref', 'url' => '/product/stock/productlot_card.php?id='),
			'searchintomo' => array('table' => 'mrp_mo', 'entity' => 'mo', 'fields' => array('t.ref', 't.label'),
				'ref' => 't.ref', 'sub' => 't.label', 'soc' => 't.fk_soc', 'url' => '/mrp/mo_card.php?id='),
			'searchintoprojects' => array('table' => 'projet', 'entity' => 'project', 'fields' => array('t.ref', 't.title'),
				'ref' => 't.ref', 'sub' => 't.title', 'url' => '/projet/card.php?id='),
			'searchintotasks' => array('table' => 'projet_task', 'entity' => 'project', 'fields' => array('t.ref', 't.label'),
				'ref' => 't.ref', 'sub' => 't.label', 'url' => '/projet/tasks/task.php?id='),
			'searchintopropal' => array('table' => 'propal', 'entity' => 'propal', 'fields' => array('t.ref', 't.ref_client'),
				'ref' => 't.ref', 'sub' => '', 'soc' => 't.fk_soc', 'url' => '/comm/propal/card.php?id='),
			'searchintoorder' => array('table' => 'commande', 'entity' => 'commande', 'fields' => array('t.ref', 't.ref_client'),
				'ref' => 't.ref', 'sub' => '', 'soc' => 't.fk_soc', 'url' => '/commande/card.php?id='),
			'searchintoshipment' => array('table' => 'expedition', 'entity' => 'expedition', 'fields' => array('t.ref', 't.ref_customer'),
				'ref' => 't.ref', 'sub' => '', 'soc' => 't.fk_soc', 'url' => '/expedition/card.php?id='),
			'searchintoinvoice' => array('table' => 'facture', 'entity' => 'invoice', 'fields' => array('t.ref', 't.ref_client'),
				'ref' => 't.ref', 'sub' => '', 'soc' => 't.fk_soc', 'url' => '/compta/facture/card.php?facid='),
			'searchintoinvoicerec' => array('table' => 'facture_rec', 'entity' => 'invoice', 'fields' => array('t.titre'),
				'ref' => 't.titre', 'sub' => '', 'soc' => 't.fk_soc', 'url' => '/compta/facture/card-rec.php?id='),
			'searchintosupplierpropal' => array('table' => 'supplier_proposal', 'entity' => 'supplier_proposal', 'fields' => array('t.ref'),
				'ref' => 't.ref', 'sub' => '', 'soc' => 't.fk_soc', 'url' => '/supplier_proposal/card.php?id='),
			'searchintosupplierorder' => array('table' => 'commande_fournisseur', 'entity' => 'supplier_order', 'fields' => array('t.ref', 't.ref_supplier'),
				'ref' => 't.ref', 'sub' => '', 'soc' => 't.fk_soc', 'url' => '/fourn/commande/card.php?id='),
			'searchintoreception' => array('table' => 'reception', 'entity' => 'reception', 'fields' => array('t.ref', 't.ref_supplier'),
				'ref' => 't.ref', 'sub' => '', 'soc' => 't.fk_soc', 'url' => '/reception/card.php?id='),
			'searchintosupplierinvoice' => array('table' => 'facture_fourn', 'entity' => 'facture_fourn', 'fields' => array('t.ref', 't.ref_supplier'),
				'ref' => 't.ref', 'sub' => '', 'soc' => 't.fk_soc', 'url' => '/fourn/facture/card.php?facid='),
			'searchintocustomerpayments' => array('table' => 'paiement', 'entity' => 'invoice', 'fields' => array('t.ref', 't.num_paiement'),
				'ref' => 't.ref', 'sub' => 't.num_paiement', 'url' => '/compta/paiement/card.php?id=',
				'socvia' => 'SELECT pf.fk_paiement FROM '.$p.'paiement_facture as pf, '.$p.'facture as f WHERE pf.fk_paiement = t.rowid AND f.rowid = pf.fk_facture AND %s'),
			'searchintovendorpayments' => array('table' => 'paiementfourn', 'entity' => 'supplier_invoice', 'fields' => array('t.ref', 't.num_paiement'),
				'ref' => 't.ref', 'sub' => 't.num_paiement', 'url' => '/fourn/paiement/card.php?id=',
				'socvia' => 'SELECT pf.fk_paiementfourn FROM '.$p.'paiementfourn_facturefourn as pf, '.$p.'facture_fourn as f WHERE pf.fk_paiementfourn = t.rowid AND f.rowid = pf.fk_facturefourn AND %s'),
			'searchintomiscpayments' => array('table' => 'payment_various', 'entity' => 'payment_various', 'fields' => array('t.ref', 't.label', 't.num_payment'),
				'ref' => 't.ref', 'sub' => 't.label', 'url' => '/compta/bank/various_payment/card.php?id='),
			'searchintocontract' => array('table' => 'contrat', 'entity' => 'contract', 'fields' => array('t.ref', 't.ref_customer', 't.ref_supplier'),
				'ref' => 't.ref', 'sub' => '', 'soc' => 't.fk_soc', 'url' => '/contrat/card.php?id='),
			'searchintointervention' => array('table' => 'fichinter', 'entity' => 'intervention', 'fields' => array('t.ref', 't.ref_client'),
				'ref' => 't.ref', 'sub' => '', 'soc' => 't.fk_soc', 'url' => '/fichinter/card.php?id='),
			'searchintoknowledgemanagement' => array('table' => 'knowledgemanagement_knowledgerecord', 'entity' => 'knowledgerecord', 'fields' => array('t.ref', 't.question'),
				'ref' => 't.ref', 'sub' => 't.question', 'url' => '/knowledgemanagement/knowledgerecord_card.php?id='),
			'searchintotickets' => array('table' => 'ticket', 'entity' => 'ticket', 'fields' => array('t.ref', 't.subject', 't.track_id'),
				'ref' => 't.ref', 'sub' => 't.subject', 'soc' => 't.fk_soc', 'url' => '/ticket/card.php?id='),
			'searchintouser' => array('table' => 'user', 'entity' => 'user', 'fields' => array('t.login', 't.lastname', 't.firstname', 't.email'),
				'person' => true, 'sub' => 't.login', 'soc' => 't.fk_soc', 'salerep' => false, 'url' => '/user/card.php?id='),
			'searchintoexpensereport' => array('table' => 'expensereport', 'entity' => 'expensereport', 'fields' => array('t.ref'),
				'ref' => 't.ref', 'sub' => 'au', 'join' => ' LEFT JOIN '.$p.'user as au ON au.rowid = t.fk_user_author', 'url' => '/expensereport/card.php?id='),
			'searchintoleaves' => array('table' => 'holiday', 'entity' => 'holiday', 'fields' => array('t.ref', 't.description'),
				'ref' => 't.ref', 'sub' => 'au', 'join' => ' LEFT JOIN '.$p.'user as au ON au.rowid = t.fk_user', 'url' => '/holiday/card.php?id='),
			'searchintoresourcess' => array('table' => 'resource', 'entity' => 'resource', 'fields' => array('t.ref', 't.description'),
				'ref' => 't.ref', 'sub' => '', 'url' => '/resource/card.php?id='),
		);
	}
}

if (!function_exists('fz_fusion_search_text')) {
	/**
	 * Plain text, on one line, cut to a length.
	 *
	 * @param	mixed	$value	Database value
	 * @param	int		$max	Maximum length, in characters
	 * @return	string
	 */
	function fz_fusion_search_text($value, $max)
	{
		$value = is_string($value) ? dol_string_nohtmltag($value) : '';
		$value = trim(preg_replace('/[\x00-\x1F\x7F\s]+/u', ' ', $value));
		return dol_trunc($value, $max, 'right', 'UTF-8');
	}
}

if (!function_exists('fz_fusion_search_field_label')) {
	/**
	 * Label of a searched column, shown when a row matched on a column that is not on
	 * screen ("Description: …TEST…"), so the user sees why it is there.
	 *
	 * @param	string	$column	Column, with its alias ("t.description")
	 * @return	string
	 */
	function fz_fusion_search_field_label($column)
	{
		global $langs;
		$keys = array('description' => 'Description', 'email' => 'Email', 'ref_client' => 'RefCustomer', 'ref_customer' => 'RefCustomer',
			'ref_supplier' => 'RefSupplier', 'code_client' => 'CustomerCode', 'code_fournisseur' => 'SupplierCode', 'tva_intra' => 'VATIntra',
			'barcode' => 'BarCode', 'name_alias' => 'AliasNames', 'track_id' => 'TicketTrackId', 'num_paiement' => 'PaymentNumber',
			'num_payment' => 'PaymentNumber', 'login' => 'Login', 'label' => 'Label', 'title' => 'Title', 'titre' => 'Title', 'subject' => 'Subject',
			'batch' => 'Batch', 'societe' => 'Company', 'firstname' => 'Firstname', 'lastname' => 'Lastname', 'nom' => 'ThirdPartyName', 'ref' => 'Ref');
		$name = preg_replace('/^.*\./', '', $column);
		return isset($keys[$name]) ? $langs->transnoentitiesnoconv($keys[$name]) : $name;
	}
}

if (!function_exists('fz_fusion_search_excerpt')) {
	/**
	 * The part of a text around the first of the searched words it contains.
	 *
	 * @param	string			$text	Plain text
	 * @param	array<string>	$words	Searched words, lowercase
	 * @param	int				$max	Length of the excerpt, in characters
	 * @return	string					'' when the text contains none of the words
	 */
	function fz_fusion_search_excerpt($text, $words, $max)
	{
		foreach ($words as $word) {
			$at = mb_stripos($text, $word, 0, 'UTF-8');
			if ($at === false) {
				continue;
			}
			$start = max(0, $at - (int) floor(($max - mb_strlen($word, 'UTF-8')) / 2));
			$excerpt = mb_substr($text, $start, $max, 'UTF-8');
			return ($start > 0 ? '…' : '').$excerpt.($start + $max < mb_strlen($text, 'UTF-8') ? '…' : '');
		}
		return '';
	}
}

if (!function_exists('fz_fusion_search_query')) {
	/**
	 * The matches of one type, with the restrictions of its list page.
	 *
	 * @param	string					$key	Entry key of selectsearchbox.php
	 * @param	array<string,mixed>		$type	Its fz_fusion_search_types() definition
	 * @param	string					$q		Searched text
	 * @param	int						$limit	Maximum number of rows
	 * @return	array<int,array{u:string,r:string,l?:string,m?:string,x?:string}>|null	Null when the query failed;
	 *				m/x: label and excerpt of the column matched, when it is not on screen
	 */
	function fz_fusion_search_query($key, $type, $q, $limit)
	{
		global $db, $user;

		$socid = $user->socid > 0 ? (int) $user->socid : 0;
		$restrictsale = !$socid && !$user->hasRight('societe', 'client', 'voir');
		$soc = isset($type['soc']) ? $type['soc'] : '';
		$person = !empty($type['person']);
		$withsoc = ($type['sub'] === '' && $soc !== '');

		$sql = "SELECT t.rowid";
		$sql .= $person ? ", t.firstname, t.lastname" : ", ".$type['ref']." as ref";
		$subuser = ($type['sub'] === 'au');
		if ($withsoc) {
			$sql .= ", so.nom as sub";
		} elseif ($subuser) {
			$sql .= ", au.firstname as subfirstname, au.lastname as sublastname";
		} elseif ($type['sub'] !== '') {
			$sql .= ", ".$type['sub']." as sub";
		}
		foreach ($type['fields'] as $i => $field) {
			$sql .= ", ".$field." as fz_m".$i;
		}
		$sql .= " FROM ".$db->prefix().$type['table']." as t";
		$sql .= isset($type['join']) ? $type['join'] : '';
		if ($withsoc) {
			$sql .= " LEFT JOIN ".$db->prefix()."societe as so ON so.rowid = ".$soc;
		}
		$sql .= " WHERE t.entity IN (".getEntity($type['entity']).")";
		$sql .= natural_search($type['fields'], $q);

		// Third party restrictions, as on the list pages
		$salerep = !isset($type['salerep']) || $type['salerep'];
		if ($soc !== '') {
			if ($socid) {
				$sql .= " AND ".$soc." = ".((int) $socid);
			} elseif ($restrictsale && $salerep) {
				$sql .= " AND EXISTS (SELECT sc.fk_soc FROM ".$db->prefix()."societe_commerciaux as sc WHERE sc.fk_soc = ".$soc." AND sc.fk_user = ".((int) $user->id).")";
			}
		} elseif (isset($type['socvia'])) {
			if ($socid) {
				$sql .= " AND EXISTS (".sprintf($type['socvia'], "f.fk_soc = ".((int) $socid)).")";
			} elseif ($restrictsale) {
				$sql .= " AND EXISTS (".sprintf($type['socvia'], "EXISTS (SELECT sc.fk_soc FROM ".$db->prefix()."societe_commerciaux as sc WHERE sc.fk_soc = f.fk_soc AND sc.fk_user = ".((int) $user->id).")").")";
			}
		}
		// Restrictions of their own
		if ($key == 'searchintocontact') {
			$sql .= " AND (t.priv = 0 OR t.fk_user_creat = ".((int) $user->id).")";
		}
		if ($key == 'searchintoproduct' || $key == 'searchintobatch') {
			$canproduct = isModEnabled('product') && $user->hasRight('product', 'read');
			$canservice = isModEnabled('service') && $user->hasRight('service', 'read');
			if (!$canproduct || !$canservice) {
				$sql .= " AND ".($key == 'searchintobatch' ? 'pr' : 't').".fk_product_type = ".($canproduct ? 0 : 1);
			}
		}
		if (($key == 'searchintoprojects' || $key == 'searchintotasks') && !$user->hasRight('projet', 'all', 'lire')) {
			$project = new Project($db);
			$allowed = $project->getProjectsAuthorizedForUser($user, 0, 1, $socid);
			$sql .= " AND t.".($key == 'searchintotasks' ? 'fk_projet' : 'rowid')." IN (".$db->sanitize($allowed !== '' ? $allowed : '0').")";
		}
		if ($key == 'searchintomember' && $socid) {
			$sql .= " AND t.fk_soc = ".((int) $socid);
		}
		if ($key == 'searchintoexpensereport' && !$user->hasRight('expensereport', 'readall') && !$user->hasRight('expensereport', 'lire_tous')
			&& (!getDolGlobalString('MAIN_USE_ADVANCED_PERMS') || !$user->hasRight('expensereport', 'writeall_advance'))) {
			$sql .= " AND t.fk_user_author IN (".$db->sanitize(implode(',', $user->getAllChildIds(1))).")";
		}
		if ($key == 'searchintoleaves' && !$user->hasRight('holiday', 'readall')) {
			$sql .= " AND t.fk_user IN (".$db->sanitize(implode(',', $user->getAllChildIds(1))).")";
		}
		$sql .= " ORDER BY t.rowid DESC";
		$sql .= $db->plimit($limit);

		$resql = $db->query($sql);
		if (!$resql) {
			dol_syslog('Fusion search: '.$key.' failed: '.$db->lasterror(), LOG_WARNING);
			return null;
		}
		$words = array_filter(preg_split('/\s+/u', mb_strtolower($q, 'UTF-8')), 'strlen');
		$items = array();
		while ($obj = $db->fetch_object($resql)) {
			$ref = $person ? dolGetFirstLastname((string) $obj->firstname, (string) $obj->lastname) : (string) $obj->ref;
			$item = array('u' => DOL_URL_ROOT.$type['url'].((int) $obj->rowid), 'r' => fz_fusion_search_text($ref, 120));
			if ($item['r'] === '') {
				$item['r'] = '#'.((int) $obj->rowid);
			}
			$sub = $subuser ? dolGetFirstLastname((string) $obj->subfirstname, (string) $obj->sublastname) : (isset($obj->sub) ? $obj->sub : '');
			$sub = fz_fusion_search_text($sub, 160);
			if ($sub !== '' && $sub !== $item['r']) {
				$item['l'] = $sub;
			}
			// Matched on a column not on screen: show which one, around the match
			$shown = mb_strtolower($item['r'].' '.$sub, 'UTF-8');
			$hidden = array();
			foreach ($words as $word) {
				if (mb_strpos($shown, $word, 0, 'UTF-8') === false) {
					$hidden[] = $word;
				}
			}
			foreach ($hidden ? $type['fields'] : array() as $i => $field) {
				$prop = 'fz_m'.$i;
				$text = isset($obj->$prop) ? fz_fusion_search_text((string) $obj->$prop, 2000) : '';
				$excerpt = fz_fusion_search_excerpt($text, $hidden, 110);
				if ($excerpt !== '') {
					$item['m'] = fz_fusion_search_field_label($field);
					$item['x'] = $excerpt;
					break;
				}
			}
			$items[] = $item;
		}
		$db->free($resql);
		return $items;
	}
}

if (empty($user->id)) {
	accessforbidden();
}

// The native form sends search_all (its own field name), fusion.js sends q
$q = trim(GETPOST('q', 'alphanohtml'));
if ($q === '') {
	$q = trim(GETPOST('search_all', 'alphanohtml'));
}
$q = dol_trunc($q, 100, 'right', 'UTF-8', 1);
$limit = max(1, min(50, getDolGlobalInt('FUSION_SEARCH_LIMIT', 10)));

// The entries core offers to this user, in its own order, labels and list urls.
// selectsearchbox.php reads the text from GETPOST('q').
$_GET['q'] = $q;
$hookmanager->initHooks(array('searchform'));
$usedbyinclude = 1;
$arrayresult = array();
if ($q !== '') {
	include DOL_DOCUMENT_ROOT.'/core/ajax/selectsearchbox.php';
}
$types = fz_fusion_search_types();

$title = $langs->trans('Search').($q !== '' ? ' : '.$q : '');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-fusion page-search');

print load_fiche_titre($langs->trans('Search'), '', 'search');

print '<form method="GET" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" class="fz-search-page-form">';
print '<input type="search" name="q" class="flat minwidth300" value="'.dol_escape_htmltag($q).'" placeholder="'.dol_escape_htmltag($langs->trans('Search')).'" autofocus>';
print ' <input type="submit" class="button small" value="'.dol_escape_htmltag($langs->trans('Search')).'">';
print '</form><br>';

$found = 0;
foreach ($arrayresult as $key => $entry) {
	if (!is_array($entry) || empty($entry['url'])) {
		continue;
	}
	$items = isset($types[$key]) ? fz_fusion_search_query($key, $types[$key], $q, $limit) : null;
	if (isset($types[$key]) && !$items) {
		continue;	// a type Fusion searched, with nothing found (or a failed query): no group
	}
	$found += $items ? count($items) : 0;
	// core's own text: the picto and the type, as in its search box
	print '<div class="div-table-responsive-no-min fz-search-group">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><th>'.(isset($entry['text']) ? $entry['text'] : dol_escape_htmltag($entry['label'])).'</th>';
	print '<th class="right"><a href="'.dol_escape_htmltag($entry['url']).'">'.dol_escape_htmltag($langs->trans('SeeAll')).'</a></th></tr>';
	foreach ($items ? $items : array() as $item) {
		print '<tr class="oddeven"><td class="tdoverflowmax300"><a href="'.dol_escape_htmltag($item['u']).'">'.dol_escape_htmltag($item['r']).'</a></td>';
		print '<td class="tdoverflowmax500"><span class="opacitymedium">'.dol_escape_htmltag(isset($item['l']) ? $item['l'] : '').'</span>';
		if (isset($item['m'])) {
			// the searched words in bold: split the raw text on them, then escape every
			// part (bolding the escaped text could cut an entity such as &Eacute; in two)
			$pattern = implode('|', array_map(function ($word) {
				return preg_quote($word, '/');
			}, array_filter(preg_split('/\s+/u', $q), 'strlen')));
			$excerpt = '';
			foreach (preg_split('/('.$pattern.')/iu', $item['x'], -1, PREG_SPLIT_DELIM_CAPTURE) as $n => $part) {
				$excerpt .= ($n % 2) ? '<strong>'.dol_escape_htmltag($part).'</strong>' : dol_escape_htmltag($part);
			}
			print (isset($item['l']) ? '<br>' : '').'<span class="small"><span class="opacitymedium">'.dol_escape_htmltag($item['m']).' :</span> '.$excerpt.'</span>';
		}
		print '</td></tr>';
	}
	print '</table></div><br>';
}
if ($q !== '' && !$found && !array_diff_key($arrayresult, $types)) {
	print '<div class="opacitymedium">'.dol_escape_htmltag($langs->trans('NoRecordFound')).'</div>';
}

llxFooter();
$db->close();
