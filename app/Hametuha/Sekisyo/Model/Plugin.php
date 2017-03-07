<?php
namespace Hametuha\Sekisyo\Model;

/**
 * Class Plugin
 * @package sekisyo
 * @property array $option
 * @property array $data
 * @property string $license
 * @property string $version
 * @property string $url
 * @property string $author
 * @property string $name
 * @property bool $valid
 * @property int  $failed
 * @property string|false $last_checked
 */
class Plugin {

	public $label = '';

	public $key = '';

	public $guid = '';

	public $description = '';

	public $validate_url = '';

	public $file = '';

	public $name = '';

	public $fail_limit = 0;

	/**
	 * Constructor
	 *
	 * @param string $guid
	 * @param string $file
	 * @param string $label
	 * @param string $description
	 * @param string $validate_url
     * @param int    $fail_limit
	 */
	public function __construct( $guid, $file, $label, $description, $validate_url, $fail_limit = 0 ) {
		$this->file = ltrim( str_replace( ABSPATH . 'wp-content/plugins', '', $file ), '/' );
		$this->guid = $guid;
		$this->description = $description;
		$this->label = $label;
		$this->validate_url = $validate_url;
		$this->fail_limit = $fail_limit;
	}

	/**
	 * Render row
	 *
	 * @return string
	 */
	public function render() {
		ob_start();
		?>
        <div class="sekisyo-row" data-sekisyo-guid="<?= esc_attr( $this->guid ) ?>">
            <div class="sekisyo-status">
	            <?php if ( $this->valid ) : ?>
                    <span class="dashicons dashicons-yes"></span>
	            <?php else : ?>
                    <span class="dashicons dashicons-no"></span>
	            <?php endif; ?>
            </div>
            <p class="sekisyo-row-title">
                <strong><?= esc_html( $this->label ) ?></strong>
                Version <?= esc_html( $this->version ) ?>
                <small><?php esc_html_e( 'Author', 'sekisyo' ) ?>
                    : <?= esc_html( $this->author ?: __( 'Undefined', 'sekisyo' ) ) ?> </small>
            </p>
			<?php if ( $desc = $this->description ) : ?>
                <p class="sekisyo-row--description">
					<?= nl2br( esc_html( trim( $desc ) ) ) ?>
                </p>
			<?php endif; ?>
            <div class="sekisyo-license">
                <input type="text" name="sekisyo-license" value="<?= esc_attr( $this->license ) ?>"
                       placeholder="XXXX-XXXX-XXXX-XXXX"/>
                <button class="sekisyo-btn button"><?php esc_html_e( 'Validate', 'sekisyo' ) ?></button>
	            <?php if ( $this->valid ) : ?>
                    <button class="sekisyo-unlink button"><?php esc_html_e( 'Unlink', 'sekisyo' ) ?></button>
	            <?php endif; ?>
            </div>


			<?php if ( $url = $this->url ) : ?>
                <a href="<?= esc_url( $url ) ?>"
                   target="_blank"><?php esc_html_e( 'Visit Plugin Site', 'sekisyo' ) ?></a>
			<?php endif; ?>
        </div>
		<?php
		$out = ob_get_contents();
		ob_end_clean();
		return $out;
	}

	/**
	 * Update license
	 *
	 * @param string $license
	 * @param bool $keep_status
	 *
	 * @return bool|\WP_Error
	 */
	public function update_license( $license, $keep_status = false ) {
		$option   = get_option( 'sekisyo_license', [] );
		$validity = $this->validate( $license );
		$failed   = $this->failed;
		if ( is_wp_error( $validity ) ) {
			$failed ++;
		} else {
			$failed = 0;
		}
		$prev_status           = $this->valid;
		$cur_status            = is_wp_error( $validity ) ? false : true;
		$option[ $this->guid ] = [
			'license'      => $license,
			'valid'        => ( $keep_status && ! $cur_status ) ? $prev_status : $cur_status,
			'last_checked' => current_time( 'mysql', true ),
			'failed'       => $failed,
		];
		update_option( 'sekisyo_license', $option );

		return is_wp_error( $validity ) ? $validity : true;
	}

	/**
	 * Deactivate license
	 */
	public function inactivate() {
		$option = get_option( 'sekisyo_license', [] );
		$option[ $this->guid ]['valid'] = false;
		update_option( 'sekisyo_license', $option );
	}

	/**
	 * Validate license
	 *
	 * @param string $license
	 *
	 * @return bool|\WP_Error
	 */
	public function validate( $license ) {
		try {
			$url      = untrailingslashit( home_url( '' ) );
			$guid     = $this->guid;
			$response = wp_remote_post( $this->validate_url, [
				'body' => [
					'license' => $license,
					'url'     => $url,
				],
			] );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			if ( 200 === $response['response']['code'] ) {
				return true;
			} else {
				$body = json_decode( $response['body'] );
				return new \WP_Error( $response['response']['code'], $body->message );
			}
		} catch ( \Exception $e ) {
			return new \WP_Error( $e->getCode(), $e->getMessage() );
		}
	}

	/**
	 * Update license
	 *
	 * @param string $license
	 * @return bool|\WP_Error
	 */
	public function unlink_license( $license ) {
		try {
			$option   = get_option( 'sekisyo_license', [] );
			$url      = untrailingslashit( home_url( '' ) );
			$guid     = $this->guid;
			$endpoint = add_query_arg( [
				'license' => $license,
				'url'     => $url,
			], $this->validate_url );
			$response = wp_remote_request( $endpoint, [
				'method' => 'DELETE',
			] );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			$option[ $this->guid ] = [
				'license'      => '',
				'valid'        => false,
				'last_checked' => current_time( 'mysql', true ),
				'failed' => 0,
			];
			update_option( 'sekisyo_license', $option );

			return true;
		} catch ( \Exception $e ) {
			return new \WP_Error( $e->getCode(), $e->getMessage() );
		}
	}

	/**
	 * Getter
	 *
	 * @param string $name
	 * @return mixed
	 */
	public function __get( $name ) {
		switch ( $name ) {
			case 'data':
				$path = ABSPATH . 'wp-content/plugins/' . $this->file;
				if ( file_exists( $path ) ) {
					return get_file_data( $path, [
						'name' => 'Plugin Name',
						'url' => 'Plugin URI',
						'version' => 'Version',
						'author' => 'Author',
						'description' => 'Description',
					] );
				} else {
					return [
						'name' => $this->file,
						'url' => '',
						'version' => __( 'Undefined', 'sekisyo' ),
						'author' => __( 'Undefined', 'sekisyo' ),
						'description' => '',
					];
				}
				break;
			case 'name':
			case 'version':
			case 'url':
			case 'author':
				return $this->data[ $name ];
				break;
			case 'option':
				$option = get_option( 'sekisyo_license', [] );

				return isset( $option[ $this->guid ] ) ? $option[ $this->guid ] : [
					'license'      => '',
					'valid'        => false,
					'last_checked' => false,
					'failed'       => 0,
				];
				break;
			case 'license':
			case 'valid':
			case 'last_checked':
				return $this->option[ $name ];
				break;
			case 'failed':
				return isset( $this->option['failed'] ) ? (int) $this->option['failed'] : 0;
				break;
			default:
				return null;
				break;
		}
	}
}
