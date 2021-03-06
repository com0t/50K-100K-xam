<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\License\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class LicenseEmails {

	use ModConsumer;

	public function sendLicenseWarningEmail() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$opts = $this->getOptions();

		$bCanSend = Services::Request()
							->carbon()
							->subDay( 1 )->timestamp > $opts->getOpt( 'last_warning_email_sent_at' );

		if ( $bCanSend ) {
			$opts->setOptAt( 'last_warning_email_sent_at' );
			$mod->saveModOptions();

			$aMessage = [
				__( 'Attempts to verify Shield Pro license has just failed.', 'wp-simple-firewall' ),
				sprintf( __( 'Please check your license on-site: %s', 'wp-simple-firewall' ), $mod->getUrl_AdminPage() ),
				sprintf( __( 'If this problem persists, please contact support: %s', 'wp-simple-firewall' ), 'https://support.getshieldsecurity.com/' )
			];
			$mod->getEmailProcessor()
				->sendEmailWithWrap(
					$mod->getPluginReportEmail(),
					'Pro License Check Has Failed',
					$aMessage
				);
			$this->getCon()->fireEvent( 'lic_fail_email' );
		}
	}

	public function sendLicenseDeactivatedEmail() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$opts = $this->getOptions();

		$canSend = Services::Request()
						   ->carbon()
						   ->subDay( 1 )->timestamp > $opts->getOpt( 'last_deactivated_email_sent_at' );

		if ( $canSend ) {
			$opts->setOptAt( 'last_deactivated_email_sent_at' );
			$mod->saveModOptions();

			$mod->getEmailProcessor()
				->sendEmailWithWrap(
					$mod->getPluginReportEmail(),
					'[Action May Be Required] Pro License Has Been Deactivated',
					[
						__( 'All attempts to verify Shield Pro license have failed.', 'wp-simple-firewall' ),
						sprintf( __( 'Please check your license on-site: %s', 'wp-simple-firewall' ), $mod->getUrl_AdminPage() ),
						sprintf( __( 'If this problem persists, please contact support: %s', 'wp-simple-firewall' ), 'https://support.getshieldsecurity.com/' )
					]
				);
		}
	}
}