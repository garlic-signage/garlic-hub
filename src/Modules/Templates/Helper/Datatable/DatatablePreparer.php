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

namespace App\Modules\Templates\Helper\Datatable;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\Datatable\AbstractDatatablePreparer;
use App\Framework\Utils\Datatable\PrepareService;
use App\Framework\Utils\Datatable\Results\HeaderField;
use App\Modules\Templates\Services\AclValidator;
use DateMalformedStringException;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Class DatatablePreparer
 *
 * Extends the AbstractDatatablePreparer to process and organize data for use in datatables. This class
 * handles the preparation of table body and player context menus with necessary translations, formatting,
 * and element mapping.
 */
class DatatablePreparer extends AbstractDatatablePreparer
{
	private array $usedTemplates = [];

	public function __construct(PrepareService $prepareService, private readonly AclValidator $aclValidator, Parameters $parameters)
	{
		parent::__construct('templates', $prepareService, $parameters);
	}

	/**
	 * @param array<int,bool> $usedTemplates
	 */
	public function setUsedTemplates(array $usedTemplates): static
	{
		$this->usedTemplates = $usedTemplates;
		return $this;
	}


	/**
	 * This method is cringe, but I do not have a better idea without starting over engineering
	 *
	 * @param list<array<string,mixed>> $currentFilterResults
	 * @param list<HeaderField> $fields
	 * @param int $currentUID
	 * @return list<array<string,mixed>>
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws DateMalformedStringException
	 */
	public function prepareTableBody(array $currentFilterResults, array $fields, int $currentUID): array
	{
		$body = [];
		foreach($currentFilterResults as $template)
		{
			$list            = [];
			$list['UNIT_ID'] = $template['template_id'];
			foreach($fields as $HeaderField)
			{
				$innerKey = $HeaderField->getName();

				$resultElements = [];
				$resultElements['CONTROL_NAME_BODY'] = $innerKey;
				switch ($innerKey)
				{
					case 'UID':
						$resultElements['is_UID'] = $this->prepareService->getBodyPreparer()->formatUID($template['UID'], $template['username']);
						break;
					case 'type':
						$resultElements['is_text'] = $this->prepareService->getBodyPreparer()->formatText($this->translator->translateArrayForOptions('types_selects', 'templates')[$template['type']]);
						break;
					case 'used':
						$count = 0;
						if (array_key_exists($template['template_id'], $this->usedTemplates))
							$count  = $this->usedTemplates[$template['template_id']];

						$resultElements['is_text'] = $this->prepareService->getBodyPreparer()->formatText((string) $count);

						break;
					case 'name':
						$resultElements['is_link'] = $this->prepareService->getBodyPreparer()->formatLink(
							$template[$innerKey],
							$this->translator->translate('composer', 'templates'). ': '.$template[$innerKey],
							'/templates/composer/' . $template['template_id'],
							(string) $template['template_id']
						);
						break;
					default:
						$resultElements['is_text'] = $this->prepareService->getBodyPreparer()->formatText($template[$innerKey]);
						break;
				}
				$list['elements_result_element'][] = $resultElements;
				if ($template['UID'] == $currentUID || $this->aclValidator->isTemplateEditable($currentUID, $template))
				{
					$list['has_action'] = [
						$this->prepareService->getBodyPreparer()->formatAction(
							$this->translator->translate('composer', 'templates'),
							'templates/composer/'.$template['template_id'],
							'composer', (string) $template['template_id'], 'columns'),
						$this->prepareService->getBodyPreparer()->formatAction(
							$this->translator->translate('settings', 'main'),
							'templates/settings/'.$template['template_id'],
							'edit', (string) $template['template_id'], 'gear')
					];
					if (!array_key_exists($template['template_id'], $this->usedTemplates) &&
						$this->aclValidator->isAllowedToDeleteTemplate($currentUID, $template))
					{
						$deleteText = $this->translator->translate('confirm_delete', 'templates');
						$list['has_delete'] = $this->prepareService->getBodyPreparer()->formatActionDelete(
							$this->translator->translate('delete', 'main'),
							sprintf($deleteText, $template['name']),
							(string) $template['template_id'],
							'delete-template'
						);
					}

				}
			}
			$body[] = $list;
		}

		return $body;
	}



}