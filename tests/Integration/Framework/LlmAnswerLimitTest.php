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

namespace Tests\Integration\Framework;

use App\Framework\Llm\LlmClientFactory;
use App\Framework\Llm\LlmConnection;
use App\Framework\Llm\LlmException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The answer of an LLM endpoint has a limit: an endpoint of the user must not fill memory or disk of the hub.
 * A local server answers with a real HTTP connection, the limit is one of curl.
 */
#[Group('io')]
class LlmAnswerLimitTest extends TestCase
{
	private static string $serverDir = '';
	private static int $port = 0;
	/** @var resource|null */
	private static $server = null;

	public static function setUpBeforeClass(): void
	{
		self::$serverDir = sys_get_temp_dir() . '/garlic-hub-llm-' . bin2hex(random_bytes(4));
		mkdir(self::$serverDir);
		file_put_contents(self::$serverDir . '/router.php', <<<'PHP'
<?php
// a list of models, padded to the size of the path: /<bytes>/v1/models, with or without Content-Length
$parts  = explode('/', trim((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'));
$size   = (int) ($parts[0] ?? 0);
$length = ($parts[1] ?? '') === 'length';
$head   = '{"object":"list","data":[{"id":"qwen3","object":"model","created":1}],"padding":"';
$tail   = '"}';
$fill   = max(0, $size - strlen($head) - strlen($tail));
header('Content-Type: application/json');
if ($length)
	header('Content-Length: ' . (strlen($head) + $fill + strlen($tail)));
echo $head;
for ($i = 0; $i < intdiv($fill, 65536); $i++)
{
	echo str_repeat('x', 65536);
	flush();
}
echo str_repeat('x', $fill % 65536), $tail;
PHP);
		$probe = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
		self::assertIsResource($probe);
		self::$port = (int) parse_url((string) stream_socket_get_name($probe, false), PHP_URL_PORT);
		fclose($probe);
		$process = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . self::$port, self::$serverDir . '/router.php'], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
		self::assertIsResource($process);
		self::$server = $process;
		for ($i = 0; $i < 50; $i++)
		{
			if (@fsockopen('127.0.0.1', self::$port))
				return;
			usleep(100_000);
		}
		self::fail('The test server did not start.');
	}

	public static function tearDownAfterClass(): void
	{
		if (is_resource(self::$server))
		{
			proc_terminate(self::$server);
			proc_close(self::$server);
		}
		@unlink(self::$serverDir . '/router.php');
		@rmdir(self::$serverDir);
	}

	/**
	 * @return list<string>
	 */
	private function models(int $size, bool $withLength): array
	{
		$url = 'http://127.0.0.1:' . self::$port . '/' . $size . '/' . ($withLength ? 'length' : 'chunked') . '/v1';

		return new LlmClientFactory()->create(new LlmConnection(LlmConnection::API_OPENAI, $url, ''), 30)->listModels();
	}

	/**
	 * @return array<string,array{bool}>
	 */
	public static function withAndWithoutLength(): array
	{
		return ['with Content-Length' => [true], 'without' => [false]];
	}

	#[DataProvider('withAndWithoutLength')]
	public function testAnAnswerBelowTheLimitIsRead(bool $withLength): void
	{
		static::assertSame(['qwen3'], $this->models(1024 * 1024, $withLength));
	}

	#[DataProvider('withAndWithoutLength')]
	public function testAnAnswerAboveTheLimitIsStopped(bool $withLength): void
	{
		try
		{
			$this->models(LlmClientFactory::MAX_ANSWER_BYTES + 1024 * 1024, $withLength);
			static::fail('Expected LlmException');
		}
		catch (LlmException $e)
		{
			// with the Content-Length before the answer comes, without it while it comes
			static::assertSame($withLength ? 'llm_answer_too_large' : 'llm_no_models', $e->getTranslationKey());
		}
	}
}
