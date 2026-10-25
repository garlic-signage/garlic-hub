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

namespace App\Framework\Utils\Datatable\Results;

/**
 * Just a simple storage container for a table header field
*/
class HeaderField
{
	protected string $name = '';
	protected bool $sortable = false;

	protected bool $skipTranslation = false;
	protected string $specificLangModule = '';

	public function isSortable(): bool
	{
		return $this->sortable;
	}

	public function sortable(bool $sortable): static
	{
		$this->sortable = $sortable;
		return $this;
	}

	public function skipTranslation(bool $translated): static
	{
		$this->skipTranslation = $translated;
		return $this;
	}

	public function shouldSkipTranslation(): bool
	{
		return $this->skipTranslation;
	}

	public function useSpecificLangModule(string $langModule): static
	{
		$this->specificLangModule = $langModule;
		return $this;
	}

	public function hasSpecificLangModule(): bool
	{
		return !empty($this->specificLangModule);
	}

	public function getSpecificLanguageModule(): string
	{
		return $this->specificLangModule;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function setName(string $name): static
	{
		$this->name = $name;
		return $this;
	}
}
