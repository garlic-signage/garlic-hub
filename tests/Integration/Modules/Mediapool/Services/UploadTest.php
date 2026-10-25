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

use App\Framework\Config\Config;
use App\Framework\Config\IniConfigLoader;
use App\Framework\Media\Ffmpeg;
use App\Framework\Media\MediaProperties;
use App\Framework\Media\MimeTypeService;
use App\Framework\System\ShellExecutor;
use App\Modules\Mediapool\Repositories\FilesRepository;
use App\Modules\Mediapool\Services\NodesService;
use App\Modules\Mediapool\Services\UploadService;
use App\Modules\Mediapool\Utils\ImagickFactory;
use App\Modules\Mediapool\Utils\MediaHandlerFactory;
use App\Modules\Mediapool\Utils\SsrfValidator;
use App\Modules\Mediapool\Utils\ZipFilesystemFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Imagick;
use PHPUnit\Framework\Attributes\Group;
use Slim\Psr7\UploadedFile;
use Tests\Integration\Support\ContainerTestCase;

#[Group('acl')]
#[Group('io')]
class UploadTest extends ContainerTestCase
{
	private string $dir;
	private UploadService $uploadService;
	private int $files = 0;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('mediapool/nodes.sql');
		$this->dir = sys_get_temp_dir() . '/garlic-hub-upload-' . bin2hex(random_bytes(4));
		foreach (['uploads', 'thumbs', 'originals', 'previews'] as $sub)
			mkdir($this->dir . '/var/storage/mediapool/' . $sub, 0775, true);
		mkdir($this->dir . '/public/images/icons', 0775, true);
		mkdir($this->dir . '/incoming', 0775, true);
		$root = (string) realpath(__DIR__ . '/../../../../..');
		copy($root . '/public/images/icons/file.svg', $this->dir . '/public/images/icons/file.svg');
		copy($root . '/public/images/icons/database.svg', $this->dir . '/public/images/icons/database.svg');
		$this->uploadService = $this->buildService([]);
	}

	protected function tearDown(): void
	{
		$this->removeDir($this->dir);
		parent::tearDown();
	}

	/**
	 * @param list<Response|\Throwable> $answers
	 */
	private function buildService(array $answers): UploadService
	{
		$root   = (string) realpath(__DIR__ . '/../../../../..');
		$config = new Config(new IniConfigLoader($root . '/config/settings'), ['systemDir' => $this->dir], ['APP_PLATFORM_EDITION' => 'edge']);
		$fs     = new Filesystem(new LocalFilesystemAdapter($this->dir));

		return new UploadService(
			new MediaHandlerFactory($config, $fs, new ZipFilesystemFactory(), new ImagickFactory(), new Ffmpeg($config, $fs, new MediaProperties(), new ShellExecutor())),
			$this->container->get(NodesService::class),
			new Client(['handler' => HandlerStack::create(new MockHandler($answers))]),
			new FilesRepository($this->connection),
			$this->container->get(MimeTypeService::class),
			new SsrfValidator(static fn(string $host): string => $host === 'media.example.com' ? '93.184.216.34' : $host)
		);
	}

	private function png(string $name, int $red = 200, string $clientType = 'image/png'): UploadedFile
	{
		$path  = $this->dir . '/incoming/' . (++$this->files) . '.tmp';
		$image = imagecreatetruecolor(8, 6);
		imagefill($image, 0, 0, (int) imagecolorallocate($image, max(0, min(255, $red)), 20, 30));
		imagepng($image, $path);

		return new UploadedFile($path, $name, $clientType, (int) filesize($path));
	}

	private function content(string $name, string $content, string $clientType): UploadedFile
	{
		$path = $this->dir . '/incoming/' . (++$this->files) . '.tmp';
		file_put_contents($path, $content);

		return new UploadedFile($path, $name, $clientType, (int) filesize($path));
	}

	/**
	 * @return list<string> the names of the files in a directory of the storage
	 */
	private function listing(string $sub): array
	{
		$names = array_values(array_filter((array) scandir($this->dir . '/var/storage/mediapool/' . $sub), static fn(mixed $name): bool => is_string($name) && !in_array($name, ['.', '..'], true)));
		sort($names);

		return $names;
	}

	private function removeDir(string $dir): void
	{
		if (!is_dir($dir))
			return;

		foreach (array_diff((array) scandir($dir), ['.', '..']) as $entry)
			is_dir($dir . '/' . $entry) ? $this->removeDir($dir . '/' . $entry) : unlink($dir . '/' . $entry);
		rmdir($dir);
	}

	private function hashOf(UploadedFile $file): string
	{
		return (string) hash_file('sha256', (string) $file->getStream()->getMetadata('uri'));
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private function rows(): array
	{
		return $this->connection->fetchAllAssociative('SELECT * FROM mediapool_files ORDER BY node_id, media_id');
	}

	public function testAnImageIsStoredUnderTheHashOfItsContent(): void
	{
		$this->actingAs(2);
		$file = $this->png('Urlaub.PNG');
		$hash = $this->hashOf($file);

		$result = $this->uploadService->uploadMediaFile(8, 2, $file, ['description' => 'Strand']);

		static::assertTrue($result['success']);
		static::assertSame([$hash . '.png'], $this->listing('originals'));
		static::assertSame([$hash . '.png'], $this->listing('thumbs'));
		static::assertSame([], $this->listing('uploads'));
		$rows = $this->rows();
		static::assertCount(1, $rows);
		static::assertSame($hash, $rows[0]['checksum']);
		static::assertSame('image/png', $rows[0]['mimetype']);
		static::assertSame('png', $rows[0]['extension']);
		static::assertSame('png', $rows[0]['thumb_extension']);
		static::assertSame('urlaub.png', $rows[0]['filename']);
		static::assertSame(8, (int) $rows[0]['node_id']);
		static::assertSame(2, (int) $rows[0]['UID']);
		static::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $rows[0]['media_id']);
		$metadata = json_decode($rows[0]['metadata'], true);
		static::assertSame(['width' => 8, 'height' => 6], $metadata['dimensions']);
		static::assertGreaterThan(0, $metadata['size']);
		static::assertSame('Strand', $metadata['description']);
	}

	public function testTheSameFileIsStoredOnlyOnce(): void
	{
		$this->actingAs(2);
		$first  = $this->png('eins.png', 120);
		$second = $this->png('zwei.png', 120);
		$hash   = $this->hashOf($first);
		static::assertSame($hash, $this->hashOf($second));

		$this->uploadService->uploadMediaFile(8, 2, $first, ['description' => 'Strand']);
		$this->uploadService->uploadMediaFile(9, 2, $second, []);

		static::assertSame([$hash . '.png'], $this->listing('originals'), 'The second upload leaves no file behind.');
		static::assertSame([$hash . '.png'], $this->listing('thumbs'));
		$rows = $this->rows();
		static::assertCount(2, $rows, 'Both directories have their media.');
		static::assertSame([8, 9], array_map('intval', array_column($rows, 'node_id')));
		static::assertSame($rows[0]['metadata'], $rows[1]['metadata']);
		static::assertSame($rows[0]['mimetype'], $rows[1]['mimetype']);
		static::assertNotSame($rows[0]['media_id'], $rows[1]['media_id']);
	}

	/**
	 * @return array<string,array{string,string}> the name and the type which the client names
	 */
	public static function claimsOfTheClient(): array
	{
		return [
			'a script ending'    => ['bild.php', 'image/png'],
			'another image type' => ['bild.jpg', 'image/jpeg'],
			'no ending'          => ['bild', 'image/png'],
			'double ending'      => ['bild.php.png', 'image/png'],
		];
	}

	/**
	 * The file is a PNG whatever the client says about it.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('claimsOfTheClient')]
	public function testTheTypeAndTheEndingComeFromTheContent(string $name, string $clientType): void
	{
		$this->actingAs(2);
		$file = $this->png($name, 60, $clientType);
		$hash = $this->hashOf($file);

		$this->uploadService->uploadMediaFile(8, 2, $file, []);

		static::assertSame([$hash . '.png'], $this->listing('originals'));
		$rows = $this->rows();
		static::assertSame('image/png', $rows[0]['mimetype']);
		static::assertSame('png', $rows[0]['extension']);
	}

	public function testAFileOverTheLimitIsRefusedBeforeItIsStored(): void
	{
		$this->actingAs(2);
		$file = new UploadedFile($this->dir . '/incoming/big.tmp', 'big.png', 'image/png', 21 * 1024 * 1024);

		try
		{
			$this->uploadService->uploadMediaFile(8, 2, $file, []);
			static::fail('Expected a TranslatableModuleException');
		}
		catch (\App\Framework\Exceptions\TranslatableModuleException $e)
		{
			static::assertSame('filesize_exceeded', $e->getTranslationKey());
		}

		static::assertSame([], $this->listing('originals'));
		static::assertSame([], $this->rows());
	}

	/**
	 * @return array<string,array{int}>
	 */
	public static function foreignNodes(): array
	{
		return ['private directory of bernd' => [11], 'public directory of bernd' => [12], 'user folder of bernd' => [10], 'public images' => [2]];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('foreignNodes')]
	public function testWithoutTheRightToUploadNothingIsStored(int $nodeId): void
	{
		$this->actingAs(2);

		try
		{
			$this->uploadService->uploadMediaFile($nodeId, 2, $this->png('x.png'), []);
			static::fail('Expected an AccessDeniedException');
		}
		catch (\App\Framework\Exceptions\AccessDeniedException|\App\Framework\Exceptions\NotFoundException)
		{
			static::addToAssertionCount(1);
		}

		static::assertSame([], $this->listing('originals'));
		static::assertSame([], $this->listing('uploads'));
		static::assertSame([], $this->rows());
	}

	public function testATypeWhichIsNotSupportedIsRefused(): void
	{
		$this->actingAs(2);

		try
		{
			$this->uploadService->uploadMediaFile(8, 2, $this->content('x.php', '<?php echo 1;', 'application/x-httpd-php'), []);
			static::fail('Expected a TranslatableModuleException');
		}
		catch (\App\Framework\Exceptions\TranslatableModuleException $e)
		{
			static::assertSame('unsupported_mimetype', $e->getTranslationKey());
		}

		static::assertSame([], $this->listing('originals'));
		static::assertSame([], $this->rows());
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function namesWithPaths(): array
	{
		return [
			'parent directories'  => ['../../evil.png'],
			'backslashes'         => ['..\\..\\evil.png'],
			'absolute path'       => ['/etc/evil.png'],
			'control characters'  => ["evil\x00\n.png"],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('namesWithPaths')]
	public function testTheNameOfTheClientCanNotLeaveTheStorage(string $name): void
	{
		$this->actingAs(2);

		$this->uploadService->uploadMediaFile(8, 2, $this->png($name), []);

		$found = [];
		foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS)) as $file)
			$found[] = substr((string) $file, strlen($this->dir));
		foreach ($found as $path)
			static::assertMatchesRegularExpression('#^/(var/storage/mediapool/|public/images/icons/|incoming/)#', $path);
		static::assertCount(1, $this->rows());
		static::assertStringNotContainsString('/', $this->rows()[0]['filename']);
		static::assertStringNotContainsString("\x00", $this->rows()[0]['filename']);
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function namesWithoutAName(): array
	{
		return ['empty' => [''], 'dot' => ['.'], 'dots' => ['..'], 'only a path' => ['a/b/..']];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('namesWithoutAName')]
	public function testAFileWithoutAUsableNameIsRefused(string $name): void
	{
		$this->actingAs(2);

		$this->expectException(\App\Framework\Exceptions\ModuleException::class);
		$this->uploadService->uploadMediaFile(8, 2, $this->png($name), []);
	}

	/**
	 * The checks of the sizes keep their own texts, and the file does not stay in the storage.
	 */
	public function testAnImageOverTheMaximumResolutionIsRefusedWithItsOwnTextAndLeavesNothing(): void
	{
		$this->actingAs(2);
		$imagick = new Imagick();
		$imagick->newImage(3841, 2, 'red');
		$imagick->setImageFormat('png');
		$path = $this->dir . '/incoming/wide.tmp';
		file_put_contents($path, $imagick->getImagesBlob());

		try
		{
			$this->uploadService->uploadMediaFile(8, 2, new UploadedFile($path, 'wide.png', 'image/png', (int) filesize($path)), []);
			static::fail('Expected a TranslatableModuleException');
		}
		catch (\App\Framework\Exceptions\TranslatableModuleException $e)
		{
			static::assertSame('width_exceeded', $e->getTranslationKey());
		}

		static::assertSame([], $this->listing('originals'));
		static::assertSame([], $this->listing('thumbs'));
		static::assertSame([], $this->listing('uploads'));
		static::assertSame([], $this->rows());
	}

	public function testAnExternalImageIsStoredLikeAnUploadedOne(): void
	{
		$this->actingAs(2);
		$source = $this->png('x.png', 77);
		$hash   = $this->hashOf($source);
		$body   = (string) file_get_contents((string) $source->getStream()->getMetadata('uri'));
		$service = $this->buildService([
			new Response(200, ['Content-Type' => 'image/png', 'Content-Length' => (string) strlen($body)]),
			new Response(200, ['Content-Type' => 'image/png'], $body),
		]);

		$result = $service->uploadExternalMedia(8, 2, 'https://media.example.com/img/Katze.png', ['description' => 'Eine Katze', 'source' => 'stock']);

		static::assertTrue($result['success']);
		static::assertSame([$hash . '.png'], $this->listing('originals'));
		$rows = $this->rows();
		static::assertSame('katze.png', $rows[0]['filename']);
		static::assertSame('Eine Katze', $rows[0]['media_description']);
		static::assertSame('stock', json_decode($rows[0]['metadata'], true)['source']);
		static::assertSame($hash, $rows[0]['checksum']);
	}

	public function testAnExternalFileOverTheLimitIsRefusedBeforeTheDownload(): void
	{
		$this->actingAs(2);
		$service = $this->buildService([new Response(200, ['Content-Type' => 'image/png', 'Content-Length' => (string) (21 * 1024 * 1024)])]);

		try
		{
			$service->uploadExternalMedia(8, 2, 'https://media.example.com/img/riesig.png', []);
			static::fail('Expected a TranslatableModuleException');
		}
		catch (\App\Framework\Exceptions\TranslatableModuleException $e)
		{
			static::assertSame('filesize_exceeded', $e->getTranslationKey());
		}

		static::assertSame([], $this->listing('originals'));
	}

	public function testAnExternalRedirectIntoTheOwnNetworkStoresNothing(): void
	{
		$this->actingAs(2);
		$service = $this->buildService([
			new Response(302, ['Location' => 'http://127.0.0.1:9000/internal.png']),
			new Response(200, ['Content-Type' => 'image/png'], 'internal data'),
		]);

		try
		{
			$service->uploadExternalMedia(8, 2, 'https://media.example.com/img/a.png', []);
			static::fail('Expected a TranslatableModuleException');
		}
		catch (\App\Framework\Exceptions\TranslatableModuleException $e)
		{
			static::assertSame('url_not_allowed', $e->getTranslationKey());
		}

		static::assertSame([], $this->listing('originals'));
		static::assertSame([], $this->rows());
	}

	/**
	 * The size before the download is what the server says in the answer to the first request. The download is
	 * checked on its own: a server can announce a small file and send a big one.
	 */
	public function testAnExternalFileWhichGrowsOverTheLimitIsStopped(): void
	{
		$this->actingAs(2);
		$service = $this->buildService([
			new Response(200, ['Content-Type' => 'image/png', 'Content-Length' => '1000']),
			new Response(200, ['Content-Type' => 'image/png'], str_repeat('x', 21 * 1024 * 1024)),
		]);

		try
		{
			$service->uploadExternalMedia(8, 2, 'https://media.example.com/img/wachsend.png', []);
			static::fail('Expected a TranslatableModuleException');
		}
		catch (\App\Framework\Exceptions\TranslatableModuleException $e)
		{
			static::assertSame('filesize_exceeded', $e->getTranslationKey());
		}

		static::assertSame([], $this->listing('originals'));
		static::assertSame([], $this->listing('uploads'));
		static::assertSame([], $this->rows());
	}

	/**
	 * The upload lies in a directory of its own until it has its place under the name of its hash. A file named
	 * like the hash of an existing original replaces nothing. The hashes are part of the urls of public media.
	 */
	public function testAFileNamedLikeAnOriginalReplacesNothing(): void
	{
		$this->actingAs(2);
		$victim = $this->png('opfer.png', 10);
		$hash   = $this->hashOf($victim);
		$this->uploadService->uploadMediaFile(8, 2, $victim, []);
		$before = (string) file_get_contents($this->dir . '/var/storage/mediapool/originals/' . $hash . '.png');

		$other     = $this->png($hash . '.png', 99);
		$otherHash = $this->hashOf($other);
		$this->uploadService->uploadMediaFile(8, 2, $other, []);

		static::assertSame($this->sorted([$hash . '.png', $otherHash . '.png']), $this->listing('originals'));
		static::assertSame($before, (string) file_get_contents($this->dir . '/var/storage/mediapool/originals/' . $hash . '.png'));
		static::assertSame($hash, hash('sha256', $before));
		static::assertSame([], $this->listing('uploads'));
		static::assertCount(2, $this->rows());
	}

	/**
	 * @return array<string,array{string,string}>
	 */
	public static function filesWhichCanNotBeRead(): array
	{
		return [
			'text as png'        => ['x.png', 'this is no image'],
			'script as gif'      => ['evil.php', "GIF89a<?php echo 1;"],
			'cut off png header' => ['kaputt.png', "\x89PNG\r\n\x1a\n\x00\x00"],
		];
	}

	/**
	 * A file which looks like an image by the type but can not be read: the user gets a text, and nothing of
	 * the file stays in the storage.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('filesWhichCanNotBeRead')]
	public function testAFileWhichCanNotBeReadLeavesNothingBehind(string $name, string $content): void
	{
		$this->actingAs(2);
		$type = str_ends_with($name, 'php') ? 'image/gif' : 'image/png';

		try
		{
			$this->uploadService->uploadMediaFile(8, 2, $this->content($name, $content, $type), []);
			static::fail('Expected a TranslatableModuleException');
		}
		catch (\App\Framework\Exceptions\TranslatableModuleException $e)
		{
			static::assertSame('file_not_readable', $e->getTranslationKey());
			static::assertNotNull($e->getPrevious(), 'the reason stays for the log');
		}

		static::assertSame([], $this->listing('originals'));
		static::assertSame([], $this->listing('thumbs'));
		static::assertSame([], $this->listing('uploads'));
		static::assertSame([], $this->rows());
	}

	/**
	 * @param list<string> $names
	 * @return list<string>
	 */
	private function sorted(array $names): array
	{
		sort($names);

		return $names;
	}
}
