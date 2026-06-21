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

namespace App\Framework\Utils\FormParameters;

use App\Framework\Core\Sanitizer;
use App\Framework\Core\Session;
use App\Framework\Exceptions\ModuleException;

/**
 * The BaseFilterParameters class serves as an abstract foundational class for defining and managing
 * filtering parameters, specifically tailored for handling user requests. It includes functionalities
 * that involve parsing and storing parameters in session, ensuring parameters are structured
 * correctly, and facilitating parameter-related operations such as sorting and pagination used in datatables.
 *
 * This class is designed to be used within larger systems that require a consistent approach
 * to managing and applying filter parameters. The implementation supports flexible configuration
 * for default parameter values and ensures customizable behavior for extending classes.
 *
 */
abstract class BaseFilterParameters extends BaseParameters implements BaseFilterParametersInterface
{
	protected readonly string $sessionStoreKey;
	protected readonly Session $session;

	/** @var array<string, array{scalar_type: ScalarType, default_value: mixed, parsed: bool}> */
	protected array $defaultParameters = [
		self::PARAMETER_ELEMENTS_PER_PAGE  => ['scalar_type'  => ScalarType::INT,       'default_value' => 10,    'parsed' => false],
		self::PARAMETER_ELEMENTS_PAGE      => ['scalar_type'  => ScalarType::INT,       'default_value' => 1,     'parsed' => false],
		self::PARAMETER_SORT_COLUMN        => ['scalar_type'  => ScalarType::STRING,    'default_value' => '',    'parsed' => false],
		self::PARAMETER_SORT_ORDER         => ['scalar_type'  => ScalarType::STRING,    'default_value' => 'ASC', 'parsed' => false]
	];

	public function __construct(string $moduleName, Sanitizer $sanitizer, Session $session, string $session_key_store = '')
	{
		$this->session           = $session;
		$this->sessionStoreKey   = $session_key_store;

		parent::__construct($moduleName, $sanitizer);
	}

	/**
	 * @throws ModuleException
	 */
	public function setParameterDefaultValues(string $defaultSortColumn): static
	{
		$this->setDefaultForParameter(self::PARAMETER_SORT_COLUMN, $defaultSortColumn);
		return $this;
	}

	/**
	 * since we are using ELEMENTS_PAGE and ELEMENTS_PER_PAGE for the limit clause in MySQL
	 * this method sets both values to 0 (zero).
	 * That means, there will be no LIMIT clause in the SQL query
	 *
	 * @throws ModuleException
	 */
	public function setElementsParametersToNull(): static
	{
		if ($this->hasParameter(self::PARAMETER_ELEMENTS_PAGE))
			$this->setValueOfParameter(self::PARAMETER_ELEMENTS_PAGE, 0);

		if ($this->hasParameter(self::PARAMETER_ELEMENTS_PER_PAGE))
			$this->setValueOfParameter(self::PARAMETER_ELEMENTS_PER_PAGE, 0);

		return $this;
	}

	/**
	 * checks if parameters are stored in session from previous visit
	 * iterates over all parameters and sets the values
	 *
	 * @throws  ModuleException
	 */
	public function parseInputFilterAllUsers(): static
	{
		if ($this->storedParametersInSessionExists())
		{
			$storedParameters        = $this->getStoredSearchParamsFromSession();
			$this->currentParameters = $storedParameters;
		}

		$this->parseInputAllParameters();
		if ($this->hasFilterParametersChanged($storedParameters))
		{
			$this->setValueOfParameter(self::PARAMETER_ELEMENTS_PAGE, 1);
		}

		$this->storeSearchParamsToSession($this->currentParameters);

		return $this;
	}

	public function hasSessionKeyStore(): bool
	{
		return !empty($this->sessionStoreKey);
	}

	public function addCompany(): void
	{
		$this->addParameter(self::PARAMETER_COMPANY_ID, ScalarType::STRING, '');
	}

	/**
	 * @param array<string,mixed> $search
	 */
	protected function storeSearchParamsToSession(array $search): static
	{
		if ($this->hasSessionKeyStore())
			$this->session->set($this->sessionStoreKey, $search);

		return $this;
	}

	protected function storedParametersInSessionExists(): bool
	{
		if ($this->hasSessionKeyStore())
		{
			return ($this->session->exists($this->sessionStoreKey));
		}
		return false;
	}

	/**
	 * @return array<string, array{scalar_type: ScalarType, default_value: mixed, parsed: bool}>
	 */
	protected function getStoredSearchParamsFromSession(): array
	{
		$ret =  $this->session->get($this->sessionStoreKey);
		if (!is_array($ret))
			return [];

		return $ret;
	}

	/**
	 * @param array<string, array{scalar_type: ScalarType, default_value: mixed, parsed: bool, value?:mixed}> $storedParameters
	 */
	protected function hasFilterParametersChanged(array $storedParameters): bool
	{
		if (empty($storedParameters))
		{
			return false;
		}

		// compare except of pagination and sort
		foreach ($this->currentParameters as $key => $param)
		{
			// Ignore pagination and sort
			if (in_array($key, [
				self::PARAMETER_ELEMENTS_PAGE,
				self::PARAMETER_ELEMENTS_PER_PAGE,
				self::PARAMETER_SORT_COLUMN,
				self::PARAMETER_SORT_ORDER
			], true))
			{
				continue;
			}

			// if something changed
			if (!isset($storedParameters[$key]) ||
				!isset($param['value']) ||
				!isset($storedParameters[$key]['value']) ||
				$storedParameters[$key]['value'] !== $param['value'])
			{
				return true;
			}
		}

		return false;
	}

}