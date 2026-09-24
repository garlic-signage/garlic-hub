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
use App\Framework\Exceptions\ModuleException;

/**
 * The BaseParameters class provides functionality to manage parameter configuration,
 * including adding, removing, validating, sanitizing and retrieving parameters and their values.
 * This class is designed to be extended and can include custom hooks for preprocessing and postprocessing
 * of parameter values.
 *
 * Features include:
 * - Adding parameters with detailed type constraints and optional default values.
 * - Removing single or multiple parameters by name.
 * - Retrieving and setting parameter values, including defaults.
 * - Parsing parameter values, leveraging sanitization methods.
 * - Detecting and managing user input for parameter processing.
 * - Support for custom hooks before and after parameter parsing.
 *
 * This class includes support for enforcing scalar data types via `ScalarType` and sanitization logic
 * integrated with the `Sanitizer` utility.
 *
 * @throws ModuleException Various methods may throw ModuleException for invalid operations
 *                         such as unsupported scalar types or missing parameters.
 */
abstract class BaseParameters
{
	public const string PARAMETER_UID = 'UID';

	protected readonly string $moduleName;
	protected readonly Sanitizer $sanitizer;
	/** @var array<string, array{scalar_type: ScalarType, default_value: mixed, parsed: bool, value?:mixed}> */
	protected array $currentParameters;
	/** @var array<string,mixed>  */
	protected array $userInputs;

	public function __construct(string $moduleName, Sanitizer $sanitizer)
	{
		$this->moduleName = $moduleName;
		$this->sanitizer  = $sanitizer;
	}

	/**
	 * @param array<string,mixed> $userInputs
	 * @return $this
	 */
	public function setUserInputs(array $userInputs): static
	{
		$this->userInputs = $userInputs;
		return $this;
	}

	public function addOwner(): void
	{
		$this->addParameter(self::PARAMETER_UID, ScalarType::INT, 0);
	}

	public function addParameter(string $parameter_name, ScalarType $scalarType, mixed $default_value = null): static
	{
		$this->currentParameters[$parameter_name] = ['scalar_type' => $scalarType, 'default_value' => $default_value, 'parsed' => false];
		return $this;
	}

	/**
	 * @param string $parameter_name
	 * @return  $this
	 */
	public function removeParameter(string $parameter_name): static
	{
		if (array_key_exists($parameter_name, $this->currentParameters))
		{
			unset ($this->currentParameters[$parameter_name]);
		}

		return $this;
	}

	/**
	 * @param string[] $parameterNames
	 */
	public function removeParameters(array $parameterNames): static
	{
		foreach($parameterNames as $parameter)
		{
			$this->removeParameter($parameter);
		}

		return $this;
	}

	/**
	 * @throws ModuleException
	 */
	public function getValueOfParameter(string $parameter_name): mixed
	{
		if (!$this->hasParameter($parameter_name))
			throw new ModuleException($this->moduleName, 'A parameter with name: ' . $parameter_name . ' is not found.');

		if (!array_key_exists('value', $this->currentParameters[$parameter_name]))
			throw new ModuleException($this->moduleName, 'A value for parameter with name: ' . $parameter_name . ' is not set.');

		return $this->currentParameters[$parameter_name]['value'];
	}

	/**
	 * @phpstan-param int|string|array<string,mixed> $value
	 * @throws ModuleException
	 */
	public function setValueOfParameter(string $parameter_name, int|string|array $value): static
	{
		if (!$this->hasParameter($parameter_name))
			throw new ModuleException($this->moduleName, 'A parameter with name: ' . $parameter_name . ' is not found.');

		$this->currentParameters[$parameter_name]['value'] = $value;
		return $this;
	}

	/**
	 * @phpstan-param int|string|array<string,mixed> $defaultValue
	 * @throws ModuleException
	 */
	public function setDefaultForParameter(string $parameterName, int|string|array $defaultValue): static
	{
		if (!$this->hasParameter($parameterName))
			throw new ModuleException($this->moduleName, 'A parameter with name: ' . $parameterName . ' is not found.');

		$this->currentParameters[$parameterName]['default_value'] = $defaultValue;
		return $this;
	}


	public function hasParameter(string $parameterName): bool
	{
		return (array_key_exists($parameterName, $this->currentParameters));
	}

	/**
	 * @return array<string, array{scalar_type: ScalarType, default_value: mixed, parsed: bool, value?:mixed}>
	 */
	public function getInputParametersArray(): array
	{
		return $this->currentParameters;
	}

