<?php

namespace FernleafSystems\Wordpress\Plugin\Core\Databases\Base;

use FernleafSystems\Wordpress\Services\Services;

class Insert extends BaseQuery {

	/**
	 * @var array
	 */
	protected $insertData;

	public function getInsertData() :array {
		return array_intersect_key(
			is_array( $this->insertData ) ? $this->insertData : [],
			array_flip( $this->getDbH()->getTableSchema()->getColumnNames() )
		);
	}

	/**
	 * @param Record $record
	 * @return bool
	 */
	public function insert( $record ) :bool {
		return $this->setInsertData( $record->getRawData() )->query() === 1;
	}

	/**
	 * Verifies insert data keys against actual table columns
	 * @param array $data
	 * @return $this
	 */
	protected function setInsertData( $data ) {
		$this->insertData = array_intersect_key(
			is_array( $data ) ? $data : [],
			array_flip( $this->getDbH()->getTableSchema()->getColumnNames() )
		);
		return $this;
	}

	/**
	 * @return $this
	 * @throws \Exception
	 */
	protected function verifyInsertData() {
		$baseData = [ 'created_at' => Services::Request()->ts() ];
		if ( $this->getDbH()->getTableSchema()->hasColumn( 'updated_at' ) ) {
			$baseData[ 'updated_at' ] = Services::Request()->ts();
		}
		return $this->setInsertData( array_merge( $baseData, $this->getInsertData() ) );
	}

	public function query() :bool {
		try {
			$this->verifyInsertData();
			$this->lastQueryResult = Services::WpDb()
											 ->insertDataIntoTable(
											 $this->getDbH()->getTable(),
											 $this->getInsertData()
										 );
			$success = (bool)$this->lastQueryResult;
		}
		catch ( \Exception $e ) {
			$success = false;
		}
		return $success;
	}

	/**
	 * Offset never applies
	 *
	 * @return string
	 */
	protected function buildOffsetPhrase() {
		return '';
	}
}