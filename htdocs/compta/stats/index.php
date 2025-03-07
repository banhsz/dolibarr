<?php
/* Copyright (C) 2001-2004  Rodolphe Quiedeville    <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2012  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2009  Regis Houssin           <regis.houssin@inodbox.com>
 * Copyright (C) 2017       Olivier Geffroy         <jeff@jeffinfo.com>
 * Copyright (C) 2018-2020  Frédéric France         <frederic.france@netlogic.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *	\file        htdocs/compta/stats/index.php
 *	\brief       Page reporting CA
 */

// Load Dolibarr environment
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/report.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

// Load translation files required by the page
$langs->loadLangs(array('compta', 'bills', 'donation', 'salaries'));

$date_startday = GETPOST('date_startday', 'int');
$date_startmonth = GETPOST('date_startmonth', 'int');
$date_startyear = GETPOST('date_startyear', 'int');
$date_endday = GETPOST('date_endday', 'int');
$date_endmonth = GETPOST('date_endmonth', 'int');
$date_endyear = GETPOST('date_endyear', 'int');

$nbofyear = 4;

// Date range
$year = GETPOST('year', 'int');
if (empty($year)) {
	$year_current = dol_print_date(dol_now(), "%Y");
	$month_current = dol_print_date(dol_now(), "%m");
	$year_start = $year_current - ($nbofyear - 1);
} else {
	$year_current = $year;
	$month_current = dol_print_date(dol_now(), "%m");
	$year_start = $year - ($nbofyear - 1);
}
$date_start = dol_mktime(0, 0, 0, $date_startmonth, $date_startday, $date_startyear, 'tzserver');
$date_end = dol_mktime(23, 59, 59, $date_endmonth, $date_endday, $date_endyear, 'tzserver');

// We define date_start and date_end
if (empty($date_start) || empty($date_end)) { // We define date_start and date_end
	$q = GETPOST("q") ? GETPOST("q") : 0;
	if ($q == 0) {
		// We define date_start and date_end
		$year_end = $year_start + ($nbofyear - 1);
		$month_start = GETPOSTISSET("month") ? GETPOST("month", 'int') : ($conf->global->SOCIETE_FISCAL_MONTH_START ? $conf->global->SOCIETE_FISCAL_MONTH_START : 1);
		if (!GETPOST('month')) {
			if (!GETPOST("year") && $month_start > $month_current) {
				$year_start--;
				$year_end--;
			}
			$month_end = $month_start - 1;
			if ($month_end < 1) {
				$month_end = 12;
			} else {
				$year_end++;
			}
		} else {
			$month_end = $month_start;
		}
		$date_start = dol_get_first_day($year_start, $month_start, false);
		$date_end = dol_get_last_day($year_end, $month_end, false);
	}
	if ($q == 1) {
		$date_start = dol_get_first_day($year_start, 1, false);
		$date_end = dol_get_last_day($year_start, 3, false);
	}
	if ($q == 2) {
		$date_start = dol_get_first_day($year_start, 4, false);
		$date_end = dol_get_last_day($year_start, 6, false);
	}
	if ($q == 3) {
		$date_start = dol_get_first_day($year_start, 7, false);
		$date_end = dol_get_last_day($year_start, 9, false);
	}
	if ($q == 4) {
		$date_start = dol_get_first_day($year_start, 10, false);
		$date_end = dol_get_last_day($year_start, 12, false);
	}
}

$userid = GETPOST('userid', 'int');
$socid = GETPOST('socid', 'int');

$tmps = dol_getdate($date_start);
$mothn_start = $tmps['mon'];
$year_start = $tmps['year'];
$tmpe = dol_getdate($date_end);
$month_end = $tmpe['mon'];
$year_end = $tmpe['year'];
$nbofyear = ($year_end - $year_start) + 1;

// Define modecompta ('CREANCES-DETTES' or 'RECETTES-DEPENSES' or 'BOOKKEEPING')
$modecompta = $conf->global->ACCOUNTING_MODE;
if (isModEnabled('accounting')) {
	$modecompta = 'BOOKKEEPING';
}
if (GETPOST("modecompta")) {
	$modecompta = GETPOST("modecompta", 'alpha');
}

