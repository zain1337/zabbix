<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * @var CView $this
 */

$this->addJsFile('multiselect.js');
$this->addJsFile('layout.mode.js');

$this->includeJsFile('monitoring.latest.view.js.php');

$this->enableLayoutModes();
$web_layout_mode = $this->getLayoutMode();

$widget = (new CWidget())
	->setTitle(_('Latest data'))
	->setWebLayoutMode($web_layout_mode)
	->setControls(
		(new CTag('nav', true, (new CList())->addItem(get_icon('kioskmode', ['mode' => $web_layout_mode]))))
			->setAttribute('aria-label', _('Content controls'))
	);

if ($web_layout_mode == ZBX_LAYOUT_NORMAL) {
	$filter_tags_table = (new CTable())
		->setId('filter-tags_#{uniqid}')
		->addRow(
			(new CCol(
				(new CRadioButtonList('evaltype', (int) $data['filter']['evaltype']))
					->addValue(_('And/Or'), TAG_EVAL_TYPE_AND_OR, 'evaltype_0#{uniqid}')
					->addValue(_('Or'), TAG_EVAL_TYPE_OR, 'evaltype_2#{uniqid}')
					->setModern(true)
					->setId('evaltype_#{uniqid}')
			))->setColSpan(4)
		);

	foreach ($data['filter']['tags'] as $i => $tag) {
		$filter_tags_table->addRow([
			(new CTextBox('tags['.$i.'][tag]', $tag['tag']))
				->setAttribute('placeholder', _('tag'))
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
			(new CRadioButtonList('tags['.$i.'][operator]', (int) $tag['operator']))
				->addValue(_('Contains'), TAG_OPERATOR_LIKE)
				->addValue(_('Equals'), TAG_OPERATOR_EQUAL)
				->setModern(true),
			(new CTextBox('tags['.$i.'][value]', $tag['value']))
				->setAttribute('placeholder', _('value'))
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
			(new CCol(
				(new CButton('tags['.$i.'][remove]', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-remove')
					->removeId()
			))->addClass(ZBX_STYLE_NOWRAP)
		], 'form_row');
	}

	$filter_tags_table->addRow(
		(new CCol(
			(new CButton('tags_add', _('Add')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-add')
				->removeId()
		))->setColSpan(3)
	);

	$widget->addItem((new CFilter((new CUrl('zabbix.php'))->setArgument('action', 'latest.view')))
		->setProfile('web.latest.filter')
		->setActiveTab($data['active_tab'])
		->addFormItem((new CVar('action', 'latest.view'))->removeId())
		->addFilterTab(_('Filter'), [
			(new CFormList())
				->addRow((new CLabel(_('Host groups'), 'filter_groupids__ms')),
					(new CMultiSelect([
						'name' => 'filter_groupids[]',
						'object_name' => 'hostGroup',
						'data' => $data['multiselect_hostgroup_data'],
						'popup' => [
							'parameters' => [
								'srctbl' => 'host_groups',
								'srcfld1' => 'groupid',
								'dstfrm' => 'zbx_filter',
								'dstfld1' => 'filter_groupids_',
								'real_hosts' => true,
								'enrich_parent_groups' => true
							]
						]
					]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				)
				->addRow((new CLabel(_('Hosts'), 'filter_hostids__ms')),
					(new CMultiSelect([
						'name' => 'filter_hostids[]',
						'object_name' => 'hosts',
						'data' => $data['multiselect_host_data'],
						'popup' => [
							'filter_preselect_fields' => [
								'hostgroups' => 'filter_groupids_'
							],
							'parameters' => [
								'srctbl' => 'hosts',
								'srcfld1' => 'hostid',
								'dstfrm' => 'zbx_filter',
								'dstfld1' => 'filter_hostids_'
							]
						]
					]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				)
				->addRow(_('Name'),
					(new CTextBox('filter_select', $data['filter']['select']))
						->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				),
			(new CFormList())
				->addRow(_('Tags'), $filter_tags_table)
				->addRow(_('Show details'), [
					(new CCheckBox('filter_show_details'))->setChecked($data['filter']['show_details'] == 1),
					(new CDiv([
						(new CLabel(_('Show items without data'), 'show_suppressed_#{uniqid}'))
							->addClass(ZBX_STYLE_SECOND_COLUMN_LABEL),
						(new CCheckBox('filter_show_without_data'))
							->setChecked($data['filter']['show_without_data'] == 1)
							->setAttribute('disabled', $data['filter']['hostids'] ? null : 'disabled')
					]))->addClass(ZBX_STYLE_TABLE_FORMS_SECOND_COLUMN)
				])
		])
	);
}

$widget->addItem(new CPartial('monitoring.latest.view.html', array_intersect_key($data,
	array_flip(['filter', 'sort_field', 'sort_order', 'view_curl', 'paging', 'hosts', 'items', 'history', 'config'])
)));

$widget->show();

// Initialize page refresh.
(new CScriptTag('latest_page.start();'))
	->setOnDocumentReady()
	->show();
