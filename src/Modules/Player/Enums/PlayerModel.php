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

namespace App\Modules\Player\Enums;

/**
 * Enum representing different types of player models.
 *
 * Each constant corresponds to a specific player model or software,
 * along with its features or compatibility level.
 */
enum PlayerModel: int
{
	case UNKNOWN        = 0; // IAdea 1x0 Series SD Video 1920px images
	case IADEA_XMP1X0   = 1; // IAdea 1x0 Series SD Video 1920px images
	case IADEA_XMP3X0   = 2; // IAdea 3x0 Series +HD Video
	case IADEA_XMP3X50  = 3; // IAdea 3x50 Series +HTML5
	case COMPATIBLE     = 4; // fs5 Kathrein crap with only h264 in ts Container
	case IADEA_XMP2X00  = 5; // IAdea new 2000, 6000 and 7000 (4K) Android Series with new xml config and SMIL Structure
	case GARLIC         = 6; // Sagiadinos open source software player garlic
	case IDS            = 7; // iDSPlayer
	case QBIC           = 8; // QBiC Signage Player
	case SCREENLITE     = 9; // https://github.com/screenlite

}