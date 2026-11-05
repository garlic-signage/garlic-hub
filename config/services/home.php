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

use App\Controller\HomeController;
use App\Framework\Dashboards\DashboardsAggregator;
use App\Framework\Dashboards\SystemDashboard;
use App\Modules\Player\Dashboard\PlayerDashboard;
use App\Modules\Playlists\Dashboard\PlaylistsDashboard;
use Psr\Container\ContainerInterface;

$dependencies = [];

$dependencies[DashboardsAggregator::class] = DI\factory(function (ContainerInterface $container)
{
	$aggregator = new DashboardsAggregator();

	$aggregator->registerDashboard($container->get(PlayerDashboard::class));

	$aggregator->registerDashboard($container->get(PlayerDashboard::class));
	$aggregator->registerDashboard($container->get(PlaylistsDashboard::class));
	$aggregator->registerDashboard($container->get(SystemDashboard::class));

	return $aggregator;
});
$dependencies[HomeController::class] = DI\factory(function (ContainerInterface $container)
{
	return new HomeController(
		$container->get(DashboardsAggregator::class)
	);
});

return $dependencies;