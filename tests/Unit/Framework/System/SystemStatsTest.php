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

namespace Tests\Unit\Framework\System;

use App\Framework\Exceptions\CoreException;
use App\Framework\System\SystemStats;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Support\ScriptedShellExecutor;

/**
 * SystemStats parses what free and df answer. The shell is replaced by answers of real machines.
 */
class SystemStatsTest extends TestCase
{
	use PHPMock;

	private const array NO_RAM  = ['total' => 0, 'used' => 0, 'free' => 0];
	private const array NO_DISC = ['size' => '', 'used' => '', 'available' => '', 'percent' => ''];
	private const array NO_LOAD = ['1_min' => '', '5_min' => '', '15_min' => ''];

	private const string FREE_EN = "               total        used        free      shared  buff/cache   available\nMem:            7940        3000        1000         100        3940        4500\nSwap:           2047           0        2047\n";
	private const string FREE_DE = "                gesamt     benutzt       frei  gemns. Puffer/Cache  verfügbar\nSpeicher:         7940        3000        1000         100        3940        4500\nSwap:             2047           0        2047\n";
	private const string DF      = "Filesystem      Size  Used Avail Use% Mounted on\n/dev/sda1       100G   60G   40G  60% /\ntmpfs           3.9G     0  3.9G   0% /dev/shm\n/dev/sdb1       200G   15G  185G   8% /data\ntotal           304G   75G  225G  25% -\n";

	private ScriptedShellExecutor $shell;
	private SystemStats $systemStats;

	protected function setUp(): void
	{
		parent::setUp();
		$this->shell       = new ScriptedShellExecutor(['free -m' => self::FREE_EN, 'df -h --total' => self::DF]);
		$this->systemStats = new SystemStats($this->shell);
	}

	#[Group('units')]
	public function testIsLinux(): void
	{
		$this->systemStats->setIsLinux(true);
		static::assertTrue($this->systemStats->isLinux());

		$this->systemStats->setIsLinux(false);
		static::assertFalse($this->systemStats->isLinux());
	}

