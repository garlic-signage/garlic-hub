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

namespace Tests\Unit\Framework\Utils\Datatable;

use App\Framework\Utils\Datatable\UrlBuilder;
use Error;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class UrlBuilderTest extends TestCase
{
	private UrlBuilder $urlBuilder;

	protected function setUp(): void
	{
		parent::setUp();
		$this->urlBuilder = (new UrlBuilder())
			->setSite('/playlists')
			->setPage(2)
			->setSortColumn('created')
			->setSortOrder('DESC')
			->setElementsPerPage(25);
	}

	#[Group('units')]
	public function testBuildFilterUrlWithoutAdditionalParameters(): void
	{
		static::assertSame('/playlists?elements_page=2&sort_column=created&sort_order=DESC&elements_per_page=25', $this->urlBuilder->buildFilterUrl());
		static::assertFalse($this->urlBuilder->hasAdditionalUrlParameters());
	}

	#[Group('units')]
	public function testTheSettersAreChainable(): void
	{
		static::assertSame($this->urlBuilder, $this->urlBuilder->setSite('/x')->setPage(1)->setSortColumn('a')->setSortOrder('ASC')->setElementsPerPage(5));
		static::assertSame($this->urlBuilder, $this->urlBuilder->addAdditionalUrlParameter('a', 'b')->clearAdditionalUrlParameters());
	}

	#[Group('units')]
	public function testBuildFilterUrlWithAdditionalParameters(): void
	{
		$this->urlBuilder->addAdditionalUrlParameter('name', 'Anna')->addAdditionalUrlParameter('status', '3');

		static::assertTrue($this->urlBuilder->hasAdditionalUrlParameters());
		static::assertSame(
			'/playlists?elements_page=2&sort_column=created&sort_order=DESC&elements_per_page=25&name=Anna&status=3',
			$this->urlBuilder->buildFilterUrl()
		);
	}

	#[Group('units')]
	public function testClearAdditionalUrlParameters(): void
	{
		$this->urlBuilder->addAdditionalUrlParameter('name', 'Anna')->clearAdditionalUrlParameters();

		static::assertFalse($this->urlBuilder->hasAdditionalUrlParameters());
		static::assertSame('/playlists?elements_page=2&sort_column=created&sort_order=DESC&elements_per_page=25', $this->urlBuilder->buildFilterUrl());
	}

	/**
	 * Values are encoded for the query, a value can not start another parameter.
	 */
	#[Group('units')]
	public function testSpecialCharactersAreEncoded(): void
	{
		$this->urlBuilder->setSortColumn('a b&c=d')->addAdditionalUrlParameter('name', 'Anna & Bert=<x>#1');

		$url = $this->urlBuilder->buildFilterUrl();

		static::assertSame(
			'/playlists?elements_page=2&sort_column=a+b%26c%3Dd&sort_order=DESC&elements_per_page=25&name=Anna+%26+Bert%3D%3Cx%3E%231',
			$url
		);
		parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
		static::assertSame('a b&c=d', $query['sort_column']);
		static::assertSame('Anna & Bert=<x>#1', $query['name']);
	}

	#[Group('units')]
	public function testAnAdditionalParameterReplacesAStandardOne(): void
	{
		$this->urlBuilder->addAdditionalUrlParameter('sort_order', 'asc');

		static::assertStringContainsString('sort_order=asc', $this->urlBuilder->buildFilterUrl());
		static::assertStringNotContainsString('sort_order=DESC', $this->urlBuilder->buildFilterUrl());
	}

	#[Group('units')]
	public function testAnAdditionalParameterIsSetAgainByTheSameKey(): void
	{
		$this->urlBuilder->addAdditionalUrlParameter('name', 'Anna')->addAdditionalUrlParameter('name', 'Berta');

		static::assertStringEndsWith('&name=Berta', $this->urlBuilder->buildFilterUrl());
	}

	#[Group('units')]
	public function testTheSiteIsEmptyByDefault(): void
	{
		$url = (new UrlBuilder())->setPage(1)->setSortColumn('a')->setSortOrder('ASC')->setElementsPerPage(10)->buildFilterUrl();

		static::assertSame('?elements_page=1&sort_column=a&sort_order=ASC&elements_per_page=10', $url);
	}

	/**
	 * Every part of the URL has to be set before: there is no default.
	 */
	#[Group('units')]
	public function testWithoutThePartsTheUrlCanNotBeBuilt(): void
	{
		$this->expectException(Error::class);

		(new UrlBuilder())->setSite('/x')->buildFilterUrl();
	}
}
