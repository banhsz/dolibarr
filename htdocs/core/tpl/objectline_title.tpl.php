<?php
/* Copyright (C) 2010-2013	Regis Houssin		<regis.houssin@inodbox.com>
 * Copyright (C) 2010-2011	Laurent Destailleur	<eldy@users.sourceforge.net>
 * Copyright (C) 2012-2013	Christophe Battarel	<christophe.battarel@altairis.fr>
 * Copyright (C) 2012       Cédric Salvador     <csalvador@gpcsolutions.fr>
 * Copyright (C) 2012-2014  Raphaël Doursenaud  <rdoursenaud@gpcsolutions.fr>
 * Copyright (C) 2013		Florian Henry		<florian.henry@open-concept.pro>
 * Copyright (C) 2017		Juanjo Menent		<jmenent@2byte.es>
 * Copyright (C) 2022		OpenDSI				<support@open-dsi.fr>
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
 *
 * Need to have following variables defined:
 * $object (invoice, order, ...)
 * $conf
 * $langs
 * $element     (used to test $user->rights->$element->creer)
 * $permtoedit  (used to replace test $user->rights->$element->creer)
 * $inputalsopricewithtax (0 by default, 1 to also show column with unit price including tax)
 * $outputalsopricetotalwithtax
 * $usemargins (0 to disable all margins columns, 1 to show according to margin setup)
 *
 * $type, $text, $description, $line
 */

// Protection to avoid direct call of template
if (empty($object) || !is_object($object)) {
	print "Error, template page can't be called as URL";
	exit;
}

print "<!-- BEGIN PHP TEMPLATE objectline_title.tpl.php -->\n";

// Title line
print "<thead>\n";

print '<tr class="liste_titre nodrag nodrop">';

// Adds a line numbering column
if (!empty($conf->global->MAIN_VIEW_LINE_NUMBER)) {
	print '<td class="linecolnum center">&nbsp;</td>';
}

// Description
print '<td class="linecoldescription">'.$langs->trans('Description').'</td>';

// Supplier ref
if ($this->element == 'supplier_proposal' || $this->element == 'order_supplier' || $this->element == 'invoice_supplier' || $this->element == 'invoice_supplier_rec') {
	print '<td class="linerefsupplier maxwidth125"><span id="title_fourn_ref">'.$langs->trans("SupplierRef").'</span></td>';
}

// VAT
print '<td class="linecolvat right nowraponall">';
if (!empty($conf->global->FACTURE_LOCAL_TAX1_OPTION) || !empty($conf->global->FACTURE_LOCAL_TAX2_OPTION)) {
	print $langs->trans('Taxes');
} else {
	print $langs->trans('VAT');
}

if (in_array($object->element, array('propal', 'commande', 'facture', 'supplier_proposal', 'order_supplier', 'invoice_supplier')) && $object->status == $object::STATUS_DRAFT) {
	global $mysoc;

	if (empty($disableedit)) {
		print '<a class="reposition" href="'.$_SERVER["PHP_SELF"].'?mode=vatforalllines&id='.$object->id.'">'.img_edit($langs->trans("UpdateForAllLines"), 0, 'class="clickvatforalllines opacitymedium paddingleft cursorpointer"').'</a>';
	}
	//print '<script>$(document).ready(function() { $(".clickvatforalllines").click(function() { jQuery(".classvatforalllines").toggle(); }); });</script>';
	if (GETPOST('mode', 'aZ09') == 'vatforalllines') {
		print '<div class="classvatforalllines inline-block nowraponall">';
		print $form->load_tva('vatforalllines', '', $mysoc, $object->thirdparty, 0, 0, '', false, 1);
		print '<input class="inline-block button smallpaddingimp" type="submit" name="submitforalllines" value="'.$langs->trans("Update").'">';
		print '</div>';
	}
}
print '</td>';

// Price HT
print '<td class="linecoluht right nowraponall">'.$langs->trans('PriceUHT').'</td>';

// Multicurrency
if (isModEnabled("multicurrency") && $this->multicurrency_code != $conf->currency) {
	print '<td class="linecoluht_currency right" style="width: 80px">'.$langs->trans('PriceUHTCurrency', $this->multicurrency_code).'</td>';
}

