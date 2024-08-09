<?php

$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom"

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/usergroups.lib.php';

dol_include_once('/digikanban/class/digikanban.class.php');
dol_include_once('/digikanban/class/digikanban_columns.class.php');
dol_include_once('/digikanban/lib/digikanban.lib.php');

if (empty($conf->digikanban->enabled) || !$user->rights->digikanban->lire) accessforbidden();

// Load translation files required by the page
$langs->loadLangs(array('users', 'other', 'hrm'));
$langs->load('digikanban@digikanban');

// Protection if external user
if ($user->socid > 0) {
	accessforbidden();
}

$usercancreate = $user->rights->digikanban->creer;
$usercandelete = $user->rights->digikanban->supprimer;


$action     = GETPOST('action', 'aZ09'); 		// The action 'add', 'create', 'edit', 'update', 'view', ...
$massaction = GETPOST('massaction', 'alpha'); 	// The bulk action (combo box choice into lists)
$show_files = GETPOST('show_files', 'int'); 	// Show files area generated by bulk actions ?
$confirm    = GETPOST('confirm', 'alpha'); 		// Result of a confirmation
$cancel     = GETPOST('cancel', 'alpha'); 		// We click on a Cancel button
$toselect   = GETPOST('toselect', 'array'); 	// Array of ids of elements selected into a list
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'digikanban_columns'; // To manage different context of search

$backtopage = GETPOST('backtopage', 'alpha'); // Go back to a dedicated page
$optioncss = GETPOST('optioncss', 'aZ'); // Option for the css output (always '' except when 'print')

$id = GETPOST('id', 'int');

// Security check
if ($user->socid > 0) {	// Protection if external user
	//$socid = $user->socid;
	accessforbidden();
}

$diroutputmassaction = $conf->digikanban->dir_output.'/temp/massgeneration/'.$user->id;

// Load variable for pagination
$limit = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone') - 1) : GETPOST("page", 'int');

if (empty($page) || $page == -1) {
	$page = 0;
}     // If $page is not defined, or '' or -1
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (!$sortorder) {
	$sortorder = "DESC";
}
if (!$sortfield) {
	$sortfield = "o.rowid";
}

$search_rowid        = GETPOST('search_rowid', 'alphanohtml');
$search_label        = GETPOST('search_label', 'alphanohtml');
$search_author       = GETPOST('search_author', 'int');

$search_datec_startday = GETPOST('search_datec_startday', 'int');
$search_datec_startyear = GETPOST('search_datec_startyear', 'int');
$search_datec_startmonth = GETPOST('search_datec_startmonth', 'int');

$search_datec_endday = GETPOST('srch_datec_endday', 'int');
$search_datec_endyear = GETPOST('srch_datec_endyear', 'int');
$search_datec_endmonth = GETPOST('srch_datec_endmonth', 'int');

$search_tms_startday = GETPOST('srch_tms_startday', 'int');
$search_tms_startyear = GETPOST('srch_tms_startyear', 'int');
$search_tms_startmonth = GETPOST('srch_tms_startmonth', 'int');

$search_tms_endday = GETPOST('srch_tms_endday', 'int');
$search_tms_endyear = GETPOST('srch_tms_endyear', 'int');
$search_tms_endmonth = GETPOST('srch_tms_endmonth', 'int');

$search_datec_start = dol_mktime(0, 0, 0, $search_datec_startmonth, $search_datec_startday, $search_datec_startyear);
$search_datec_end 	= dol_mktime(23, 59, 59, $search_datec_endmonth, $search_datec_endday, $search_datec_endyear);

$search_tms_start   = dol_mktime(0, 0, 0, $search_tms_startmonth, $search_tms_startday, $search_tms_startyear);
$search_tms_end     = dol_mktime(23, 59, 59, $search_tms_endmonth, $search_tms_endday, $search_tms_endyear);

// Initialize technical objects
$object 			= new digikanban_columns($db);
$type	            = new digikanban_columns($db);
$form 				= new Form($db);
$formother 			= new FormOther($db);
$formfile 			= new FormFile($db);
$fuser 				= new User($db);
$userauthor         = new User($db);

