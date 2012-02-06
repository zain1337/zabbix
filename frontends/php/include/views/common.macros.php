<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

include dirname(__FILE__).'/js/common.macros.js.php';

$macrosTable = new CTable(SPACE, 'formElementTable');
$macrosTable->setAttribute('id', 'tbl_macros');
$macrosTable->addRow(array(_('Macro'), SPACE, _('Value'), SPACE));

// fields
$macros = array_values($this->data['macros']);
foreach ($macros as $macroid => $macro) {
	$text1 = new CTextBox('macros['.$macroid.'][macro]', $macro['macro'], 30, 'no', 64);
	$text1->setAttribute('placeholder', '{$MACRO}');
	$text1->setAttribute('style', 'text-transform:uppercase;');
	$text2 = new CTextBox('macros['.$macroid.'][value]', $macro['value'], 40, 'no', 255);
	$text2->setAttribute('placeholder', _('value'));
	$span = new CSpan(RARR);
	$span->addStyle('vertical-align:top;');

	$deleteButton = new CButton('macros_del', _('Remove'));
	$deleteButton->addClass('link_menu macroRemove');

	$row = array($text1, $span, $text2, $deleteButton);
	if (isset($macro['globalmacroid'])) {
		$row[] = new CVar('macros['.$macroid.'][globalmacroid]', $macro['globalmacroid']);
	}
	if (isset($macro['hostmacroid'])) {
		$row[] = new CVar('macros['.$macroid.'][hostmacroid]', $macro['hostmacroid']);
	}
	$macrosTable->addRow($row, 'form_row');
}

// buttons
$addButton = new CButton('macro_add', _('Add'));
$addButton->addClass('link_menu');

$buttonColumn = new CCol($addButton);
$buttonColumn->setAttribute('colspan', 5);

$buttonRow = new CRow();
$buttonRow->setAttribute('id', 'row_new_macro');
$buttonRow->addItem($buttonColumn);

$macrosTable->addRow($buttonRow);

// form list
$macrosFormList = new CFormList('macrosFormList');
$macrosFormList->addRow($macrosTable);

return $macrosFormList;