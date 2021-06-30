<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Core\Databases\Common;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class TableSchema
 * @package FernleafSystems\Wordpress\Plugin\Shield\Databases\Common
 * @property string   $slug
 * @property string   $table        - full and complete table name including any prefixes
 * @property string   $table_prefix
 * @property string   $primary_key
 * @property string[] $cols_ids
 * @property string[] $cols_custom
 * @property string[] $cols_timestamps
 * @property string   $col_older_than
 * @property bool     $has_updated_at
 * @property int      $autoexpire
 * @property bool     $has_ip_col
 * @property bool     $is_ip_binary
 */
class TableSchema extends DynPropertiesClass {

	const PRIMARY_KEY = 'id';

	public function __get( string $key ) {
		$val = parent::__get( $key );
		switch ( $key ) {
			case 'has_ip_col':
				$val = array_key_exists( 'ip', $this->enumerateColumns() );
				break;
			case 'is_ip_binary':
				$val = $this->has_ip_col && ( stripos( $this->cols_custom[ 'ip' ], 'varbinary' ) !== false );
				break;
			case 'table':
				$val = $this->buildTableName();
				break;
			case 'col_older_than':
				if ( empty( $val ) || !$this->hasColumn( $val ) ) {
					$val = 'created_at';
				}
				break;
			case 'has_updated_at':
				$val = is_null( $val ) ? true : $val;
				break;
			default:
				break;
		}
		return $val;
	}

	protected function buildTableName() :string {
		return sprintf( '%s%s%s',
			Services::WpDb()->getPrefix(),
			empty( $this->table_prefix ) ? '' : $this->table_prefix.'_',
			$this->slug
		);
	}

	public function buildCreate() :string {
		$cols = [];
		foreach ( $this->enumerateColumns() as $col => $def ) {
			$cols[] = sprintf( '%s %s', $col, $def );
		}
		$cols[] = $this->getPrimaryKeyDef();

		return sprintf(
			'CREATE TABLE %s (
				%s
			) %s;',
			$this->table,
			implode( ", ", $cols ),
			Services::WpDb()->getCharCollate()
		);
	}

	/**
	 * @return string[]
	 */
	public function getColumnNames() :array {
		return array_keys( $this->enumerateColumns() );
	}

	/**
	 * @return string[]
	 */
	public function enumerateColumns() :array {
		return array_map(
			function ( array $colDef ) {
				// convert from array column def to string.
				return ( new BuildColumnFromDef( $colDef ) )->build();
			},
			array_merge(
				$this->getColumn_ID(),
				$this->cols_custom ?? [],
				$this->getColumns_Timestamps()
			)
		);
	}

	/**
	 * @return string[]
	 */
	protected function getColumn_ID() :array {
		return [
			$this->getPrimaryKeyColumnName() => [ 'macro_type' => BuildColumnFromDef::MACROTYPE_PRIMARYID ],
		];
	}

	/**
	 * @return string[]
	 */
	protected function getColumns_Timestamps() :array {

		$standardTsCols = [
			'created_at' => [
				'comment' => 'Created'
			],
			'deleted_at' => [
				'comment' => 'Soft Deleted'
			],
		];

		if ( $this->has_updated_at && !array_key_exists( 'updated_at', $this->cols_timestamps ) ) {
			$standardTsCols[ 'updated_at' ] = [
				'comment' => 'Last Updated'
			];
		}

		return array_map(
			function ( array $colDef ) {
				$colDef[ 'macro_type' ] = BuildColumnFromDef::MACROTYPE_TIMESTAMP;
				return $colDef;
			},
			array_merge(
				$this->cols_timestamps ?? [],
				$standardTsCols
			)
		);
	}

	protected function getPrimaryKeyDef() :string {
		return sprintf( 'PRIMARY KEY  (%s)', $this->getPrimaryKeyColumnName() );
	}

	protected function getPrimaryKeyColumnName() :string {
		return $this->primary_key ?? static::PRIMARY_KEY;
	}

	public function hasColumn( string $col ) :bool {
		return in_array( strtolower( $col ), $this->getColumnNames() );
	}
}