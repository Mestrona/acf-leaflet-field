<?php
/*
    Plugin Name: Advanced Custom Fields: Leaflet Field *do not update!*
    Plugin URI: https://github.com/jensjns/acf-leaflet-field
    Description: Adds a Leaflet map-field to Advanced Custom Fields.
    Version: 1.2.1
    Author: Jens Nilsson
    Author URI: http://jensnilsson.nu/
    License: GPLv2 or later
    License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/


class acf_field_leaflet_field_plugin
{
    /*
    *  Construct
    *
    *  @description:
    *  @since: 3.6
    *  @created: 1/04/13
    */

    function __construct()
    {
        // set text domain
        $domain = 'acf-leaflet_field';
        $mofile = trailingslashit(dirname(__File__)) . 'lang/' . $domain . '-' . get_locale() . '.mo';
        load_textdomain( $domain, $mofile );

	    add_action( 'rest_api_init', function () {
		    register_rest_route( 'acf_leaflet_field/v1', 'geodata/(?P<id>\d+).geojson', array(
				    'methods'  => WP_REST_Server::READABLE,
				    'callback' => array( $this, 'get_geodata' ),
				    'args' => array(
					    'id' => array(
						    'validate_callback' => function($param, $request, $key) {
							    return is_numeric( $param );
						    }
					    ),
				    )
			    )
		    );

	    });


	    // version 5 (PRO)
        add_action('acf/include_field_types', array($this, 'register_fields_v5'));

        // version 4+
        add_action('acf/register_fields', array($this, 'register_fields_v4'));

        // version 3-
        if(function_exists('register_field'))
        {
            add_action( 'init', array( $this, 'init' ));
        }
    }


    function get_geodata( WP_REST_Request $request)
    {
        $post_id = $request['id'];
        $field_name = sanitize_key($request['field']);

	    header('Content-Description: File Transfer');
	    header('Content-Type: text/json');
	    header('Content-Disposition: attachment; filename="' . $field_name . '-' . $post_id . '.geojson"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');

	    $field_obj = get_field_object(
		    $field_name,
		    $post_id,
		    array(
			    'load_value' => true
		    )
	    );

        $result = $field_obj['value']->drawnItems;

        foreach ($field_obj['value']->markers as $marker)  {
	        $result->features[] = $marker;
        }

        return $result;
    }
    /*
    *  Init
    *
    *  @description:
    *  @since: 3.6
    *  @created: 1/04/13
    */

    function init()
    {
        register_field('acf_field_leaflet_field', dirname(__File__) . '/leaflet_field-v3.php');
    }

    /*
    *  register_fields
    *
    *  @description:
    *  @since: 3.6
    *  @created: 1/04/13
    */

    function register_fields_v4()
    {
        include_once('leaflet_field-v4.php');
    }

    function register_fields_v5()
    {
        include_once('leaflet_field-v5.php');
    }

}

new acf_field_leaflet_field_plugin();


    /**
     * Render a leaflet field
     * Usually called internally
     * Required by advanced-custom-field-leaflet-field-aggregator Plugin
     *
     * @param object $field_obj Field Object
     */
    function acf_lf_render_direct($field_obj)
    {
        $field_obj['map_provider'] = acf_field_leaflet_field::$map_providers[$field_obj['map_provider']];
        
        if( $field_obj['map_provider']['requires_key'] ) {
            $field_obj['map_provider']['url'] = str_replace( '{api_key}', $field_obj['api_key'], $field_obj['map_provider']['url'] );
        }
        // FIXME: combine common code with \acf_field_leaflet_field::create_field
        // FIXME: API KEy of base map provider must be the same as all additional providers
        if (is_array($field_obj['additional_map_providers'] ?? null)) {
            foreach ($field_obj['additional_map_providers'] as $key => $mapProvider) {
            	$providerData  = acf_field_leaflet_field::$map_providers[$mapProvider];
	            $providerData['url'] = str_replace( '{api_key}', $field_obj['api_key'], $providerData['url'] );
                $field_obj['additional_map_providers'][$key] = $providerData;
            }
        }

        // enqueue styles
        wp_enqueue_style( 'leaflet', plugins_url( '/js/leaflet/leaflet.css', __FILE__ ), array(), '1.0.2', 'all' );
        wp_enqueue_style( 'icomoon', plugins_url( '/css/icomoon/style.css', __FILE__ ), array(), '1.0.0', 'all' );
        wp_enqueue_style( 'leaflet-field', plugins_url( '/css/input.css', __FILE__ ), array( 'leaflet', 'icomoon' ), '1', 'all' );

        // enqueue scripts
        wp_enqueue_script( 'jquery' );
        wp_enqueue_script( 'leaflet', plugins_url( '/js/leaflet/leaflet.js', __FILE__ ), array(), '1.0.2', true );
        wp_enqueue_script( 'leaflet-frontend', plugins_url( '/js/leaflet-frontend.js', __FILE__ ), array( 'jquery', 'leaflet' ), '1.2.2', true );
        wp_localize_script( 'leaflet-frontend', 'leaflet_field', $field_obj );
        echo '<div id="' . $field_obj['prefix'] . '-field-' . $field_obj['name'] . '_map" class="leaflet-map"' . ($field_obj['height'] ? ' style="height:' . $field_obj['height'] . 'px;"' : '') . '></div>';
    }

    /**
     *  the_leaflet_field()
     *
     *  Renders leaflet field
     *
     *  @param   $field_name - Required, The name of the field
     *  @param   $post_id - Optional, the id of the post (will try to render for the current page if no id is specified)
     *
     *  @since   0.1.0
     *  @date    10/04/13
     */
    function the_leaflet_field( $field_name, $post_id = false ) {
        if( !$post_id ) {
            global $post;
            $post_id = $post->ID;
        }

        $field_obj = get_field_object(
            $field_name,
            $post_id,
            array(
                'load_value' => true
            )
        );

        if( $field_obj['value'] ) {
            if (is_string($field_obj['value'])) {
                $field_obj['value'] = json_decode($field_obj['value']);
            }
            if (function_exists('get_post_color')) {
                $field_obj['value']->color = get_post_color($post_id);
            } else {
                $field_obj['value']->color = '#000000';
            }

            acf_lf_render_direct($field_obj);
        }
    }

