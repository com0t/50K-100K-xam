<?php

namespace FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Util;

/**
 * Class Diff
 * @package FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Util
 */
class Diff extends Base {

	const API_ENDPOINT = 'util/diff';
	const REQUEST_TYPE = 'POST';
	const RESPONSE_DATA_KEY = 'diff';

	/**
	 * @param string $sLeft
	 * @param string $sRight
	 * @return array|null
	 */
	public function getDiff( $sLeft, $sRight ) {
		$oReq = $this->getRequestVO();
		$oReq->left = $sLeft;
		$oReq->right = $sRight;
		return $this->query();
	}
}