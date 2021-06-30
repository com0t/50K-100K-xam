<?php

namespace FernleafSystems\Wordpress\Plugin\Core\Databases\Base;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Core\Databases\Common\{
	AlignTableWithSchema,
	Iterator,
	SubQueryLoader,
	TableSchema
};
use FernleafSystems\Wordpress\Plugin\Core\Databases\Exceptions\{
	NoSlugProvidedException
};
use FernleafSystems\Wordpress\Services\Services;

abstract class Handler {

	use ExecOnce;

	/**
	 * @var bool
	 */
	private $isReady;

	/**
	 * @var array
	 */
	protected $tableDefinition;

	/**
	 * @var TableSchema
	 */
	protected $schema;

	public function __construct( array $tableDefinition ) {
		if ( empty( $tableDefinition[ 'slug' ] ) ) {
			throw new NoSlugProvidedException( 'Slug not provided in Table Definition' );
		}
		$this->tableDefinition = $tableDefinition;
	}

	/**
	 * @throws \Exception
	 */
	protected function run() {
		$this->tableInit();
	}

	/**
	 * @return $this
	 * @throws \Exception
	 */
	public function tableInit() {

		$this->setupTableSchema();

		if ( !$this->isReady() ) {

			$this->tableCreate();

			if ( !$this->isReady( true ) ) {
				$this->tableDelete();
				$this->tableCreate();
			}
		}

		return $this;
	}

	private function setupTableSchema() :TableSchema {
		$this->schema = new TableSchema();

		$this->schema->applyFromArray( array_merge(
			[
				'slug'            => '',
				'table_prefix'    => '',
				'primary_key'     => 'id',
				'cols_custom'     => [],
				'cols_timestamps' => [],
				'has_updated_at'  => false,
				'col_older_than'  => 'created_at',
				'autoexpire'      => 0,
				'has_ip_col'      => false,
			],
			$this->tableDefinition
		) );

		$this->schema->table = $this->getTable();

		return $this->schema;
	}

	public function autoCleanDb() {
	}

	public function tableCleanExpired( int $autoExpireDays ) {
		if ( $autoExpireDays > 0 ) {
			$this->deleteRowsOlderThan( Services::Request()->ts() - $autoExpireDays*DAY_IN_SECONDS );
		}
	}

	/**
	 * @param int $timestamp
	 * @return bool
	 */
	public function deleteRowsOlderThan( int $timestamp ) :bool {
		return $this->isReady() &&
			   $this->getQueryDeleter()
					->addWhereOlderThan( $timestamp, $this->getTableSchema()->col_older_than )
					->query();
	}

	public function getTable() :string {
		return $this->getTableSchema()->table;
	}

	/**
	 * @return Iterator
	 */
	public function getIterator() {
		$o = new Iterator();
		return $o->setDbHandler( $this );
	}

	/**
	 * @return Delete|mixed
	 */
	public function getQueryDeleter() {
		return ( new SubQueryLoader() )
			->setDbHandler( $this )
			->delete();
	}

	/**
	 * @return Insert|mixed
	 */
	public function getQueryInserter() {
		return ( new SubQueryLoader() )
			->setDbHandler( $this )
			->insert();
	}

	/**
	 * @return Select|mixed
	 */
	public function getQuerySelector() {
		return ( new SubQueryLoader() )
			->setDbHandler( $this )
			->select()
			->setResultsAsVo( true );
	}

	/**
	 * @return Update|mixed
	 */
	public function getQueryUpdater() {
		return ( new SubQueryLoader() )
			->setDbHandler( $this )
			->update();
	}

	/**
	 * @return Record|mixed
	 */
	public function getRecord() {
		return ( new SubQueryLoader() )
			->setDbHandler( $this )
			->record();
	}

	/**
	 * @return Record|mixed
	 * @deprecated
	 */
	public function getVo() {
		return $this->getRecord();
	}

	/**
	 * @return $this
	 * @throws \Exception
	 */
	protected function tableCreate() {
		$DB = Services::WpDb();
		$sch = $this->getTableSchema();
		if ( !$DB->getIfTableExists( $sch->table ) ) {
			$DB->doSql( $sch->buildCreate() );
		}
		return $this;
	}

	public function tableDelete( bool $truncate = false ) :bool {
		$table = $this->getTable();
		$DB = Services::WpDb();
		$mResult = !$this->tableExists() ||
				   ( $truncate ? $DB->doTruncateTable( $table ) : $DB->doDropTable( $table ) );
		$this->reset();
		return $mResult !== false;
	}

	public function tableExists() :bool {
		return Services::WpDb()->getIfTableExists( $this->getTable() );
	}

	public function tableTrimExcess( int $rowsLimit ) :self {
		try {
			$this->getQueryDeleter()->deleteExcess( $rowsLimit );
		}
		catch ( \Exception $e ) {
		}
		return $this;
	}

	public function isReady( bool $reTest = false ) :bool {
		if ( $reTest ) {
			$this->reset();
		}

		if ( !isset( $this->isReady ) ) {
			try {
				$align = new AlignTableWithSchema( $this->getTableSchema() );
				$align->align();
				$this->isReady = $this->tableExists() && $align->isAligned();
			}
			catch ( \Exception $e ) {
				$this->isReady = false;
			}
		}

		return $this->isReady;
	}

	public function getTableSchema() :TableSchema {
		return $this->schema;
	}

	/**
	 * @return $this
	 */
	private function reset() {
		unset( $this->isReady );
		return $this;
	}

	public function getBaseNamespaces() :array {
		return [ __NAMESPACE__ ];
	}
}