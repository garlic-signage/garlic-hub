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
import { LabeledZoneFactory } from './LabeledZoneFactory.js';
import { CanvasView } from './CanvasView.js';
import { ContextMenu } from './ContextMenu.js';
import { ZoneProperties } from './ZoneProperties.js';
import { ZonesModel } from './ZonesModel.js';
import { CanvasEvents } from './CanvasEvents.js';
import { AutocompleteFactory } from '../../../core/AutocompleteFactory.js';
import { PlaylistsService } from "../standard/playlists/PlaylistsService.js";
import { FetchClient } from "../../../core/FetchClient.js";

window.onload = async function ()
{
    let MyCanvas                = new fabric.Canvas("canvas", {stopContextMenu: true, fireRightClick: true, preserveObjectStacking: true})
	MyCanvas.selection = false;

	let MyLabeledZoneFactory    = new LabeledZoneFactory();
    let MyCanvasView            = new CanvasView(MyCanvas);

    const MyAutocompleteFactory = new AutocompleteFactory();
    const MyPlaylistSearch      = MyAutocompleteFactory.create(
        "zone_playlist", "/async/playlists/find/master/"
    );
    MyPlaylistSearch.initWithExistingFields();


    let MyContextMenu           = new ContextMenu(MyCanvasView);
    let MyZoneProperties        = new ZoneProperties(MyCanvasView, MyPlaylistSearch);
    let MyZonesModel            = new ZonesModel(MyCanvasView);
    let MyCanvasEvents          = new CanvasEvents(
        MyZonesModel,
        MyContextMenu,
        MyCanvasView,
        MyZoneProperties,
        MyLabeledZoneFactory,
        new PlaylistsService(new FetchClient())
    );

    let playlist_id = document.getElementById("playlist_id").value;

    try
    {
        await MyZonesModel.loadFromDataBase(playlist_id);

        await MyCanvasEvents.buildUI();
        MyCanvasEvents.initInteractions();
    }
    catch(error)
    {
        console.log("Error while loading zones:", error);

       // ThymianLog.logException("Error while loading zones:", error);
       // jThymian.printError("Error while loading zones:", error);
    }

};