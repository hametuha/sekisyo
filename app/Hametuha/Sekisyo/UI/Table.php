<?php

namespace Hametuha\Sekisyo\UI;


use Hametuha\Sekisyo\GateKeeper;
use Hametuha\Sekisyo\Model\Plugin;

/**
 * Table instance
 *
 * @since 1.0.0
 * @package sekisyo
 * @property GateKeeper $gate_keeper
 */
class Table extends \WP_List_Table {

	/**
	 * Table constructor
	 *
	 * @param array $args
	 */
	public function __construct( $args = [] ) {
		parent::__construct( [
			'singular' => 'plugin',
			'plural'   => 'plugins',
			'ajax'     => false,
		] );
	}

	/**
	 * Prepare items
	 */
	public function prepare_items() {
		$this->_column_headers = [
			$this->get_columns(),
			[],
			[],
		];
		$this->items = $this->gate_keeper->all_plugins();
		$this->set_pagination_args( array(
			'total_items' => count( $this->items ),
			'per_page'    => count( $this->items ),
			'total_pages' => 1,
		) );
	}

	/**
	 * Render column
	 *
	 * @param Plugin $item
	 * @param string $column_name
	 */
	protected function column_default( $item, $column_name ) {
		echo $item->render();
	}

	/**
	 * Get columns
	 *
	 * @return array
	 */
	public function get_columns() {
		return [
			'plugin' => __( 'Plugin', 'sekisyo' ),
		];
	}


	public function __get( $name ) {
		switch ( $name ) {
			case 'gate_keeper':
				return GateKeeper::get_instance();
				break;
			default:
				return parent::__get( $name );
				break;
		}
	}


}
