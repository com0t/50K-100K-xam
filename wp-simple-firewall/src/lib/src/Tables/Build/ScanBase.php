<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Tables;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class ScanBase
 * @package FernleafSystems\Wordpress\Plugin\Shield\Tables\Build
 */
class ScanBase extends BaseBuild {

	protected function buildEmpty() :string {
		return sprintf( '<div class="alert alert-success m-0">%s</div>',
			__( "The previous scan either didn't detect any items that require your attention or they've already been repaired.", 'wp-simple-firewall' ) );
	}

	/**
	 * @return array[]
	 */
	public function getEntriesFormatted() :array {
		$entries = [];

		/** @var ModCon $mod */
		$mod = $this->getMod();
		foreach ( $this->getEntriesRaw() as $key => $entry ) {
			/** @var Scanner\EntryVO $entry */
			$entries[ $key ] = $mod->getScanCon( $entry->scan )
								   ->getTableEntryFormatter()
								   ->setEntryVO( $entry )
								   ->format();
		}

		return $entries;
	}

	/**
	 * Override this to apply table-specific query filters.
	 * @return $this
	 */
	protected function applyCustomQueryFilters() {
		$aParams = $this->getParams();
		/** @var Scanner\Select $oSelector */
		$oSelector = $this->getWorkingSelector();

		$oSelector->filterByScan( $aParams[ 'fScan' ] );

		if ( $aParams[ 'fIgnored' ] !== 'Y' ) {
			$oSelector->filterByNotIgnored();
		}

		return $this;
	}

	protected function getCustomParams() :array {
		return [
			'fScan'    => 'wcf',
			'fSlug'    => '',
			'fIgnored' => 'N',
		];
	}

	protected function getParamDefaults() :array {
		return array_merge(
			parent::getParamDefaults(),
			[ 'limit' => PHP_INT_MAX ]
		);
	}

	/**
	 * @param Scanner\EntryVO $entry
	 * @return string
	 */
	protected function formatIsIgnored( $entry ) {
		return ( $entry->ignored_at > 0 && Services::Request()->ts() > $entry->ignored_at ) ?
			__( 'Yes' ) : __( 'No' );
	}
}