function acf_leaflet_field_render_features(map, map_settings) {
    mapData = leafield_field_map_objects[leaflet_field.id];
    map = mapData.map;
    featureLayers = mapData.featureLayers;
    if (Object.keys(map_settings.markers).length > 0 || (map_settings.drawnItems && map_settings.drawnItems.features.length > 0)) {
        jQuery.each(map_settings.markers, function (index, marker) {
            L.geoJson(marker, {
                onEachFeature: function (feature, layer) {
                    if (feature.properties && feature.properties.popupContent && feature.properties.popupContent != "") {
                        layer.bindPopup(feature.properties.popupContent);
                    }
                }
            }).addTo(featureLayers);
        });

        L.geoJson(map_settings.drawnItems, {
            onEachFeature: function (feature, layer) {
                layer.options.color = "#000000";
            }
        }).addTo(featureLayers);
    }
}

jQuery(document).ready(function($) {
    // only render the map if an api-key is present
    render_leaflet_map();

    function render_leaflet_map() {
        if( typeof leaflet_field.value == 'object' ) {
            map_settings = leaflet_field.value;
        }
        else if(typeof leaflet_field.value == 'string') {
            map_settings = JSON.parse(leaflet_field.value);
        }
        else {
            map_settings = {
                zoom_level:null,
                center:{
                    lat:null,
                    lng:null
                },
                markers:{}
            };
        }

        var bounds = null;
        if( map_settings.center.lat == null ) {
            map_settings.center.lat = leaflet_field.lat;
            map_settings.center.lng = leaflet_field.lng;

            if (leaflet_field.lat2 != null) {
                bounds = L.latLngBounds(L.latLng(leaflet_field.lat, leaflet_field.lng),
                    L.latLng(leaflet_field.lat2, leaflet_field.lng2));
            }
        }

        // check if the zoom level is set and within 1-18
        if( map_settings.zoom_level == null || map_settings.zoom_level > 18 || map_settings.zoom_level < 1 ) {
            if( leaflet_field.zoom_level > 0 && leaflet_field.zoom_level < 19 ) {
                map_settings.zoom_level = leaflet_field.zoom_level;
            }
            else {
                map_settings.zoom_level = 13;
            }
        }

        var map = L.map( leaflet_field.id + '_map', {
            center: new L.LatLng( map_settings.center.lat, map_settings.center.lng ),
            zoom: map_settings.zoom_level,
            doubleClickZoom: true,
            scrollWheelZoom: false
        });

        map.once('focus', function() { map.scrollWheelZoom.enable(); });

        var addControl = function(toolName, icon, active, funct) { // FIXME: do not duplicate so much code from backend -> lib
            active = (active) ? ' active' : '';
            var newControl = L.control({position: 'topleft'});

            newControl.onAdd = function () {
                this._div = L.DomUtil.create('div', 'tool tool-' + toolName + ' icon-' + icon + active);

                var stop = L.DomEvent.stopPropagation;

                L.DomEvent
                    .on(this._div, 'click', stop)
                    .on(this._div, 'mousedown', stop)
                    .on(this._div, 'dblclick', stop)
                    .on(this._div, 'click', L.DomEvent.preventDefault)
                    .on(this._div, 'click', funct);

                return this._div;
            };

            newControl.addTo(map);
        }

        // global hash of all the map objects
        if (typeof leafield_field_map_objects == 'undefined') {
            leafield_field_map_objects = {};
        }
        var featureLayers = L.layerGroup();
        featureLayers.addTo(map);
        var lowZoomMarkerLayers = L.layerGroup();
        lowZoomMarkerLayers.addTo(map);
        leafield_field_map_objects[leaflet_field.id] = {map: map, featureLayers: featureLayers, lowZoomMarkerLayers: lowZoomMarkerLayers };

        L.tileLayer(leaflet_field.map_provider.url, {
            attribution: leaflet_field.map_provider.attribution,
            maxZoom: 18
        }).addTo(map);

        acf_leaflet_field_render_features(leaflet_field.id, map_settings);

        if (bounds) {
            map.fitBounds(bounds);
        }

        addControl('refocus', 'map-pin-stroke', true, function() {
            map.fitBounds(bounds); // fixme: also support center refocus
        });


        if (typeof after_render_leaflet_map == 'function') {
            after_render_leaflet_map();
        }
    }

});