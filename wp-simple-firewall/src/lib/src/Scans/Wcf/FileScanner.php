<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\Compare\CompareHash;

/**
 * Class FileScanner
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf
 */
class FileScanner extends Shield\Scans\Base\Files\BaseFileScanner {

	/**
	 * @param string $fullPath
	 * @return ResultItem|null
	 */
	public function scan( string $fullPath ) {
		$oResult = null;
		$oHashes = Services::CoreFileHashes();

		/** @var ResultItem $oRes */
		$oRes = $this->getScanActionVO()->getNewResultItem();
		$oRes->path_full = $fullPath;
		$oRes->path_fragment = $oHashes->getFileFragment( $fullPath );
		$oRes->md5_file_wp = $oHashes->getFileHash( $oRes->path_fragment );
		$oRes->is_missing = !Services::WpFs()->exists( $oRes->path_full );
		$oRes->is_checksumfail = !$oRes->is_missing && $this->isChecksumFail( $oRes );
		$oRes->is_excluded = $this->isExcluded( $oRes->path_fragment )
							 || ( $oRes->is_missing && $this->isExcludedMissing( $oRes->path_fragment ) );

		if ( !$oRes->is_excluded && ( $oRes->is_missing || $oRes->is_checksumfail ) ) {
			$oResult = $oRes;
		}

		return $oResult;
	}

	/**
	 * @param $sPathFragment
	 * @return false|int
	 */
	private function isExcluded( $sPathFragment ) {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		return !empty( $oAction->exclusions_files_regex ) && preg_match( $oAction->exclusions_files_regex, $sPathFragment );
	}

	/**
	 * @param $sPathFragment
	 * @return false|int
	 */
	private function isExcludedMissing( $sPathFragment ) {
		/** @var ScanActionVO $oAction */
		$oAction = $this->getScanActionVO();
		return !empty( $oAction->exclusions_missing_regex ) && preg_match( $oAction->exclusions_missing_regex, $sPathFragment );
	}

	/**
	 * @param ResultItem $item
	 * @return bool
	 */
	private function isChecksumFail( $item ) {
		$fail = false;
		if ( !$item->is_missing ) {
			try {
				$fail = ( strpos( $item->path_full, '.php' ) > 0 )
						 && !( new CompareHash() )->isEqualFileMd5( $item->path_full, $item->md5_file_wp );
			}
			catch ( \Exception $e ) {
			}
		}
		return $fail;
	}
}