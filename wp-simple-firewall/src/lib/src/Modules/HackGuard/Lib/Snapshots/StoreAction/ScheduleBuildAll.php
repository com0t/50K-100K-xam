<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\FindAssetsToSnap;
use FernleafSystems\Wordpress\Services\Core\VOs;
use FernleafSystems\Wordpress\Services\Services;

class ScheduleBuildAll extends BaseBulk {

	public function build() {
		foreach ( $this->getAssetsThatNeedBuilt() as $asset ) {
			try {
				( new Build() )
					->setMod( $this->getMod() )
					->setAsset( $asset )
					->run();
			}
			catch ( \Exception $e ) {
			}
		}
	}

	public function hookBuild() {
		if ( wp_next_scheduled( $this->getCronHook() ) !== false ) {
			add_action( $this->getCronHook(), [ $this, 'build' ] );
		}
	}

	public function schedule() {
		$sHook = $this->getCronHook();
		if ( wp_next_scheduled( $sHook ) === false && count( $this->getAssetsThatNeedBuilt() ) > 0 ) {
			wp_schedule_single_event( Services::Request()->ts() + 15, $sHook );
		}
	}

	/**
	 * Only those that don't have a meta file or the versions are different
	 * @return VOs\WpPluginVo[]|VOs\WpThemeVo[]
	 */
	private function getAssetsThatNeedBuilt() {
		return array_filter(
			( new FindAssetsToSnap() )
				->setMod( $this->getMod() )
				->run(),
			function ( $asset ) {
				/** @var VOs\WpPluginVo|VOs\WpThemeVo $asset */
				try {
					$meta = ( new Load() )
						->setMod( $this->getMod() )
						->setAsset( $asset )
						->run()
						->getSnapMeta();
				}
				catch ( \Exception $e ) {
					$meta = null;
				}
				return ( empty( $meta ) || $asset->version !== $meta[ 'version' ] );
			}
		);
	}

	private function getCronHook() :string {
		return $this->getCon()->prefix( 'ptg_build_snapshots' );
	}
}