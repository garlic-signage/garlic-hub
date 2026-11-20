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

namespace Tests\Integration\Framework\Http;

use App\Framework\Config\ConfigOverrides;
use App\Framework\Http\ClientIp;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;

/**
 * The trusted proxies of the installation: the default of config/settings, a change in var/settings survives
 * an update.
 */
#[Group('acl')]
class ClientIpTest extends ContainerTestCase
{
	public function testThePrivateNetworksAreTrustedByDefault(): void
	{
		$clientIp = $this->container->get(ClientIp::class);

		static::assertSame('203.0.113.7', $clientIp->resolve(['REMOTE_ADDR' => '172.18.0.1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.7']));
	}

	public function testAChangeOfTheAdminCounts(): void
	{
		new ConfigOverrides(self::runtimeDir() . '/settings')->save('main', ['Proxy' => ['trusted_proxies' => '198.51.100.1']]);

		$clientIp = $this->container->get(ClientIp::class);

		static::assertSame('203.0.113.7', $clientIp->resolve(['REMOTE_ADDR' => '198.51.100.1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.7']));
		static::assertSame('172.18.0.1', $clientIp->resolve(['REMOTE_ADDR' => '172.18.0.1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.7']));
	}
}