// Security check
if ($user->socid > 0) {
	$socid = $user->socid;
}
if (isModEnabled('comptabilite')) {
	$result = restrictedArea($user, 'compta', '', '', 'resultat');
}
if (isModEnabled('accounting')) {
	$result = restrictedArea($user, 'accounting', '', '', 'comptarapport');
}




/*
 * View
 */

$param = '';
if ($date_startday && $date_startmonth && $date_startyear) {
	$param .= '&date_startday='.$date_startday.'&date_startmonth='.$date_startmonth.'&date_startyear='.$date_startyear;
}
if ($date_endday && $date_endmonth && $date_endyear) {
	$param .= '&date_endday='.$date_endday.'&date_endmonth='.$date_endmonth.'&date_endyear='.$date_endyear;
}

llxHeader();

$form = new Form($db);

$exportlink="";
$namelink="";
// Affiche en-tete du rapport
if ($modecompta == "CREANCES-DETTES") {
	$name = $langs->trans("Turnover");
	$calcmode = $langs->trans("CalcModeDebt");
	//$calcmode.='<br>('.$langs->trans("SeeReportInInputOutputMode",'<a href="'.$_SERVER["PHP_SELF"].'?year_start='.$year_start.'&modecompta=RECETTES-DEPENSES">','</a>').')';
	if (isModEnabled('accounting')) {
		$calcmode .= '<br>('.$langs->trans("SeeReportInBookkeepingMode", '{link1}', '{link2}').')';
		$calcmode = str_replace('{link1}', '<a class="bold" href="'.$_SERVER["PHP_SELF"].'?'.($param ? $param : 'year_start='.$year_start).'&modecompta=BOOKKEEPING">', $calcmode);
		$calcmode = str_replace('{link2}', '</a>', $calcmode);
	}
	$periodlink = ($year_start ? "<a href='".$_SERVER["PHP_SELF"]."?year=".($year_start + $nbofyear - 2)."&modecompta=".$modecompta."'>".img_previous()."</a> <a href='".$_SERVER["PHP_SELF"]."?year=".($year_start + $nbofyear)."&modecompta=".$modecompta."'>".img_next()."</a>" : "");
	$description = $langs->trans("RulesCADue");
	if (!empty($conf->global->FACTURE_DEPOSITS_ARE_JUST_PAYMENTS)) {
		$description .= $langs->trans("DepositsAreNotIncluded");
	} else {
		$description .= $langs->trans("DepositsAreIncluded");
	}
	$builddate = dol_now();
	//$exportlink=$langs->trans("NotYetAvailable");
} elseif ($modecompta == "RECETTES-DEPENSES") {
	$name = $langs->trans("TurnoverCollected");
	$calcmode = $langs->trans("CalcModeEngagement");
	//$calcmode .= '<br>('.$langs->trans("SeeReportInDueDebtMode",'<a href="'.$_SERVER["PHP_SELF"].'?year_start='.$year_start.'&modecompta=CREANCES-DETTES">','</a>').')';
	//if (isModEnabled('accounting')) {
	//$calcmode.='<br>('.$langs->trans("SeeReportInBookkeepingMode",'<a href="'.$_SERVER["PHP_SELF"].'?year_start='.$year_start.'&modecompta=BOOKKEEPINGCOLLECTED">','</a>').')';
	//}
	$periodlink = ($year_start ? "<a href='".$_SERVER["PHP_SELF"]."?year=".($year_start + $nbofyear - 2)."&modecompta=".$modecompta."'>".img_previous()."</a> <a href='".$_SERVER["PHP_SELF"]."?year=".($year_start + $nbofyear)."&modecompta=".$modecompta."'>".img_next()."</a>" : "");
	$description = $langs->trans("RulesCAIn");
	$description .= $langs->trans("DepositsAreIncluded");
	$builddate = dol_now();
	//$exportlink=$langs->trans("NotYetAvailable");
} elseif ($modecompta == "BOOKKEEPING") {
	$name = $langs->trans("Turnover");
	$calcmode = $langs->trans("CalcModeBookkeeping");
	$calcmode .= '<br>('.$langs->trans("SeeReportInDueDebtMode", '{link1}', '{link2}').')';
	$calcmode = str_replace('{link1}', '<a class="bold" href="'.$_SERVER["PHP_SELF"].'?'.($param ? $param : 'year_start='.$year_start).'&modecompta=CREANCES-DETTES">', $calcmode);
	$calcmode = str_replace('{link2}', '</a>', $calcmode);
	//$calcmode.='<br>('.$langs->trans("SeeReportInInputOutputMode",'<a href="'.$_SERVER["PHP_SELF"].'?year_start='.$year_start.'&modecompta=RECETTES-DEPENSES">','</a>').')';
	$periodlink = ($year_start ? "<a href='".$_SERVER["PHP_SELF"]."?year=".($year_start + $nbofyear - 2)."&modecompta=".$modecompta."'>".img_previous()."</a> <a href='".$_SERVER["PHP_SELF"]."?year=".($year_start + $nbofyear)."&modecompta=".$modecompta."'>".img_next()."</a>" : "");
	$description = $langs->trans("RulesSalesTurnoverOfIncomeAccounts");
	$builddate = dol_now();
	//$exportlink=$langs->trans("NotYetAvailable");
}
$period = $form->selectDate($date_start, 'date_start', 0, 0, 0, '', 1, 0, 0, '', '', '', '', 1, '', '', 'tzserver');
$period .= ' - ';
$period .= $form->selectDate($date_end, 'date_end', 0, 0, 0, '', 1, 0, 0, '', '', '', '', 1, '', '', 'tzserver');

