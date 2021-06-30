<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Utilities\Data\Response\StdResponse;
use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Session\Update;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider;
use FernleafSystems\Wordpress\Services\Services;

class MfaController {

	use Shield\Modules\ModConsumer;
	use Shield\Utilities\Consumer\WpLoginCapture;
	use ExecOnce;

	/**
	 * @var Provider\BaseProvider[]
	 */
	private $providers;

	/**
	 * @var LoginIntentPage
	 */
	private $oLoginIntentPageHandler;

	protected function run() {
		add_action( 'init', [ $this, 'onWpInit' ] );
		add_action( 'wp_loaded', [ $this, 'onWpLoaded' ] );
		$this->setupLoginCaptureHooks();
		$this->handleLoginLink();
	}

	public function onWpInit() {
		$user = Services::WpUsers()->getCurrentWpUser();
		if ( $user instanceof \WP_User ) {
			$this->assessLoginIntent( $user );
		}
	}

	public function onWpLoaded() {
		( new UserProfile() )
			->setMfaController( $this )
			->run();
		( new MfaProfilesController() )->setMfaController( $this )->execute();

		add_shortcode( 'SHIELD_2FA_LOGIN', function () {
			return $this->getLoginIntentPageHandler()->renderForm();
		} );
	}

	protected function captureLogin( \WP_User $user ) {
		$this->captureLoginIntent( $user );
	}

	private function captureLoginIntent( \WP_User $user ) {
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();
		if ( $this->isSubjectToLoginIntent( $user )
			 && !Services::WpUsers()->isAppPasswordAuth() && !$this->canUserMfaSkip( $user ) ) {

			$providers = $this->getProvidersForUser( $user, true );
			if ( !empty( $providers ) ) {
				foreach ( $providers as $provider ) {
					$provider->captureLoginAttempt( $user );
				}

				$this->setLoginIntentExpiresAt(
					Services::Request()
							->carbon()
							->addMinutes( $opts->getLoginIntentMinutes() )->timestamp
				);
			}
		}
	}

	private function handleLoginLink() {
		add_action( $this->getCon()->prefix( 'shield_nonce_action' ), function ( string $action ) {
			if ( strpos( $action, '2fa_verify' ) === 0 ) {
				try {
					$this->processEmail2faLink();
				}
				catch ( \Exception $e ) {
					wp_die( $e->getMessage() );
				}
			}
		} );
	}

	/**
	 * @throws \Exception
	 */
	private function processEmail2faLink() {
		$req = Services::Request();
		$user = sanitize_user( $req->query( 'user' ) );
		if ( empty( $user ) ) {
			throw new \Exception( 'Not valid data.' );
		}

		$user = Services::WpUsers()->getUserByUsername( $user );
		if ( !$user instanceof \WP_User ) {
			throw new \Exception( 'Not valid data.' );
		}

		$providers = $this->getProvidersForUser( $user, true );
		if ( !isset( $providers[ Provider\Email::SLUG ] ) ) {
			throw new \Exception( 'Not a support provider' );
		}
		if ( !$providers[ Provider\Email::SLUG ]->validateLoginIntent( $user ) ) {
			throw new \Exception( 'Login validation failed.' );
		}

		$providers[ Provider\Email::SLUG ]->postSuccessActions( $user );
		if ( (int)$user->ID !== (int)Services::WpUsers()->getCurrentWpUserId() ) {
			throw new \Exception( 'Action completed successfully. Please refresh your browser where you logged-in.' );
		}

		if ( $req->query( 'redirect_to' ) ) {
			Services::Response()->redirect( $req->query( 'redirect_to' ) );
		}
		else {
			Services::Response()->redirectToAdmin();
		}
	}

	private function assessLoginIntent( \WP_User $user ) {
		if ( $this->getLoginIntentExpiresAt() > 0 ) {

			if ( $this->isSubjectToLoginIntent( $user ) ) {

				if ( $this->getLoginIntentExpiresAt() > Services::Request()->ts() ) {
					$this->processActiveLoginIntent();
				}
				else {
					Services::WpUsers()->logoutUser(); // clears the login and login intent
					Services::Response()->redirectHere();
				}
			}
			else {
				// This handles the case where an admin changes a setting while a user is logged-in
				// So to prevent this, we remove any intent for a user that isn't subject to it right now
				$this->removeLoginIntent();
			}
		}
	}

	private function getLoginIntentPageHandler() :LoginIntentPage {
		if ( !isset( $this->oLoginIntentPageHandler ) ) {
			$this->oLoginIntentPageHandler = ( new LoginIntentPage() )->setMfaController( $this );
		}
		return $this->oLoginIntentPageHandler;
	}

	/**
	 * @return Provider\BaseProvider[]
	 */
	public function getProviders() :array {
		if ( !is_array( $this->providers ) ) {
			$this->providers = array_map(
				function ( $provider ) {
					return $provider->setMod( $this->getMod() );
				},
				[
					Provider\Email::SLUG       => new Provider\Email(),
					Provider\GoogleAuth::SLUG  => new Provider\GoogleAuth(),
					Provider\Yubikey::SLUG     => new Provider\Yubikey(),
					Provider\BackupCodes::SLUG => new Provider\BackupCodes(),
					Provider\U2F::SLUG         => new Provider\U2F(),
				]
			);
		}
		return $this->providers;
	}

