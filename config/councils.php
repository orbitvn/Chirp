<?php

/*
 * Road controlling authorities Chirp can work with. Each one is a separate RAMM
 * database (same RAMM login); users switch between them from the maps page.
 *
 * fwp_source: the RAMM table holding the Forward Works Programme.
 * roads_url: live GeoJSON road centrelines (road_id + Roadname). When null the
 * road network is built from that council's RAMM offline bundle instead.
 */
return [

    'default' => env('COUNCIL_DEFAULT', 'hastings'),

    'list' => [

        'hastings' => [
            'name'          => 'Hastings District Council',
            'short'         => 'Hastings',
            'ramm_database' => env('RAMM_DATABASE', 'Hastings District Council'),
            'center'        => [-39.5661, 176.8750],
            'zoom'          => 11,
            'roads_url'     => 'https://services1.arcgis.com/8L3DQUzjrkgEmDpQ/arcgis/rest/services/OpenData_RoadCentrelines/FeatureServer/1/query'
                . '?where=1%3D1&outFields=road_id%2CRoadname&outSR=4326&f=geojson',
            'roads_attribution' => 'Roads &copy; <a href="https://data-hdcgis.opendata.arcgis.com/" target="_blank" rel="noopener">Hastings DC</a> / NZTA',
            'fwp_source'    => 'ud_fwp_works',           // user-defined FWP table
        ],

        'wairoa' => [
            'name'          => 'Wairoa District Council',
            'short'         => 'Wairoa',
            'ramm_database' => env('RAMM_DATABASE_WAIROA', 'Wairoa District Council'),
            'center'        => [-39.0333, 177.4167],
            'zoom'          => 10,
            'roads_url'     => null,
            'roads_attribution' => 'Roads &copy; Wairoa DC (RAMM)',
            'fwp_source'    => 'fw_forward_work_view',   // RAMM "Forward Work Treatment View"
        ],

    ],

];