$moreparam = array();
if (!empty($modecompta)) {
	$moreparam['modecompta'] = $modecompta;
}
report_header($name, $namelink, $period, $periodlink, $description, $builddate, $exportlink, $moreparam, $calcmode);

if (isModEnabled('accounting') && $modecompta != 'BOOKKEEPING') {
	print info_admin($langs->trans("WarningReportNotReliable"), 0, 0, 1);
}


if ($modecompta == 'CREANCES-DETTES') {
	$sql = "SELECT date_format(f.datef,'%Y-%m') as dm, sum(f.total_ht) as amount, sum(f.total_ttc) as amount_ttc";
	$sql .= " FROM ".MAIN_DB_PREFIX."facture as f";
	$sql .= " WHERE f.fk_statut in (1,2)";
	if (!empty($conf->global->FACTURE_DEPOSITS_ARE_JUST_PAYMENTS)) {
		$sql .= " AND f.type IN (0,1,2,5)";
	} else {
		$sql .= " AND f.type IN (0,1,2,3,5)";
	}
	$sql .= " AND f.entity IN (".getEntity('invoice').")";
	if ($socid) {
		$sql .= " AND f.fk_soc = ".((int) $socid);
	}
} elseif ($modecompta == "RECETTES-DEPENSES") {
	/*
	 * Liste des paiements (les anciens paiements ne sont pas vus par cette requete car, sur les
	 * vieilles versions, ils n'etaient pas lies via paiement_facture. On les ajoute plus loin)
	 */
	$sql = "SELECT date_format(p.datep, '%Y-%m') as dm, sum(pf.amount) as amount_ttc";
	$sql .= " FROM ".MAIN_DB_PREFIX."facture as f";
	$sql .= ", ".MAIN_DB_PREFIX."paiement_facture as pf";
	$sql .= ", ".MAIN_DB_PREFIX."paiement as p";
	$sql .= " WHERE p.rowid = pf.fk_paiement";
	$sql .= " AND pf.fk_facture = f.rowid";
	$sql .= " AND f.entity IN (".getEntity('invoice').")";
	if ($socid) {
		$sql .= " AND f.fk_soc = ".((int) $socid);
	}
} elseif ($modecompta == "BOOKKEEPING") {
	$pcgverid = $conf->global->CHARTOFACCOUNTS;
	$pcgvercode = dol_getIdFromCode($db, $pcgverid, 'accounting_system', 'rowid', 'pcg_version');
	if (empty($pcgvercode)) {
		$pcgvercode = $pcgverid;
	}

	$sql = "SELECT date_format(b.doc_date, '%Y-%m') as dm, sum(b.credit - b.debit) as amount_ttc";
	$sql .= " FROM ".MAIN_DB_PREFIX."accounting_bookkeeping as b,";
	$sql .= " ".MAIN_DB_PREFIX."accounting_account as aa";
	$sql .= " WHERE b.entity = ".$conf->entity; // In module double party accounting, we never share entities
	$sql .= " AND b.numero_compte = aa.account_number";
	$sql .= " AND b.doc_type = 'customer_invoice'";
	$sql .= " AND aa.entity = ".$conf->entity;
	$sql .= " AND aa.fk_pcg_version = '".$db->escape($pcgvercode)."'";
	$sql .= " AND aa.pcg_type = 'INCOME'";		// TODO Be able to use a custom group
}
$sql .= " GROUP BY dm";
$sql .= " ORDER BY dm";
// TODO Add a filter on $date_start and $date_end to reduce quantity on data
//print $sql;

