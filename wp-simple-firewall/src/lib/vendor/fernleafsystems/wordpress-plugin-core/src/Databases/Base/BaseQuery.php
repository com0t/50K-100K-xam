<?php

namespace FernleafSystems\Wordpress\Plugin\Core\Databases\Base;

use Carbon\Carbon;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseQuery {

	/**
	 * @var Handler
	 */
	protected $dbh;

	/**
	 * @var array
	 */
	protected $rawWheres;

	protected $includeSoftDeleted;

	/**
	 * @var int
	 */
	protected $limit = 0;

	/**
	 * @var int
	 */
	protected $page = 1;

	/**
	 * @var array
	 */
	protected $orderBys;

	/**
	 * @var string
	 */
	protected $groupBy;

	/**
	 * @var mixed
	 */
	protected $lastQueryResult;

	public function __construct() {
		$this->customInit();
	}

	/**
	 * override to add custom init actions
	 */
	protected function customInit() {
	}

	/**
	 * @param string       $column
	 * @param string|array $value
	 * @param string       $operator
	 * @return $this
	 */
	public function addWhere( $column, $value, $operator = '=' ) {
		if ( !$this->isValidComparisonOperator( $operator ) ) {
			return $this; // Exception?
		}

		if ( is_array( $value ) ) {
			$value = array_map( 'esc_sql', $value );
			$value = "('".implode( "','", $value )."')";
		}
		else {
			if ( strtoupper( $operator ) === 'LIKE' ) {
				$value = sprintf( '%%%s%%', $value );
			}
			if ( !is_int( $value ) ) {
				$value = sprintf( "'%s'", esc_sql( $value ) );
			}
		}

		$rawWheres = $this->getRawWheres();
		$rawWheres[] = [
			esc_sql( $column ),
			$operator,
			$value
		];

		return $this->setRawWheres( $rawWheres );
	}

	/**
	 * @param string $column
	 * @param mixed  $mValue
	 * @return $this
	 */
	public function addWhereEquals( string $column, $mValue ) {
		return $this->addWhere( $column, $mValue, '=' );
	}

	/**
	 * @param string $column
	 * @param array  $values
	 * @return $this
	 */
	public function addWhereIn( string $column, $values ) {
		if ( !empty( $values ) && is_array( $values ) ) {
			$this->addWhere( $column, $values, 'IN' );
		}
		return $this;
	}

	/**
	 * @param string $column
	 * @param string $like
	 * @param string $left
	 * @param string $right
	 * @return $this
	 */
	public function addWhereLike( string $column, $like, $left = '%', $right = '%' ) {
		return $this->addWhere( $column, $left.$like.$right, 'LIKE' );
	}

	/**
	 * @param int    $nNewerThanTimeStamp
	 * @param string $column
	 * @return $this
	 */
	public function addWhereNewerThan( $nNewerThanTimeStamp, $column = 'created_at' ) {
		return $this->addWhere( $column, $nNewerThanTimeStamp, '>' );
	}

	/**
	 * @param int    $nOlderThanTimeStamp
	 * @param string $column
	 * @return $this
	 */
	public function addWhereOlderThan( $nOlderThanTimeStamp, $column = 'created_at' ) {
		return $this->addWhere( $column, $nOlderThanTimeStamp, '<' );
	}

	/**
	 * @param string $sColumn
	 * @param mixed  $mValue
	 * @return $this
	 */
	public function addWhereSearch( $sColumn, $mValue ) {
		return $this->addWhere( $sColumn, $mValue, 'LIKE' );
	}

	/**
	 * @return string
	 */
	public function buildExtras() {
		$aExtras = array_filter(
			[
				$this->getGroupBy(),
				$this->buildOrderBy(),
				$this->buildLimitPhrase(),
				$this->buildOffsetPhrase(),
			]
		);
		return implode( "\n", $aExtras );
	}

	/**
	 * @return string
	 */
	public function buildLimitPhrase() {
		return $this->hasLimit() ? sprintf( 'LIMIT %s', $this->getLimit() ) : '';
	}

	/**
	 * @return string
	 */
	protected function buildOffsetPhrase() {
		return $this->hasLimit() ? sprintf( 'OFFSET %s', $this->getOffset() ) : '';
	}

	/**
	 * @return $this
	 */
	public function clearWheres() {
		return $this->setRawWheres( [] );
	}

	/**
	 * @return int
	 */
	protected function getOffset() {
		return (int)$this->getLimit()*( $this->getPage() - 1 );
	}

	/**
	 * @return string
	 */
	public function buildWhere() {
		$wheres = $this->getRawWheres();
		if ( !$this->isIncludeSoftDeletedRows() ) {
			$wheres[] = [ 'deleted_at', '=', 0 ];
		}
		$wheres = array_map( function ( array $where ) {
			return $this->rawWhereToString( $where );
		}, $wheres );
		return implode( ' AND ', $wheres );
	}

	public function buildQuery() :string {
		return sprintf( $this->getBaseQuery(),
			$this->getDbH()->getTable(),
			$this->buildWhere(),
			$this->buildExtras()
		);
	}

	/**
	 * @param int    $ts
	 * @param string $comparisonOp
	 * @return $this
	 */
	public function filterByCreatedAt( $ts, $comparisonOp ) {
		if ( !preg_match( '#[^=<>]#', $comparisonOp ) && is_numeric( $ts ) ) {
			$this->addWhere( 'created_at', (int)$ts, $comparisonOp );
		}
		return $this;
	}

	/**
	 * @param int $startTS
	 * @param int $endTS
	 * @return $this
	 */
	public function filterByBoundary( $startTS, $endTS ) {
		return $this->filterByCreatedAt( $endTS, '<=' )
					->filterByCreatedAt( $startTS, '>=' );
	}

	/**
	 * @param int $ts
	 * @return $this
	 */
	public function filterByBoundary_Day( $ts ) {
		$carbon = ( new Carbon() )->setTimestamp( $ts );
		return $this->filterByBoundary( $carbon->startOfDay()->timestamp, $carbon->endOfDay()->timestamp );
	}

	/**
	 * @param int $ts
	 * @return $this
	 */
	public function filterByBoundary_Hour( $ts ) {
		$carbon = ( new Carbon() )->setTimestamp( $ts );
		return $this->filterByBoundary( $carbon->startOfHour()->timestamp, $carbon->endOfHour()->timestamp );
	}

	/**
	 * @param int $ts
	 * @return $this
	 */
	public function filterByBoundary_Month( $ts ) {
		$carbon = ( new Carbon() )->setTimestamp( $ts );
		return $this->filterByBoundary( $carbon->startOfMonth()->timestamp, $carbon->endOfMonth()->timestamp );
	}

	/**
	 * @param int $ts
	 * @return $this
	 */
	public function filterByBoundary_Week( $ts ) {
		$carbon = ( new Carbon() )->setTimestamp( $ts );
		return $this->filterByBoundary( $carbon->startOfWeek()->timestamp, $carbon->endOfWeek()->timestamp );
	}

	/**
	 * @param int $ts
	 * @return $this
	 */
	public function filterByBoundary_Year( $ts ) {
		$carbon = ( new Carbon() )->setTimestamp( $ts );
		return $this->filterByBoundary( $carbon->startOfYear()->timestamp, $carbon->endOfYear()->timestamp );
	}

	protected function getBaseQuery() :string {
		return "SELECT * FROM `%s` WHERE %s %s";
	}

	/**
	 * @return Handler
	 */
	public function getDbH() {
		return $this->dbh;
	}

	/**
	 * @param Handler $dbh
	 * @return $this
	 */
	public function setDbH( $dbh ) {
		$this->dbh = $dbh;
		return $this;
	}

	public function query() :bool {
		$this->lastQueryResult = Services::WpDb()->doSql( $this->buildQuery() );
		return ( $this->lastQueryResult === false ) ? false : $this->lastQueryResult > 0;
	}

	/**
	 * @return array[]|int|string[]|Record[]|mixed|null
	 */
	public function queryWithResult() {
		return $this->query() ? $this->getLastQueryResult() : null;
	}

	/**
	 * @return array[]|int|string[]|Record[]|mixed
	 */
	public function getLastQueryResult() {
		return $this->lastQueryResult;
	}

	public function getLimit() :int {
		return (int)max( $this->limit, 0 );
	}

	public function getRawWheres() :array {
		return is_array( $this->rawWheres ) ? $this->rawWheres : [];
	}

	public function getGroupBy() :string {
		return empty( $this->groupBy ) ? '' : sprintf( 'GROUP BY `%s`', $this->groupBy );
	}

	protected function buildOrderBy() :string {
		$order = '';
		if ( !is_array( $this->orderBys ) ) {
			// Defaults to created_at if aOrderBys is untouched. Set to empty array for no order
			$this->orderBys = [ 'created_at' => 'DESC' ];
		}
		if ( !empty( $this->orderBys ) ) {
			$orders = [];
			foreach ( $this->orderBys as $col => $order ) {
				$orders[] = sprintf( '`%s` %s', esc_sql( $col ), esc_sql( $order ) );
			}
			$order = sprintf( 'ORDER BY %s', implode( ', ', $orders ) );
		}
		return $order;
	}

	public function getPage() :int {
		return (int)max( $this->page, 1 );
	}

	public function hasLimit() :bool {
		return $this->getLimit() > 0;
	}

	public function isIncludeSoftDeletedRows() :bool {
		return $this->includeSoftDeleted ?? false;
	}

	protected function rawWhereToString( array $rawWhere ) :string {
		return vsprintf( '`%s` %s %s', $rawWhere );
	}

	/**
	 * @return $this
	 */
	public function reset() {
		return $this->setLimit( 0 )
					->setRawWheres( [] )
					->setPage( 1 )
					->setOrderBy( '' );
	}

	/**
	 * @param bool $includeSoftDeleted
	 * @return $this
	 */
	public function setIncludeSoftDeleted( bool $includeSoftDeleted ) {
		$this->includeSoftDeleted = $includeSoftDeleted;
		return $this;
	}

	/**
	 * @param int $limit
	 * @return $this
	 */
	public function setLimit( int $limit ) {
		$this->limit = $limit;
		return $this;
	}

	/**
	 * @param string $groupByColumn
	 * @return $this
	 */
	public function setGroupBy( $groupByColumn ) {
		if ( empty( $groupByColumn ) ) {
			$this->groupBy = '';
		}
		elseif ( $this->getDbH()->getTableSchema()->hasColumn( $groupByColumn ) ) {
			$this->groupBy = $groupByColumn;
		}
		return $this;
	}

	/**
	 * @param string $orderByColumn
	 * @param string $order
	 * @param bool   $replace
	 * @return $this
	 */
	public function setOrderBy( string $orderByColumn, $order = 'DESC', $replace = false ) {
		if ( empty( $orderByColumn ) ) {
			$this->orderBys = $orderByColumn;
		}
		else {
			if ( !is_array( $this->orderBys ) || $replace ) {
				$this->orderBys = [];
			}
			$this->orderBys[ $orderByColumn ] = $order;
		}
		return $this;
	}

	/**
	 * @param int $nPage
	 * @return $this
	 */
	public function setPage( $nPage ) {
		$this->page = $nPage;
		return $this;
	}

	/**
	 * @param array[] $wheres
	 * @return $this
	 */
	public function setRawWheres( array $wheres ) {
		$this->rawWheres = $wheres;
		return $this;
	}

	/**
	 * @param Record $VO
	 * @return $this
	 */
	public function setWheresFromVo( $VO ) {
		foreach ( $VO->getRawData() as $col => $mVal ) {
			$this->addWhereEquals( $col, $mVal );
		}
		return $this;
	}

	/**
	 * Very basic
	 * @param string $op
	 * @return bool
	 */
	protected function isValidComparisonOperator( $op ) {
		return in_array(
			strtoupper( $op ),
			[ '=', '<', '>', '!=', '<>', '<=', '>=', '<=>', 'IN', 'LIKE', 'NOT LIKE' ]
		);
	}
}