<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Htaccess;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class RootHtaccess {

	use ExecOnce;
	use PluginControllerConsumer;
	use PluginCronsConsumer;

	protected function run() {
		$this->setupCronHooks();
	}

	public function runDailyCron() {
		$hadFile = (bool)Services::WpFs()->exists( $this->getPathToHtaccess() );
		$couldAccess = $this->testCanAccessURL();

		if ( $hadFile && !$couldAccess ) {
			$this->deleteHtaccess();
		}
		elseif ( !$hadFile && $couldAccess ) {
			// Create the file and test you can access it. If not, delete it again.
			if ( $this->createHtaccess() && !$this->testCanAccessURL() ) {
				$this->deleteHtaccess();
			}
		}
	}

	private function testCanAccessURL() :bool {
		$httpReq = Services::HttpRequest();
		return $httpReq->get( $this->getTestURL() ) && $httpReq->lastResponse->getCode() < 400;
	}

	private function getTestURL() :string {
		return add_query_arg( [ 'rand' => rand( 1000, 9999 ) ], $this->getCon()->urls->forJs( 'plugin.js' ) );
	}

	private function getPathToHtaccess() :string {
		return path_join( $this->getCon()->getRootDir(), '.htaccess' );
	}

	private function deleteHtaccess() {
		Services::WpFs()->deleteFile( $this->getPathToHtaccess() );
	}

	private function createHtaccess() :bool {
		return Services::WpFs()->putFileContent(
			$this->getPathToHtaccess(),
			implode( "\n", [
				'Order Allow,Deny',
				'<FilesMatch "^.*\.(css|js|png|jpg|svg)$" >',
				' Allow from all',
				'</FilesMatch>',
			] )
		);
	}
}