$minyearmonth = $maxyearmonth = 0;

$cum = array();
$cum_ht = array();
$total_ht = array();
$total = array();

$result = $db->query($sql);
if ($result) {
	$num = $db->num_rows($result);
	$i = 0;
	while ($i < $num) {
		$obj = $db->fetch_object($result);
		$cum_ht[$obj->dm] = empty($obj->amount) ? 0 : $obj->amount;
		$cum[$obj->dm] = empty($obj->amount_ttc) ? 0 : $obj->amount_ttc;
		if ($obj->amount_ttc) {
			$minyearmonth = ($minyearmonth ? min($minyearmonth, $obj->dm) : $obj->dm);
			$maxyearmonth = max($maxyearmonth, $obj->dm);
		}
		$i++;
	}
	$db->free($result);
} else {
	dol_print_error($db);
}

// On ajoute les paiements anciennes version, non lies par paiement_facture (very old versions)
if ($modecompta == 'RECETTES-DEPENSES') {
	$sql = "SELECT date_format(p.datep,'%Y-%m') as dm, sum(p.amount) as amount_ttc";
	$sql .= " FROM ".MAIN_DB_PREFIX."bank as b";
	$sql .= ", ".MAIN_DB_PREFIX."bank_account as ba";
	$sql .= ", ".MAIN_DB_PREFIX."paiement as p";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."paiement_facture as pf ON p.rowid = pf.fk_paiement";
	$sql .= " WHERE pf.rowid IS NULL";
	$sql .= " AND p.fk_bank = b.rowid";
	$sql .= " AND b.fk_account = ba.rowid";
	$sql .= " AND ba.entity IN (".getEntity('bank_account').")";
	$sql .= " GROUP BY dm";
	$sql .= " ORDER BY dm";

	$result = $db->query($sql);
	if ($result) {
		$num = $db->num_rows($result);
		$i = 0;
		while ($i < $num) {
			$obj = $db->fetch_object($result);
			if (empty($cum[$obj->dm])) {
				$cum[$obj->dm] = $obj->amount_ttc;
			} else {
				$cum[$obj->dm] += $obj->amount_ttc;
			}
			if ($obj->amount_ttc) {
				$minyearmonth = ($minyearmonth ?min($minyearmonth, $obj->dm) : $obj->dm);
				$maxyearmonth = max($maxyearmonth, $obj->dm);
			}
			$i++;
		}
	} else {
		dol_print_error($db);
	}
}

$moreforfilter = '';

print '<div class="div-table-responsive">';
print '<table class="tagtable liste'.($moreforfilter ? " listwithfilterbefore" : "").'">'."\n";

