<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc\Table;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Table\BaseFileEntryFormatter;

class EntryFormatter extends BaseFileEntryFormatter {

	public function format() :array {
		$e = $this->getBaseData();
		$e[ 'status' ] = __( 'Unrecognised', 'wp-simple-firewall' );
		return $e;
	}

	/**
	 * @return string[]
	 */
	protected function getExplanation() :array {
		return [
			__( 'This file was discovered within one of your core WordPress directories.', 'wp-simple-firewall' ),
			__( "But it isn't part of the official WordPress distribution for this version.", 'wp-simple-firewall' ),
			__( "You may want to download it to ensure that the contents are what you expect.", 'wp-simple-firewall' )
			.' '.sprintf( __( "You can then click to '%s' or '%s' the file.", 'wp-simple-firewall' ),
				__( 'Ignore', 'wp-simple-firewall' ), __( 'Delete', 'wp-simple-firewall' ) ),
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function getSupportedActions() :array {
		return array_merge( parent::getSupportedActions(), [ 'delete', 'download' ] );
	}
}