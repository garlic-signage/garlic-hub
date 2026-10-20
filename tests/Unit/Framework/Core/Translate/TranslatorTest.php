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

namespace Tests\Unit\Framework\Core\Translate;

use App\Framework\Core\Locales\Locales;
use App\Framework\Core\Translate\MessageFormatterFactory;
use App\Framework\Core\Translate\TranslationLoaderInterface;
use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use MessageFormatter;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Phpfastcache\Helper\Psr16Adapter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TranslatorTest extends TestCase
{
    private Locales&MockObject $localesMock;
    private TranslationLoaderInterface&MockObject $loaderMock;
    private Psr16Adapter&MockObject $cacheMock;
    private MessageFormatterFactory&MockObject $formatterFactoryMock;
	private Translator $translator;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
		parent::setUp();
		$this->localesMock = $this->createMock(Locales::class);
        $this->loaderMock = $this->createMock(TranslationLoaderInterface::class);
        $this->cacheMock = $this->createMock(Psr16Adapter::class);
        $this->formatterFactoryMock = $this->createMock(MessageFormatterFactory::class);

        $this->translator = new Translator(
            $this->localesMock,
            $this->loaderMock,
            $this->formatterFactoryMock,
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
        $this->localesMock->method('getLanguageCode')->willReturn('en');
        $this->cacheMock->expects($this->once())->method('get')->willReturn(null);
        $this->loaderMock->method('load')->willReturn(['greeting' => 'Hello, {name}!']);
        $this->cacheMock->expects($this->once())->method('set');

        $result = $this->translator->translate('greeting', 'test_module', ['{name}' => 'John']);

        static::assertEquals('Hello, John!', $result);
    }

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testTranslateReturnsEmpty(): void
	{
		$this->localesMock->method('getLanguageCode')->willReturn('en');
		$this->cacheMock->method('get')->willReturn(null);
		$this->loaderMock->method('load')->willReturn(['greeting' => ['Hello','John']]);
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
        $this->localesMock->method('getLanguageCode')->willReturn('en');
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
        $this->localesMock->method('getLanguageCode')->willReturn('en');
        $this->cacheMock->method('get')->willReturn(null);
        $this->loaderMock->method('load')->willReturn(['other_key' => 'Some value']);
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
        $this->localesMock->method('getLanguageCode')->willReturn('en');
        $this->cacheMock->method('get')->willReturn(null);
        $this->loaderMock->method('load')->willReturn(['item_count' => '{count} items']);

        $formatterMock = $this->createMock(MessageFormatter::class);
        $formatterMock->method('format')->willReturn('5 items');
        $this->formatterFactoryMock->method('create')->willReturn($formatterMock);

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
		$this->localesMock->method('getLanguageCode')->willReturn('en');
		$this->cacheMock->method('get')->willReturn(null);
		$this->loaderMock->method('load')->willReturn(['item_count' => ['uiuiuiui']]);

		$formatterMock = $this->createMock(MessageFormatter::class);
		$formatterMock->method('format')->willReturn('5 items');
		$this->formatterFactoryMock->method('create')->willReturn($formatterMock);

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
        $this->localesMock->method('getLanguageCode')->willReturn('en');
        $this->cacheMock->method('get')->willReturn(null);
        $this->loaderMock->method('load')->willReturn(['item_count' => '{count} items']);

        $formatterMock = $this->createMock(MessageFormatter::class);
        $formatterMock->method('format')->willReturn(false);

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
        $this->localesMock->method('getLanguageCode')->willReturn('en');
        $this->cacheMock->method('get')->willReturn(null);
        $this->loaderMock->method('load')->willReturn(['options' => ['opt1' => 'Option 1', 'opt2' => 'Option 2']]);

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
        $this->localesMock->method('getLanguageCode')->willReturn('en');
        $this->cacheMock->method('get')->willReturn(null);
        $this->loaderMock->method('load')->willReturn(['options' => 'Not an array']);

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
		$this->localesMock->method('getLanguageCode')->willReturn('en');
		$this->cacheMock->method('get')->willReturn(null);
		$this->loaderMock->method('load')->willReturn(['time_unit_ago' => ['days' => '{count} days ago']]);

		$formatterMock = $this->createMock(MessageFormatter::class);
		$formatterMock->method('format')->willReturn('5 days ago');
		$this->formatterFactoryMock->method('create')->willReturn($formatterMock);

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
		$this->localesMock->method('getLanguageCode')->willReturn('en');
		$this->cacheMock->method('get')->willReturn(null);
		$this->loaderMock->method('load')->willReturn(['time_unit_ago' => ['hours' => '{count} hours ago']]);

		$formatterMock = $this->createMock(MessageFormatter::class);
		$formatterMock->method('format')->willReturn('');
		$this->formatterFactoryMock->method('create')->willReturn($formatterMock);

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
		$this->localesMock->method('getLanguageCode')->willReturn('en');
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
		$this->localesMock->method('getLanguageCode')->willReturn('de');
		$this->cacheMock->expects($this->never())->method('has');
		$this->cacheMock->expects($this->once())->method('get')->with('lang_de_main')->willReturn(['save' => 'Speichern']);
		$this->cacheMock->expects($this->never())->method('set');
		$this->loaderMock->expects($this->never())->method('load');

		static::assertSame('Speichern', $this->translator->translate('save', 'main'));
	}
}