print '<tr class="liste_titre"><td>&nbsp;</td>';

for ($annee = $year_start; $annee <= $year_end; $annee++) {
	if ($modecompta == 'CREANCES-DETTES') {
		print '<td align="center" width="10%" colspan="3">';
	} else {
		print '<td align="center" width="10%" colspan="2" class="borderrightlight">';
	}
	if ($modecompta != 'BOOKKEEPING') {
		print '<a href="casoc.php?year='.$annee.'">';
	}
	print $annee;
	if ($conf->global->SOCIETE_FISCAL_MONTH_START > 1) {
		print '-'.($annee + 1);
	}
	if ($modecompta != 'BOOKKEEPING') {
		print '</a>';
	}
	print '</td>';
	if ($annee != $year_end) {
		print '<td width="15">&nbsp;</td>';
	}
}
print '</tr>';

print '<tr class="liste_titre"><td class="liste_titre">'.$langs->trans("Month").'</td>';
for ($annee = $year_start; $annee <= $year_end; $annee++) {
	if ($modecompta == 'CREANCES-DETTES') {
		print '<td class="liste_titre right">'.$langs->trans("AmountHT").'</td>';
	}
	print '<td class="liste_titre right">';
	if ($modecompta == "BOOKKEEPING") {
		print $langs->trans("Amount");
	} else {
		print $langs->trans("AmountTTC");
	}
	print '</td>';
	print '<td class="liste_titre right borderrightlight">'.$langs->trans("Delta").'</td>';
	if ($annee != $year_end) {
		print '<td class="liste_titre" width="15">&nbsp;</td>';
	}
}
print '</tr>';

$now_show_delta = 0;
$minyear = substr($minyearmonth, 0, 4);
$maxyear = substr($maxyearmonth, 0, 4);
$nowyear = strftime("%Y", dol_now());
$nowyearmonth = strftime("%Y-%m", dol_now());
$maxyearmonth = max($maxyearmonth, $nowyearmonth);
$now = dol_now();
$casenow = dol_print_date($now, "%Y-%m");

