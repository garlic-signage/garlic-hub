<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace Tests\Unit\Framework\Localization\Translate;

use App\Framework\Localization\Locales\Locales;
use App\Framework\Localization\Translate\MessageFormatterFactory;
use App\Framework\Localization\Translate\TranslationLoaderInterface;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\TranslatableModuleException;
use MessageFormatter;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Phpfastcache\Helper\Psr16Adapter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class TranslatorTest extends TestCase
{
    private Locales&Stub $localesStub;
    private TranslationLoaderInterface&MockObject $loaderMock;
    private Psr16Adapter&MockObject $cacheMock;
    private MessageFormatterFactory&Stub $formatterFactoryStub;
	private Translator $translator;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
		parent::setUp();
		$this->localesStub = $this->createStub(Locales::class);
        $this->loaderMock = $this->createMock(TranslationLoaderInterface::class);
        $this->cacheMock = $this->createMock(Psr16Adapter::class);
        $this->formatterFactoryStub = $this->createStub(MessageFormatterFactory::class);

        $this->translator = new Translator(
            $this->localesStub,
            $this->loaderMock,
            $this->formatterFactoryStub,
            $this->cacheMock
        );
    }

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
    #[Group('units')]
    public function testTranslateReturnsTranslation(): void
    {
        $this->localesStub->method('getLanguageCode')->willReturn('en');
        $this->cacheMock->expects($this->once())->method('get')->willReturn(null);
        $this->loaderMock->expects($this->atLeastOnce())->method('load')->willReturn(['greeting' => 'Hello, {name}!']);
        $this->cacheMock->expects($this->once())->method('set');

        $result = $this->translator->translate('greeting', 'test_module', ['{name}' => 'John']);

        static::assertEquals('Hello, John!', $result);
    }

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testTranslateExceptionUsesKeyModuleAndReplacements(): void
	{
		$this->localesStub->method('getLanguageCode')->willReturn('en');
		$this->cacheMock->expects($this->once())->method('get')->with('lang_en_test_module')->willReturn(['not_found' => 'Item {item_id} of {name} not found.']);
		$this->loaderMock->expects($this->never())->method('load');
		$this->cacheMock->expects($this->never())->method('set');

		$exception = new TranslatableModuleException('test_module', 'not_found', ['item_id' => 7, 'name' => 'Lobby']);

		static::assertSame('Item 7 of Lobby not found.', $this->translator->translateException($exception));
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testTranslateReturnsEmpty(): void
	{
		$this->localesStub->method('getLanguageCode')->willReturn('en');
		$this->cacheMock->method('get')->willReturn(null);
		$this->loaderMock->expects($this->atLeastOnce())->method('load')->willReturn(['greeting' => ['Hello','John']]);
		$this->cacheMock->expects($this->once())->method('set');

		$result = $this->translator->translate('greeting', 'test_module');

		static::assertEmpty($result);
	}


	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
    #[Group('units')]
    public function testTranslateSucceedWithCacheKey(): void
    {
        $this->localesStub->method('getLanguageCode')->willReturn('en');
        $this->cacheMock->method('get')->willReturn(['greeting' => 'Hello, {name}!']);
        $this->loaderMock->expects($this->never())->method('load');
        $this->cacheMock->expects($this->never())->method('set');

        $result = $this->translator->translate('greeting', 'test_module', ['{name}' => 'John']);
        static::assertEquals('Hello, John!', $result);
    }

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
    #[Group('units')]
    public function testTranslateThrowsExceptionForMissingKey(): void
    {
        $this->localesStub->method('getLanguageCode')->willReturn('en');
        $this->cacheMock->method('get')->willReturn(null);
        $this->loaderMock->expects($this->atLeastOnce())->method('load')->willReturn(['other_key' => 'Some value']);
        $this->cacheMock->expects($this->once())->method('set');

        $result = $this->translator->translate('missing_key', 'test_module');
        static::assertEmpty($result);
    }

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
    #[Group('units')]
    public function testTranslatePluralReturnsFormattedString(): void
    {
        $this->localesStub->method('getLanguageCode')->willReturn('en');
        $this->cacheMock->expects($this->atLeastOnce())->method('get')->willReturn(null);
        $this->loaderMock->expects($this->atLeastOnce())->method('load')->willReturn(['item_count' => '{count} items']);

        $formatterStub = $this->createStub(MessageFormatter::class);
        $formatterStub->method('format')->willReturn('5 items');
        $this->formatterFactoryStub->method('create')->willReturn($formatterStub);

        $result = $this->translator->translateWithPlural('item_count', 'test_module', 5);
        static::assertEquals('5 items', $result);
    }

	/**
	 * @throws Exception
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testTranslatePluralReturnsEmpty(): void
	{
		$this->localesStub->method('getLanguageCode')->willReturn('en');
		$this->cacheMock->expects($this->atLeastOnce())->method('get')->willReturn(null);
		$this->loaderMock->expects($this->atLeastOnce())->method('load')->willReturn(['item_count' => ['uiuiuiui']]);

		$formatterStub = $this->createStub(MessageFormatter::class);
		$formatterStub->method('format')->willReturn('5 items');
		$this->formatterFactoryStub->method('create')->willReturn($formatterStub);

		$result = $this->translator->translateWithPlural('item_count', 'test_module', 5);
		static::assertEmpty($result);
	}


	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
    public function testTranslatePluralReturnsFrameworkException(): void
    {
        $this->localesStub->method('getLanguageCode')->willReturn('en');
        $this->cacheMock->expects($this->atLeastOnce())->method('get')->willReturn(null);
        $this->loaderMock->expects($this->atLeastOnce())->method('load')->willReturn(['item_count' => '{count} items']);

        $formatterStub = $this->createStub(MessageFormatter::class);
        $formatterStub->method('format')->willReturn(false);

        $this->expectException(FrameworkException::class);
        $this->expectExceptionMessage('MessageFormatter error: ');
        $this->translator->translateWithPlural('item_count', 'test_module', 5);
    }


	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
    #[Group('units')]
    public function testTranslateArrayForOptionsReturnsArray(): void
    {
        $this->localesStub->method('getLanguageCode')->willReturn('en');
        $this->cacheMock->expects($this->atLeastOnce())->method('get')->willReturn(null);
        $this->loaderMock->expects($this->atLeastOnce())->method('load')->willReturn(['options' => ['opt1' => 'Option 1', 'opt2' => 'Option 2']]);

        $result = $this->translator->translateArrayForOptions('options', 'test_module');
        static::assertArrayHasKey('opt1', $result);
        static::assertEquals('Option 1', $result['opt1']);
    }

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
    #[Group('units')]
    public function testTranslateArrayForOptionsHandlesNonArrayGracefully(): void
    {
        $this->localesStub->method('getLanguageCode')->willReturn('en');
        $this->cacheMock->expects($this->atLeastOnce())->method('get')->willReturn(null);
        $this->loaderMock->expects($this->atLeastOnce())->method('load')->willReturn(['options' => 'Not an array']);

		$result = $this->translator->translateArrayForOptions('options', 'test_module');
        static::assertEmpty($result);
    }

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testTranslateArrayWithPluralReturnsFormattedStringSuccessfully(): void
	{
		$this->localesStub->method('getLanguageCode')->willReturn('en');
		$this->cacheMock->expects($this->atLeastOnce())->method('get')->willReturn(null);
		$this->loaderMock->expects($this->atLeastOnce())->method('load')->willReturn(['time_unit_ago' => ['days' => '{count} days ago']]);

		$formatterStub = $this->createStub(MessageFormatter::class);
		$formatterStub->method('format')->willReturn('5 days ago');
		$this->formatterFactoryStub->method('create')->willReturn($formatterStub);

		$result = $this->translator->translateArrayWithPlural('days', 'time_unit_ago', 'test_module', 5);
		static::assertEquals('5 days ago', $result);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testTranslateArrayWithPluralHandlesMissingKeyGracefully(): void
	{
		$this->localesStub->method('getLanguageCode')->willReturn('en');
		$this->cacheMock->expects($this->atLeastOnce())->method('get')->willReturn(null);
		$this->loaderMock->expects($this->atLeastOnce())->method('load')->willReturn(['time_unit_ago' => ['hours' => '{count} hours ago']]);

		$formatterStub = $this->createStub(MessageFormatter::class);
		$formatterStub->method('format')->willReturn('');
		$this->formatterFactoryStub->method('create')->willReturn($formatterStub);

		$result = $this->translator->translateArrayWithPlural('days', 'time_unit_ago', 'test_module', 5);
		static::assertEquals('', $result);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testTranslateLoadsDirectlyWhenCacheFails(): void
	{
		$this->localesStub->method('getLanguageCode')->willReturn('en');
		$this->cacheMock->expects($this->once())->method('get')
			->willThrowException(new PhpfastcacheSimpleCacheException('cache down'));
		$this->cacheMock->expects($this->never())->method('has');
		$this->cacheMock->expects($this->once())->method('set')
			->willThrowException(new PhpfastcacheSimpleCacheException('cache down'));
		$this->loaderMock->expects($this->once())->method('load')->with('en', 'main')->willReturn(['save' => 'Save']);

		static::assertSame('Save', $this->translator->translate('save', 'main'));
		// loaded translations stay in memory for this request
		static::assertSame('Save', $this->translator->translate('save', 'main'));
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testTranslateUsesCachedTranslations(): void
	{
		$this->localesStub->method('getLanguageCode')->willReturn('de');
		$this->cacheMock->expects($this->never())->method('has');
		$this->cacheMock->expects($this->once())->method('get')->with('lang_de_main')->willReturn(['save' => 'Speichern']);
		$this->cacheMock->expects($this->never())->method('set');
		$this->loaderMock->expects($this->never())->method('load');

		static::assertSame('Speichern', $this->translator->translate('save', 'main'));
	}
}
