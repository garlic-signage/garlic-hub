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

namespace Tests\Unit\Framework\Utils\FormParameters;

use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\FormParameters\BaseParameters;
use App\Framework\Utils\FormParameters\ScalarType;
use App\Framework\Validation\Sanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class ConcreteBaseParameters extends BaseParameters
{
	/** @var list<string> */
	public array $beforeHookCalls = [];

	public function __construct(string $moduleName = 'testModule', string $allowedTags = '')
	{
		parent::__construct($moduleName, new Sanitizer($allowedTags));
		$this->currentParameters = [];
		$this->userInputs        = [];
	}

	protected function beforeParseHook(string $parameterName, array $parameter): array
	{
		$this->beforeHookCalls[] = $parameterName;

		return $parameter;
	}

	protected function afterParseHook(string $parameter_name, array $parameter): array
	{
		if ($parameter_name === 'shout' && isset($parameter['value']))
			$parameter['value'] = strtoupper((string) $parameter['value']);

		return $parameter;
	}
}

/**
 * The parameters of a request with the real Sanitizer: what the visitor sent comes out as the type the module asked for.
 */
class BaseParametersTest extends TestCase
{
	private ConcreteBaseParameters $parameters;

	protected function setUp(): void
	{
		parent::setUp();
		$this->parameters = new ConcreteBaseParameters();
	}

	#[Group('units')]
	public function testAddAndGetParameters(): void
	{
		$result = $this->parameters->addParameter('name', ScalarType::STRING, 'guest')->addParameter('age', ScalarType::INT, 18);

		static::assertSame($this->parameters, $result);
		static::assertTrue($this->parameters->hasParameter('name'));
		static::assertFalse($this->parameters->hasParameter('unknown'));
		static::assertSame(['name', 'age'], $this->parameters->getInputParametersKeys());
		static::assertSame(
			[
				'name' => ['scalar_type' => ScalarType::STRING, 'default_value' => 'guest', 'parsed' => false],
				'age'  => ['scalar_type' => ScalarType::INT, 'default_value' => 18, 'parsed' => false],
			],
			$this->parameters->getInputParametersArray()
		);
		static::assertSame($this->parameters->getInputParametersArray(), $this->parameters->getCurrentParameters());
	}

	#[Group('units')]
	public function testAddParameterWithoutDefaultHasNull(): void
	{
		$this->parameters->addParameter('name', ScalarType::STRING);

		static::assertNull($this->parameters->getDefaultValueOfParameter('name'));
	}

	#[Group('units')]
	public function testAddOwner(): void
	{
		$this->parameters->addOwner();

		static::assertSame(['scalar_type' => ScalarType::INT, 'default_value' => 0, 'parsed' => false], $this->parameters->getInputParametersArray()[BaseParameters::PARAMETER_UID]);
	}

	#[Group('units')]
	public function testRemoveParameters(): void
	{
		$this->parameters->addParameter('a', ScalarType::INT)->addParameter('b', ScalarType::INT)->addParameter('c', ScalarType::INT);

		$this->parameters->removeParameter('a')->removeParameter('does_not_exist');
		static::assertSame(['b', 'c'], $this->parameters->getInputParametersKeys());

		$this->parameters->removeParameters(['b', 'c', 'unknown']);
		static::assertSame([], $this->parameters->getInputParametersKeys());
	}