// Loop on each month
$nb_mois_decalage = GETPOSTISSET('date_startmonth') ? (GETPOST('date_startmonth', 'int') - 1) : (empty($conf->global->SOCIETE_FISCAL_MONTH_START) ? 0 : ($conf->global->SOCIETE_FISCAL_MONTH_START - 1));
for ($mois = 1 + $nb_mois_decalage; $mois <= 12 + $nb_mois_decalage; $mois++) {
	$mois_modulo = $mois; // ajout
	if ($mois > 12) {
		$mois_modulo = $mois - 12;
	} // ajout

	if ($year_start == $year_end) {
		// If we show only one year or one month, we do not show month before the selected month
		if ($mois < $date_startmonth && $year_start <= $date_startyear) {
			continue;
		}
		// If we show only one year or one month, we do not show month after the selected month
		if ($mois > $date_endmonth && $year_end >= $date_endyear) {
			break;
		}
	}

	print '<tr class="oddeven">';

	// Month
	print "<td>".dol_print_date(dol_mktime(12, 0, 0, $mois_modulo, 1, 2000), "%B")."</td>";

	for ($annee = $year_start - 1; $annee <= $year_end; $annee++) {	// We start one year before to have data to be able to make delta
		$annee_decalage = $annee;
		if ($mois > 12) {
			$annee_decalage = $annee + 1;
		}
		$case = dol_print_date(dol_mktime(1, 1, 1, $mois_modulo, 1, $annee_decalage), "%Y-%m");
		$caseprev = dol_print_date(dol_mktime(1, 1, 1, $mois_modulo, 1, $annee_decalage - 1), "%Y-%m");

		if ($annee >= $year_start) {	// We ignore $annee < $year_start, we loop on it to be able to make delta, nothing is output.
			if ($modecompta == 'CREANCES-DETTES') {
				// Value turnover of month w/o VAT
				print '<td class="right">';
				if ($annee < $year_end || ($annee == $year_end && $mois <= $month_end)) {
					if ($cum_ht[$case]) {
						$now_show_delta = 1; // On a trouve le premier mois de la premiere annee generant du chiffre.
						print '<a href="casoc.php?year='.$annee_decalage.'&month='.$mois_modulo.($modecompta ? '&modecompta='.$modecompta : '').'">'.price($cum_ht[$case], 1).'</a>';
					} else {
						if ($minyearmonth < $case && $case <= max($maxyearmonth, $nowyearmonth)) {
							print '0';
						} else {
							print '&nbsp;';
						}
					}
				}
				print "</td>";
			}

			// Value turnover of month
			print '<td class="right">';
			if ($annee < $year_end || ($annee == $year_end && $mois <= $month_end)) {
				if ($cum[$case]) {
					$now_show_delta = 1; // On a trouve le premier mois de la premiere annee generant du chiffre.
					if ($modecompta != 'BOOKKEEPING') {
						print '<a href="casoc.php?year='.$annee_decalage.'&month='.$mois_modulo.($modecompta ? '&modecompta='.$modecompta : '').'">';
					}
					print price($cum[$case], 1);
					if ($modecompta != 'BOOKKEEPING') {
						print '</a>';
					}
				} else {
					if ($minyearmonth < $case && $case <= max($maxyearmonth, $nowyearmonth)) {
						print '0';
					} else {
						print '&nbsp;';
					}
				}
			}
			print "</td>";

			// Percentage of month
			print '<td class="borderrightlight right"><span class="opacitymedium">';
			//var_dump($annee.' '.$year_end.' '.$mois.' '.$month_end);
			if ($annee < $year_end || ($annee == $year_end && $mois <= $month_end)) {
				if ($annee_decalage > $minyear && $case <= $casenow) {
					if ($cum[$caseprev] && $cum[$case]) {
						$percent = (round(($cum[$case] - $cum[$caseprev]) / $cum[$caseprev], 4) * 100);
						//print "X $cum[$case] - $cum[$caseprev] - $cum[$caseprev] - $percent X";
						print ($percent >= 0 ? "+$percent" : "$percent").'%';
					}
					if ($cum[$caseprev] && !$cum[$case]) {
						print '-100%';
					}
					if (!$cum[$caseprev] && $cum[$case]) {
						//print '<td class="right">+Inf%</td>';
						print '-';
					}
					if (isset($cum[$caseprev]) && !$cum[$caseprev] && !$cum[$case]) {
						print '+0%';
					}
					if (!isset($cum[$caseprev]) && !$cum[$case]) {
						print '-';
					}
				} else {
					if ($minyearmonth <= $case && $case <= $maxyearmonth) {
						print '-';
					} else {
						print '&nbsp;';
					}
				}
			}
			print '</span></td>';

			if ($annee_decalage < $year_end || ($annee_decalage == $year_end && $mois > 12 && $annee < $year_end)) {
				print '<td width="15">&nbsp;</td>';
			}
		}

		if ($annee < $year_end || ($annee == $year_end && $mois <= $month_end)) {
			$total_ht[$annee] += ((!empty($cum_ht[$case])) ? $cum_ht[$case] : 0);
			$total[$annee] += $cum[$case];
		}
	}

	print '</tr>';
}

