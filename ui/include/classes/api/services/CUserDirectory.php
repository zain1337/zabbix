<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


class CUserDirectory extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'create' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'test' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	protected $tableName = 'userdirectory';
	protected $tableAlias = 'ud';

	/**
	 * Common UserDirectory properties.
	 *
	 * @var array
	 */
	protected $output_fields = ['userdirectoryid', 'name', 'idp_type', 'provision_status', 'description'];

	/**
	 * LDAP specific properties.
	 *
	 * @var array
	 */
	protected $ldap_output_fields = [
		'host', 'port', 'base_dn', 'search_attribute', 'bind_dn', 'bind_password', 'start_tls', 'search_filter',
		'group_basedn', 'group_name', 'group_member', 'group_filter', 'group_membership', 'user_username',
		'user_lastname'
	];

	/**
	 * SAML specific properties.
	 *
	 * @var array
	 */
	protected $saml_output_fields = [
		'idp_entityid', 'sso_url', 'slo_url', 'username_attribute', 'sp_entityid', 'nameid_format', 'sign_messages',
		'sign_assertions', 'sign_authn_requests', 'sign_logout_requests', 'sign_logout_responses', 'encrypt_nameid',
		'encrypt_assertions', 'group_name', 'user_username', 'user_lastname', 'scim_status', 'scim_token'
	];

	/**
	 * @param array $options
	 *
	 * @throws APIException
	 *
	 * @return array|string
	 */
	public function get(array $options) {
		$this->validateGet($options);

		if (!$options['countOutput'] && $options['output'] === API_OUTPUT_EXTEND) {
			$options['output'] = array_merge(
				$this->output_fields, $this->ldap_output_fields, $this->saml_output_fields
			);
		}

		$sql_parts = [
			'select' => ['userdirectory' => 'ud.userdirectoryid'],
			'from' => ['userdirectory' => 'userdirectory ud'],
			'left_join' => [],
			'order' => [],
			'group' => []
		];

		$sql_parts = $this->applyQueryFilterOptions($this->tableName, $this->tableAlias, $options, $sql_parts);
		$sql_parts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sql_parts);
		$sql_parts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sql_parts);

		$result = DBselect($this->createSelectQueryFromParts($sql_parts), $options['limit']);

		$db_userdirectories = [];
		while ($row = DBfetch($result)) {
			if ($options['countOutput']) {
				return $row['rowscount'];
			}

			$db_userdirectories[$row['userdirectoryid']] = $row;
		}

		if ($db_userdirectories) {
			$db_userdirectories = $this->addRelatedObjects($options, $db_userdirectories);
			$db_userdirectories = $this->unsetExtraFields($db_userdirectories, ['userdirectoryid'], $options['output']);

			if (!$options['preservekeys']) {
				$db_userdirectories = array_values($db_userdirectories);
			}
		}

		return $db_userdirectories;
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sql_parts) {
		$sql_parts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sql_parts);

		$selected_ldap_fields = [];
		foreach ($this->ldap_output_fields as $field) {
			if ($this->outputIsRequested($field, $options['output'])) {
				$selected_ldap_fields[] = 'ldap.'.$field;
			}
		}
		if ($selected_ldap_fields) {
			$sql_parts['left_join'][] = [
				'alias' => 'ldap',
				'table' => 'userdirectory_ldap',
				'using' => 'userdirectoryid'
			];
			$sql_parts['left_table'] = ['alias' => $tableAlias, 'table' => $tableName];

			if (!$options['countOutput']) {
				$sql_parts['select'] = array_merge($sql_parts['select'], $selected_ldap_fields);
			}
		}

		$selected_saml_fields = [];
		foreach ($this->saml_output_fields as $field) {
			if ($this->outputIsRequested($field, $options['output'])) {
				$selected_saml_fields[] = 'saml.'.$field;
			}
		}
		if ($selected_saml_fields) {
			$sql_parts['left_join'][] = [
				'alias' => 'saml',
				'table' => 'userdirectory_saml',
				'using' => 'userdirectoryid'
			];
			$sql_parts['left_table'] = ['alias' => $tableAlias, 'table' => $tableName];

			if (!$options['countOutput']) {
				$sql_parts['select'] = array_merge($sql_parts['select'], $selected_saml_fields);
			}
		}

		return $sql_parts;
	}

	private function validateGet(array &$options): void {
		$output_fields = array_merge($this->output_fields, $this->ldap_output_fields, $this->saml_output_fields);

		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'userdirectoryids' =>			['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'filter' =>						['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'userdirectoryid' =>			['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'provision_status' =>			['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', [JIT_PROVISIONING_DISABLED, JIT_PROVISIONING_ENABLED])],
				'idp_type' =>					['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', [IDP_TYPE_LDAP, IDP_TYPE_SAML])]
			]],
			'search' =>						['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'name' =>						['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'description' =>				['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE]
			]],
			'searchByAny' =>				['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>				['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>				['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>		['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>						['type' => API_OUTPUT, 'in' => implode(',', $output_fields), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>				['type' => API_FLAG, 'default' => false],
			'selectUsrgrps' =>				['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', ['usrgrpid', 'name', 'gui_access', 'users_status', 'debug_mode']), 'default' => null],
			'selectProvisionMedia' =>		['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', ['name', 'mediatypeid', 'attribute']), 'default' => null],
			'selectProvisionGroups' =>		['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', ['is_fallback', 'fallback_status', 'name', 'roleid', 'user_groups']), 'default' => null],
			// sort and limit
			'sortfield' =>					['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', ['name']), 'uniq' => true, 'default' => []],
			'sortorder' =>					['type' => API_SORTORDER, 'default' => []],
			'limit' =>						['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],
			// flags
			'preservekeys' =>				['type' => API_BOOLEAN, 'default' => false]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	/**
	 * @param array $options
	 * @param array $result
	 *
	 * @return array
	 */
	protected function addRelatedObjects(array $options, array $result): array {
		$result = parent::addRelatedObjects($options, $result);

		self::addRelatedUserGroups($options, $result);
		self::addRelatedProvisionMedia($options, $result);
		self::addRelatedProvisionGroups($options, $result);

		return $result;
	}

	/**
	 * @param array $options
	 * @param array $result
	 */
	private static function addRelatedUserGroups(array $options, array &$result): void {
		if ($options['selectUsrgrps'] === null) {
			return;
		}

		foreach ($result as &$row) {
			$row['usrgrps'] = [];
		}
		unset($row);

		if ($options['selectUsrgrps'] === API_OUTPUT_COUNT) {
			$output = ['userdirectoryid'];
		}
		elseif ($options['selectUsrgrps'] === API_OUTPUT_EXTEND) {
			$output = ['usrgrpid', 'name', 'gui_access', 'users_status', 'debug_mode', 'userdirectoryid'];
		}
		else {
			$output = array_unique(array_merge(['userdirectoryid'], $options['selectUsrgrps']));
		}

		$db_usergroups = API::UserGroup()->get([
			'output' => $output,
			'filter' => ['userdirectoryid' => array_keys($result)]
		]);

		foreach ($db_usergroups as $db_usergroup) {
			$result[$db_usergroup['userdirectoryid']]['usrgrps'][] =
				array_diff_key($db_usergroup, array_flip(['userdirectoryid']));
		}

		if ($options['selectUsrgrps'] === API_OUTPUT_COUNT) {
			foreach ($result as &$row) {
				$row['usrgrps'] = (string) count($row['usrgrps']);
			}
			unset($row);
		}
	}

	/**
	 * Add provision media objects.
	 *
	 * @param array $options
	 * @param array $result
	 */
	private static function addRelatedProvisionMedia(array $options, array &$result): void {
		if ($options['selectProvisionMedia'] === null) {
			return;
		}

		foreach ($result as &$row) {
			$row['provision_media'] = [];
		}
		unset($row);

		if ($options['selectProvisionMedia'] === API_OUTPUT_EXTEND) {
			$options['selectProvisionMedia'] = ['name', 'mediatypeid', 'attribute'];
		}

		$db_provisioning_media = DB::select('userdirectory_media', [
			'output' => array_merge($options['selectProvisionMedia'], ['userdirectoryid']),
			'filter' => [
				'userdirectoryid' => array_keys($result)
			]
		]);

		foreach ($db_provisioning_media as $db_provisioning_media) {
			$result[$db_provisioning_media['userdirectoryid']]['provision_media'][]
				= array_diff_key($db_provisioning_media, array_flip(['userdirectoryid']));
		}
	}

	/**
	 * Add provision user group objects.
	 *
	 * @param array $options
	 * @param array $result
	 */
	private static function addRelatedProvisionGroups(array $options, array &$result): void {
		if ($options['selectProvisionGroups'] === null) {
			return;
		}

		foreach ($result as &$row) {
			$row['provision_groups'] = [];
		}
		unset($row);

		if ($options['selectProvisionGroups'] === API_OUTPUT_EXTEND) {
			$options['selectProvisionGroups'] = ['is_fallback', 'fallback_status', 'name', 'roleid', 'user_groups'];
		}

		$user_groups_index = array_search('user_groups', $options['selectProvisionGroups']);
		if ($user_groups_index !== false) {
			unset($options['selectProvisionGroups'][$user_groups_index]);
		}

		$db_provision_idpgroups = DB::select('userdirectory_idpgroup', [
			'output' => array_merge($options['selectProvisionGroups'], ['userdirectoryid', 'userdirectory_idpgroupid']),
			'filter' => [
				'userdirectoryid' => array_keys($result)
			],
			'sortfield' => ['sortorder'],
			'preservekeys' => true
		]);

		$db_provision_usergroups = $user_groups_index !== false && $db_provision_idpgroups
			? DB::select('userdirectory_usrgrp', [
				'output' => ['usrgrpid', 'userdirectory_idpgroupid'],
				'filter' => [
					'userdirectory_idpgroupid' => array_keys($db_provision_idpgroups)
				]
			])
			: [];

		$provision_usergroups = [];
		foreach ($db_provision_usergroups as $usrgrp) {
			$provision_usergroups[$usrgrp['userdirectory_idpgroupid']][] = [
				'usrgrpid' => $usrgrp['usrgrpid']
			];
		}

		foreach ($db_provision_idpgroups as $db_provision_idpgroup) {
			$idpgroup = array_intersect_key($db_provision_idpgroup, array_flip($options['selectProvisionGroups']));

			if ($user_groups_index !== false) {
				$provision_idpgroupid = $db_provision_idpgroup['userdirectory_idpgroupid'];
				$idpgroup['user_groups'] = array_key_exists($provision_idpgroupid, $provision_usergroups)
					? $provision_usergroups[$provision_idpgroupid]
					: [];
			}

			$result[$db_provision_idpgroup['userdirectoryid']]['provision_groups'][] = $idpgroup;
		}
	}

	/**
	 * @param array $userdirectories
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function create(array $userdirectories): array {
		self::validateCreate($userdirectories);

		$db_ldap_idp_count = DB::select('userdirectory', [
			'countOutput' => true,
			'filter' => [
				'idp_type' => IDP_TYPE_LDAP
			]
		]);

		$userdirectoryids = DB::insert('userdirectory', $userdirectories);
		$userdirectory_media = [];
		$userdirectory_idpgroups = [];
		$userdirectory_usrgrps = [];
		$create_idps_ldap = [];
		$create_idps_saml = [];

		foreach ($userdirectories as $index => &$userdirectory) {
			$userdirectory['userdirectoryid'] = $userdirectoryids[$index];

			if ($userdirectory['idp_type'] == IDP_TYPE_LDAP) {
				$create_idps_ldap[] = $userdirectory;
			}
			elseif ($userdirectory['idp_type'] == IDP_TYPE_SAML) {
				$create_idps_saml[] = $userdirectory;
			}

			if (array_key_exists('provision_media', $userdirectory)) {
				foreach ($userdirectory['provision_media'] as $media) {
					$userdirectory_media[] = [
						'userdirectoryid' => $userdirectory['userdirectoryid']
					] + $media;
				}
			}

			if (array_key_exists('provision_groups', $userdirectory)) {
				foreach ($userdirectory['provision_groups'] as $num => $groups) {
					$userdirectory_idpgroups[] = [
						'userdirectoryid' => $userdirectory['userdirectoryid'],
						'sortorder' => ++$num
					] + $groups;
				}
			}
		}
		unset($userdirectory);

		if ($userdirectory_idpgroups) {
			$idpgroupids = DB::insertBatch('userdirectory_idpgroup', $userdirectory_idpgroups);

			foreach ($idpgroupids as $index => $idpgroupid) {
				foreach ($userdirectory_idpgroups[$index]['user_groups'] as $usrgrp) {
					$userdirectory_usrgrps[] = [
						'userdirectory_idpgroupid' => $idpgroupid,
						'usrgrpid' => $usrgrp['usrgrpid']
					];
				}
			}

			$userdirectory_usrgrpids = DB::insertBatch('userdirectory_usrgrp', $userdirectory_usrgrps);
		}

		if ($create_idps_ldap) {
			DB::insertBatch('userdirectory_ldap', $create_idps_ldap);
		}

		if ($create_idps_saml) {
			DB::insertBatch('userdirectory_saml', $create_idps_saml);
		}

		if ($userdirectory_media) {
			$userdirectory_mediaids = DB::insertBatch('userdirectory_media', $userdirectory_media);
		}

		// Return IDs for audit log.
		foreach ($userdirectories as &$userdirectory) {
			if (array_key_exists('provision_media', $userdirectory)) {
				foreach ($userdirectory['provision_media'] as &$media) {
					$media['userdirectory_mediaid'] = array_shift($userdirectory_mediaids);
				}
				unset($media);
			}

			if (array_key_exists('provision_groups', $userdirectory)) {
				foreach ($userdirectory['provision_groups'] as &$provision_group) {
					$provision_group['userdirectory_idpgroupid'] = array_shift($idpgroupids);

					foreach ($provision_group['user_groups'] as &$user_group) {
						$user_group['userdirectory_usrgrpid'] = array_shift($userdirectory_usrgrpids);
					}
					unset($user_group);
				}
				unset($provision_group);
			}
		}
		unset($userdirectory);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_USERDIRECTORY, $userdirectories);

		if ($db_ldap_idp_count == 0 && $create_idps_ldap) {
			$idp_ldap = reset($create_idps_ldap);
			API::Authentication()->update(['ldap_userdirectoryid' => $idp_ldap['userdirectoryid']]);
		}

		return ['userdirectoryids' => $userdirectoryids];
	}

	/**
	 * @param array $userdirectories
	 *
	 * @throws APIException
	 */
	private static function validateCreate(array &$userdirectories): void {
		$api_input_rules = self::getValidationRules('create');

		if (!CApiInputValidator::validate($api_input_rules, $userdirectories, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkDuplicates($userdirectories);
		self::checkSamlExists($userdirectories);
	}

	/**
	 * Validate if only one user directory of type IDP_TYPE_SAML exists.
	 *
	 * @return void
	 */
	private static function checkSamlExists($userdirectories): void {
		$userdirectories = array_filter($userdirectories, function ($userdirectory) {
			return $userdirectory['idp_type'] == IDP_TYPE_SAML;
		});
		if (count($userdirectories) == 0) {
			return;
		}

		$saml_userdirectory = DB::select('userdirectory', [
			'countOutput' => true,
			'filter' => [
				'idp_type' => IDP_TYPE_SAML
			]
		]);

		if ($saml_userdirectory != 0) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Only one user directory of type "%1$s" can exist.', IDP_TYPE_SAML)
			);
		}
	}

	/**
	 * @param array $userdirectories
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function update(array $userdirectories): array {
		$this->validateUpdate($userdirectories, $db_userdirectories);

		$upd_userdirectories = [];
		$upd_userdirectories_ldap = [];
		$upd_userdirectories_saml = [];

		foreach ($userdirectories as $userdirectory) {
			$db_userdirectory = $db_userdirectories[$userdirectory['userdirectoryid']];

			$upd_userdirectory = DB::getUpdatedValues('userdirectory',
				array_intersect_key($userdirectory, array_flip($this->output_fields)),
				$db_userdirectories[$userdirectory['userdirectoryid']]
			);
			if ($upd_userdirectory) {
				$upd_userdirectories[] = [
					'values' => $upd_userdirectory,
					'where' => ['userdirectoryid' => $userdirectory['userdirectoryid']]
				];
			}

			if ($db_userdirectory['idp_type'] == IDP_TYPE_LDAP) {
				$upd_userdirectory_ldap = DB::getUpdatedValues('userdirectory_ldap',
					array_intersect_key($userdirectory, array_flip($this->ldap_output_fields)),
					$db_userdirectories[$userdirectory['userdirectoryid']]
				);
				if ($upd_userdirectory_ldap) {
					$upd_userdirectories_ldap[] = [
						'values' => $upd_userdirectory_ldap,
						'where' => ['userdirectoryid' => $userdirectory['userdirectoryid']]
					];
				}
			}
			elseif ($db_userdirectory['idp_type'] == IDP_TYPE_SAML) {
				$upd_userdirectory_saml = DB::getUpdatedValues('userdirectory_saml',
					array_intersect_key($userdirectory, array_flip($this->saml_output_fields)),
					$db_userdirectories[$userdirectory['userdirectoryid']]
				);
				if ($upd_userdirectory_saml) {
					$upd_userdirectories_saml[] = [
						'values' => $upd_userdirectory_saml,
						'where' => ['userdirectoryid' => $userdirectory['userdirectoryid']]
					];
				}
			}
		}

		DB::update('userdirectory', $upd_userdirectories);
		DB::update('userdirectory_ldap', $upd_userdirectories_ldap);
		DB::update('userdirectory_saml', $upd_userdirectories_saml);

		self::updateProvisionMedia($userdirectories, $db_userdirectories);
		self::updateProvisionGroups($userdirectories, $db_userdirectories);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_USERDIRECTORY, $userdirectories, $db_userdirectories);

		return ['userdirectoryids' => array_column($userdirectories, 'userdirectoryid')];
	}

	/**
	 * Validate function for 'update' method.
	 *
	 * Validation is performed in multiple steps. First we check if userdirectoryid(s) are present. Then we extend each
	 * of given $userdirectories objects with 'idp_type' property from database, then perform full input validation.
	 *
	 * @param array      $userdirectories
	 * @param array|null $db_userdirectories
	 *
	 * @throws APIException
	 */
	private function validateUpdate(array &$userdirectories, ?array &$db_userdirectories): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE | API_ALLOW_UNEXPECTED, 'fields' => [
			'userdirectoryid' => ['type' => API_ID, 'flags' => API_REQUIRED]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $userdirectories, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_userdirectories = $this->get([
			'output' => API_OUTPUT_EXTEND,
			'userdirectoryids' => array_column($userdirectories, 'userdirectoryid'),
			'preservekeys' => true
		]);

		if (count($db_userdirectories) != count($userdirectories)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		foreach ($userdirectories as &$userdirectory) {
			$db_userdirectory = $db_userdirectories[$userdirectory['userdirectoryid']];
			if (!array_key_exists('idp_type', $userdirectory)) {
				$userdirectory['idp_type'] = $db_userdirectory['idp_type'];
			}

			if ($userdirectory['idp_type'] != $db_userdirectory['idp_type']) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect value for field "%1$s": %2$s.', 'idp_type', _('cannot be changed'))
				);
			}
		}
		unset($userdirectory);

		$api_input_rules = self::getValidationRules('update');
		if (!CApiInputValidator::validate($api_input_rules, $userdirectories, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkDuplicates($userdirectories, $db_userdirectories);
		self::addAffectedObjects($userdirectories, $db_userdirectories);
	}

	private static function addAffectedObjects(array $userdirectories, array &$db_userdirectories): void {
		self::addAffectedProvisionMedia($userdirectories, $db_userdirectories);
		self::addAffectedProvisionGroups($userdirectories, $db_userdirectories);
	}

	private static function addAffectedProvisionMedia(array $userdirectories, array &$db_userdirectories): void {
		$affected_userdirectoryids = [];
		foreach ($userdirectories as $userdirectory) {
			if (array_key_exists('provision_media', $userdirectory)) {
				$affected_userdirectoryids[$userdirectory['userdirectoryid']] = true;
				$db_userdirectories[$userdirectory['userdirectoryid']]['provision_media'] = [];
			}
		}

		if (!$affected_userdirectoryids) {
			return;
		}

		$db_provision_media = DB::select('userdirectory_media', [
			'output' => ['userdirectory_mediaid', 'userdirectoryid', 'mediatypeid', 'name', 'attribute'],
			'filter' => [
				'userdirectoryid' => array_keys($affected_userdirectoryids)
			]
		]);

		foreach ($db_provision_media as $media) {
			$db_userdirectories[$media['userdirectoryid']]['provision_media'][] = [
				'userdirectory_mediaid' => $media['userdirectory_mediaid'],
				'mediatypeid' => $media['mediatypeid'],
				'name' => $media['name'],
				'attribute' => $media['attribute']
			];
		}
	}

	private static function addAffectedProvisionGroups(array $userdirectories, array &$db_userdirectories): void {
		$affected_userdirectoryids = [];
		foreach ($userdirectories as $userdirectory) {
			if (array_key_exists('provision_groups', $userdirectory)) {
				$affected_userdirectoryids[$userdirectory['userdirectoryid']] = true;
				$db_userdirectories[$userdirectory['userdirectoryid']]['provision_groups'] = [];
			}
		}

		if (!$affected_userdirectoryids) {
			return;
		}

		$db_provision_groups = DB::select('userdirectory_idpgroup', [
			'output' => ['userdirectory_idpgroupid', 'userdirectoryid', 'roleid', 'is_fallback', 'fallback_status',
				'name', 'sortorder'
			],
			'filter' => [
				'userdirectoryid' => array_keys($affected_userdirectoryids)
			],
			'preservekeys' => true
		]);

		$db_provision_usrgrps = DB::select('userdirectory_usrgrp', [
			'output' => ['userdirectory_usrgrpid', 'userdirectory_idpgroupid', 'usrgrpid'],
			'filter' => [
				'userdirectory_idpgroupid' => array_keys($db_provision_groups)
			]
		]);

		$db_idpgroup_usergroups = [];
		foreach ($db_provision_usrgrps as $db_prov_usrgrp) {
			['userdirectory_idpgroupid' => $prov_groupid, 'userdirectory_usrgrpid' => $prov_usrgrpid] = $db_prov_usrgrp;

			$db_idpgroup_usergroups[$prov_groupid][] = [
				'usrgrpid' => $db_prov_usrgrp['usrgrpid'],
				'userdirectory_usrgrpid' => $prov_usrgrpid
			];
		}

		foreach ($db_provision_groups as $db_prov_groups) {
			['userdirectory_idpgroupid' => $prov_groupid, 'userdirectoryid' => $userdirectoryid] = $db_prov_groups;

			$db_userdirectories[$userdirectoryid]['provision_groups'][] = [
				'userdirectory_idpgroupid' => $prov_groupid,
				'is_fallback' => $db_prov_groups['is_fallback'],
				'fallback_status' => $db_prov_groups['fallback_status'],
				'name' => $db_prov_groups['name'],
				'roleid' => $db_prov_groups['roleid'],
				'sortorder' => $db_prov_groups['sortorder'],
				'user_groups' => $db_idpgroup_usergroups[$prov_groupid]
			];
		}
	}

	/**
	 * Check for unique names.
	 *
	 * @param array      $userdirectories
	 * @param array|null $db_userdirectories
	 *
	 * @throws APIException if userdirectory name is not unique.
	 */
	private static function checkDuplicates(array $userdirectories, array $db_userdirectories = null): void {
		$names = [];

		foreach ($userdirectories as $userdirectory) {
			if (!array_key_exists('name', $userdirectory)) {
				continue;
			}

			if ($db_userdirectories === null
					|| $userdirectory['name'] !== $db_userdirectories[$userdirectory['userdirectoryid']]['name']) {
				$names[] = $userdirectory['name'];
			}
		}

		if (!$names) {
			return;
		}

		$duplicates = DB::select('userdirectory', [
			'output' => ['name'],
			'filter' => ['name' => $names],
			'limit' => 1
		]);

		if ($duplicates) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('User directory "%1$s" already exists.',
				$duplicates[0]['name'])
			);
		}
	}

	/**
	 * @param array $userdirectoryids
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function delete(array $userdirectoryids): array {
		self::validateDelete($userdirectoryids, $db_userdirectories);

		DB::update('users', [[
			'values' => ['userdirectoryid' => 0],
			'where' => ['userdirectoryid' => $userdirectoryids]
		]]);

		DB::delete('userdirectory', ['userdirectoryid' => $userdirectoryids]);

		//self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_USERDIRECTORY, $db_userdirectories);

		return ['userdirectoryids' => $userdirectoryids];
	}

	/**
	 * @param array      $userdirectoryids
	 * @param array|null $db_userdirectories
	 *
	 * @throws APIException
	 */
	private static function validateDelete(array $userdirectoryids, ?array &$db_userdirectories): void {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $userdirectoryids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_userdirectories = API::UserDirectory()->get([
			'output' => ['userdirectoryid', 'name'],
			'userdirectoryids' => $userdirectoryids,
			'preservekeys' => true
		]);

		if (count($db_userdirectories) != count($userdirectoryids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$userdirectories_left = API::UserDirectory()->get(['countOutput' => true]) - count($userdirectoryids);
		$auth = API::Authentication()->get([
			'output' => ['ldap_userdirectoryid', 'authentication_type', 'ldap_configured']
		]);

		if (in_array($auth['ldap_userdirectoryid'], $userdirectoryids)
				&& ($auth['ldap_configured'] == ZBX_AUTH_LDAP_ENABLED || $userdirectories_left > 0)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete default user directory.'));
		}

		$db_groups = API::UserGroup()->get([
			'output' => ['userdirectoryid'],
			'filter' => [
				'gui_access' => [GROUP_GUI_ACCESS_LDAP, GROUP_GUI_ACCESS_SYSTEM],
				'userdirectoryid' => $userdirectoryids
			],
			'limit' => 1
		]);

		if ($db_groups) {
			$db_group = reset($db_groups);

			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot delete user directory "%1$s".',
				$db_userdirectories[$db_group['userdirectoryid']]['name'])
			);
		}

		if (in_array($auth['ldap_userdirectoryid'], $userdirectoryids)) {
			// If last (default) is removed, reset default userdirectoryid to prevent from foreign key constraint.
			API::Authentication()->update(['ldap_userdirectoryid' => 0]);
		}
	}

	/**
	 * Test user against specific userdirectory connection.
	 *
	 * @param array $userdirectory
	 *
	 * @throws APIException
	 *
	 * @return bool
	 */
	public function test(array $userdirectory): bool {
		$this->validateTest($userdirectory);

		$user = [
			'username' => $userdirectory['test_username'],
			'password' => $userdirectory['test_password']
		];
		$ldap_validator = new CLdapAuthValidator(['conf' => $userdirectory]);

		if (!$ldap_validator->validate($user)) {
			self::exception(
				$ldap_validator->isConnectionError() ? ZBX_API_ERROR_PARAMETERS : ZBX_API_ERROR_PERMISSIONS,
				$ldap_validator->getError()
			);
		}

		return true;
	}

	/**
	 * Validate user directory and test user credentials to be used for testing.
	 *
	 * @param array $userdirectory
	 *
	 * @throws APIException
	 */
	protected function validateTest(array &$userdirectory): void {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'fields' => [
			'userdirectoryid' =>	['type' => API_ID, 'flags' => API_ALLOW_NULL, 'default' => null],
			'host' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory_ldap', 'host')],
			'port' =>				['type' => API_PORT, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'base_dn' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory_ldap', 'base_dn')],
			'bind_dn' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory_ldap', 'bind_dn'), 'default' => ''],
			'bind_password' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory_ldap', 'bind_password')],
			'search_attribute' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory_ldap', 'search_attribute')],
			'start_tls' =>			['type' => API_INT32, 'in' => ZBX_AUTH_START_TLS_OFF.','.ZBX_AUTH_START_TLS_ON, 'default' => ZBX_AUTH_START_TLS_OFF],
			'search_filter' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory_ldap', 'search_filter'), 'default' => ''],
			'test_username' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'test_password' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $userdirectory, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if ($userdirectory['userdirectoryid'] !== null) {
			$db_userdirectory = $this->get([
				'output' => ['host', 'port', 'base_dn', 'bind_dn', 'bind_password', 'search_attribute', 'start_tls',
					'search_filter'
				],
				'userdirectoryids' => $userdirectory['userdirectoryid'],
				'filter' => [
					'idp_type' => IDP_TYPE_LDAP
				]
			]);
			$db_userdirectory = reset($db_userdirectory);

			if (!$db_userdirectory) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			$userdirectory += $db_userdirectory;
		}
	}

	/**
	 * @param array      $userdirectories
	 */
	private static function updateProvisionMedia(array &$userdirectories, array $db_userdirectories): void {
		$userdirectories = array_column($userdirectories, null, 'userdirectoryid');
		$userdirectoryids = array_keys(array_filter($userdirectories, function ($userdirectory) {
			return array_key_exists('provision_media', $userdirectory);
		}));

		$provision_media_remove = [];
		foreach ($userdirectoryids as $userdirectoryid) {
			foreach ($db_userdirectories[$userdirectoryid]['provision_media'] as $media) {
				$provision_media_remove[$userdirectoryid][$media['userdirectory_mediaid']] =
					array_intersect_key($media, array_flip(['name', 'mediatypeid', 'attribute']));
			}
		}

		// Remove unchanged provision media entries.
		foreach ($userdirectoryids as $userdirectoryid) {
			$userdirectory = $userdirectories[$userdirectoryid];
			foreach ($provision_media_remove[$userdirectoryid] as $remove_mediaid => $provision_media_data) {
				foreach ($userdirectory['provision_media'] as $index => $new_provision_media) {
					if ($provision_media_data == $new_provision_media) {
						unset($provision_media_remove[$userdirectoryid][$remove_mediaid]);
						unset($userdirectories[$userdirectoryid]['provision_media'][$index]);
					}
				}
			}
		}

		// Collect IDs to remove.
		$provision_mediaids_remove = [];
		foreach ($provision_media_remove as $media_remove) {
			$provision_mediaids_remove = array_merge($provision_mediaids_remove, array_keys($media_remove));
		}

		if ($provision_mediaids_remove) {
			DB::delete('userdirectory_media', ['userdirectory_mediaid' => $provision_mediaids_remove]);
		}

		// Collect new provision media entries.
		$provision_media_insert = [];
		foreach ($userdirectoryids as $userdirectoryid) {
			foreach ($userdirectories[$userdirectoryid]['provision_media'] as $media) {
				$provision_media_insert[] = ['userdirectoryid' => $userdirectoryid] + $media;
			}
		}

		if ($provision_media_insert) {
			$provision_mediaids = DB::insertBatch('userdirectory_media', $provision_media_insert);

			foreach ($userdirectoryids as $userdirectoryid) {
				foreach ($userdirectories[$userdirectoryid]['provision_media'] as $index => $new_provision_media) {
					if (!array_key_exists('userdirectory_mediaid', $new_provision_media)) {
						$userdirectories[$userdirectoryid]['provision_media'][$index]['userdirectory_mediaid']
							= array_shift($provision_mediaids);
					}
				}
			}
		}
	}

	private static function updateProvisionGroups(array &$userdirectories, array $db_userdirectories): void {
		$userdirectories = array_column($userdirectories, null, 'userdirectoryid');
		$userdirectoryids = array_keys(array_filter($userdirectories, function ($userdirectory) {
			return array_key_exists('provision_groups', $userdirectory);
		}));

		$provision_groups_remove = [];
		foreach ($userdirectoryids as $userdirectoryid) {
			foreach ($db_userdirectories[$userdirectoryid]['provision_groups'] as $group) {
				$user_groups = array_column($group['user_groups'], null, 'userdirectory_usrgrpid');
				$group['user_groups'] = array_map(function ($usrgrp) {
					return array_intersect_key($usrgrp, array_flip(['usrgrpid']));
				}, $user_groups);

				$provision_groups_remove[$userdirectoryid][$group['userdirectory_idpgroupid']] = array_intersect_key(
					$group, array_flip(['is_fallback', 'fallback_status', 'name', 'roleid', 'sortorder', 'user_groups'])
				);
			}
		}

		foreach ($userdirectoryids as $userdirectoryid) {
			// Add sortorder value for each provision group.
			foreach ($userdirectories[$userdirectoryid]['provision_groups'] as $index => $provision_group) {
				$userdirectories[$userdirectoryid]['provision_groups'][$index]['sortorder'] = $index + 1;
			}

			// Unset provision groups that actually have not been changed.
			foreach ($provision_groups_remove[$userdirectoryid] as $groupid => $group) {
				CArrayHelper::sort($group['user_groups'], ['usrgrpid']);
				$group['user_groups'] = array_values($group['user_groups']);

				foreach ($userdirectories[$userdirectoryid]['provision_groups'] as $index => $new_provision_group) {
					CArrayHelper::sort($new_provision_group['user_groups'], ['usrgrpid']);
					$new_provision_group['user_groups'] = array_values($new_provision_group['user_groups']);

					if ($group == $new_provision_group) {
						unset($provision_groups_remove[$userdirectoryid][$groupid]);
						unset($userdirectories[$userdirectoryid]['provision_groups'][$index]);
					}
				}
			}
		}

		// Collect IDs to remove.
		$provision_groupids_remove = [];
		foreach ($provision_groups_remove as $groups_remove) {
			$provision_groupids_remove = array_merge($provision_groupids_remove, array_keys($groups_remove));
		}

		if ($provision_groupids_remove) {
			DB::delete('userdirectory_idpgroup', ['userdirectory_idpgroupid' => $provision_groupids_remove]);
		}

		// Record new provision groups.
		$idpgroups_insert = [];
		foreach ($userdirectoryids as $userdirectoryid) {
			foreach ($userdirectories[$userdirectoryid]['provision_groups'] as $group) {
				$idpgroups_insert[] = ['userdirectoryid' => $userdirectoryid] + $group;
			}
		}

		if ($idpgroups_insert) {
			$idpgroupids = DB::insertBatch('userdirectory_idpgroup', $idpgroups_insert);

			$user_groups = [];
			foreach ($idpgroupids as $index => $idpgroupid) {
				$idpgroup = $idpgroups_insert[$index];

				foreach ($idpgroup['user_groups'] as $usrgrp) {
					$user_groups[] = ['userdirectory_idpgroupid' => $idpgroupid] + $usrgrp;
				}
			}

			if ($user_groups) {
				$user_groupids = DB::insertBatch('userdirectory_usrgrp', $user_groups);
			}

			// Set IDs for audit log.
			foreach ($userdirectoryids as $userdirectoryid) {
				foreach ($userdirectories[$userdirectoryid]['provision_groups'] as &$group) {
					$group['userdirectory_idpgroupid'] = array_shift($idpgroupids);
					$group['user_groups'] = array_map(function ($usrgrp) use (&$user_groupids) {
						return $usrgrp + ['userdirectory_usrgrpid' => array_shift($user_groupids)];
					}, $group['user_groups']);
				}
				unset($group);
			}
		}

		$userdirectories = array_values($userdirectories);
	}

	private static function getValidationRules(string $method = 'create'): array {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'idp_type' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [IDP_TYPE_LDAP, IDP_TYPE_SAML])],
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'name')],
			'provision_status' =>	['type' => API_INT32, 'in' => implode(',', [JIT_PROVISIONING_DISABLED, JIT_PROVISIONING_ENABLED]), 'default' => DB::getDefault('userdirectory', 'provision_status')],
			'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory', 'description'), 'length' => DB::getFieldLength('userdirectory', 'description')],
			'host' =>				['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory_ldap', 'host')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'port' =>				['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_PORT, 'flags' => API_REQUIRED | API_NOT_EMPTY],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'base_dn' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory_ldap', 'base_dn')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'bind_dn' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory_ldap', 'bind_dn')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'bind_password' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory_ldap', 'bind_password')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'search_attribute' =>	['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory_ldap', 'search_attribute')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'start_tls' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_INT32, 'in' => implode(',', [ZBX_AUTH_START_TLS_OFF, ZBX_AUTH_START_TLS_ON])],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'search_filter' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory_ldap', 'search_filter')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'group_basedn' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory_ldap', 'group_basedn')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'group_name' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory_ldap', 'group_name')],
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory_saml', 'group_name')]
			]],
			'user_username' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory_ldap', 'user_username')],
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory_saml', 'user_username')]
			]],
			'user_lastname' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory_ldap', 'user_lastname')],
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory_saml', 'user_lastname')]
			]],
			'group_member' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory_ldap', 'group_member')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'group_filter' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory_ldap', 'group_filter')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'group_membership' =>	['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory_ldap', 'group_membership')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'idp_entityid' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory_saml', 'idp_entityid')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'sso_url' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory_saml', 'sso_url')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'slo_url' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory_saml', 'slo_url')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'sp_entityid' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory_saml', 'sp_entityid')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'nameid_format' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory_saml', 'nameid_format')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'username_attribute' =>	['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory_saml', 'username_attribute')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'sign_messages' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_INT32, 'in' => implode(',', ['0', '1']), 'default' => DB::getDefault('userdirectory_saml', 'sign_messages')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'sign_assertions' =>	['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_INT32, 'in' => implode(',', ['0', '1']), 'default' => DB::getDefault('userdirectory_saml', 'sign_assertions')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'sign_authn_requests' => ['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_INT32, 'in' => implode(',', ['0', '1']), 'default' => DB::getDefault('userdirectory_saml', 'sign_authn_requests')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'sign_logout_requests' => ['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_INT32, 'in' => implode(',', ['0', '1']), 'default' => DB::getDefault('userdirectory_saml', 'sign_logout_requests')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'sign_logout_responses' => ['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_INT32, 'in' => implode(',', ['0', '1']), 'default' => DB::getDefault('userdirectory_saml', 'sign_logout_responses')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'encrypt_nameid' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_INT32, 'in' => implode(',', ['0', '1']), 'default' => DB::getDefault('userdirectory_saml', 'encrypt_nameid')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'encrypt_assertions' => ['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_INT32, 'in' => implode(',', ['0', '1']), 'default' => DB::getDefault('userdirectory_saml', 'encrypt_assertions')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'scim_status' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_INT32, 'in' => implode(',', ['0', '1']), 'default' => DB::getDefault('userdirectory_saml', 'scim_status')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'scim_token' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_STRING_UTF8, 'default' => DB::getDefault('userdirectory_saml', 'scim_token')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'provision_media' =>	['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['mediatypeid', 'attribute']], 'fields' => [
				'name' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory_media', 'name')],
				'mediatypeid' =>		['type' => API_ID, 'flags' => API_REQUIRED | API_NOT_EMPTY],
				'attribute' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory_media', 'attribute')]
			]],
			'provision_groups' =>	['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'fields' => [
				'is_fallback' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [GROUP_MAPPING_REGULAR, GROUP_MAPPING_FALLBACK])],
				'fallback_status' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [GROUP_MAPPING_FALLBACK_OFF, GROUP_MAPPING_FALLBACK_ON])],
				'name' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory_idpgroup', 'is_fallback')],
				'roleid' =>				['type' => API_ID, 'flags' => API_REQUIRED | API_NOT_EMPTY],
				'user_groups' =>		['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'uniq' => [['usrgrpid']], 'fields' => [
					'usrgrpid' =>		['type' => API_ID, 'flags' => API_REQUIRED]
				]]
			]]
		]];

		if ($method === 'update') {
			// Make all fields optional and remove default values.
			foreach ($api_input_rules['fields'] as &$field) {
				if (array_key_exists('rules', $field)) {
					foreach ($field['rules'] as &$rule) {
						if (array_key_exists('flags', $rule) && API_REQUIRED & $rule['flags']) {
							$rule['flags'] &= ~API_REQUIRED;
						}
						unset($rule['default']);
					}
					unset($rule);
				}
				else {
					if (array_key_exists('flags', $field) && API_REQUIRED & $field['flags']) {
						$field['flags'] &= ~API_REQUIRED;
					}
					unset($field['default']);
				}
			}
			unset($field);

			$api_input_rules['fields']['userdirectoryid'] = ['type' => API_ID, 'flags' => API_REQUIRED];
		}

		return $api_input_rules;
	}
}