	#[Group('units')]
	public function testGetModuleName(): void
	{
		static::assertSame('testModule', $this->parameters->getModuleName());
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testSetAndGetValueAndDefault(): void
	{
		$this->parameters->addParameter('name', ScalarType::STRING, 'guest');

		$result = $this->parameters->setValueOfParameter('name', 'Anna')->setDefaultForParameter('name', 'visitor');

		static::assertSame($this->parameters, $result);
		static::assertSame('Anna', $this->parameters->getValueOfParameter('name'));
		static::assertSame('visitor', $this->parameters->getDefaultValueOfParameter('name'));
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testGetInputValuesArrayHasOnlyTheParametersWithAValue(): void
	{
		$this->parameters->addParameter('a', ScalarType::STRING)->addParameter('b', ScalarType::INT)->addParameter('c', ScalarType::INT);
		$this->parameters->setValueOfParameter('a', 'value')->setValueOfParameter('c', 3);

		static::assertSame(['value', 3], $this->parameters->getInputValuesArray());
	}

	#[Group('units')]
	public function testGetInputValuesArrayIsEmptyWithoutValues(): void
	{
		$this->parameters->addParameter('a', ScalarType::STRING);

		static::assertSame([], $this->parameters->getInputValuesArray());
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function unknownParameterCalls(): array
	{
		return ['getValue' => ['getValueOfParameter'], 'getDefault' => ['getDefaultValueOfParameter'], 'parse' => ['parseInputFilterByName']];
	}

	#[Group('units')]
	#[DataProvider('unknownParameterCalls')]
	public function testAnUnknownParameterThrowsWithTheModuleName(string $method): void
	{
		$parameters = new ConcreteBaseParameters('playlists');

		try
		{
			$parameters->$method('missing');
		}
		catch (ModuleException $e)
		{
			static::assertSame('A parameter with name: missing is not found.', $e->getMessage());
			static::assertSame('playlists', $e->getModuleName());

			return;
		}

		static::fail('Expected a ModuleException.');
	}

	#[Group('units')]
	public function testSetAnUnknownParameterThrows(): void
	{
		$this->parameters->addParameter('known', ScalarType::INT);

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('A parameter with name: missing is not found.');

		$this->parameters->setValueOfParameter('missing', 1);
	}

	#[Group('units')]
	public function testSetADefaultForAnUnknownParameterThrows(): void
	{
		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('A parameter with name: missing is not found.');

		$this->parameters->setDefaultForParameter('missing', 1);
	}

	#[Group('units')]
	public function testTheValueOfAParameterWhichWasNotParsedYetThrows(): void
	{
		$this->parameters->addParameter('name', ScalarType::STRING);

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('A value for parameter with name: name is not set.');

		$this->parameters->getValueOfParameter('name');
	}

	/**
	 * @return array<string, array{ScalarType, mixed, mixed}> type, what the visitor sent, what the module gets
	 */
	public static function parseProvider(): array
	{
		return [
			'int'                   => [ScalarType::INT, '42', 42],
			'int with text after'   => [ScalarType::INT, '12abc', 12],
			'int without number'    => [ScalarType::INT, 'abc', 0],
			'float'                 => [ScalarType::FLOAT, '1.5', 1.5],
			'string is trimmed'     => [ScalarType::STRING, "  Anna \x01 ", 'Anna'],
			'string keeps markup'   => [ScalarType::STRING, '<b>Anna</b>', '<b>Anna</b>'],
			'html strips tags'      => [ScalarType::HTML_STRING, '<b>Anna</b><script>x</script>', 'Annax'],
			'raw stays as sent'     => [ScalarType::RAW, ' <b>Anna</b> ', ' <b>Anna</b> '],
			'boolean true'          => [ScalarType::BOOLEAN, 'true', true],
			'boolean yes'           => [ScalarType::BOOLEAN, 'yes', true],
			'boolean one'           => [ScalarType::BOOLEAN, '1', true],
			'boolean zero'          => [ScalarType::BOOLEAN, '0', false],
			'boolean something'     => [ScalarType::BOOLEAN, 'maybe', false],
			'numeric array'         => [ScalarType::NUMERIC_ARRAY, ['1', '2x', 'a'], [1, 2, 0]],
			'numeric array drops arrays' => [ScalarType::NUMERIC_ARRAY, ['1', ['2']], [0 => 1]],
			'string array'          => [ScalarType::STRING_ARRAY, [' a ', 'b'], ['a', 'b']],
			'json'                  => [ScalarType::JSON, '{"a":1,"b":[1,2]}', ['a' => 1, 'b' => [1, 2]]],
			'json invalid'          => [ScalarType::JSON, '{"a":', []],
			'json no object'        => [ScalarType::JSON, '"text"', []],
			'json html trims'       => [ScalarType::JSON_HTML, '{"a":" x ","b":{"c":" y "},"d":5}', ['a' => 'x', 'b' => ['c' => 'y'], 'd' => 5]],
			'mediapool file'        => [ScalarType::MEDIAPOOL_FILE, 'abc ', 'hidden_abc'],
		];
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	#[DataProvider('parseProvider')]
	public function testTheInputBecomesTheTypeOfTheParameter(ScalarType $type, mixed $input, mixed $expected): void
	{
		$this->parameters->addParameter('field', $type, '')->setUserInputs(['field' => $input]);

		$this->parameters->parseInputFilterByName('field');

		static::assertSame($expected, $this->parameters->getValueOfParameter('field'));
	}

	#[Group('units')]
	public function testHtmlKeepsTheAllowedTags(): void
	{
		$parameters = new ConcreteBaseParameters('testModule', '<b>');
		$parameters->addParameter('text', ScalarType::HTML_STRING, '')->setUserInputs(['text' => '<b>bold</b><i>italic</i>']);

		$parameters->parseInputFilterByName('text');

		static::assertSame('<b>bold</b>italic', $parameters->getValueOfParameter('text'));
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testTheDefaultIsParsedWhenTheVisitorSentNothing(): void
	{
		$this->parameters->addParameter('name', ScalarType::STRING, ' guest ')->addParameter('age', ScalarType::INT, '42')->setUserInputs([]);

		$this->parameters->parseInputAllParameters();

		static::assertSame('guest', $this->parameters->getValueOfParameter('name'));
		static::assertSame(42, $this->parameters->getValueOfParameter('age'));
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testTheInputOfTheVisitorWinsOverTheDefault(): void
	{
		$this->parameters->addParameter('name', ScalarType::STRING, 'guest')->addParameter('age', ScalarType::INT, 42)
			->setUserInputs(['name' => 'Anna', 'age' => '84', 'unknown' => 'ignored']);

		$result = $this->parameters->parseInputAllParameters();

		static::assertSame($this->parameters, $result);
		static::assertSame('Anna', $this->parameters->getValueOfParameter('name'));
		static::assertSame(84, $this->parameters->getValueOfParameter('age'));
		static::assertFalse($this->parameters->hasParameter('unknown'), 'Input without a parameter is ignored.');
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testAnEmptyInputIsAnInputAndNotTheDefault(): void
	{
		$this->parameters->addParameter('name', ScalarType::STRING, 'guest')->setUserInputs(['name' => '']);

		$this->parameters->parseInputFilterByName('name');

		static::assertSame('', $this->parameters->getValueOfParameter('name'));
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testAParsedParameterIsMarkedAndNotParsedTwice(): void
	{
		$this->parameters->addParameter('name', ScalarType::STRING, 'guest')->setUserInputs(['name' => 'Anna']);
		static::assertFalse($this->parameters->getInputParametersArray()['name']['parsed']);

		$this->parameters->parseInputFilterByName('name');
		$this->parameters->parseInputFilterByName('name');
		$this->parameters->parseInputAllParameters();

		static::assertTrue($this->parameters->getInputParametersArray()['name']['parsed']);
		static::assertSame(['name'], $this->parameters->beforeHookCalls, 'The hook runs once for the one parsing.');
	}

	/**
	 * A value which the module has set and which differs from the input is replaced by the input
	 * when the parameter is parsed again, the input of the visitor is the source.
	 *
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testAValueSetByTheModuleIsReplacedByTheInputOnTheNextParsing(): void
	{
		$this->parameters->addParameter('name', ScalarType::STRING, 'guest')->setUserInputs(['name' => 'Anna']);
		$this->parameters->parseInputFilterByName('name');

		$this->parameters->setValueOfParameter('name', 'set by the module');
		$this->parameters->parseInputFilterByName('name');

		static::assertSame('Anna', $this->parameters->getValueOfParameter('name'));
	}

	/**
	 * Without an input there is nothing to compare: the value of the module stays.
	 *
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testAValueSetByTheModuleStaysWithoutAnInput(): void
	{
		$this->parameters->addParameter('name', ScalarType::STRING, 'guest')->setUserInputs([]);
		$this->parameters->parseInputFilterByName('name');

		$this->parameters->setValueOfParameter('name', 'set by the module');
		$this->parameters->parseInputFilterByName('name');

		static::assertSame('set by the module', $this->parameters->getValueOfParameter('name'));
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testAChangedInputIsParsedAgain(): void
	{
		$this->parameters->addParameter('name', ScalarType::STRING, 'guest')->setUserInputs(['name' => 'Anna']);
		$this->parameters->parseInputFilterByName('name');

		$this->parameters->setUserInputs(['name' => 'Berta'])->parseInputFilterByName('name');

		static::assertSame('Berta', $this->parameters->getValueOfParameter('name'));
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testTheHooksRunBeforeAndAfterParsing(): void
	{
		$this->parameters->addParameter('shout', ScalarType::STRING, '')->addParameter('quiet', ScalarType::STRING, '')
			->setUserInputs(['shout' => 'hello', 'quiet' => 'hello']);

		$this->parameters->parseInputAllParameters();

		static::assertSame(['shout', 'quiet'], $this->parameters->beforeHookCalls);
		static::assertSame('HELLO', $this->parameters->getValueOfParameter('shout'));
		static::assertSame('hello', $this->parameters->getValueOfParameter('quiet'));
	}

	/**
	 * A request can send any shape: an array where a text is expected (?name[]=x), a text where an array is
	 * expected (?ids=5) or nothing at all (a JSON null). Such an input is no input, the default is taken.
	 *
	 * @throws ModuleException
	 */
	#[Group('units')]
	#[DataProvider('wrongShapeProvider')]
	public function testAnInputInTheWrongShapeGetsTheDefault(ScalarType $type, mixed $input, mixed $default, mixed $expected): void
	{
		$this->parameters->addParameter('field', $type, $default)->setUserInputs(['field' => $input]);

		$this->parameters->parseInputFilterByName('field');

		static::assertSame($expected, $this->parameters->getValueOfParameter('field'));
	}

	/**
	 * @return array<string, array{ScalarType, mixed, mixed, mixed}> type, input, default of the parameter, value
	 */
	public static function wrongShapeProvider(): array
	{
		return [
			'array for a string'         => [ScalarType::STRING, ['x'], ' guest ', 'guest'],
			'array for an int'           => [ScalarType::INT, ['x'], 7, 7],
			'array for a float'          => [ScalarType::FLOAT, ['x'], 1.5, 1.5],
			'array for a boolean'        => [ScalarType::BOOLEAN, ['x'], 'true', true],
			'array for html'             => [ScalarType::HTML_STRING, ['x'], 'text', 'text'],
			'array for a mediapool file' => [ScalarType::MEDIAPOOL_FILE, ['x'], 'abc', 'hidden_abc'],
			'array for raw'              => [ScalarType::RAW, ['x'], ' raw ', ' raw '],
			'null for a string'          => [ScalarType::STRING, null, 'guest', 'guest'],
			'null for an int'            => [ScalarType::INT, null, '3', 3],
			'null for a boolean'         => [ScalarType::BOOLEAN, null, '', false],
			'null for raw'               => [ScalarType::RAW, null, 'raw', 'raw'],
			'null without a default'     => [ScalarType::STRING, null, null, ''],
			'text for a number array'    => [ScalarType::NUMERIC_ARRAY, '5', [], []],
			'text for a string array'    => [ScalarType::STRING_ARRAY, 'x', [], []],
			'null for a string array'    => [ScalarType::STRING_ARRAY, null, ['a'], ['a']],
		];
	}

	/**
	 * A form can send an array for JSON data, it is read as the JSON of this array.
	 *
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testAnArrayForJsonIsTheJsonOfTheArray(): void
	{
		$this->parameters->addParameter('data', ScalarType::JSON, '')->addParameter('html', ScalarType::JSON_HTML, '')
			->setUserInputs(['data' => ['a' => 1, 'b' => [2]], 'html' => ['k' => ' v ']]);

		$this->parameters->parseInputAllParameters();

		static::assertSame(['a' => 1, 'b' => [2]], $this->parameters->getValueOfParameter('data'));
		static::assertSame(['k' => 'v'], $this->parameters->getValueOfParameter('html'));
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testNullForJsonGetsTheDefault(): void
	{
		$this->parameters->addParameter('data', ScalarType::JSON, '{"a":1}')->setUserInputs(['data' => null]);

		$this->parameters->parseInputFilterByName('data');

		static::assertSame(['a' => 1], $this->parameters->getValueOfParameter('data'));
	}

	/**
	 * A request in JSON can send numbers and booleans where the module expects a text, and the other way round.
	 *
	 * @throws ModuleException
	 */
	#[Group('units')]
	#[DataProvider('otherScalarProvider')]
	public function testAScalarOfAnotherTypeIsConvertedToTheTypeOfTheParameter(ScalarType $type, mixed $input, mixed $expected): void
	{
		$this->parameters->addParameter('field', $type, '')->setUserInputs(['field' => $input]);

		$this->parameters->parseInputFilterByName('field');

		static::assertSame($expected, $this->parameters->getValueOfParameter('field'));
	}

	/**
	 * @return array<string, array{ScalarType, mixed, mixed}>
	 */
	public static function otherScalarProvider(): array
	{
		return [
			'int for a string'      => [ScalarType::STRING, 5, '5'],
			'bool for a string'     => [ScalarType::STRING, true, '1'],
			'float for a string'    => [ScalarType::STRING, 1.5, '1.5'],
			'int for html'          => [ScalarType::HTML_STRING, 5, '5'],
			'int for a file'        => [ScalarType::MEDIAPOOL_FILE, 5, 'hidden_5'],
			'int for raw'           => [ScalarType::RAW, 5, '5'],
			'float for an int'      => [ScalarType::INT, 1.9, 1],
			'bool for an int'       => [ScalarType::INT, true, 1],
			'int for a float'       => [ScalarType::FLOAT, 2, 2.0],
			'bool for a float'      => [ScalarType::FLOAT, true, 1.0],
			'int for a boolean'     => [ScalarType::BOOLEAN, 1, true],
			'zero for a boolean'    => [ScalarType::BOOLEAN, 0, false],
			'int for a json'        => [ScalarType::JSON, 5, []],
			'int in a string array' => [ScalarType::STRING_ARRAY, [1, 2], ['1', '2']],
		];
	}

	/**
	 * The parameter classes of the modules set their own parameters, a bare one has none and does not fail.
	 *
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testAParameterClassWithoutAnythingSetHasNoParameters(): void
	{
		$bare = new class('testModule', new Sanitizer()) extends BaseParameters {};

		static::assertFalse($bare->hasParameter('anything'));
		static::assertSame([], $bare->getInputParametersArray());
		static::assertSame([], $bare->getInputParametersKeys());
		static::assertSame([], $bare->getInputValuesArray());
		static::assertSame($bare, $bare->parseInputAllParameters());
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testAParameterClassWithoutAnythingSetThrowsTheModuleExceptionForAnUnknownParameter(): void
	{
		$bare = new class('testModule', new Sanitizer()) extends BaseParameters {};

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('A parameter with name: x is not found.');

		$bare->parseInputFilterByName('x');
	}

	/**
	 * Without setUserInputs() the request sent nothing: the defaults are taken.
	 *
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testWithoutUserInputsTheDefaultsAreTaken(): void
	{
		$bare = new class('testModule', new Sanitizer()) extends BaseParameters {};
		$bare->addParameter('name', ScalarType::STRING, ' guest ');

		$bare->parseInputAllParameters();

		static::assertSame('guest', $bare->getValueOfParameter('name'));
	}
}
