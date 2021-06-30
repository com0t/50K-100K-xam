<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\FileLocker\DecryptFile;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Encrypt\OpenSslEncryptVo;

/**
 * Class ReadOriginalFileContent
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops
 */
class ReadOriginalFileContent extends BaseOps {

	/**
	 * @param Databases\FileLocker\EntryVO $lock
	 * @return string
	 */
	public function run( $lock ) {
		try {
			$content = $this->useOriginalFile( $lock );
		}
		catch ( \Exception $e ) {
			$content = $this->useCacheAndApi( $lock );
		}
		return $content;
	}

	/**
	 * @param Databases\FileLocker\EntryVO $lock
	 * @return string|null
	 * @throws \Exception
	 */
	private function useOriginalFile( Databases\FileLocker\EntryVO $lock ) {
		$FS = Services::WpFs();
		if ( empty( $lock->detected_at ) && empty( $lock->hash_current )
			 && $FS->exists( $lock->file ) ) {
			return $FS->getFileContent( $lock->file );
		}
		throw new \Exception( 'Cannot use original file' );
	}

	/**
	 * @param Databases\FileLocker\EntryVO $lock
	 * @return string|null
	 */
	private function useCacheAndApi( Databases\FileLocker\EntryVO $lock ) {
		$sCacheKey = 'file-content-'.$lock->id;
		$content = wp_cache_get( $sCacheKey, $this->getCon()->prefix( 'filelocker' ) );
		if ( $content === false ) {
			$VO = ( new OpenSslEncryptVo() )->applyFromArray( json_decode( $lock->content, true ) );
			$content = ( new DecryptFile() )
				->setMod( $this->getMod() )
				->retrieve( $VO, $lock->public_key_id );
			wp_cache_set( $sCacheKey, $content, $this->getCon()->prefix( 'filelocker' ), 3 );
		}
		return $content;
	}
}