	/**
	 * @return string[]|int[]|array<string,mixed>
	 */
	public function getInputValuesArray(): array
	{
		return array_column($this->currentParameters, 'value');
	}

	/**
	 * @return string[]
	 */
	public function getInputParametersKeys(): array
	{
		return array_keys($this->currentParameters);
	}

	/**
	 * async calls can use iterating over parameters and parse them directly,
	 * without using the session store
	 *
	 * if you want to use the session store, use the method above
	 * @see BaseFilterParameters::parseInputFilterAllUsers()
	 *
	 * @throws ModuleException
	 */
	public function parseInputAllParameters(): static
	{
		foreach(array_keys($this->currentParameters) as $parameterName)
		{
			$this->parseInputFilterByName($parameterName);
		}
		return $this;
	}

	/**
	 * @throws ModuleException
	 */
	public function getDefaultValueOfParameter(string $parameter_name): mixed
	{
		if (!array_key_exists($parameter_name, $this->currentParameters))
		{
			throw new ModuleException($this->moduleName, 'A parameter with name: ' . $parameter_name . ' is not found.');
		}
		return $this->currentParameters[$parameter_name]['default_value'];
	}

	public function getModuleName(): string
	{
		return $this->moduleName;
	}

	/**
	 * @return array<string, array{scalar_type: ScalarType, default_value: mixed, parsed: bool, value?:mixed}>
	 */
	public function getCurrentParameters(): array
	{
		return $this->currentParameters;
	}

	/**
	 * @throws  ModuleException
	 */
	public function parseInputFilterByName(string $parameterName): static
	{
		if (!array_key_exists($parameterName, $this->currentParameters))
			throw new ModuleException($this->moduleName, 'A parameter with name: ' . $parameterName . ' is not found.');

		if (isset($this->userInputs[$parameterName]) &&
				isset($this->currentParameters[$parameterName]['value']) &&
					$this->userInputs[$parameterName] != $this->currentParameters[$parameterName]['value'])
			$this->currentParameters[$parameterName]['parsed'] = false;

		// don't parse them twice
		if ($this->currentParameters[$parameterName]['parsed'] === true)
			return $this;

		if (array_key_exists($parameterName, $this->userInputs))
			$parameterValue = $this->userInputs[$parameterName];
		else
			$parameterValue = $this->currentParameters[$parameterName]['default_value'];

		$parameter = $this->beforeParseHook($parameterName, $this->currentParameters[$parameterName]);

		$value = match ($parameter['scalar_type'])
		{
			ScalarType::INT            => $this->sanitizer->int($parameterValue),
            ScalarType::RAW            => (string) $parameterValue,
			ScalarType::FLOAT          => $this->sanitizer->float($parameterValue),
			ScalarType::STRING         => $this->sanitizer->string($parameterValue),
			ScalarType::NUMERIC_ARRAY  => $this->sanitizer->intArray($parameterValue),
			ScalarType::STRING_ARRAY   => $this->sanitizer->stringArray($parameterValue),
			ScalarType::HTML_STRING    => $this->sanitizer->html($parameterValue),
			ScalarType::JSON           => $this->sanitizer->jsonArray($parameterValue),
			ScalarType::JSON_HTML      => $this->sanitizer->jsonHTML($parameterValue),
			ScalarType::MEDIAPOOL_FILE => $this->sanitizer->string('hidden_' . $parameterValue),
			ScalarType::BOOLEAN        => $this->sanitizer->bool($parameterValue)
		};

		$parameter['value'] = $value;
		$parameter['parsed'] = true;

		$this->currentParameters[$parameterName] = $this->afterParseHook($parameterName, $parameter);
		return $this;
	}

	/**
	 * Hook that can be overwritten in your class.
	 * Will be called after parsing
	 *
	 * @param array{scalar_type: ScalarType, default_value: mixed, parsed: bool, value?:mixed} $parameter
	 * @return array{scalar_type: ScalarType, default_value: mixed, parsed: bool, value?:mixed}
	 */
	protected function afterParseHook(string $parameter_name, array $parameter): array
	{
		return $parameter;
	}

	/**
	 * Hook that can be overwritten in your class
	 * Will be called before parsing, but if the parameter has not already been parsed
	 *
	 * @param  array{scalar_type: ScalarType, default_value: mixed, parsed: bool, value?:mixed} $parameter
	 * @return array{scalar_type: ScalarType, default_value: mixed, parsed: bool, value?:mixed}
	 */
	protected function beforeParseHook(string $parameterName, array $parameter): array
	{
		return $parameter;
	}
}