	#[Group('units')]
	public function testTheStatsAreEmptyBeforeTheyAreDetermined(): void
	{
		static::assertSame(self::NO_RAM, $this->systemStats->getRamStats());
		static::assertSame(self::NO_DISC, $this->systemStats->getDiscInfo());
		static::assertSame(self::NO_LOAD, $this->systemStats->getLoadData());
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	#[DataProvider('freeProvider')]
	public function testDetermineRamStats(string $output): void
	{
		$this->shell->outputs['free -m'] = $output;

		$this->systemStats->determineRamStats();

		static::assertSame(['total' => 7940, 'used' => 3000, 'free' => 1000], $this->systemStats->getRamStats());
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function freeProvider(): array
	{
		return ['english' => [self::FREE_EN], 'german' => [self::FREE_DE]];
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	#[DataProvider('invalidFreeProvider')]
	public function testDetermineRamStatsHandlesInvalidOutput(string $output): void
	{
		$this->shell->outputs['free -m'] = $output;

		$this->systemStats->determineRamStats();

		static::assertSame(self::NO_RAM, $this->systemStats->getRamStats());
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function invalidFreeProvider(): array
	{
		return [
			'text'                => ['invalid output'],
			'empty'               => [''],
			'only the header'     => ["               total        used        free\n"],
			'row without numbers' => ["header\nMem:"],
			'row too short'       => ["header\nMem: 8000"],
			'row still too short' => ["header\nMem: 8000 6000"],
		];
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testDetermineDiskUsage(): void
	{
		$this->systemStats->determineDiskUsage();

		static::assertSame(['size' => '304G', 'used' => '75G', 'available' => '225G', 'percent' => '25%'], $this->systemStats->getDiscInfo());
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	#[DataProvider('invalidDfProvider')]
	public function testDetermineDiskUsageHandlesInvalidOutput(string $output): void
	{
		$this->shell->outputs['df -h --total'] = $output;

		$this->systemStats->determineDiskUsage();

		static::assertSame(self::NO_DISC, $this->systemStats->getDiscInfo());
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function invalidDfProvider(): array
	{
		return [
			'text'                => ['Invalid data format'],
			'empty'               => [''],
			'no sum line'         => ["Filesystem Size Used Avail Use% Mounted on\n/dev/sda1 100G 60G 40G 60% /\n"],
			'sum line too short'  => ["Filesystem Size Used Avail Use% Mounted on\ntotal 100G 75G"],
			'file system named total' => ["Filesystem Size Used Avail Use% Mounted on\ntotally-fake 1G 1G 0 100% /mnt\n"],
		];
	}

	/**
	 * A file system whose name starts with "total" is no sum line, wherever it is listed.
	 *
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testAFileSystemNamedLikeTheSumLineIsIgnored(): void
	{
		$this->shell->outputs['df -h --total'] = self::DF . "totally-fake        1G    1G     0 100% /mnt\n";

		$this->systemStats->determineDiskUsage();

		static::assertSame(['size' => '304G', 'used' => '75G', 'available' => '225G', 'percent' => '25%'], $this->systemStats->getDiscInfo());
	}

	/**
	 * @param array<int,float>|false $load
	 * @param array<string,string> $expected
	 */
	#[RunInSeparateProcess] #[Group('units')]
	#[DataProvider('loadProvider')]
	public function testDetermineSystemLoad(array|false $load, array $expected): void
	{
		$getLoadAvg = $this->getFunctionMock('App\Framework\System', 'sys_getloadavg');
		$getLoadAvg->expects($this->once())->willReturn($load);

		$this->systemStats->determineSystemLoad();

		static::assertSame($expected, $this->systemStats->getLoadData());
		static::assertSame([], $this->shell->commands, 'The load is read without the shell.');
	}

	/**
	 * @return array<string, array{array<int,float>|false, array<string,string>}>
	 */
	public static function loadProvider(): array
	{
		return [
			'normal'         => [[1.23, 0.56, 2.03], ['1_min' => '1.23', '5_min' => '0.56', '15_min' => '2.03']],
			'rounded'        => [[12.345, 0.0, 1.0], ['1_min' => '12.35', '5_min' => '0.00', '15_min' => '1.00']],
			'big numbers'    => [[1234.5, 0.0, 0.0], ['1_min' => '1234.50', '5_min' => '0.00', '15_min' => '0.00']],
			'too few values' => [[1.23, 0.56], self::NO_LOAD],
			'not available'  => [false, self::NO_LOAD],
		];
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testDetermineSystemStatsOnLinuxReadsEverything(): void
	{
		$this->systemStats->setIsLinux(true);

		$this->systemStats->determineSystemStats();

		static::assertSame(['total' => 7940, 'used' => 3000, 'free' => 1000], $this->systemStats->getRamStats());
		static::assertSame('304G', $this->systemStats->getDiscInfo()['size']);
		static::assertMatchesRegularExpression('/^\d+\.\d\d$/', $this->systemStats->getLoadData()['1_min']);
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testDetermineSystemStatsHandlesNonLinuxSystems(): void
	{
		$this->systemStats->setIsLinux(false);

		$this->systemStats->determineSystemStats();

		static::assertSame([], $this->shell->commands, 'Nothing is started on a system without free and df.');
		static::assertSame(self::NO_RAM, $this->systemStats->getRamStats());
		static::assertSame(self::NO_DISC, $this->systemStats->getDiscInfo());
		static::assertSame(self::NO_LOAD, $this->systemStats->getLoadData());
	}

	#[Group('units')]
	public function testAFailingCommandIsNotSwallowed(): void
	{
		$this->systemStats->setIsLinux(true);
		$this->shell->outputs = [];

		$this->expectException(CoreException::class);
		$this->expectExceptionMessage('Unexpected command: free -m');

		$this->systemStats->determineSystemStats();
	}
}
