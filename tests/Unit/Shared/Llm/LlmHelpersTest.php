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

namespace Tests\Unit\Shared\Llm;

use App\Framework\Llm\LlmClient;
use App\Framework\Llm\LlmException;
use App\Framework\Llm\LlmRequest;
use App\Framework\Localization\Locales\Locales;
use App\Shared\Llm\ImageGeneration;
use App\Shared\Llm\ImageSuggestion;
use App\Shared\Llm\LlmResolver;
use App\Shared\Llm\MediaTagging;
use App\Shared\Llm\PlaylistComposition;
use App\Shared\Llm\TextCorrection;
use App\Shared\Llm\TextRewriting;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The small helpers with a model which answers what the test wants: what they send, what they accept and their limits.
 * The limits count characters, not bytes: the texts are in umlauts.
 */
class LlmHelpersTest extends TestCase
{
	/** the smallest PNG: one transparent pixel */
	private const string PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

	/** @var list<LlmRequest> */
	private array $requests = [];
	/** @var list<array{string,string,string}> prompt, size, negative prompt */
	private array $images = [];
	private string $answer = '';
	private LlmResolver $resolver;

	protected function setUp(): void
	{
		parent::setUp();
		$client = $this->createStub(LlmClient::class);
		$client->method('complete')->willReturnCallback(function (LlmRequest $request): string
		{
			$this->requests[] = $request;

			return $this->answer;
		});
		$client->method('generateImage')->willReturnCallback(function (string $prompt, string $size, string $negativePrompt = ''): string
		{
			$this->images[] = [$prompt, $size, $negativePrompt];

			return $this->answer;
		});
		$resolver = $this->createStub(LlmResolver::class);
		$resolver->method('forUser')->willReturn($client);
		$this->resolver = $resolver;
	}

	private function request(int $index): LlmRequest
	{
		static::assertArrayHasKey($index, $this->requests);

		return $this->requests[$index];
	}

	private function assertRefused(string $reason, callable $call): void
	{
		try
		{
			$call();
			static::fail('refused with ' . $reason);
		}
		catch (LlmException $e)
		{
			static::assertSame($reason, $e->getTranslationKey());
		}
	}

	#[Group('units')]
	public function testTheCorrectionAsksNothingForAnEmptyTextAndCountsCharacters(): void
	{
		$correction = new TextCorrection($this->resolver);

		static::assertSame("  \n", $correction->correct(1, "  \n"));
		static::assertSame([], $this->requests);

		$this->answer = '<think>hm</think> Grüße ';
		static::assertSame('Grüße', $correction->correct(1, str_repeat('ü', TextCorrection::MAX_LENGTH)));
		$this->assertRefused('llm_text_too_long', fn() => $correction->correct(1, str_repeat('ü', TextCorrection::MAX_LENGTH + 1)));
	}

	#[Group('units')]
	public function testARewriteSendsTheTextAndCountsCharacters(): void
	{
		$locales = $this->createStub(Locales::class);
		$locales->method('getAvailableLocales')->willReturn(['de_DE', 'en_US']);
		$rewriting   = new TextRewriting($this->resolver, $locales);
		$this->answer = 'kurz';

		static::assertSame(' ', $rewriting->shorten(1, ' ', 20));
		static::assertSame("\t", $rewriting->translate(1, "\t", 'en_US'));
		static::assertSame([], $this->requests, 'nothing to rewrite');

		// a text which fits becomes tighter, a longer one shorter
		$rewriting->shorten(1, str_repeat('ü', 20), 20);
		$rewriting->shorten(1, str_repeat('ü', 21), 20);
		static::assertStringContainsString('a little more concise', $this->request(0)->system);
		static::assertStringContainsString('at most 20 characters', $this->request(1)->system);
		static::assertSame(str_repeat('ü', 20), $this->request(0)->messages[0]->text);

		static::assertSame('kurz', $rewriting->translate(1, str_repeat('ü', TextCorrection::MAX_LENGTH), 'en_US'));
		$this->assertRefused('llm_text_too_long', fn() => $rewriting->translate(1, str_repeat('ü', TextCorrection::MAX_LENGTH + 1), 'en_US'));
	}

	#[Group('units')]
	public function testAnImageGetsTheTrimmedPromptsAndCountsCharacters(): void
	{
		$generation   = new ImageGeneration($this->resolver);
		$this->answer = (string) base64_decode(self::PNG);

		static::assertStringStartsWith('data:image/png;base64,', $generation->generate(1, ' ein Baum ', 'landscape', ' unscharf '));
		static::assertSame('ein Baum', $this->images[0][0]);
		static::assertSame('unscharf', $this->images[0][2]);

		$generation->generate(1, str_repeat('ü', ImageGeneration::MAX_PROMPT_LENGTH), 'landscape', str_repeat('ü', ImageGeneration::MAX_PROMPT_LENGTH));
		$this->assertRefused('llm_input_too_long', fn() => $generation->generate(1, str_repeat('ü', ImageGeneration::MAX_PROMPT_LENGTH + 1), 'landscape'));
		$this->assertRefused('llm_input_too_long', fn() => $generation->generate(1, 'Baum', 'landscape', str_repeat('ü', ImageGeneration::MAX_PROMPT_LENGTH + 1)));

		$this->answer = 'no image';
		$this->assertRefused('llm_no_answer', fn() => $generation->generate(1, 'Baum', 'landscape'));
	}

