<?php

require_once(SG_REQUEST_PATH.'SGRequest.php');

class SGReloadHandler
{
	private $host;
	private $uri;
	private $scheme;
	private $port;

	public function __construct($url)
	{
		$this->host = @$_SERVER['HTTP_HOST'];
		$this->url = $url;
		$this->port = @$_SERVER['SERVER_PORT'];
		$this->scheme = backupGuardGetCurrentUrlScheme();

		if (!$this->port) {
			$this->port = 80;
		}
	}

	public function reload()
	{
		$selectedReloadMethod = SGConfig::get('SG_BACKGROUND_RELOAD_METHOD');
		$url = $this->scheme.'://'.$this->host.$this->url."&method=".$selectedReloadMethod;

		$request = SGRequest::getInstance();
		$request->setUrl($url);
		$request->setParams(array());
		$request->setHeaders(array());
		$request->sendGetRequest(array(
			'blocking'    => false,
			'timeout'     => 0.5
		));
	}
}