if ($inputalsopricewithtax) {
	print '<td class="right nowraponall">'.$langs->trans('PriceUTTC').'</td>';
}

// Qty
print '<td class="linecolqty right">'.$langs->trans('Qty').'</td>';

// Unit
if (!empty($conf->global->PRODUCT_USE_UNITS)) {
	print '<td class="linecoluseunit left">'.$langs->trans('Unit').'</td>';
}

// Reduction short
print '<td class="linecoldiscount right nowraponall">';
print $langs->trans('ReductionShort');

if (in_array($object->element, array('propal', 'commande', 'facture')) && $object->status == $object::STATUS_DRAFT) {
	global $mysoc;

	if (empty($disableedit)) {
		print '<a class="reposition" href="'.$_SERVER["PHP_SELF"].'?mode=remiseforalllines&id='.$object->id.'">'.img_edit($langs->trans("UpdateForAllLines"), 0, 'class="clickvatforalllines opacitymedium paddingleft cursorpointer"').'</a>';
	}
	//print '<script>$(document).ready(function() { $(".clickremiseforalllines").click(function() { jQuery(".classremiseforalllines").toggle(); }); });</script>';
	if (GETPOST('mode', 'aZ09') == 'remiseforalllines') {
		print '<div class="remiseforalllines inline-block nowraponall">';
		print '<input class="inline-block smallpaddingimp width50 right" name="remiseforalllines" value="" placeholder="%">';
		print '<input class="inline-block button smallpaddingimp" type="submit" name="submitforalllines" value="'.$langs->trans("Update").'">';
		print '</div>';
	}
}
print '</td>';

// Fields for situation invoice
if (isset($this->situation_cycle_ref) && $this->situation_cycle_ref) {
	print '<td class="linecolcycleref right">'.$langs->trans('Progress').'</td>';
	print '<td class="linecolcycleref2 right">'.$form->textwithpicto($langs->trans('TotalHT100Short'), $langs->trans('UnitPriceXQtyLessDiscount')).'</td>';
}

// Purchase price
if ($usemargins && isModEnabled('margin') && empty($user->socid)) {
	if (!empty($user->rights->margins->creer)) {
		if ($conf->global->MARGIN_TYPE == "1") {
			print '<td class="linecolmargin1 margininfos right" style="width: 80px">'.$langs->trans('BuyingPrice').'</td>';
		} else {
			print '<td class="linecolmargin1 margininfos right" style="width: 80px">'.$langs->trans('CostPrice').'</td>';
		}
	}

	if (!empty($conf->global->DISPLAY_MARGIN_RATES) && $user->rights->margins->liretous) {
		print '<td class="linecolmargin2 margininfos right" style="width: 50px">'.$langs->trans('MarginRate').'</td>';
	}
	if (!empty($conf->global->DISPLAY_MARK_RATES) && $user->rights->margins->liretous) {
		print '<td class="linecolmargin2 margininfos right" style="width: 50px">'.$langs->trans('MarkRate').'</td>';
	}
}

// Total HT
print '<td class="linecolht right">'.$langs->trans('TotalHTShort').'</td>';

// Multicurrency
if (isModEnabled("multicurrency") && $this->multicurrency_code != $conf->currency) {
	print '<td class="linecoltotalht_currency right">'.$langs->trans('TotalHTShortCurrency', $this->multicurrency_code).'</td>';
}

if ($outputalsopricetotalwithtax) {
	print '<td class="right" style="width: 80px">'.$langs->trans('TotalTTCShort').'</td>';
}

if (isModEnabled('asset') && $object->element == 'invoice_supplier') {
	print '<td class="linecolasset"></td>';
}

print '<td class="linecoledit"></td>'; // No width to allow autodim

print '<td class="linecoldelete" style="width: 10px"></td>';

print '<td class="linecolmove" style="width: 10px"></td>';

if ($action == 'selectlines') {
	print '<td class="linecolcheckall center">';
	print '<input type="checkbox" class="linecheckboxtoggle" />';
	print '<script>$(document).ready(function() {$(".linecheckboxtoggle").click(function() {var checkBoxes = $(".linecheckbox");checkBoxes.prop("checked", this.checked);})});</script>';
	print '</td>';
}

print "</tr>\n";
print "</thead>\n";

print "<!-- END PHP TEMPLATE objectline_title.tpl.php -->\n";
