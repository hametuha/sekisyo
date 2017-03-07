<?php

namespace Hametuha\Sekisyo;

use Hametuha\Sekisyo\Model\Plugin;
use Hametuha\Sekisyo\UI\Table;

/**
 * Gate keeper of Skisyo
 *
 * @package sekisyo
 */
class GateKeeper {

	public $version = '1.0.0';

	private static $self = null;

	private $plugins = [];

	/**
	 * Constructor
	 */
	private function __construct() {
		add_action( 'init', function () {
			$asset_dir     = plugin_dir_url( dirname( dirname( dirname( __FILE__ ) ) ) ) . 'assets';
			$base_dir      = dirname( dirname( dirname( dirname( __FILE__ ) ) ) );
			$lang_rel_path = str_replace( ABSPATH . 'wp-content/plugins/', '', $base_dir );
			// Add i18n
			load_plugin_textdomain( 'sekisyo', false, $lang_rel_path . '/languages' );
			// Register menu
			add_action( 'admin_menu', [ $this, 'admin_menu' ] );
			// Add scripts
			wp_register_script( 'sekisyo-helper', $asset_dir . '/js/sekisyo-helper.js', [
				'jquery',
				'wp-api',
			], $this->version, true );
			wp_register_style( 'sekisyo-admin', $asset_dir . '/css/sekisyo-admin.css', [], $this->version );
			// Enqueue assets
			add_action( 'admin_enqueue_scripts', function ( $page ) {
				if ( 'plugins_page_sekisyo' === $page ) {
					wp_enqueue_style( 'sekisyo-admin' );
					wp_enqueue_script( 'sekisyo-helper' );
					// wp_localize_script( 'sekisyo-helper', 'Sekisyo', [] );
				}
			} );
			// Add API
			add_action( 'rest_api_init', [ $this, 'rest_api_init' ] );
			// Add license cron
			add_filter( 'cron_schedules', function ( $schedules ) {
				$schedules['sekisyo_validate'] = [
					'interval' => 60 * 60 * 2,
					'display'  => __( 'Check plugins\' validity once in 2 hours.', 'sekisyo' ),
				];

				return $schedules;
			} );
			// Cron message
			if ( ! wp_next_scheduled( 'sekisyo_validate' ) ) {
				wp_schedule_event( current_time( 'timestamp', true ), 'sekisyo_validate', 'sekisyo_check_validity' );
			}
			add_action( 'sekisyo_check_validity', [ $this, 'check_validity' ] );
		} );
	}

	/**
	 * Register menu
	 */
	public function admin_menu() {
		add_plugins_page(
			__( 'Plugin License', 'sekisyo' ),
			__( 'License', 'sekisyo' ),
			'activate_plugins', 'sekisyo',
			[ $this, 'render_admin' ]
		);
	}

	/**
	 * Render admin screen
	 */
	public function render_admin() {
		?>
        <div class="wrap wrap-sekisyo">
            <h2><?php esc_html_e( 'Plugin License', 'sekisyo' ) ?></h2>
			<?php
			$table = new Table();
			$table->prepare_items();
			$table->display();
			/**
			 * sekisyo_footer
			 *
			 * Executed on setting screen.
			 *
			 * @since 1.0.0
			 * @package sekisyo
			 */
			do_action( 'sekisyo_footer' );
			?>
        </div>
		<?php
	}

