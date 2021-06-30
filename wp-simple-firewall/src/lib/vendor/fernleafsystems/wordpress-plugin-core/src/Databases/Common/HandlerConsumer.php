<?php

namespace FernleafSystems\Wordpress\Plugin\Core\Databases\Common;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Handler;

trait HandlerConsumer {

	/**
	 * @var Handler
	 */
	private $dbh;

	/**
	 * @return Handler|mixed
	 */
	public function getDbHandler() {
		return $this->dbh;
	}

	/**
	 * @param Handler $dbh
	 * @return $this
	 */
	public function setDbHandler( $dbh ) {
		$this->dbh = $dbh;
		return $this;
	}
}