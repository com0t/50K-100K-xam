<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render\WpListTable;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class ScanWpv extends ScanBase {

	/**
	 * @param array $aItem
	 * @return string
	 */
	public function column_asset( $aItem ) {
		$sContent = sprintf( '<span class="asset-title font-weight-bold">%s</span> v%s',
			$aItem[ 'asset_name' ], ltrim( $aItem[ 'asset_version' ], 'v' ) );

		$aButtons = [];

		$bHasUpdate = $aItem[ 'has_update' ];
		$aButtons[] = $this->buildActionButton_Custom(
			$bHasUpdate ? __( 'Apply Update', 'wp-simple-firewall' ) : __( 'No Update Available', 'wp-simple-firewall' ),
			[ ( $bHasUpdate ? 'custom-action text-success' : 'disabled' ) ],
			[
				'rid'           => $aItem[ 'id' ],
				'custom-action' => 'item_repair'
			]
		);

		if ( $aItem[ 'can_deactivate' ] ) {
			$aButtons[] = $this->buildActionButton_Custom(
				__( 'Deactivate', 'wp-simple-firewall' ),
				[ 'custom-action' ],
				[
					'rid'           => $aItem[ 'id' ],
					'custom-action' => 'item_asset_deactivate'
				]
			);
		}

		return $sContent.$this->buildActions( $aButtons );
	}

	/**
	 * @param array $item
	 * @return string
	 */
	public function column_vulnerability( $item ) {
		/** @var Scans\Wpv\WpVulnDb\WpVulnVO $vuln */
		$vuln = $item[ 'wpvuln_vo' ];
		$sContent = sprintf( '<span class="vuln-title">%s</span>', $vuln->title );

		$buttons = [
			$this->getActionButton_Ignore( $item[ 'id' ] ),
			sprintf( '<a href="%s" class="btn btn-sm btn-link text-info" target="_blank">%s</a>',
				$vuln->url, __( 'More Info', 'wp-simple-firewall' ) ),
		];
		return $sContent.$this->buildActions( $buttons );
	}

	/**
	 * @return array
	 */
	protected function get_bulk_actions() {
		return [
			'ignore' => __( 'Ignore', 'wp-simple-firewall' ),
		];
	}

	/**
	 * @return array
	 */
	public function get_columns() {
		return [
			'cb'            => '&nbsp;',
			'vulnerability' => __( 'Vulnerability', 'wp-simple-firewall' ),
			'asset'         => __( 'Asset Details', 'wp-simple-firewall' ),
			'created_at'    => __( 'Discovered', 'wp-simple-firewall' ),
		];
	}
}