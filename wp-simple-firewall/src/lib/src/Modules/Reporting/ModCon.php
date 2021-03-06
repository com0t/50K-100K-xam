<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class ModCon extends BaseShield\ModCon {

	/**
	 * @var Lib\ReportingController
	 */
	private $reportsCon;

	public function getDbHandler_Reports() :Databases\Reports\Handler {
		return $this->getDbH( 'reports' );
	}

	public function getReportingController() :Lib\ReportingController {
		if ( !isset( $this->reportsCon ) ) {
			$this->reportsCon = ( new Lib\ReportingController() )->setMod( $this );
		}
		return $this->reportsCon;
	}
}