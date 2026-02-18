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

use App\Controller\HomeController;
use App\Framework\Middleware\FinalRenderMiddleware;
use App\Modules\Auth\LoginController;
use App\Modules\Auth\OAuth2Controller;
use App\Modules\Mediapool\Controller\MediaController;
use App\Modules\Mediapool\Controller\NodesController;
use App\Modules\Mediapool\Controller\ShowController;
use App\Modules\Mediapool\Controller\UploadController;
use App\Modules\Player\Controller\PlayerController;
use App\Modules\Player\Controller\PlayerPlaylistController;
use App\Modules\Player\Controller\PlayerIndexController;
use App\Modules\Player\Controller\ShowConnectivityController;
use App\Modules\Playlists\Controller\ConditionalPlayController;
use App\Modules\Playlists\Controller\ExportController;
use App\Modules\Playlists\Controller\ItemsController;
use App\Modules\Playlists\Controller\PlaylistsController;
use App\Modules\Playlists\Controller\ShowComposeController;
use App\Modules\Playlists\Controller\ShowDatatableController;
use App\Modules\Playlists\Controller\ShowSettingsController;
use App\Modules\Playlists\Controller\TriggerController;
use App\Modules\Playlists\Controller\WidgetsController;
use App\Modules\Profile\Controller\EditLocalesController;
use App\Modules\Profile\Controller\ShowPasswordController;
use App\Modules\Users\Controller\ShowAdminController;
use App\Modules\Users\Controller\ShowInitialAdminController;
use App\Modules\Users\Controller\UsersController;
use App\Modules\Users\Controller\UserTokenController;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

/**
 * @param string[] $controllerCallable
 */
function resolve(array $controllerCallable, ContainerInterface $container): Closure
{
	if (count($controllerCallable) !== 2)
		throw new InvalidArgumentException('Controller callable must be an array like [ControllerClass::class, \'methodName\'].');

	[$controllerClass, $methodName] = $controllerCallable;

	return function (Request $request, Response $response, array $args) use ($controllerClass, $methodName, $container)
	{
		$controller = $container->get($controllerClass);
		return $controller->{$methodName}($request, $response, $args);
	};
}
// Do not catch any exception here. This job is done by the Slim Middleware!
/* @var App $app */
/** @phpstan-ignore-next-line */
assert($app instanceof App);
/** @var ContainerInterface $container */
$container = $app->getContainer();

$app->get('/smil-index', resolve([PlayerIndexController::class, 'index'], $container));

