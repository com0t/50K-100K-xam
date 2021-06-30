<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\Calculator;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\EntryVoConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\BotSignals\EntryVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

abstract class BaseBuildScores {

	use EntryVoConsumer;
	use ModConsumer;

	abstract public function build() :array;

	protected function score_known() :int {
		try {
			list( $ipID, $ipName ) = ( new IpID( $this->getRecord()->ip ) )->run();
		}
		catch ( \Exception $e ) {
			$ipID = null;
		}
		return ( empty( $ipID ) || in_array( $ipID, [ IpID::UNKNOWN, IpID::VISITOR ] ) )
			? 0 : 100;
	}

	protected function lastAtTs( $fieldFunction ) :int {
		$field = str_replace( 'score_', '', $fieldFunction ).'_at';
		return $this->getRecord()->{$field} ?? 0;
	}

	protected function diffTs( $fieldFunction ) :int {
		$field = str_replace( 'score_', '', $fieldFunction ).'_at';
		return Services::Request()->ts() - ( $this->getRecord()->{$field} ?? 0 );
	}

	protected function getAllFields( $filterForMethods = false ) :array {
		$botSignalDBH = shield_security_get_plugin()->getController()
													->getModule_IPs()
													->getDbHandler_BotSignals();
		$fields = array_map(
			function ( $col ) {
				return str_replace( '_at', '', $col );
			},
			array_filter(
				$botSignalDBH->getTableSchema()->getColumnNames(),
				function ( $col ) {
					return preg_match( '#_at$#', $col ) &&
						   !in_array( $col, [ 'updated_at', 'deleted_at' ] );
				}
			)
		);

		if ( $filterForMethods ) {
			$fields = array_filter( $fields, function ( $field ) {
				return method_exists( $this, 'score_'.$field );
			} );
		}

		return $fields;
	}

	protected function getRecord() :EntryVO {
		return $this->getEntryVO();
	}
}