<?php

namespace FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Vulnerabilities;

use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes;

abstract class Base extends WpHashes\ApiBase {

	const API_ENDPOINT = 'vulnerabilities';
	const ASSET_TYPE = '';
	const RESPONSE_DATA_KEY = 'vulnerabilities';

	/**
	 * @return array[]|null
	 */
	public function query() {
		return parent::query();
	}

	protected function getApiUrl() :string {
		return parent::getApiUrl().'/'.$this->getRequestVO()->type;
	}

	/**
	 * @return RequestVO
	 */
	protected function getRequestVO() {
		/** @var RequestVO $req */
		$req = parent::getRequestVO();
		$req->type = static::ASSET_TYPE;
		return $req;
	}

	/**
	 * @return RequestVO
	 */
	protected function newReqVO() {
		return new RequestVO();
	}
}