$arrayfields = array(
	'o.rowid'=>array('label'=>$langs->trans("Ref"), 'checked'=>1),
	'o.label'=>array('label'=>$langs->trans("Label"), 'checked'=>1, 'position'=>20),
	'o.fk_user_author'=>array('label'=>$langs->trans("Author"), 'checked'=>1, 'position'=>20),
	'o.datec'=>array('label'=>$langs->trans("DateCreation"), 'checked'=>1, 'position'=>20),
	'o.tms'=>array('label'=>$langs->trans("DateLastModification"), 'checked'=>1, 'position'=>20),
);


/*
 * Actions
 */


if (GETPOST('cancel', 'alpha')) {
	$action = 'list'; $massaction = '';
}

if (!GETPOST('confirmmassaction', 'alpha') && $massaction != 'predelete') {
	$massaction = '';
}

// Selection of new fields
include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';


// Delete recu en masse
if ($massaction == 'predelete' && $usercandelete) {
	$error = 0;
	$nbchanged = 0;
	$ttr = 0;
	$ids = '';
	$db->begin();

	$nbchanged = count($toselect);
	foreach ($toselect as $recuid) {
		$ids = ($ids != '') ? $ids.','.$recuid : $recuid;
	}

	if($ids != '') {
		$result = $db->query("DELETE FROM ".MAIN_DB_PREFIX.$type->table_element." WHERE rowid IN (".$ids.") ");
	    if (!$result) setEventMessages($db->lasterror(), null, 'errors');
	    else{
			$result = $db->query("UPDATE ".MAIN_DB_PREFIX."projet_task_extrafields SET digikanban_colomn = '' WHERE digikanban_colomn IN (".$ids.") ");
	    }
	}

	if (!$error) {
		setEventMessages($langs->trans("RecordsDeleted", $nbchanged), null, 'mesgs');
		$db->commit();
	} else {
		$db->rollback();
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit();
}


// Purge search criteria
if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) { // All tests are required to be compatible with all browsers
	$search_rowid = "";
	$search_label = "";
	$search_author = "";
	$search_datec_start = "";
	$search_datec_startday = "";
	$search_datec_startmonth = "";
	$search_datec_startyear = "";
	$search_datec_end = "";
	$search_datec_endday = "";
	$search_datec_endmonth = "";
	$search_datec_endyear = "";
	$search_tms_start = "";
	$search_tms_end = "";
	$toselect = '';
}
if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')
	|| GETPOST('button_search_x', 'alpha') || GETPOST('button_search.x', 'alpha') || GETPOST('button_search', 'alpha')) {
	$massaction = ''; // Protection to avoid mass action if we force a new search during a mass action confirmation
}

// Mass actions
$objectclass = 'digikanban_columns';
$objectlabel = 'digikanban_columns';
$permissiontoread = $user->rights->digikanban->lire;
$permissiontodelete = $usercandelete;
$uploaddir = $conf->digikanban->dir_output;
include DOL_DOCUMENT_ROOT.'/core/actions_massactions.inc.php';


$param = '';
if (!empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) {
	$param .= '&contextpage='.urlencode($contextpage);
}
if ($limit > 0 && $limit != $conf->liste_limit) {
	$param .= '&limit='.urlencode($limit);
}
if ($optioncss != '') {
	$param .= '&optioncss='.urlencode($optioncss);
}
$param .= $search_label ? '&search_label='.urlencode($search_label) : '';
$param .= $search_author ? '&search_author='.urlencode($search_author) : '';
$param .= $search_datec_startday ? '&search_datec_startday='.urlencode($search_datec_startday) : '';
$param .= $search_datec_startyear ? '&search_datec_startyear='.urlencode($search_datec_startyear) : '';
$param .= $search_datec_startmonth ? '&search_datec_startmonth='.urlencode($search_datec_startmonth) : '';
$param .= $search_datec_endday ? '&search_datec_endday='.urlencode($search_datec_endday) : '';
$param .= $search_datec_endyear ? '&search_datec_endyear='.urlencode($search_datec_endyear) : '';
$param .= $search_datec_endmonth ? '&search_datec_endmonth='.urlencode($search_datec_endmonth) : '';
$param .= $search_tms_startday ? '&search_tms_startday='.urlencode($search_tms_startday) : '';
$param .= $search_tms_startyear ? '&search_tms_startyear='.urlencode($search_tms_startyear) : '';
$param .= $search_tms_startmonth ? '&search_tms_startmonth='.urlencode($search_tms_startmonth) : '';
$param .= $search_tms_endday ? '&search_tms_endday='.urlencode($search_tms_endday) : '';
$param .= $search_tms_endyear ? '&search_tms_endyear='.urlencode($search_tms_endyear) : '';
$param .= $search_tms_endmonth ? '&search_tms_endmonth='.urlencode($search_tms_endmonth) : '';