$app->group('', function (RouteCollectorProxy $group) use ($container)
{
	$group->get('/', resolve([HomeController::class, 'index'], $container));
	$group->get('/create-initial', resolve([ShowInitialAdminController::class, 'show'], $container));
	$group->post('/create-initial', resolve([ShowInitialAdminController::class, 'store'], $container));
	$group->get('/legals', resolve([HomeController::class, 'legals'], $container));
	$group->get('/login', resolve([LoginController::class, 'showLogin'], $container));
	$group->post('/login', resolve([LoginController::class, 'login'], $container));
	$group->get('/logout', resolve([LoginController::class, 'logout'], $container));
	$group->get('/set-locales/{locale}', resolve([EditLocalesController::class, 'setLocales'], $container));

	$group->get('/users', resolve([\App\Modules\Users\Controller\ShowDatatableController::class, 'show'], $container));
	$group->delete('/users', resolve([\App\Modules\Users\Controller\ShowDatatableController::class, 'delete'], $container));
	$group->post('/users/edit', resolve([ShowAdminController::class, 'store'], $container));
	$group->get('/users/new', resolve([ShowAdminController::class, 'newUserForm'], $container));
	$group->get('/users/edit/{UID:\d+}', resolve([ShowAdminController::class, 'editUserForm'], $container));

	// for later profile call
//	$group->get('/user/{UID:\d}', createControllerCallable([xxxx::class, 'profile'], $container));
	$group->get('/profile/settings', resolve([\App\Modules\Profile\Controller\ShowSettingsController::class, 'show'], $container));
	$group->get('/profile/password', resolve([ShowPasswordController::class, 'showPasswordForm'], $container));
	$group->post('/profile/password', resolve([ShowPasswordController::class, 'store'], $container));
	$group->get('/force-password', resolve([ShowPasswordController::class, 'showForcedPasswordForm'], $container));
	$group->post('/force-password', resolve([ShowPasswordController::class, 'storeForcedPassword'], $container));

	$group->get('/mediapool', resolve([ShowController::class, 'show'], $container));

	$group->get('/playlists', resolve([ShowDatatableController::class, 'show'], $container));
	$group->get('/playlists/settings/{playlist_mode:master|internal|external|multizone|channel}', resolve([ShowSettingsController::class, 'newPlaylistForm'], $container));
	$group->get('/playlists/settings/{playlist_id:\d+}', resolve([ShowSettingsController::class, 'editPlaylistForm'], $container));
	$group->delete('/playlists/settings/{playlist_id:\d+}', resolve([ShowSettingsController::class, 'delete'], $container));
	$group->post('/playlists/settings', resolve([ShowSettingsController::class, 'store'], $container));
	$group->get('/playlists/compose/{playlist_id}', resolve([ShowComposeController::class, 'show'], $container));

	$group->get('/player', resolve([\App\Modules\Player\Controller\ShowDatatableController::class, 'show'], $container));
	$group->get('/player/connectivity/{player_id}', resolve([ShowConnectivityController::class, 'show'], $container));
	$group->post('/player/connectivity', resolve([ShowConnectivityController::class, 'store'], $container));

	$group->get('/templates', resolve([\App\Modules\Templates\Controller\ShowDatatableController::class, 'show'], $container));
	$group->get('/templates/settings/{type:canvas|svg|html}', resolve([\App\Modules\Templates\Controller\ShowSettingsController::class, 'create'], $container));
	$group->get('/templates/settings/{template_id:\d+}', resolve([\App\Modules\Templates\Controller\ShowSettingsController::class, 'edit'], $container));
	$group->post('/templates/settings', resolve([\App\Modules\Templates\Controller\ShowSettingsController::class, 'store'], $container));

	$group->get('/templates/composer/{template_id:\d+}', resolve([\App\Modules\Templates\Controller\ShowSettingsController::class, 'compose'], $container));


})->add($container->get(FinalRenderMiddleware::class));

$app->group('/api', function (RouteCollectorProxy $group) use ($container)
{
	$group->get('/authorize', resolve([OAuth2Controller::class, 'authorize'], $container));
	$group->post('/token', resolve([OAuth2Controller::class, 'token'], $container));
})->add(function ($request, $handler) {return $handler->handle($request)->withHeader('Content-Type', 'text/html');});