/*
 for ($mois = 1 ; $mois < 13 ; $mois++)
 {

 print '<tr class="oddeven">';

 print "<td>".dol_print_date(dol_mktime(12,0,0,$mois,1,2000),"%B")."</td>";
 for ($annee = $year_start ; $annee <= $year_end ; $annee++)
 {
 $casenow = dol_print_date(dol_now(),"%Y-%m");
 $case = dol_print_date(dol_mktime(1,1,1,$mois,1,$annee),"%Y-%m");
 $caseprev = dol_print_date(dol_mktime(1,1,1,$mois,1,$annee-1),"%Y-%m");

 // Valeur CA du mois
 print '<td class="right">';
 if ($cum[$case])
 {
 $now_show_delta=1;  // On a trouve le premier mois de la premiere annee generant du chiffre.
 print '<a href="casoc.php?year='.$annee.'&month='.$mois.'">'.price($cum[$case],1).'</a>';
 }
 else
 {
 if ($minyearmonth < $case && $case <= max($maxyearmonth,$nowyearmonth)) { print '0'; }
 else { print '&nbsp;'; }
 }
 print "</td>";

 // Pourcentage du mois
 if ($annee > $minyear && $case <= $casenow) {
 if ($cum[$caseprev] && $cum[$case])
 {
 $percent=(round(($cum[$case]-$cum[$caseprev])/$cum[$caseprev],4)*100);
 //print "X $cum[$case] - $cum[$caseprev] - $cum[$caseprev] - $percent X";
 print '<td class="right">'.($percent>=0?"+$percent":"$percent").'%</td>';

 }
 if ($cum[$caseprev] && ! $cum[$case])
 {
 print '<td class="right">-100%</td>';
 }
 if (! $cum[$caseprev] && $cum[$case])
 {
 print '<td class="right">+Inf%</td>';
 }
 if (! $cum[$caseprev] && ! $cum[$case])
 {
 print '<td class="right">+0%</td>';
 }
 }
 else
 {
 print '<td class="right">';
 if ($minyearmonth <= $case && $case <= $maxyearmonth) { print '-'; }
 else { print '&nbsp;'; }
 print '</td>';
 }

 $total[$annee]+=$cum[$case];
 if ($annee != $year_end) print '<td width="15">&nbsp;</td>';
 }

 print '</tr>';
 }
 */

// Show total
print '<tr class="liste_total"><td>'.$langs->trans("Total").'</td>';
for ($annee = $year_start; $annee <= $year_end; $annee++) {
	if ($modecompta == 'CREANCES-DETTES') {
		// Montant total HT
		if ($total_ht[$annee] || ($annee >= $minyear && $annee <= max($nowyear, $maxyear))) {
			print '<td class="nowrap right">';
			print ($total_ht[$annee] ?price($total_ht[$annee]) : "0");
			print "</td>";
		} else {
			print '<td>&nbsp;</td>';
		}
	}

	// Total amount
	if ($total[$annee] || ($annee >= $minyear && $annee <= max($nowyear, $maxyear))) {
		print '<td class="nowrap right">';
		print ($total[$annee] ?price($total[$annee]) : "0");
		print "</td>";
	} else {
		print '<td>&nbsp;</td>';
	}

	// Pourcentage total
	if ($annee > $minyear && $annee <= max($nowyear, $maxyear)) {
		if ($total[$annee - 1] && $total[$annee]) {
			$percent = (round(($total[$annee] - $total[$annee - 1]) / $total[$annee - 1], 4) * 100);
			print '<td class="nowrap borderrightlight right">';
			print ($percent >= 0 ? "+$percent" : "$percent").'%';
			print '</td>';
		}
		if ($total[$annee - 1] && !$total[$annee]) {
			print '<td class="borderrightlight right">-100%</td>';
		}
		if (!$total[$annee - 1] && $total[$annee]) {
			print '<td class="borderrightlight right">+'.$langs->trans('Inf').'%</td>';
		}
		if (!$total[$annee - 1] && !$total[$annee]) {
			print '<td class="borderrightlight right">+0%</td>';
		}
	} else {
		print '<td class="borderrightlight right">';
		if ($total[$annee] || ($minyear <= $annee && $annee <= max($nowyear, $maxyear))) {
			print '-';
		} else {
			print '&nbsp;';
		}
		print '</td>';
	}

	if ($annee != $year_end) {
		print '<td width="15">&nbsp;</td>';
	}
}
print "</tr>\n";
print "</table>";
print '</div>';


/*
 * En mode recettes/depenses, on complete avec les montants factures non regles
 * et les propales signees mais pas facturees. En effet, en recettes-depenses,
 * on comptabilise lorsque le montant est sur le compte donc il est interessant
 * d'avoir une vision de ce qui va arriver.
 */