/*
 * View
 */


$title = $langs->trans('list_columns');

$arrayofjs = array('digikanban/js/script.js');
llxHeader('', $title, $help_url = '', $target = '', $disablejs = 0, $disablehead = 0, $arrayofjs);

$linkback ="";
digikanbanPrepareAdminHead('columns', $linkback, 'title_setup');


$sql = "SELECT";
$sql .= " o.rowid, o.label, o.fk_user_author, o.datec, o.tms";
$sql .= ' ,u.login as login, u.lastname as lastname, u.firstname as firstname, CONCAT(u.firstname, " ", u.lastname) as full_name, u.email as user_email, u.statut as user_status, u.entity as user_entity, u.photo as photo, u.office_phone as office_phone, u.office_fax as office_fax, u.user_mobile as user_mobile, u.job as job, u.gender as gender';
$sql .= " FROM ".MAIN_DB_PREFIX."digikanban_columns as o";
$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'user as u ON o.fk_user_author = u.rowid';
$sql .= " WHERE o.entity IN (0,".(int) $conf->entity.")";

if ($search_author != '' && $search_author > 0)   $sql .= natural_search("o.fk_user_author", $search_author, 1);
if ($search_datec_start)   $sql .= " AND CAST(o.datec as date) >= '".$db->idate($search_datec_start)."'";
if ($search_datec_end)    $sql .= " AND CAST(o.datec as date) <= '".$db->idate($search_datec_end)."'";
if ($search_tms_start) 	$sql .= " AND CAST(o.tms as date) >= '".$db->idate($search_tms_start)."'";
if ($search_tms_end) 	$sql .= " AND CAST(o.tms as date) <= '".$db->idate($search_tms_end)."'";



// Rowid
if (!empty($search_rowid)) {
	$sql .= natural_search("o.rowid", $search_rowid);
}
// Label
if (!empty($search_label)) {
	$sql .= natural_search("o.label", $search_label);
}
$sql .= $db->order($sortfield, $sortorder);

// Count total nb of records
$nbtotalofrecords = '';
if (empty($conf->global->MAIN_DISABLE_FULL_SCANLIST) || 1>0) {
	$result = $db->query($sql);
	$nbtotalofrecords = $db->num_rows($result);
	if (($page * $limit) > $nbtotalofrecords) {	// if total resultset is smaller then paging size (filtering), goto and load page 0
		$page = 0;
		$offset = 0;
	}
}

$sql .= $db->plimit($limit + 1, $offset);

//print $sql;
$resql = $db->query($sql);