$app->group('/async', function (RouteCollectorProxy $group) use ($container)
{

	$group->get('/mediapool/node[/{parent_id:\d+}]', resolve([NodesController::class, 'list'], $container)); // parent_id is optional with []
	$group->post('/mediapool/node', resolve([NodesController::class, 'add'], $container));
	$group->delete('/mediapool/node', resolve([NodesController::class, 'delete'], $container));
	$group->patch('/mediapool/node', resolve([NodesController::class, 'edit'], $container));
	$group->post('/mediapool/node/move', resolve([NodesController::class, 'move'], $container));
	$group->post('/mediapool/uploadLocalFile', resolve([UploadController::class, 'uploadLocalFile'], $container));
	$group->post('/mediapool/uploadFromUrl', resolve([UploadController::class, 'uploadFromUrl'], $container));
	$group->post('/mediapool/searchStockImages', resolve([UploadController::class, 'searchStockImages'], $container));
	$group->get('/mediapool/media/list/{node_id:\d+}',resolve( [MediaController::class, 'list'], $container));
	$group->get('/mediapool/media/{media_id}', resolve([MediaController::class, 'getInfo'], $container));
	$group->post('/mediapool/media', resolve([MediaController::class, 'add'], $container));
	$group->delete('/mediapool/media', resolve([MediaController::class, 'delete'], $container));
	$group->post('/mediapool/media/edit', resolve([MediaController::class, 'edit'], $container));
	$group->post('/mediapool/media/move', resolve([MediaController::class, 'move'], $container));
	$group->post('/mediapool/media/clone', resolve([MediaController::class, 'clone'], $container));

	$group->get('/playlists/find/{playlist_mode:master|internal|external|multizone|channel}[/{playlist_name}]', resolve([PlaylistsController::class, 'findByName'], $container));
	$group->get('/playlists/find/for-player[/{playlist_name}]', resolve([PlaylistsController::class, 'findForPlayerAssignment'], $container));
	$group->delete('/playlists', resolve([PlaylistsController::class, 'delete'], $container));
	$group->put('/playlists', resolve([ExportController::class, 'export'], $container));
	$group->get('/playlists/find/{playlist_id:\d+}', resolve([PlaylistsController::class, 'findById'], $container));
	$group->get('/playlists/multizone/{playlist_id:\d+}', resolve([PlaylistsController::class, 'loadZone'], $container));
	$group->post('/playlists/multizone/{playlist_id:\d+}', resolve([PlaylistsController::class, 'saveZone'], $container));
	$group->patch('/playlists/shuffle', resolve([PlaylistsController::class, 'toggleShuffle'], $container));
	$group->patch('/playlists/picking', resolve([PlaylistsController::class, 'shufflePicking'], $container));

	$group->get('/playlists/items/load/{playlist_id:\d+}', resolve([ItemsController::class, 'loadItems'], $container));
	$group->post('/playlists/items/insert', resolve([ItemsController::class, 'insert'], $container));
	$group->delete('/playlists/items', resolve([ItemsController::class, 'delete'], $container));
	$group->patch('/playlists/items', resolve([ItemsController::class, 'updateItemOrders'], $container));
	$group->get('/playlists/item/{item_id:\d+}', resolve([ItemsController::class, 'fetch'], $container));
	$group->patch('/playlists/item', resolve([ItemsController::class, 'edit'], $container));

	$group->get('/playlists/widget/fetch/{item_id:\d+}', resolve([WidgetsController::class, 'fetch'], $container));
	$group->patch('/playlists/widget/save', resolve([WidgetsController::class, 'save'], $container));
	$group->get('/playlists/item/conditional-play/{item_id:\d+}', resolve([ConditionalPlayController::class, 'fetch'], $container));
	$group->patch('/playlists/item/conditional-play', resolve([ConditionalPlayController::class, 'save'], $container));
	$group->get('/playlists/item/touch-media/{item_id:\d+}', resolve([TriggerController::class, 'fetchMedia'], $container));
	$group->get('/playlists/item/begin-trigger/{item_id:\d+}', resolve([TriggerController::class, 'fetchTrigger'], $container));
	$group->patch('/playlists/item/begin-trigger', resolve([TriggerController::class, 'save'], $container));

	$group->patch('/player/playlist', resolve([PlayerPlaylistController::class, 'replacePlaylist'], $container));
	$group->patch('/player/push', resolve([PlayerPlaylistController::class, 'pushPlaylist'], $container));
	$group->get('/player/acls/{player_id:\d+}', resolve([PlayerController::class, 'determineRights'], $container));
	$group->get('/player/{player_id:\d+}', resolve([PlayerController::class, 'fetchPlayer'], $container));
	$group->delete('/player', resolve([PlayerController::class, 'delete'], $container));

	$group->get('/users/find/{username}', resolve([UsersController::class, 'findByName'], $container));

	$group->post('/profile/tokens', resolve([UserTokenController::class, 'refresh'], $container));
	$group->delete('/profile/tokens', resolve([UserTokenController::class, 'delete'], $container));

})->add(function ($request, $handler) {return $handler->handle($request)->withHeader('Content-Type', 'text/html');});
