<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Spam\Handlers;

class Forminator extends Base {

	protected function run() {
		add_filter( 'forminator_spam_protection', function ( $wasSpam ) {
			return $wasSpam || $this->isSpam();
		}, 1000 );
	}

	protected function getProviderName() :string {
		return 'Forminator';
	}

	protected function isProviderAvailable() :bool {
		return defined( 'FORMINATOR_VERSION' ) && @class_exists( '\Forminator' );
	}
}