/*
 Je commente toute cette partie car les chiffres affichees sont faux - Eldy.
 En attendant correction.

 if ($modecompta != 'CREANCES-DETTES')
 {

 print '<br><table width="100%" class="noborder">';

 // Factures non reglees
 // Y a bug ici. Il faut prendre le reste a payer et non le total des factures non reglees !

 $sql = "SELECT f.ref, f.rowid, s.nom, s.rowid as socid, f.total_ttc, sum(pf.amount) as am";
 $sql .= " FROM ".MAIN_DB_PREFIX."societe as s,".MAIN_DB_PREFIX."facture as f left join ".MAIN_DB_PREFIX."paiement_facture as pf on f.rowid=pf.fk_facture";
 $sql .= " WHERE s.rowid = f.fk_soc AND f.paye = 0 AND f.fk_statut = 1";
 if ($socid)
 {
 $sql .= " AND f.fk_soc = $socid";
 }
 $sql .= " GROUP BY f.ref,f.rowid,s.nom, s.rowid, f.total_ttc";

 $resql=$db->query($sql);
 if ($resql)
 {
 $num = $db->num_rows($resql);
 $i = 0;

 if ($num)
 {
 $total_ttc_Rac = $totalam_Rac = $total_Rac = 0;
 while ($i < $num)
 {
 $obj = $db->fetch_object($resql);
 $total_ttc_Rac +=  $obj->total_ttc;
 $totalam_Rac +=  $obj->am;
 $i++;
 }

 print "<tr class="oddeven"><td class=\"right\" colspan=\"5\"><i>Facture a encaisser : </i></td><td class=\"right\"><i>".price($total_ttc_Rac)."</i></td><td colspan=\"5\"><-- bug ici car n'exclut pas le deja r?gl? des factures partiellement r?gl?es</td></tr>";
 }
 $db->free($resql);
 }
 else
 {
 dol_print_error($db);
 }
 */

/*
 *
 * Propales signees, et non facturees
 *
 */

/*
 Je commente toute cette partie car les chiffres affichees sont faux - Eldy.
 En attendant correction.

 $sql = "SELECT sum(f.total_ht) as tot_fht,sum(f.total_ttc) as tot_fttc, p.rowid, p.ref, s.nom, s.rowid as socid, p.total_ht, p.total_ttc
 FROM ".MAIN_DB_PREFIX."commande AS p, ".MAIN_DB_PREFIX."societe AS s
 LEFT JOIN ".MAIN_DB_PREFIX."co_fa AS co_fa ON co_fa.fk_commande = p.rowid
 LEFT JOIN ".MAIN_DB_PREFIX."facture AS f ON co_fa.fk_facture = f.rowid
 WHERE p.fk_soc = s.rowid
 AND p.fk_statut >=1
 AND p.facture =0";
 if ($socid)
 {
 $sql .= " AND f.fk_soc = ".((int) $socid);
 }
 $sql .= " GROUP BY p.rowid";

 $resql=$db->query($sql);
 if ($resql)
 {
 $num = $db->num_rows($resql);
 $i = 0;

 if ($num)
 {
 $total_pr = 0;
 while ($i < $num)
 {
 $obj = $db->fetch_object($resql);
 $total_pr +=  $obj->total_ttc-$obj->tot_fttc;
 $i++;
 }

 print "<tr class="oddeven"><td class=\"right\" colspan=\"5\"><i>Signe et non facture:</i></td><td class=\"right\"><i>".price($total_pr)."</i></td><td colspan=\"5\"><-- bug ici, ca devrait exclure le deja facture</td></tr>";
 }
 $db->free($resql);
 }
 else
 {
 dol_print_error($db);
 }
 print "<tr class="oddeven"><td class=\"right\" colspan=\"5\"><i>Total CA previsionnel : </i></td><td class=\"right\"><i>".price($total_CA)."</i></td><td colspan=\"3\"><-- bug ici car bug sur les 2 precedents</td></tr>";
 }
 print "</table>";

 */

// End of page
llxFooter();
$db->close();
