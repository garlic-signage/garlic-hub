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

namespace Tests\Integration\Modules\Mediapool\Controller;

use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\NotFoundException;
use App\Modules\Mediapool\Controller\MediaController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * The media endpoints /async/mediapool/media as the media list of the browser uses them.
 * Media: 1111 urlaub.jpg and 2222 film.mp4 of anna in Fotos (8), 3333 geheim.png of bernd in Privat (11),
 * 4444 teilen.png of bernd in Geteilt (12, public), 5555 logo.jpg in the public images (2), 6666 is deleted.
 */
#[Group('acl')]
class MediaControllerTest extends ControllerTestCase
{
	private const string PHOTO   = '11111111-1111-4111-8111-111111111111';
	private const string PRIVATE = '33333333-3333-4333-8333-333333333333';
	private const string DELETED = '66666666-6666-4666-8666-666666666666';

	private MediaController $controller;

	protected function setUp(): void
	{
		parent::setUp();
		$this->controller = $this->container->get(MediaController::class);
	}

	private function field(string $mediaId, string $field): mixed
	{
		return $this->databaseValue('SELECT ' . $field . ' FROM mediapool_files WHERE media_id = ?', $mediaId);
	}

	/**
	 * @param array<string,mixed> $query
	 * @return list<string> the file names of the answer of the list
	 */
	private function listed(string $nodeId, array $query = []): array
	{
		$response = $this->controller->list($this->request('GET', [], $query), $this->newResponse(), ['node_id' => $nodeId]);
		$this->assertSuccess($response);

		return array_column($this->json($response)['media_list'], 'filename');
	}

	public function testTheMediaOfADirectoryAreListed(): void
	{
		$this->actingAs(2);

		static::assertSame(['film.mp4', 'urlaub.jpg'], $this->listed('8'));
	}

	/**
	 * @return array<string,array{array<string,mixed>,list<string>}>
	 */
	public static function filters(): array
	{
		return [
			'images'          => [['filter' => 'images'], ['urlaub.jpg']],
			'videos'          => [['filter' => 'videos'], ['film.mp4']],
			'audios'          => [['filter' => 'audios'], []],
			'documents'       => [['filter' => 'documents'], []],
			'unknown filter'  => [['filter' => 'everything'], ['film.mp4', 'urlaub.jpg']],
			'a mime type'     => [['filter' => 'image'], ['film.mp4', 'urlaub.jpg']],
			'a list'          => [['filter' => ['images']], ['film.mp4', 'urlaub.jpg']],
			'wildcards'       => [['filter' => '%'], ['film.mp4', 'urlaub.jpg']],
		];
	}

	/**
	 * Only the four filters of the list work. Everything else, also a list, shows all media.
	 *
	 * @param array<string,mixed> $query
	 * @param list<string> $expected
	 */
	#[DataProvider('filters')]
	public function testTheFilterIsOneOfTheFourKinds(array $query, array $expected): void
	{
		$this->actingAs(2);

		static::assertSame($expected, $this->listed('8', $query));
	}

