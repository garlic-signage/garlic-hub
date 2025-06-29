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


namespace Tests\Unit\Framework\Utils\FormParameters;

use App\Framework\Core\Sanitizer;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\FormParameters\BaseParameters;
use App\Framework\Utils\FormParameters\ScalarType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConcreteBaseParameters extends BaseParameters
{

}

class BaseParametersTest extends TestCase
{
	private Sanitizer&MockObject $sanitizerMock;
	private BaseParameters $baseParameters;

	/**
	 * @throws Exception
	 */
	public function setUp(): void
	{
		$this->sanitizerMock = $this->createMock(Sanitizer::class);

		$this->baseParameters = new ConcreteBaseParameters('testModule', $this->sanitizerMock);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testGetInputValuesArrayReturnsCorrectValues(): void
	{
		$this->baseParameters->addParameter('param1', ScalarType::STRING, 'default1');
		$this->baseParameters->setValueOfParameter('param1', 'value1');
		$this->baseParameters->addParameter('param2', ScalarType::INT, 10);
		$this->baseParameters->setValueOfParameter('param2', 42);

		$result = $this->baseParameters->getInputValuesArray();

		$this->assertSame(['value1', 42], $result);
	}

	#[Group('units')]
	public function testGetInputValuesArrayReturnsEmptyArrayWhenNoValuesSet(): void
	{
		$this->baseParameters->addParameter('param1', ScalarType::STRING, 'default1');
		$this->baseParameters->addParameter('param2', ScalarType::INT, 10);

		$result = $this->baseParameters->getInputValuesArray();

		$this->assertSame([], $result);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testGetInputValuesArrayBehavesCorrectlyWithUnsetValues(): void
	{
		$this->baseParameters->addParameter('param1', ScalarType::STRING, 'default1');
		$this->baseParameters->setValueOfParameter('param1', 'value1');
		$this->baseParameters->addParameter('param2', ScalarType::INT, 10);

		$result = $this->baseParameters->getInputValuesArray();

		$this->assertSame(['value1'], $result);
	}

	#[Group('units')]
	public function testAddOwnerSuccessfullyAddsOwnerParameter(): void
	{
		$this->baseParameters->addOwner();

		$parameters = $this->baseParameters->getInputParametersArray();
		$this->assertArrayHasKey(BaseParameters::PARAMETER_UID, $parameters);
		$this->assertSame(ScalarType::INT, $parameters[BaseParameters::PARAMETER_UID]['scalar_type']);
		$this->assertSame(0, $parameters[BaseParameters::PARAMETER_UID]['default_value']);
		$this->assertFalse($parameters[BaseParameters::PARAMETER_UID]['parsed']);
	}

	#[Group('units')]
	public function testAddParameterSuccessfullyAddsParameter(): void
	{
		$parameterName = 'testParam';
		$scalarType = ScalarType::STRING;
		$defaultValue = 'defaultValue';

		$this->baseParameters->addParameter($parameterName, $scalarType, $defaultValue);

		$parameters = $this->baseParameters->getInputParametersArray();
		$this->assertArrayHasKey($parameterName, $parameters);
		$this->assertSame($scalarType, $parameters[$parameterName]['scalar_type']);
		$this->assertSame($defaultValue, $parameters[$parameterName]['default_value']);
		$this->assertFalse($parameters[$parameterName]['parsed']);
	}

	#[Group('units')]
	public function testRemoveParameterSuccessfullyRemovesParameter(): void
	{
		$parameterName = 'testParam';
		$this->baseParameters->addParameter($parameterName, ScalarType::STRING, 'defaultValue');

		$this->baseParameters->removeParameter($parameterName);

		$parameters = $this->baseParameters->getInputParametersArray();
		$this->assertArrayNotHasKey($parameterName, $parameters, 'Parameter should be removed.');
	}

	#[Group('units')]
	public function testRemoveParameterDoesNotThrowWhenParameterDoesNotExist(): void
	{
		$parameterName = 'testParam';
		$this->baseParameters->addParameter($parameterName, ScalarType::STRING, 'defaultValue');

		$this->assertCount(1, $this->baseParameters->getInputParametersArray());

		$parameterName = 'nonExistentParam';
		$this->baseParameters->removeParameter($parameterName);

		$this->assertCount(1, $this->baseParameters->getInputParametersArray());
	}

	#[Group('units')]
	public function testRemoveParameters(): void
	{
		$current_filter_count = 0;

		$this->assertEquals($this->baseParameters, $this->baseParameters->addParameter('new_param1', ScalarType::STRING, 'default_this_is1'));
		$this->assertEquals($this->baseParameters, $this->baseParameters->addParameter('new_param2', ScalarType::STRING, 'default_this_is2'));
		$this->assertEquals($this->baseParameters, $this->baseParameters->addParameter('new_param3', ScalarType::STRING, 'default_this_is3'));
		$this->assertEquals($this->baseParameters, $this->baseParameters->addParameter('new_param4', ScalarType::STRING, 'default_this_is4'));

		$filters = $this->baseParameters->getInputParametersArray();
		$this->assertArrayHasKey('new_param1', $filters);
		$this->assertArrayHasKey('new_param2', $filters);
		$this->assertArrayHasKey('new_param3', $filters);
		$this->assertArrayHasKey('new_param4', $filters);
		$this->assertCount($current_filter_count + 4, $filters);

		$this->assertEquals($this->baseParameters, $this->baseParameters->removeParameters(array('new_param1', 'new_param2', 'new_param4')));
		$filters = $this->baseParameters->getInputParametersArray();
		$this->assertArrayNotHasKey('new_param1', $filters);
		$this->assertArrayNotHasKey('new_param2', $filters);
		$this->assertArrayHasKey('new_param3', $filters);
		$this->assertArrayNotHasKey('new_param4', $filters);
		$this->assertCount($current_filter_count + 1, $filters);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testGetValueOfParameterReturnsExpectedValue(): void
	{
		$this->baseParameters->addParameter('testParam', ScalarType::STRING, 'defaultValue');
		$this->baseParameters->setValueOfParameter('testParam', 'actualValue');

		$result = $this->baseParameters->getValueOfParameter('testParam');

		$this->assertSame('actualValue', $result);
	}

	#[Group('units')]
	public function testGetValueOfParameterThrowsExceptionWhenParameterNotExists(): void
	{
		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('A parameter with name: notExistsParam is not found.');

		$this->baseParameters->addParameter('testParam', ScalarType::STRING, 'defaultValue');

		$this->baseParameters->getValueOfParameter('notExistsParam');
	}

	#[Group('units')]
	public function testGetValueOfParameterThrowsExceptionWhenValueNotSet(): void
	{
		$this->baseParameters->addParameter('testParam', ScalarType::STRING, 'defaultValue');

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('A value for parameter with name: testParam is not set.');

		$this->baseParameters->getValueOfParameter('testParam');
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testSetValueOfParameterSuccessfullySetsValue(): void
	{
		$this->baseParameters->addParameter('testParam', ScalarType::STRING, 'defaultValue');

		$this->baseParameters->setValueOfParameter('testParam', 'newValue');

		$parameters = $this->baseParameters->getInputParametersArray();
		$this->assertArrayHasKey('testParam', $parameters);
		$this->assertSame('newValue', $parameters['testParam']['value']);
	}

	#[Group('units')]
	public function testSetValueOfParameterThrowsExceptionForNonExistentParameter(): void
	{
		$this->baseParameters->addParameter('testParam', ScalarType::STRING, 'defaultValue');

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('A parameter with name: invalidParam is not found.');

		$this->baseParameters->setValueOfParameter('invalidParam', 'value');
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testSetDefaultForParameterSuccessfullySetsDefaultValue(): void
	{
		$this->baseParameters->addParameter('testParam', ScalarType::STRING, 'defaultValue');

		$this->baseParameters->setDefaultForParameter('testParam', 'newDefaultValue');

		$this->assertSame(
			'newDefaultValue',
			$this->baseParameters->getDefaultValueOfParameter('testParam'),
			'The default value of the parameter was not updated correctly.'
		);
	}

	#[Group('units')]
	public function testSetDefaultForParameterThrowsExceptionForNonExistentParameter(): void
	{
		$this->baseParameters->addParameter('testParam', ScalarType::STRING, 'defaultValue');

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('A parameter with name: invalidParam is not found.');

		$this->baseParameters->setDefaultForParameter('invalidParam', 'value');
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testSetDefaultForParameterCorrectlyUpdatesDefaultValue(): void
	{
		$this->baseParameters->addParameter('testParam', ScalarType::STRING, 'defaultValue');
		$this->assertSame(
			'defaultValue',
			$this->baseParameters->getDefaultValueOfParameter('testParam'),
			'The initial default value is incorrect.'
		);

		$this->baseParameters->setDefaultForParameter('testParam', 'updatedDefaultValue');
		$this->assertSame(
			'updatedDefaultValue',
			$this->baseParameters->getDefaultValueOfParameter('testParam'),
			'The default value of the parameter was not updated correctly.'
		);
	}

	#[Group('units')]
	public function testGetDefaultForParameterFails(): void
	{
		$this->baseParameters->addParameter('testParam', ScalarType::STRING, 'defaultValue');

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('A parameter with name: invalidParam is not found.');

		$this->baseParameters->getDefaultValueOfParameter('invalidParam');
	}

	#[Group('units')]
	public function testGetInputParametersKeysReturnsCorrectKeys(): void
	{
		$this->baseParameters->addParameter('param1', ScalarType::STRING, 'default1');
		$this->baseParameters->addParameter('param2', ScalarType::INT, 10);

		$result = $this->baseParameters->getInputParametersKeys();

		$this->assertSame(['param1', 'param2'], $result);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testParseInputAllParametersParsesDefaultValuesWhenNoUserInputs(): void
	{
		$this->baseParameters->addParameter('param1', ScalarType::STRING, 'default1');
		$this->baseParameters->addParameter('param2', ScalarType::INT, 42);
		$this->baseParameters->setUserInputs([]);

		$this->sanitizerMock->expects($this->once())->method('string')->with('default1')->willReturn('default1');
		$this->sanitizerMock->expects($this->once())->method('int')->with(42)->willReturn(42);

		$this->baseParameters->parseInputAllParameters();
		$this->assertSame('default1', $this->baseParameters->getValueOfParameter('param1'));
		$this->assertSame(42, $this->baseParameters->getValueOfParameter('param2'));
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testParseInputAllParametersParsesValuesFromUserInputs(): void
	{
		$this->baseParameters->addParameter('param1', ScalarType::STRING, 'default1');
		$this->baseParameters->addParameter('param2', ScalarType::INT, 42);
		$this->baseParameters->setUserInputs(['param1' => 'userValue1', 'param2' => 84]);

		$this->sanitizerMock->expects($this->once())->method('string')->with('userValue1')->willReturn('userValue1');
		$this->sanitizerMock->expects($this->once())->method('int')->with(84)->willReturn(84);

		$this->baseParameters->parseInputAllParameters();

		$this->assertSame('userValue1', $this->baseParameters->getValueOfParameter('param1'));
		$this->assertSame(84, $this->baseParameters->getValueOfParameter('param2'));
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testParseInputAllParametersMarksParametersAsParsed(): void
	{
		$this->baseParameters->addParameter('param1', ScalarType::STRING, 'default1');
		$this->baseParameters->addParameter('param2', ScalarType::INT, 42);
		$this->baseParameters->setUserInputs(['param1' => 'default1', 'param2' => 42]);

		$this->baseParameters->parseInputAllParameters();
		$parameters = $this->baseParameters->getInputParametersArray();

		$this->assertTrue($parameters['param1']['parsed']);
		$this->assertTrue($parameters['param2']['parsed']);
	}

	#[Group('units')]
	public function testParseInputFilterNoParameter(): void
	{
		$this->baseParameters->addParameter('param1', ScalarType::STRING, 'default1');

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('A parameter with name: testParam is not found.');

		$this->baseParameters->parseInputFilterByName('testParam');
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testParseInputFilterSetParsedFalse(): void
	{
		$this->baseParameters->addParameter('testParam', ScalarType::STRING, 'defaultValue');

		$this->baseParameters->setUserInputs([]);

		$this->sanitizerMock->expects($this->exactly(2))
			->method('string')
			->willReturnMap([
				['defaultValue', 'defaultValue'],
				['userValue', 'userValue'],
			]);

		$this->baseParameters->parseInputFilterByName('testParam');
		$result = $this->baseParameters->getValueOfParameter('testParam');
		$this->assertSame('defaultValue', $result);

		$this->baseParameters->setUserInputs(['testParam' => 'userValue']);
		$this->baseParameters->parseInputFilterByName('testParam');

	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testParseInputFilterNotParseDouble(): void
	{
		$this->baseParameters->addParameter('testParam', ScalarType::STRING, 'defaultValue');

		$this->baseParameters->setUserInputs([]);

		$this->sanitizerMock->expects($this->once())
			->method('string')
			->with('defaultValue')
			->willReturn('defaultValue');

		$this->baseParameters->parseInputFilterByName('testParam');
		$result = $this->baseParameters->getValueOfParameter('testParam');
		$this->assertSame('defaultValue', $result);

		$this->baseParameters->parseInputFilterByName('testParam');
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testParseInputFilterByNameHandlesScalarTypeSanitization(): void
	{
		$this->baseParameters->addParameter('intParam', ScalarType::INT, 10);
		$this->baseParameters->addParameter('boolParam', ScalarType::BOOLEAN, false);
		$this->baseParameters->setUserInputs(['intParam' => '42', 'boolParam' => 'true']);

		$this->sanitizerMock->expects($this->once())
			->method('int')
			->with('42')
			->willReturn(42);
		$this->sanitizerMock->expects($this->once())
			->method('bool')
			->with('true')
			->willReturn(true);

		$this->baseParameters->parseInputFilterByName('intParam');
		$this->baseParameters->parseInputFilterByName('boolParam');

		$this->assertSame(42, $this->baseParameters->getValueOfParameter('intParam'));
		$this->assertTrue($this->baseParameters->getValueOfParameter('boolParam'));
	}
}
