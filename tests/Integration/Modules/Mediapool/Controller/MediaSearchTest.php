<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace Tests\Integration\Modules\Mediapool\Controller;

use App\Framework\Exceptions\AccessDeniedException;
use App\Modules\Mediapool\Controller\MediaController;
use App\Modules\Mediapool\Services\MediaService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * The search of the media list /async/mediapool/media/search over all directories the user may read.
 * Media: 1111 urlaub.jpg and 2222 film.mp4 of anna in Fotos (8), 3333 geheim.png of bernd in Privat (11),
 * 4444 teilen.png of bernd in Geteilt (12, public), 5555 logo.jpg in the public images (2), 6666 is deleted.
 */
#[Group('acl')]
class MediaSearchTest extends ControllerTestCase
{
	private const string PHOTO   = '11111111-1111-4111-8111-111111111111';
	private const string PRIVATE = '33333333-3333-4333-8333-333333333333';

	private MediaController $controller;

	protected function setUp(): void
	{
		parent::setUp();
		$this->controller = $this->container->get(MediaController::class);
	}

	/**
	 * @param array<string,mixed> $query
	 * @return list<array<string,mixed>>
	 */
	private function search(array $query): array
	{
		$response = $this->controller->search($this->request('GET', [], $query), $this->newResponse());
		$this->assertSuccess($response);

		return $this->json($response)['media_list'];
	}

	/**
	 * @param list<array<string,mixed>> $results
	 * @return list<string>
	 */
	private static function names(array $results): array
	{
		$names = array_column($results, 'filename');
		sort($names);

		return $names;
	}

	public function testTheTagsTheNameAndTheDescriptionAreSearched(): void
	{
		$this->actingAs(2);
		$this->connection->executeStatement("UPDATE mediapool_files SET tags = ',Strand,Meer,' WHERE filename = 'urlaub.jpg'");
		$this->connection->executeStatement("UPDATE mediapool_files SET media_description = 'Ein Film vom Meer' WHERE filename = 'film.mp4'");

		static::assertSame(['film.mp4', 'urlaub.jpg'], self::names($this->search(['term' => 'meer'])));
		static::assertSame(['urlaub.jpg'], self::names($this->search(['term' => 'Strand'])));
		static::assertSame(['film.mp4'], self::names($this->search(['term' => 'Film vom'])));
		// a tag is found as part, too: the commas around the tags are for an exact search later
		static::assertSame(['urlaub.jpg'], self::names($this->search(['term' => 'tran'])));
	}

	public function testAResultKnowsItsDirectoryAndWhetherTheUserMayEditIt(): void
	{
		$this->actingAs(2);

		$results = $this->search(['term' => '.png']);

		// the public directory of bernd: anna reads it, she does not edit it
		static::assertSame(['teilen.png'], self::names($results));
		static::assertSame('/ bernd / Geteilt', $results[0]['path']);
		static::assertFalse($results[0]['editable']);
		static::assertStringContainsString('sig=', $results[0]['urls']['thumbnail']);

		$own = $this->search(['term' => 'urlaub']);
		static::assertSame('/ anna / Fotos', $own[0]['path']);
		static::assertTrue($own[0]['editable']);
	}

	public function testTheFilterOfTheKindWorksLikeInTheList(): void
	{
		$this->actingAs(2);

		static::assertSame(['film.mp4'], self::names($this->search(['term' => 'film', 'filter' => 'videos'])));
		static::assertSame([], self::names($this->search(['term' => 'film', 'filter' => 'images'])));
	}

	public function testAWildcardOfSqlIsSearchedAsItIs(): void
	{
		$this->actingAs(1);

		static::assertSame([], $this->search(['term' => '%%']));
		static::assertSame([], $this->search(['term' => '__']));
	}

	/**
	 * @return array<string,array{array<string,mixed>}>
	 */
	public static function tooShort(): array
	{
		return ['none' => [[]], 'empty' => [['term' => '']], 'one letter' => [['term' => 'a']], 'spaces' => [['term' => '  a  ']], 'a list' => [['term' => ['urlaub']]]];
	}

	/**
	 * @param array<string,mixed> $query
	 */
	#[DataProvider('tooShort')]
	public function testASearchNeedsTwoLetters(array $query): void
	{
		$this->actingAs(2);

		$this->assertError($this->controller->search($this->request('GET', [], $query), $this->newResponse()), 'Search term is missing');
	}

	public function testADirectorySharedWithTheUserIsSearchedOnlyWithHisRole(): void
	{
		$this->actingAs(2);
		$this->connection->executeStatement("INSERT INTO user_vip (UID, vip_name, unit_id) VALUES (2, 'mediapool_viewer', '11')");

		// without the bit of the viewer the share does not count, like for opening the directory
		static::assertSame([], $this->search(['term' => 'geheim']));
	}

	public function testADirectorySharedWithAViewerIsSearched(): void
	{
		$this->connection->executeStatement("INSERT INTO user_acl (UID, acl, module) VALUES (2, 1, 'mediapool')");
		$this->connection->executeStatement("INSERT INTO user_vip (UID, vip_name, unit_id) VALUES (2, 'mediapool_viewer', '11')");
		$this->actingAs(2);

		$results = $this->search(['term' => 'geheim']);

		static::assertSame(['geheim.png'], self::names($results));
		static::assertFalse($results[0]['editable']);
	}

	/**
	 * @return array<string,array{int,bool}> the user and whether he is viewer of Privat (11) of bernd
	 */
	public static function readers(): array
	{
		return [
			'admin'                => [1, false],
			'anna'                 => [2, false],
			'anna, viewer of 11'   => [2, true],
			'bernd'                => [3, false],
		];
	}

	/**
	 * The search must find exactly the media which the user may open one by one: both use their own way to
	 * decide (SQL here, PHP per directory there). A change on one side only shows up here.
	 */
	#[DataProvider('readers')]
	public function testTheSearchFindsWhatTheUserMayOpen(int $UID, bool $isViewer): void
	{
		if ($isViewer)
		{
			$this->connection->executeStatement("INSERT INTO user_acl (UID, acl, module) VALUES (?, 1, 'mediapool')", [$UID]);
			$this->connection->executeStatement("INSERT INTO user_vip (UID, vip_name, unit_id) VALUES (?, 'mediapool_viewer', '11')", [$UID]);
		}
		$this->actingAs($UID);
		$service = $this->container->get(MediaService::class);

		$readable = [];
		foreach ($this->connection->fetchFirstColumn('SELECT media_id FROM mediapool_files WHERE deleted = 0') as $mediaId)
		{
			try
			{
				$service->fetchMedia((string) $mediaId);
				$readable[] = (string) $mediaId;
			}
			catch (AccessDeniedException)
			{
			}
		}
		$found = array_column($service->searchMedia('.'), 'media_id');
		sort($readable);
		sort($found);

		static::assertSame($readable, $found);
		// Privat of bernd: for bernd, the admin and a viewer of it
		static::assertSame($isViewer || $UID !== 2, in_array(self::PRIVATE, $found, true));
		// Fotos of anna is private: not for bernd
		static::assertSame($UID !== 3, in_array(self::PHOTO, $found, true));
	}
}