if ($resql) {
	$num = $db->num_rows($resql);

	$arrayofselected = is_array($toselect) ? $toselect : array();

	// List of mass actions available
	$arrayofmassactions = array();

	if (!empty($usercandelete) || $user->admin) {
		$arrayofmassactions['predelete'] = img_picto('', 'delete', 'class="pictofixedwidth"').$langs->trans("Delete");
	}
	
	$massactionbutton = $form->selectMassAction('', $arrayofmassactions);

	// Lines of title fields
	print '<form id="searchFormList" action="'.$_SERVER["PHP_SELF"].'" method="POST">'."\n";
	if ($optioncss != '') {
		print '<input type="hidden" name="optioncss" value="'.$optioncss.'">';
	}
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
	print '<input type="hidden" name="action" value="'.($action == 'edit' ? 'update' : 'list').'">';
	print '<input type="hidden" name="contextpage" value="'.$contextpage.'">';
	print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
	print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
	
	$newcardbutton = dolGetButtonTitle($langs->trans('Add'), '', 'fa fa-plus-circle', './card.php?action=create', '', $usercancreate);
	print_barre_liste($title, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, $object->picto, 0, $newcardbutton, '', $limit, 0, 0, 1);

	$varpage = empty($contextpage) ? $_SERVER["PHP_SELF"] : $contextpage;
	$selectedfields = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage); // This also change content of $arrayfields
	$selectedfields .= (count($arrayofmassactions) ? $form->showCheckAddButtons('checkforselect', 1) : '');

	print '<div class="div-table-responsive">';
	print '<table class="tagtable liste">'."\n";

	$totalarray['nbfield']=0;
	// Filters
	print '<tr class="liste_titre_filter">';

	    if (!empty($arrayfields['o.rowid']['checked'])) {
			print '<td width="200px" class="width100 liste_titre">';
				// print '<input class="flat maxwidth50" type="text" name="search_rowid" value="'.dol_escape_htmltag($search_rowid).'">';
			print '</td>';
	        $totalarray['nbfield']++;
		}

	    if (!empty($arrayfields['o.label']['checked'])) {
			print '<td class="liste_titre">';
				print '<input class="flat maxwidth100" type="text" name="search_label" value="'.dol_escape_htmltag($search_label).'">';
			print '</td>';
	        $totalarray['nbfield']++;
		}

	    // Creer par
	    if (!empty($arrayfields['o.fk_user_author']['checked'])) {
	        print '<td class="liste_titre left">';
	       		print $form->select_dolusers($search_author, "search_author", 1, "", '', '', '', 0, 0, 0, '', 0, '', 'maxwidth150');
	        print '</td>';
	        $totalarray['nbfield']++;
	    }
	    
		// Date de creation
		if (!empty($arrayfields['o.datec']['checked'])) {
			print '<td class="liste_titre center">';
				print '<div class="nowrap">';
					print $form->selectDate($search_datec_start, 'search_datec_start', 0, 0, 1, "search_form", 1, 0, 0, '', '', '', '', 1, '', $langs->trans('From'));
				print '</div>';
				
				print '<div class="nowrap">';
					print $form->selectDate($search_datec_end, 'srch_datec_end', 0, 0, 1, "search_form", 1, 0, 0, '', '', '', '', 1, '', $langs->trans('to'));
				print '</div>';
			print '</td>';
	        $totalarray['nbfield']++;
		}

	    // Date de dernière modification
	    if (!empty($arrayfields['o.tms']['checked'])) {
	        print '<td class="liste_titre center">';
		        print '<div class="nowrap">';
		            print $form->selectDate($search_tms_start, 'srch_tms_start', 0, 0, 1, "search_form", 1, 0, 0, '', '', '', '', 1, '', $langs->trans('From'));
		        print '</div>';
		        
		        print '<div class="nowrap">';
		            print $form->selectDate($search_tms_end, 'srch_tms_end', 0, 0, 1, "search_form", 1, 0, 0, '', '', '', '', 1, '', $langs->trans('to'));
		        print '</div>';
	        print '</td>';
	        $totalarray['nbfield']++;
	    }

		// Action column
		print '<td class="liste_titre maxwidthsearch">';
	        $totalarray['nbfield']++;
			$searchpicto = $form->showFilterButtons();
			print $searchpicto;
		print '</td>';

	print "</tr>\n";

	print '<tr class="liste_titre">';
	    if (!empty($arrayfields['o.rowid']['checked']))
			print_liste_field_titre($langs->trans('Ref'), $_SERVER["PHP_SELF"], "o.rowid", "", $param, '', $sortfield, $sortorder);
	    if (!empty($arrayfields['o.label']['checked']))
			print_liste_field_titre($langs->trans('Label'), $_SERVER["PHP_SELF"], "o.label", "", $param, '', $sortfield, $sortorder);
	    if (!empty($arrayfields['o.fk_user_author']['checked']))
			print_liste_field_titre($langs->trans('Author'), $_SERVER["PHP_SELF"], "o.fk_user_author", "", $param, '', $sortfield, $sortorder);
	    if (!empty($arrayfields['o.datec']['checked']))
			print_liste_field_titre($langs->trans('DateCreation'), $_SERVER["PHP_SELF"], "o.datec", "", $param, '', $sortfield, $sortorder, 'center ');
	    if (!empty($arrayfields['o.tms']['checked']))
			print_liste_field_titre($langs->trans('DateLastModification'), $_SERVER["PHP_SELF"], "o.tms", "", $param, '', $sortfield, $sortorder, 'center ');
		print_liste_field_titre($selectedfields, $_SERVER["PHP_SELF"], "", '', '', 'align="center"', $sortfield, $sortorder, 'maxwidthsearch ');
	print "</tr>\n";

	$listhalfday = array('morning'=>$langs->trans("Morning"), "afternoon"=>$langs->trans("Afternoon"));

	// If we ask a dedicated card and not allow to see it, we force on user.
	if ($num > 0) {
		// Lines
		$i = 0;
		$totalarray = array();
		$totalarray['nbfield'] = 0;
		$totalduration = 0;
		while ($i < min($num, $limit)) {
			$obj = $db->fetch_object($resql);

			$type->id 	= $obj->rowid;
			$type->rowid 	= $obj->rowid;
			$type->label 	= $obj->label;

			print '<tr class="oddeven">';

	    		if (!empty($arrayfields['o.rowid']['checked'])) {
					print '<td class="nowrap">';
						print '<a href="card.php?id='.$obj->rowid.'">';
							print img_picto('', $object->picto, 'class="pictofixedwidth"').' '.$obj->rowid;
						print '</a>';
					print '</td>';
				}
	    		if (!empty($arrayfields['o.label']['checked'])) {
					print '<td class="">'.$obj->label.'</td>';
				}
	    		if (!empty($arrayfields['o.fk_user_author']['checked'])) {
					$userauthor->id             = isset($obj->fk_user_author) ? $obj->fk_user_author : '';
		            $userauthor->login          = $obj->login;
		            $userauthor->lastname       = $obj->lastname;
		            $userauthor->firstname      = $obj->firstname;
		            $userauthor->email          = $obj->user_email;
		            $userauthor->status         = $obj->user_status;
		            $userauthor->entity         = $obj->user_entity;
		            $userauthor->photo          = $obj->photo;
		            $userauthor->office_phone   = $obj->office_phone;
		            $userauthor->office_fax     = $obj->office_fax;
		            $userauthor->user_mobile    = $obj->user_mobile;
		            $userauthor->job            = $obj->job;
		            $userauthor->gender         = $obj->gender;
					print '<td class="">'.$userauthor->getNomUrl(1).'</td>';
				}
	    		if (!empty($arrayfields['o.datec']['checked'])) {
					print '<td class="center">'.dol_print_date($obj->datec, 'day').'</td>';
	    		}
	    		if (!empty($arrayfields['o.tms']['checked'])) {
					print '<td class="center">'.dol_print_date($obj->tms, 'day').'</td>';
	    		}
	            
				// Action column
				print '<td class="nowrap center">';
					if ($massactionbutton || $massaction) {   // If we are in select mode (massactionbutton defined) or if we have already selected and sent an action ($massaction) defined
						$selected = 0;
						if (in_array($obj->rowid, $arrayofselected)) {
							$selected = 1;
						}
						print '<input id="cb'.$obj->rowid.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$obj->rowid.'"'.($selected ? ' checked="checked"' : '').'>';
					}
				print '</td>';
			print '</tr>'."\n";

			$i++;
		}

		// // Add a line for total if there is a total to show
		// if (!empty($arrayfields['duration']['checked'])) {
		// 	print '<tr class="total">';
		// 	foreach ($arrayfields as $key => $val) {
		// 		if (!empty($val['checked'])) {
		// 			if ($key == 'duration') {
		// 				print '<td class="right">'.$totalduration.' '.$langs->trans('DurationDays').'</td>';
		// 			} else {
		// 				print '<td></td>';
		// 			}
		// 		}
		// 	}
		// 	print '</tr>';
		// }
	}

	// Si il n'y a pas d'enregistrement suite à une recherche
	if ($num == 0) {
		$colspan = $totalarray['nbfield'];
		print '<tr><td colspan="'.$colspan.'" class="opacitymedium">'.$langs->trans("NoRecordFound").'</td></tr>';
	}

	print '</table>';
	print '</div>';

	print '</form>';
} else {
	dol_print_error($db);
}

// End of page
llxFooter();
$db->close();