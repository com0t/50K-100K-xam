<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\AdminNotices\NoticeVO;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\PluginUserMeta;

class AdminNotices {

	use Shield\Modules\ModConsumer;

	protected static $nCount = 0;

	public function run() {
		add_filter( $this->getCon()->prefix( 'collectNotices' ), [ $this, 'addNotices' ] );
		add_filter( $this->getCon()->prefix( 'ajaxAuthAction' ), [ $this, 'handleAuthAjax' ] );
	}

	public function handleAuthAjax( array $ajaxResponse ) :array {
		if ( empty( $ajaxResponse ) && Services::Request()->request( 'exec' ) === 'dismiss_admin_notice' ) {
			$ajaxResponse = $this->ajaxExec_DismissAdminNotice();
		}
		return $ajaxResponse;
	}

	protected function ajaxExec_DismissAdminNotice() :array {
		$aAjaxResponse = [];

		$sNoticeId = sanitize_key( Services::Request()->query( 'notice_id', '' ) );

		foreach ( $this->getAdminNotices() as $notice ) {
			if ( $sNoticeId == $notice->id ) {
				$this->setNoticeDismissed( $notice );
				$aAjaxResponse = [
					'success'   => true,
					'message'   => 'Admin notice dismissed', //not currently seen
					'notice_id' => $notice->id,
				];
				break;
			}
		}

		// leave response empty if it doesn't apply here, so other modules can process it.
		return $aAjaxResponse;
	}

	/**
	 * @param Shield\Utilities\AdminNotices\NoticeVO[] $aNotices
	 * @return Shield\Utilities\AdminNotices\NoticeVO[]
	 */
	public function addNotices( $aNotices ) {
		return array_merge( $aNotices, $this->buildNotices() );
	}

	/**
	 * @return Shield\Utilities\AdminNotices\NoticeVO[]
	 */
	protected function buildNotices() {
		$aNotices = [];

		foreach ( $this->getAdminNotices() as $notice ) {
			$this->preProcessNotice( $notice );
			if ( $notice->display ) {
				try {
					$this->processNotice( $notice );
					if ( $notice->display ) {
						$aNotices[] = $notice;
					}
				}
				catch ( \Exception $e ) {
				}
			}
		}

		return $aNotices;
	}

	/**
	 * @return NoticeVO[]
	 */
	protected function getAdminNotices() :array {
		return array_map(
			function ( $noticeDef ) {
				$noticeDef = Services::DataManipulation()
									 ->mergeArraysRecursive(
										 [
											 'schedule'         => 'conditions',
											 'type'             => 'promo',
											 'plugin_page_only' => true,
											 'valid_admin'      => true,
											 'plugin_admin'     => 'yes',
											 'can_dismiss'      => true,
											 'per_user'         => false,
											 'display'          => false,
											 'min_install_days' => 0,
											 'twig'             => true,
										 ],
										 $noticeDef
									 );
				return ( new NoticeVO() )->applyFromArray( $noticeDef );
			},
			$this->getOptions()->getAdminNotices()
		);
	}

	protected function preProcessNotice( NoticeVO $notice ) {
		$con = $this->getCon();
		$opts = $this->getOptions();

		if ( $notice->plugin_page_only && !$con->isModulePage() ) {
			$notice->non_display_reason = 'plugin_page_only';
		}
		elseif ( $notice->type == 'promo' && !$opts->isShowPromoAdminNotices() ) {
			$notice->non_display_reason = 'promo_hidden';
		}
		elseif ( $notice->valid_admin && !$con->isValidAdminArea() ) {
			$notice->non_display_reason = 'not_admin_area';
		}
		elseif ( $notice->plugin_admin == 'yes' && !$con->isPluginAdmin() ) {
			$notice->non_display_reason = 'not_plugin_admin';
		}
		elseif ( $notice->plugin_admin == 'no' && $con->isPluginAdmin() ) {
			$notice->non_display_reason = 'is_plugin_admin';
		}
		elseif ( $notice->min_install_days > 0 && $notice->min_install_days > $opts->getInstallationDays() ) {
			$notice->non_display_reason = 'min_install_days';
		}
		elseif ( static::$nCount > 0 && $notice->type !== 'error' ) {
			$notice->non_display_reason = 'max_nonerror_count';
		}
		elseif ( $notice->can_dismiss && $this->isNoticeDismissed( $notice ) ) {
			$notice->non_display_reason = 'dismissed';
		}
		elseif ( !$this->isDisplayNeeded( $notice ) ) {
			$notice->non_display_reason = 'not_needed';
		}
		else {
			static::$nCount++;
			$notice->display = true;
			$notice->non_display_reason = 'n/a';
		}

		$notice->template = '/notices/'.$notice->id;
	}

	/**
	 * @param NoticeVO $notice
	 * @return bool
	 */
	protected function isNoticeDismissed( $notice ) {
		$bDismissedUser = $this->isNoticeDismissedForCurrentUser( $notice );

		$aDisd = $this->getMod()->getDismissedNotices();
		$bDismissedMod = isset( $aDisd[ $notice->id ] ) && $aDisd[ $notice->id ] > 0;

		if ( !$notice->per_user && $bDismissedUser && !$bDismissedMod ) {
			$this->setNoticeDismissed( $notice );
		}

		return $bDismissedUser || $bDismissedMod;
	}

	/**
	 * @param NoticeVO $notice
	 * @return bool
	 */
	protected function isDisplayNeeded( NoticeVO $notice ) :bool {
		return true;
	}

	/**
	 * @param NoticeVO $notice
	 * @return bool
	 */
	protected function isNoticeDismissedForCurrentUser( $notice ) {
		$bDismissed = false;

		$meta = $this->getCon()->getCurrentUserMeta();
		if ( $meta instanceof PluginUserMeta ) {
			$sNoticeMetaKey = $this->getNoticeMetaKey( $notice );

			if ( isset( $meta->{$sNoticeMetaKey} ) ) {
				$bDismissed = true;

				// migrate from old-style array storage to plain Timestamp
				if ( is_array( $meta->{$sNoticeMetaKey} ) ) {
					$meta->{$sNoticeMetaKey} = $meta->{$sNoticeMetaKey}[ 'time' ];
				}
			}
		}

		return $bDismissed;
	}

	/**
	 * @param NoticeVO $notice
	 * @throws \Exception
	 */
	protected function processNotice( NoticeVO $notice ) {
		throw new \Exception( 'Unsupported Notice ID: '.$notice->id );
	}

	/**
	 * @param NoticeVO $notice
	 * @return $this
	 */
	protected function setNoticeDismissed( $notice ) {
		$nTs = Services::Request()->ts();

		$meta = $this->getCon()->getCurrentUserMeta();
		$sNoticeMetaKey = $this->getNoticeMetaKey( $notice );

		if ( $notice->per_user ) {
			if ( $meta instanceof PluginUserMeta ) {
				$meta->{$sNoticeMetaKey} = $nTs;
			}
		}
		else {
			$mod = $this->getMod();
			$aDismissed = $mod->getDismissedNotices();
			$aDismissed[ $notice->id ] = $nTs;
			$mod->setDismissedNotices( $aDismissed );

			// Clear out any old
			if ( $meta instanceof PluginUserMeta ) {
				unset( $meta->{$sNoticeMetaKey} );
			}
		}
		return $this;
	}

	/**
	 * @param NoticeVO $notice
	 * @return string
	 */
	private function getNoticeMetaKey( $notice ) {
		return 'notice_'.str_replace( [ '-', '_' ], '', $notice->id );
	}
}