	public function testTheListGivesSignedUrlsForTheBrowser(): void
	{
		$this->actingAs(2);

		$response = $this->controller->list($this->request('GET', [], ['filter' => 'images']), $this->newResponse(), ['node_id' => '8']);

		$media = $this->json($response)['media_list'][0];
		static::assertStringStartsWith('/media.php/orig/', $media['urls']['original']);
		static::assertStringContainsString('sig=', $media['urls']['original']);
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function noNodes(): array
	{
		return ['zero' => ['0'], 'text' => ['abc'], 'empty' => [''], 'negative' => ['-1']];
	}

	#[DataProvider('noNodes')]
	public function testTheListNeedsADirectory(string $nodeId): void
	{
		$this->actingAs(2);

		$this->assertError($this->controller->list($this->request('GET'), $this->newResponse(), ['node_id' => $nodeId]), 'node is missing');
	}

	public function testTheMediaOfAPrivateDirectoryOfAnotherUserAreNotListed(): void
	{
		$this->actingAs(2);

		$this->expectException(AccessDeniedException::class);
		$this->controller->list($this->request('GET'), $this->newResponse(), ['node_id' => '11']);
	}

	public function testTheInformationOfAMedium(): void
	{
		$this->actingAs(2);

		$response = $this->controller->getInfo($this->request('GET'), $this->newResponse(), ['media_id' => self::PHOTO]);

		$this->assertSuccess($response);
		static::assertSame('urlaub.jpg', $this->json($response)['media']['filename']);
	}

	public function testTheInformationOfAMediumWithoutAnIdIsAMissingId(): void
	{
		$this->actingAs(2);

		$this->assertError($this->controller->getInfo($this->request('GET'), $this->newResponse(), ['media_id' => '']), 'Media id is missing');
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function mediaOfOthers(): array
	{
		return ['private' => [self::PRIVATE], 'deleted' => [self::DELETED], 'unknown' => ['00000000-0000-4000-8000-000000000000']];
	}

	#[DataProvider('mediaOfOthers')]
	public function testTheInformationOfAForeignOrUnknownMediumIsRefused(string $mediaId): void
	{
		$this->actingAs(2);

		try
		{
			$this->controller->getInfo($this->request('GET'), $this->newResponse(), ['media_id' => $mediaId]);
			static::fail('Expected a rejection');
		}
		catch (AccessDeniedException|NotFoundException)
		{
			static::addToAssertionCount(1);
		}
	}

	public function testAMediumIsEdited(): void
	{
		$this->actingAs(2);

		$response = $this->controller->edit($this->request('PATCH', $this->withToken(['media_id' => self::PHOTO, 'filename' => 'strand.jpg', 'description' => 'Am Meer'])), $this->newResponse());

		$this->assertSuccess($response);
		static::assertSame('strand.jpg', $this->field(self::PHOTO, 'filename'));
		static::assertSame('Am Meer', $this->field(self::PHOTO, 'media_description'));
	}

	public function testAnEmptyDescriptionIsAllowed(): void
	{
		$this->actingAs(2);

		$this->assertSuccess($this->controller->edit($this->request('PATCH', $this->withToken(['media_id' => self::PHOTO, 'filename' => 'a.jpg', 'description' => ''])), $this->newResponse()));
	}

	/**
	 * @return array<string,array{array<string,mixed>,string}>
	 */
	public static function wrongEdits(): array
	{
		return [
			'no id'              => [['filename' => 'a.jpg', 'description' => 'd'], 'Media id is missing'],
			'id is a list'       => [['media_id' => [self::PHOTO], 'filename' => 'a.jpg', 'description' => 'd'], 'Media id is missing'],
			'id is a number'     => [['media_id' => 5, 'filename' => 'a.jpg', 'description' => 'd'], 'Media id is missing'],
			'no filename'        => [['media_id' => self::PHOTO, 'description' => 'd'], 'Filename is missing'],
			'filename is a list' => [['media_id' => self::PHOTO, 'filename' => ['a'], 'description' => 'd'], 'Filename is missing'],
			'filename is null'   => [['media_id' => self::PHOTO, 'filename' => null, 'description' => 'd'], 'Filename is missing'],
			'no description'     => [['media_id' => self::PHOTO, 'filename' => 'a.jpg'], 'Description is missing'],
			'description is a list' => [['media_id' => self::PHOTO, 'filename' => 'a.jpg', 'description' => ['d']], 'Description is missing'],
		];
	}

	/**
	 * @param array<string,mixed> $body
	 */
	#[DataProvider('wrongEdits')]
	public function testAnEditWithWrongInputChangesNothing(array $body, string $message): void
	{
		$this->actingAs(2);

		$this->assertError($this->controller->edit($this->request('PATCH', $this->withToken($body)), $this->newResponse()), $message);

		static::assertSame('urlaub.jpg', $this->field(self::PHOTO, 'filename'));
	}

	public function testAForeignMediumIsNotEdited(): void
	{
		$this->actingAs(2);

		try
		{
			$this->controller->edit($this->request('PATCH', $this->withToken(['media_id' => self::PRIVATE, 'filename' => 'gehackt.png', 'description' => 'x'])), $this->newResponse());
			static::fail('Expected a rejection');
		}
		catch (AccessDeniedException)
		{
			static::assertSame('geheim.png', $this->field(self::PRIVATE, 'filename'));
		}
	}

	public function testWithoutTheTokenOfThePageNothingIsChanged(): void
	{
		$this->actingAs(2);
		$this->csrf();

		foreach (['edit' => ['media_id' => self::PHOTO, 'filename' => 'x', 'description' => 'x'], 'delete' => ['media_id' => self::PHOTO], 'clone' => ['media_id' => self::PHOTO], 'move' => ['media_id' => self::PHOTO, 'node_id' => '9']] as $action => $body)
		{
			foreach ([[], ['csrf_token' => 'forged'], ['csrf_token' => ['x']]] as $token)
			{
				$response = $this->controller->$action($this->request('POST', $token + $body), $this->newResponse());
				static::assertSame(400, $response->getStatusCode(), $action);
				static::assertFalse($this->json($response)['success']);
			}
		}

		static::assertSame('urlaub.jpg', $this->field(self::PHOTO, 'filename'));
		static::assertSame(0, (int) $this->field(self::PHOTO, 'deleted'));
		static::assertSame(8, (int) $this->field(self::PHOTO, 'node_id'));
		static::assertSame(2, (int) $this->databaseValue('SELECT COUNT(*) FROM mediapool_files WHERE node_id = 8 AND deleted = 0'));
	}

	public function testAMediumIsDeleted(): void
	{
		$this->actingAs(2);

		$response = $this->controller->delete($this->request('DELETE', $this->withToken(['media_id' => self::PHOTO])), $this->newResponse());

		$this->assertSuccess($response);
		static::assertSame(['deleted_media' => 1], $this->json($response)['data']);
		static::assertSame(1, (int) $this->field(self::PHOTO, 'deleted'));
	}

	/**
	 * @return array<string,array{array<string,mixed>}>
	 */
	public static function wrongIds(): array
	{
		return [
			'nothing'   => [[]],
			'a list'    => [['media_id' => [self::PHOTO]]],
			'empty'     => [['media_id' => '']],
			'a number'  => [['media_id' => 1]],
		];
	}

	/**
	 * @param array<string,mixed> $body
	 */
	#[DataProvider('wrongIds')]
	public function testADeleteOrCloneWithoutAMediumIdChangesNothing(array $body): void
	{
		$this->actingAs(2);
		$before = (int) $this->databaseValue('SELECT COUNT(*) FROM mediapool_files');

		foreach (['delete', 'clone'] as $action)
			$this->assertError($this->controller->$action($this->request('POST', $this->withToken($body)), $this->newResponse()), 'Media id is missing');

		static::assertSame($before, (int) $this->databaseValue('SELECT COUNT(*) FROM mediapool_files'));
		static::assertSame(0, (int) $this->databaseValue('SELECT COUNT(*) FROM mediapool_files WHERE deleted = 1 AND media_id <> ?', self::DELETED));
	}

	public function testAForeignMediumIsNotDeletedOrCloned(): void
	{
		$this->actingAs(2);
		$before = (int) $this->databaseValue('SELECT COUNT(*) FROM mediapool_files');

		foreach (['delete', 'clone'] as $action)
		{
			try
			{
				$this->controller->$action($this->request('POST', $this->withToken(['media_id' => self::PRIVATE])), $this->newResponse());
				static::fail('Expected a rejection by the ACL for ' . $action);
			}
			catch (AccessDeniedException)
			{
				static::addToAssertionCount(1);
			}
		}

		static::assertSame($before, (int) $this->databaseValue('SELECT COUNT(*) FROM mediapool_files'));
		static::assertSame(0, (int) $this->field(self::PRIVATE, 'deleted'));
	}

	public function testAMediumIsCloned(): void
	{
		$this->actingAs(2);

		$response = $this->controller->clone($this->request('POST', $this->withToken(['media_id' => self::PHOTO])), $this->newResponse());

		$this->assertSuccess($response);
		$new = $this->json($response)['new_media'];
		static::assertNotSame(self::PHOTO, $new['media_id']);
		static::assertSame('urlaub.jpg', $this->field($new['media_id'], 'filename'));
		static::assertSame(2, (int) $this->field($new['media_id'], 'UID'));
	}

	public function testAMediumIsMoved(): void
	{
		$this->actingAs(2);

		$response = $this->controller->move($this->request('POST', $this->withToken(['media_id' => self::PHOTO, 'node_id' => '9'])), $this->newResponse());

		$this->assertSuccess($response);
		static::assertSame(9, (int) $this->field(self::PHOTO, 'node_id'));
	}

	/**
	 * @return array<string,array{array<string,mixed>}>
	 */
	public static function wrongMoves(): array
	{
		return [
			'nothing'          => [[]],
			'no directory'     => [['media_id' => self::PHOTO]],
			'no medium'        => [['node_id' => '9']],
			'medium is a list' => [['media_id' => [self::PHOTO], 'node_id' => '9']],
			'directory is a list' => [['media_id' => self::PHOTO, 'node_id' => ['9']]],
			'directory is text' => [['media_id' => self::PHOTO, 'node_id' => 'Archiv']],
		];
	}

	/**
	 * @param array<string,mixed> $body
	 */
	#[DataProvider('wrongMoves')]
	public function testAMoveWithWrongInputChangesNothing(array $body): void
	{
		$this->actingAs(2);

		$this->assertError($this->controller->move($this->request('POST', $this->withToken($body)), $this->newResponse()), 'media id or node is missing');

		static::assertSame(8, (int) $this->field(self::PHOTO, 'node_id'));
	}

	public function testAMediumIsNotMovedIntoTheDirectoryOfAnotherUser(): void
	{
		$this->actingAs(2);

		try
		{
			$this->controller->move($this->request('POST', $this->withToken(['media_id' => self::PHOTO, 'node_id' => '11'])), $this->newResponse());
			static::fail('Expected a rejection by the ACL');
		}
		catch (AccessDeniedException)
		{
			static::assertSame(8, (int) $this->field(self::PHOTO, 'node_id'));
		}
	}
}
