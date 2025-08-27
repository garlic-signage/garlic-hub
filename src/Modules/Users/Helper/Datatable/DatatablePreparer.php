<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2025 Nikolaos Sagiadinos <garlic@saghiadinos.de>
 This file is part of the garlic-hub source code

 This program is free software: you can redistribute it and/or modify
 it under the terms of the GNU Affero General Public License, version 3,
 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU Affero General Public License for more details.

 You should have received a copy of the GNU Affero General Public License
 along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
declare(strict_types=1);

namespace App\Modules\Users\Helper\Datatable;

use App\Framework\Core\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\Datatable\AbstractDatatablePreparer;
use App\Framework\Utils\Datatable\PrepareService;
use App\Framework\Utils\Datatable\Results\HeaderField;
use App\Modules\Users\Services\AclValidator;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * DatatablePreparer is responsible for preparing table data based on
 * filter results and header fields. It extends the AbstractDatatablePreparer
 * class to incorporate core datatable preparation functionalities while
 * adding custom logic for field formatting, user actions, and validations.
 */
class DatatablePreparer extends AbstractDatatablePreparer
{
	private AclValidator $aclValidator;

	public function __construct(PrepareService $prepareService, AclValidator $aclValidator, Parameters $parameters)
	{
		$this->aclValidator = $aclValidator;
		parent::__construct('users', $prepareService, $parameters);
	}

	/**
	 * This method is cringe, but I do not have a better idea without starting over engineering
	 *
	 * @param list<array<string,mixed>> $currentFilterResults
	 * @param list<HeaderField> $fields
	 * @return list<array<string,mixed>>
	 * @throws CoreException
	 * @throws Exception
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 */
	public function prepareTableBody(array $currentFilterResults, array $fields, int $currentUID): array
	{
		$body = [];
		foreach($currentFilterResults as $user)
		{
			$list = [];
			$list['UNIT_ID'] = $user['UID'];
			foreach ($fields as $HeaderField)
			{
				$innerKey = $HeaderField->getName();

				$resultElements = [];
				$resultElements['CONTROL_NAME_BODY'] = $innerKey;
				switch ($innerKey)
				{
					case 'username':
						if ($this->aclValidator->getConfig()->getEdition() === Config::PLATFORM_EDITION_EDGE)
							$resultElements['is_text'] = $this->prepareService->getBodyPreparer()->formatText($user['username']);
						else
						{
							$resultElements['is_link'] = $this->prepareService->getBodyPreparer()->formatLink(
								$user['username'],
								$this->translator->translate('edit', 'main'),
								'user/' . $user['UID'],
								'username_' . $user['UID']
							);

						}
						break;
					case 'status':
						$resultElements['is_text'] = $this->prepareService->getBodyPreparer()->formatText(
							$this->translator->translateArrayForOptions('status_selects', 'users')[$user['status']]
						);
						break;
					default:
						$resultElements['is_text'] = $this->prepareService->getBodyPreparer()->formatText($user[$innerKey]);
						break;
				}
				$list['elements_result_element'][] = $resultElements;

				if (
					$user['UID'] == $currentUID ||	$this->aclValidator->isSimpleAdmin($currentUID))
				{
					$list['has_action'] = [

						[
							'LANG_ACTION' => $this->translator->translate('edit_settings', 'users'),
							'LINK_ACTION' => 'users/edit/' .  $user['UID'],
							'ACTION_NAME' => 'edit',
							'ACTION_ICON_CLASS' => 'pencil'
						]
					];
					if ($this->aclValidator->isModuleAdmin($currentUID) && $user['status'] == 0)
					{
						$list['has_delete'] = [
							'LINK_DELETE_ACTION' => 'users/?delete_id=' . $user['UID'],
							'LANG_CONFIRM_DELETE' => $this->translator->translate('confirm_delete', 'users'),
							'DELETE_ID' => $user['UID']
						];
					}

				}
			}
			$body[] = $list;
		}
		return $body;
	}
}