	/**
	 * Ensures that BackupCode provider isn't supplied on its own, and the user profile is setup for each.
	 * @param \WP_User $user
	 * @param bool     $onlyActiveProfiles
	 * @return Provider\BaseProvider[]
	 */
	public function getProvidersForUser( \WP_User $user, $onlyActiveProfiles = false ) :array {
		$Ps = array_filter( $this->getProviders(),
			function ( $provider ) use ( $user, $onlyActiveProfiles ) {
				/** @var Provider\BaseProvider $provider */
				return $provider->isProviderAvailableToUser( $user )
					   && ( !$onlyActiveProfiles || $provider->isProfileActive( $user ) );
			}
		);

		// Neither BackupCode NOR U2F should EVER be the only 1 provider available.
		if ( count( $Ps ) === 1 ) {
			/** @var Provider\BaseProvider $first */
			$first = reset( $Ps );
			if ( !$first::STANDALONE ) {
				$Ps = [];
			}
		}
		return $Ps;
	}

	/**
	 * hooked to 'init' and only run if a user is logged-in (not on the login request)
	 */
	private function processActiveLoginIntent() {
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();
		$con = $this->getCon();
		$req = Services::Request();
		$WPResp = Services::Response();
		$WPUsers = Services::WpUsers();

		// Is 2FA/login-intent submit
		if ( $req->request( $this->getLoginIntentRequestFlag() ) == 1 ) {

			if ( $req->post( 'cancel' ) == 1 ) {
				$WPUsers->logoutUser(); // clears the login and login intent
				$sRedirectHref = $req->post( 'cancel_href' );
				empty( $sRedirectHref ) ? $WPResp->redirectToLogin() : $WPResp->redirect( $sRedirectHref );
			}
			elseif ( $this->validateLoginIntentRequest() ) {

				if ( $req->post( 'skip_mfa' ) === 'Y' ) {
					( new MfaSkip() )
						->setMod( $this->getMod() )
						->addMfaSkip( $WPUsers->getCurrentWpUser() );
				}

				$con->fireEvent( '2fa_success' );

				$sFlash = __( 'Success', 'wp-simple-firewall' ).'! '.__( 'Thank you for authenticating your login.', 'wp-simple-firewall' );
				if ( $opts->isEnabledBackupCodes() ) {
					$sFlash .= ' '.__( 'If you used your Backup Code, you will need to reset it.', 'wp-simple-firewall' ); //TODO::
				}
				$this->getMod()->setFlashAdminNotice( $sFlash );

				$this->removeLoginIntent();

				$sRedirectHref = $req->post( 'redirect_to' );
				empty( $sRedirectHref ) ? $WPResp->redirectHere() : $WPResp->redirect( rawurldecode( $sRedirectHref ) );
			}
			else {
				$con->getAdminNotices()
					->addFlash(
						__( 'One or more of your authentication codes failed or was missing.', 'wp-simple-firewall' ),
						true
					);
				// We don't protect against loops here to prevent bypassing of the login intent page.
				Services::Response()->redirect( Services::Request()->getUri(), [], true, false );
			}
		}
		elseif ( $opts->isUseLoginIntentPage() ) {
			$this->getLoginIntentPageHandler()->loadPage();
		}
		die();
	}

	/**
	 * assume that a user is logged in.
	 */
	private function validateLoginIntentRequest() :bool {
		try {
			$valid = ( new ValidateLoginIntentRequest() )
				->setMfaController( $this )
				->run();
		}
		catch ( \Exception $e ) {
			$valid = true;
		}
		return $valid;
	}

	private function canUserMfaSkip( \WP_User $user ) :bool {
		$canSkip = ( new MfaSkip() )
			->setMod( $this->getMod() )
			->canMfaSkip( $user );

		if ( !$canSkip && $this->getCon()->isPremiumActive() && @class_exists( 'WC_Social_Login' ) ) {
			// custom support for WooCommerce Social login
			$meta = $this->getCon()->getUserMeta( $user );
			$canSkip = isset( $meta->wc_social_login_valid ) ? $meta->wc_social_login_valid : false;
		}

		return (bool)apply_filters( 'icwp_shield_2fa_skip',
			apply_filters( 'odp-shield-2fa_skip', $canSkip ) );
	}

	public function isSubjectToLoginIntent( \WP_User $user ) :bool {
		return count( $this->getProvidersForUser( $user, true ) ) > 0;
	}

	public function removeAllFactorsForUser( int $userID ) :StdResponse {
		$result = new StdResponse();

		$user = Services::WpUsers()->getUserById( $userID );
		if ( $user instanceof \WP_User ) {
			foreach ( $this->getProvidersForUser( $user, true ) as $provider ) {
				$provider->remove( $user );
			}
			$result->success = true;
			$result->msg_text = sprintf( __( 'All MFA providers removed from user with ID %s.' ),
				$userID );
		}
		else {
			$result->success = false;
			$result->error_text = sprintf( __( "User doesn't exist with ID %s." ),
				$userID );
		}

		return $result;
	}

	private function getLoginIntentExpiresAt() :int {
		return $this->getCon()
					->getModule_Sessions()
					->getSessionCon()
					->hasSession() ? (int)$this->getMod()->getSession()->login_intent_expires_at : 0;
	}

	/**
	 * Use this ONLY when the login intent has been successfully verified.
	 * @return $this
	 */
	private function removeLoginIntent() {
		return $this->setLoginIntentExpiresAt( 0 );
	}

	protected function setLoginIntentExpiresAt( int $expiresAt ) :self {
		$sessMod = $this->getCon()->getModule_Sessions();
		$sessCon = $sessMod->getSessionCon();
		if ( $sessCon->hasSession() ) {
			/** @var Update $upd */
			$upd = $sessMod->getDbHandler_Sessions()->getQueryUpdater();
			$upd->updateLoginIntentExpiresAt( $sessCon->getCurrent(), $expiresAt );
		}
		return $this;
	}

	private function getLoginIntentRequestFlag() :string {
		return $this->getCon()->prefix( 'login-intent-request' );
	}
}