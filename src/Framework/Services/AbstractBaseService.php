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

namespace App\Framework\Services;

use Psr\Log\LoggerInterface;

/**
 * AbstractBaseService serves as a foundational class providing shared functionality
 * to manage and interact with logging, unique identifier (UID), and error messages.
 *
 * It includes capabilities such as adding and retrieving error messages, checking
 * error states, and maintaining a unique identifier.
 */
abstract class AbstractBaseService
{
	protected readonly LoggerInterface $logger;
	protected int $UID;
	/** @var string[]  */
	protected array $errorMessages = [];

	public function __construct(LoggerInterface $logger)
	{
		$this->logger = $logger;
	}

	public function setUID(int $UID): AbstractBaseService
	{
		$this->UID = $UID;
		return $this;
	}

	/**
	 * @return string[]
	 */
	public function getErrorMessages(): array
	{
		return $this->errorMessages;
	}

	public function getErrorMessagesAsString(): string
	{
		return implode(' | ', $this->errorMessages);
	}

	public function hasErrorMessages(): bool
	{
		return !empty($this->errorMessages);
	}

	protected function addErrorMessage(string $message): void
	{
		$this->errorMessages[] = $message;
	}

}