	#[Group('units')]
	public function testTheTagsOfAMediumNeedADescriptionOrTags(): void
	{
		$tagging = new MediaTagging($this->resolver);

		$this->answer = '{"description": " Ein Baum ", "tags": [" baum ", " ", 5, "wald"]}';
		static::assertSame(['description' => 'Ein Baum', 'tags' => ['baum', 'wald']], $tagging->suggest(1, 'data:', 'de_DE'));

		$this->answer = '{"tags": ["baum"]}';
		static::assertSame(['description' => '', 'tags' => ['baum']], $tagging->suggest(1, 'data:', 'de_DE'));

		$this->answer = '{"description": "Ein Baum"}';
		static::assertSame(['description' => 'Ein Baum', 'tags' => []], $tagging->suggest(1, 'data:', 'de_DE'));

		$this->answer = '{"description": " ", "tags": [" "]}';
		$this->assertRefused('llm_no_answer', fn() => $tagging->suggest(1, 'data:', 'de_DE'));

		$this->answer = 'kein json';
		$this->assertRefused('llm_no_answer', fn() => $tagging->suggest(1, 'data:', 'de_DE'));

		$this->answer = (string) json_encode(['tags' => array_map(static fn(int $i): string => 'tag' . $i, range(1, MediaTagging::MAX_TAGS + 3))]);
		static::assertCount(MediaTagging::MAX_TAGS, $tagging->suggest(1, 'data:', 'de_DE')['tags']);
	}

	#[Group('units')]
	public function testTheImageSuggestionSendsTheTrimmedTextsAndTheCandidatesReadable(): void
	{
		$suggestion   = new ImageSuggestion($this->resolver);
		$this->answer = '{"terms": ["Baum"], "pictures": [2, 1]}';

		static::assertSame(['Baum'], $suggestion->findSearchTerms(1, ' Wald ', 'de_DE'));
		static::assertSame('Wald', $this->request(0)->messages[0]->text);
		static::assertSame([1, 0], $suggestion->choose(1, ' Wald ', [['name' => 'Bäume/1', 'tags' => [], 'description' => ''], ['name' => 'b', 'tags' => [], 'description' => '']]));
		static::assertSame("Texts: Wald\nPictures: [{\"number\":1,\"name\":\"Bäume/1\",\"tags\":[],\"description\":\"\"},{\"number\":2,\"name\":\"b\",\"tags\":[],\"description\":\"\"}]", $this->request(1)->messages[0]->text);

		static::assertSame(['Baum'], $suggestion->findSearchTerms(1, str_repeat('ü', ImageSuggestion::MAX_TEXT_LENGTH), 'de_DE'));
		$this->assertRefused('llm_input_too_long', fn() => $suggestion->findSearchTerms(1, str_repeat('ü', ImageSuggestion::MAX_TEXT_LENGTH + 1), 'de_DE'));
		$this->assertRefused('llm_no_answer', fn() => $suggestion->findSearchTerms(1, '  ', 'de_DE'));
	}

	#[Group('units')]
	public function testThePlaylistCompositionSendsTheTrimmedThemeAndTheCandidatesReadable(): void
	{
		$composition  = new PlaylistComposition($this->resolver);
		$this->answer = '{"terms": ["Baum"], "items": ["2"]}';

		static::assertSame(['Baum'], $composition->findSearchTerms(1, ' Wald ', 'de_DE'));
		static::assertSame('Wald', $this->request(0)->messages[0]->text);
		static::assertSame([1], $composition->choose(1, ' Wald ', 60, [['name' => 'a', 'tags' => [], 'description' => '', 'kind' => 'image', 'seconds' => 10], ['name' => 'Bäume/1', 'tags' => [], 'description' => '', 'kind' => 'video', 'seconds' => 20]]));
		static::assertStringStartsWith("Theme: Wald\nMedia: [{\"number\":1,\"name\":\"a\"", $this->request(1)->messages[0]->text);
		static::assertStringContainsString('"name":"Bäume/1"', $this->request(1)->messages[0]->text);

		static::assertSame(['Baum'], $composition->findSearchTerms(1, str_repeat('ü', PlaylistComposition::MAX_THEME_LENGTH), 'de_DE'));
		$this->assertRefused('llm_input_too_long', fn() => $composition->findSearchTerms(1, str_repeat('ü', PlaylistComposition::MAX_THEME_LENGTH + 1), 'de_DE'));
	}
}