	/**
	 * Handle API request
	 *
	 * @param \WP_REST_Server $server
	 */
	public function rest_api_init( $server ) {
		register_rest_route( 'sekisyo/v1', 'license/(?P<guid>[^/]+)/?', [
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_rest_post' ],
				'args'                => [
					'guid'    => [
						'required' => true,
					],
					'license' => [
						'required' => true,
					],
				],
				'permission_callback' => function ( $request ) {
					return current_user_can( 'activate_plugins' );
				},
			],
			[
				'methods'             => 'DELETE',
				'callback'            => [ $this, 'handle_rest_delete' ],
				'args'                => [
					'guid'    => [
						'required' => true,
					],
					'license' => [
						'required' => true,
					],
				],
				'permission_callback' => function ( $request ) {
					return current_user_can( 'activate_plugins' );
				},
			],
		] );
	}

	/**
	 * Handle post request
	 *
	 * @param \WP_REST_Request $params
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_rest_post( $params ) {
		if ( ! isset( $this->plugins[ $params['guid'] ] ) ) {
			return new \WP_Error( 404, __( 'No plugin found.', 'sekisyo' ) );
		}
		/** @var Plugin $plugin */
		$plugin = $this->plugins[ $params['guid'] ];
		// Check response.
		$response = $plugin->update_license( $params['license'] );
		if ( is_wp_error( $response ) ) {
			return $response;
		} else {
			return new \WP_REST_Response( [
				'html'    => $plugin->render(),
				'message' => __( 'This license is valid.', 'sekisyo' ),
			] );
		}
	}


	/**
	 * Handle post request
	 *
	 * @param \WP_REST_Request $params
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_rest_delete( $params ) {
		if ( ! isset( $this->plugins[ $params['guid'] ] ) ) {
			return new \WP_Error( 404, __( 'No plugin found.', 'sekisyo' ) );
		}
		/** @var Plugin $plugin */
		$plugin = $this->plugins[ $params['guid'] ];
		// Check response.
		$response = $plugin->unlink_license( $params['license'] );
		if ( is_wp_error( $response ) ) {
			return $response;
		} else {
			return new \WP_REST_Response( [
				'html'    => $plugin->render(),
				'message' => __( 'License key is unlinked.', 'sekisyo' ),
			] );
		}
	}

	/**
	 * Check validity with cron job
	 */
	public function check_validity() {
		foreach ( $this->plugins as $plugin ) {
			/** @var Plugin $plugin */
			if ( $plugin->valid ) {
				// This is valid plugin, so we need check license health.
				$plugin->update_license( $plugin->license, true );
				if ( ! $plugin->fail_limit ) {
				    // If plugin has no fail limit, skip it.
				    continue;
				}
				/**
				 * sekisyo_update_error
				 *
				 * @package sekisyo
				 * @since 1.0.0
				 *
				 * @param string $guid
				 * @param int $failed
				 * @param Plugin $plugin
				 */
				do_action( 'sekisyo_update_error', $plugin->guid, $plugin->failed, $plugin );
				if ( $plugin->failed > $plugin->fail_limit ) {
					$plugin->inactivate();
				}
			}
		}
	}

	/**
	 * Get instance
	 *
	 * @return self
	 */
	public static function get_instance() {
		if ( is_null( self::$self ) ) {
			self::$self = new self();
		}

		return self::$self;
	}

	/**
	 * Get all plugins
	 *
	 * @return array
	 */
	public function all_plugins() {
		return $this->plugins;
	}

	/**
	 * Register plugin
	 *
	 * @param string $guid
	 * @param string $file
	 * @param string $label
	 * @param string $description
	 * @param string $validate_url
	 * @param int    $fail_limit
	 *
	 * @return \WP_Error
	 */
	public static function register( $guid, $file, $label, $description, $validate_url, $fail_limit = 0 ) {
		$instance = self::get_instance();
		try {
			if ( isset( $instance->plugins[ $guid ] ) ) {
				throw new \Exception( sprintf( __( '%s is already registered.', 'sekisyo' ), $guid ), 400 );
			}
			$plugin                     = new Plugin( $guid, $file, $label, $description, $validate_url, $fail_limit );
			$instance->plugins[ $guid ] = $plugin;
		} catch ( \Exception $exception ) {
			return new \WP_Error( $exception->getCode(), $exception->getMessage(), [
				'status' => $exception->getCode(),
			] );
		}
	}

	/**
	 * Get plugin instance.
	 *
	 * @param string $guid
	 *
	 * @return Plugin|\WP_Error
	 */
	public static function get( $guid ) {
		$instance = self::get_instance();
		if ( isset( $instance->plugins[ $guid ] ) ) {
			return $instance->plugins[ $guid ];
		} else {
			new \WP_Error( sprintf( __( 'Plugin %s is not registered.', 'sekisyo' ), $guid ), 404, [
				'status' => 404,
			] );
		}
	}

	/**
	 * Detect if this plugin is valid
	 *
	 * @param array $guids
	 *
	 * @return bool
	 */
	public static function is_valid( $guids = [] ) {
		$instance = self::get_instance();
		foreach ( $guids as $guid ) {
			if ( isset( $instance->plugins[ $guid ] ) && $instance->plugins[ $guid ]->valid ) {
				return true;
			}
		}

		return false;
	}
}
