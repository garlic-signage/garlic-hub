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

namespace Tests\Integration\Modules\Mediapool\Services;

use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\NotFoundException;
use App\Modules\Mediapool\Services\MediaService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Throwable;
use Tests\Integration\Support\ContainerTestCase;

/**
 * Who may see, change, move, clone and delete which media. Fixtures: the public tree 1 to 6, anna 7
 * with 8 Fotos (media 1, 2 and the deleted 6) and 9 Archiv, bernd 10 with 11 Privat (media 3) and
 * 12 Geteilt (public, media 4). Media 5 is in the public images directory 2.
 */
#[Group('acl')]
class MediaAccessTest extends ContainerTestCase
{
	private const string ANNA_PHOTO   = '11111111-1111-4111-8111-111111111111';
	private const string ANNA_VIDEO   = '22222222-2222-4222-8222-222222222222';
	private const string BERND_PRIVATE = '33333333-3333-4333-8333-333333333333';
	private const string BERND_SHARED  = '44444444-4444-4444-8444-444444444444';
	private const string PUBLIC_LOGO   = '55555555-5555-4555-8555-555555555555';
	private const string ANNA_DELETED  = '66666666-6666-4666-8666-666666666666';

	private MediaService $mediaService;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('mediapool/nodes.sql');
		$this->loadFixture('mediapool/files.sql');
		$this->mediaService = $this->container->get(MediaService::class);
	}

	public function testUserListsTheMediaOfHerOwnDirectory(): void
	{
		$this->actingAs(2);

		static::assertSame(['film.mp4', 'urlaub.jpg'], array_column($this->mediaService->listMedia(8), 'filename'));
		static::assertSame(['urlaub.jpg'], array_column($this->mediaService->listMedia(8, 'image'), 'filename'));
	}

	public function testUserListsThePublicMediaOfOthers(): void
	{
		$this->actingAs(2);

		static::assertSame(['teilen.png'], array_column($this->mediaService->listMedia(12), 'filename'));
		static::assertSame(['logo.jpg'], array_column($this->mediaService->listMedia(2), 'filename'));
	}

	public function testUserMayNotListThePrivateMediaOfOthers(): void
	{
		$this->actingAs(2);

		$this->expectException(AccessDeniedException::class);
		$this->mediaService->listMedia(11);
	}

	public function testAdminListsEverything(): void
	{
		$this->actingAs(1);

		static::assertSame(['geheim.png'], array_column($this->mediaService->listMedia(11), 'filename'));
		static::assertSame(['film.mp4', 'urlaub.jpg'], array_column($this->mediaService->listMedia(8), 'filename'));
	}

	public function testTheListGivesSignedUrlsAndNoPathOutsideTheStorage(): void
	{
		$this->actingAs(2);

		$media = $this->mediaService->listMedia(8, 'image')[0];

		static::assertStringStartsWith('/media.php/orig/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.jpg', $media['urls']['original']);
		static::assertStringContainsString('sig=', $media['urls']['original']);
		static::assertSame('var/storage/mediapool/originals/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.jpg', $media['paths']['original']);
	}

	public function testUserFetchesHerOwnAndPublicMedia(): void
	{
		$this->actingAs(2);

		static::assertSame('urlaub.jpg', $this->mediaService->fetchMedia(self::ANNA_PHOTO)['filename']);
		static::assertSame('teilen.png', $this->mediaService->fetchMedia(self::BERND_SHARED)['filename']);
		static::assertSame('logo.jpg', $this->mediaService->fetchMedia(self::PUBLIC_LOGO)['filename']);
	}

	public function testUserMayNotFetchPrivateMediaOfOthers(): void
	{
		$this->actingAs(2);

		$this->expectException(AccessDeniedException::class);
		$this->mediaService->fetchMedia(self::BERND_PRIVATE);
	}

	public function testDeletedAndUnknownMediaAreNotFound(): void
	{
		$this->actingAs(1);

		foreach ([self::ANNA_DELETED, '00000000-0000-4000-8000-000000000000'] as $mediaId)
		{
			try
			{
				$this->mediaService->fetchMedia($mediaId);
				static::fail('Expected NotFoundException');
			}
			catch (NotFoundException)
			{
				static::addToAssertionCount(1);
			}
		}
	}

	public function testUserMayChangeHerOwnMedia(): void
	{
		$this->actingAs(2);

		static::assertSame(1, $this->mediaService->updateMedia(self::ANNA_PHOTO, 'neu.jpg', 'Beschreibung'));

		static::assertSame('neu.jpg', $this->fieldOf(self::ANNA_PHOTO, 'filename'));
		static::assertSame('Beschreibung', $this->fieldOf(self::ANNA_PHOTO, 'media_description'));
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function mediaOfOthers(): array
	{
		return [
			'private media of bernd' => [self::BERND_PRIVATE],
			'public media of bernd'  => [self::BERND_SHARED],
			'public media of admin'  => [self::PUBLIC_LOGO],
		];
	}

	#[DataProvider('mediaOfOthers')]
	public function testUserMayNotChangeMediaOfOthers(string $mediaId): void
	{
		$this->actingAs(2);
		$before = $this->fieldOf($mediaId, 'filename');

		$this->expectDenied(fn() => $this->mediaService->updateMedia($mediaId, 'gehackt.jpg', 'x'));

		static::assertSame($before, $this->fieldOf($mediaId, 'filename'));
	}

	public function testUserMayDeleteHerOwnMedia(): void
	{
		$this->actingAs(2);

		static::assertSame(1, $this->mediaService->deleteMedia(self::ANNA_VIDEO));

		static::assertSame('1', (string) $this->fieldOf(self::ANNA_VIDEO, 'deleted'));
		static::assertSame(['urlaub.jpg'], array_column($this->mediaService->listMedia(8), 'filename'));
	}

	#[DataProvider('mediaOfOthers')]
	public function testUserMayNotDeleteMediaOfOthers(string $mediaId): void
	{
		$this->actingAs(2);

		$this->expectDenied(fn() => $this->mediaService->deleteMedia($mediaId));

		static::assertSame('0', (string) $this->fieldOf($mediaId, 'deleted'));
	}

	public function testAdminMayChangeAndDeleteEveryMedia(): void
	{
		$this->actingAs(1);

		$this->mediaService->updateMedia(self::BERND_PRIVATE, 'admin.png', '');
		$this->mediaService->deleteMedia(self::BERND_SHARED);

		static::assertSame('admin.png', $this->fieldOf(self::BERND_PRIVATE, 'filename'));
		static::assertSame('1', (string) $this->fieldOf(self::BERND_SHARED, 'deleted'));
	}

	public function testUserMayMoveHerOwnMediaToAnotherOwnDirectory(): void
	{
		$this->actingAs(2);

		static::assertSame(1, $this->mediaService->moveMedia(self::ANNA_PHOTO, 9));

		static::assertSame('9', (string) $this->fieldOf(self::ANNA_PHOTO, 'node_id'));
	}

	/**
	 * @return array<string,array{string,int}>
	 */
	public static function forbiddenMoves(): array
	{
		return [
			'own media into a private directory of bernd' => [self::ANNA_PHOTO, 11],
			'own media into a public directory of bernd'  => [self::ANNA_PHOTO, 12],
			'own media into the public tree'              => [self::ANNA_PHOTO, 2],
			'private media of bernd into an own directory' => [self::BERND_PRIVATE, 9],
		];
	}

	#[DataProvider('forbiddenMoves')]
	public function testUserMayNotMoveMediaWithoutRightsOnBothDirectories(string $mediaId, int $nodeId): void
	{
		$this->actingAs(2);
		$before = $this->fieldOf($mediaId, 'node_id');

		$this->expectDenied(fn() => $this->mediaService->moveMedia($mediaId, $nodeId));

		static::assertSame($before, $this->fieldOf($mediaId, 'node_id'));
	}

	/**
	 * A user may read a public directory, but not take its content away. Moving needs the right to
	 * change the directory the media comes from, not only to read it.
	 */
	#[DataProvider('publicMedia')]
	public function testUserMayNotTakePublicMediaOutOfAForeignDirectory(string $mediaId, string $nodeBefore): void
	{
		$this->actingAs(2);

		$this->expectDenied(fn() => $this->mediaService->moveMedia($mediaId, 9));

		static::assertSame($nodeBefore, (string) $this->fieldOf($mediaId, 'node_id'));
	}

	/**
	 * @return array<string,array{string,string}>
	 */
	public static function publicMedia(): array
	{
		return ['public media of bernd' => [self::BERND_SHARED, '12'], 'public media of admin' => [self::PUBLIC_LOGO, '2']];
	}

	public function testUserClonesHerOwnMedia(): void
	{
		$this->actingAs(2);

		$clone = $this->mediaService->cloneMedia(self::ANNA_PHOTO);

		static::assertNotSame(self::ANNA_PHOTO, $clone['media_id']);
		static::assertSame(2, (int) $this->fieldOf($clone['media_id'], 'UID'));
		static::assertSame('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', $this->fieldOf($clone['media_id'], 'checksum'));
		static::assertSame(3, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM mediapool_files WHERE node_id = 8 AND deleted = 0'));
	}

	#[DataProvider('mediaOfOthers')]
	public function testUserMayNotCloneMediaOfOthers(string $mediaId): void
	{
		$this->actingAs(2);
		$before = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM mediapool_files');

		$this->expectDenied(fn() => $this->mediaService->cloneMedia($mediaId));

		static::assertSame($before, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM mediapool_files'));
	}

	public function testFetchByChecksumIsOnlyForTheInternalLookupOfPlaylistItems(): void
	{
		// used by the playlist items: it gives the data of a media by its content hash and does
		// not check a directory. The callers have to be sure what they hand out.
		$media = $this->mediaService->fetchMediaByChecksum('cccccccccccccccccccccccccccccccc');

		static::assertSame('geheim.png', $media['filename']);
		static::assertSame([], $this->mediaService->fetchMediaByChecksum('0000'));
	}

	/**
	 * Passes only if the call is rejected by the ACL, not by a technical error.
	 *
	 * @param callable():mixed $call
	 */
	private function expectDenied(callable $call): void
	{
		try
		{
			$call();
		}
		catch (AccessDeniedException|NotFoundException)
		{
			static::addToAssertionCount(1);

			return;
		}
		catch (Throwable $e)
		{
			static::fail('Expected a rejection by the ACL but got ' . $e::class . ': ' . $e->getMessage());
		}

		static::fail('The call was not rejected. A user changed media of other users.');
	}

	private function fieldOf(string $mediaId, string $field): mixed
	{
		return $this->connection->fetchOne('SELECT ' . $field . ' FROM mediapool_files WHERE media_id = ?', [$mediaId]);
	}
}
