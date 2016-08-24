<?php

namespace WPEM;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

/**
 * Class Image_API
 *
 * Handle fetching of image based on category
 */
final class Image_API {

	/**
	 * Constant used to interact with the API
	 */
	const BASE_URL      = 'https://d3.godaddy.com/api/v1/';
	const IMAGE_ENPOINT = 'stock_photos/';
	const CAT_ENPOINT   = 'categories/';
	const TOKEN         = '53dacdceba099a43ed4fb45b491b16c4afb37d48';

	/**
	 * Hold transient base namespace
	 *
	 * @const string
	 */
	const TRANSIENT_BASE = 'wpem_image_api_';
	const TRANSIENT_KEY_FOR_D3_CATEGORIES = 'wpem_image_api_d3_categories';


	/**
	 * Var to hold full url
	 */
	private $image_cat_url;
	private $category_api_url;

	/**
	 * Image_API constructor.
	 */
	public function __construct() {

		$this->image_cat_url = static::BASE_URL . static::IMAGE_ENPOINT . 'category/%s/';

		$this->category_api_url = static::BASE_URL . static::CAT_ENPOINT;

	}

	/**
	 * Retrieve json response from one category and store it as a transient for later use
	 *
	 * @param string $wpem_cat
	 * @return object array of objects
	 */
	public function get_images_by_cat( $wpem_cat ) {

		if ( false === ( $category = $this->get_api_cat( $wpem_cat ) ) ) {

			return [];

		}

		// Check if we have a transient cached response for that call
		if ( $data = get_transient( static::TRANSIENT_BASE . $category ) ) {

			return $data;

		}

		if ( false === ( $data = $this->fetch_images( $category ) ) ) {

			return [];

		}

		shuffle( $data );

		set_transient( static::TRANSIENT_BASE . $category, $data, HOUR_IN_SECONDS );

		return $data;

	}

	/**
	 * Get and cache D3 categories from their API endpoint
	 * see https://d3.godaddy.com/api/v1/categories/
	 *
	 * @return false if api error, otherwise assoc array of category object's "str_id" => category object
	 */
	public function get_d3_categories() {

		// Check if we have a transient cached response for that call
		if ( $data = get_transient( static::TRANSIENT_KEY_FOR_D3_CATEGORIES ) ) {

			return $data;

		}

		if ( $data = $this->fetch_categories() ) {

			// can use slower cache expiry since the category api endpoint is updated very infrequently
			set_transient( static::TRANSIENT_KEY_FOR_D3_CATEGORIES, $data, DAY_IN_SECONDS );

		}

		return $data;

	}

	/**
	 * Get api category from wpem category
	 *
	 * @param string $wpem_cat
	 *
	 * @return bool|string
	 */
	private function get_api_cat( $wpem_cat ) {

		$list = wpem_get_site_industry_slugs_to( 'api_cat' );

		if ( isset( $list[ $wpem_cat ] ) ) {

			return $list[ $wpem_cat ];

		}

		$d3_categories = $this->get_d3_categories();

		return isset( $d3_categories[ $wpem_cat ] ) ? $wpem_cat : false;

	}

	/**
	 * Helper to fetch infomation from the api
	 *
	 * @param string $url
	 * @return array|bool|mixed|object
	 */
	private function fetch( $url ) {

		$response = wp_remote_get(
			$url,
			[
				'headers' => [
					'Accept'        => 'application/json',
					'Authorization' => 'Token ' . static::TOKEN,
				],
			]
		);

		if ( is_wp_error( $response ) ) {

			return false;

		}

		return json_decode( wp_remote_retrieve_body( $response ) );

	}

	/**
	 * Helper function to fetch stock images from the API.
	 *
	 * When the given category has no stock photos, this function will be
	 * responsible for fetching the parent category's stock photo as a fallback.
	 *
	 * @param string $category a valid "str_id" slug from the category API
	 *
	 * @return false if api error, otherwise array of objects from the api
	 */
	private function fetch_images( $category ) {

		$json = $this->fetch( sprintf( $this->image_cat_url, $category ) );

		if ( false === $json ) {

			return false;

		}

		if ( $json->count > 0 ) {

			return $json->results;

		}

		if ( empty( $json->parent_category ) ) {

			return [];

		}

		return $this->fetch_images( $json->parent_category );

	}

	/**
	 * Helper to fetch categories from the API
	 *
	 * As an implementation detail, does some post processing of the raw API json response
	 *
	 * @return false if api error, otherwise assoc array of category object's "str_id" => category object
	 */
	private function fetch_categories() {

		$data = $this->fetch( $this->category_api_url );

		if ( ! is_array( $data ) ) {

			return $data;

		}

		$output = [];

		foreach ( $data as $i => $cat ) {

			$output[ $cat->str_id ] = [
				'display_name' => $cat->display_name,
				'popularity'   => $cat->popularity,
			];

		}

		return $output;

	}

}
