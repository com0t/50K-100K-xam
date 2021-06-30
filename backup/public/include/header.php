<?php

	$isAdsEnabled = SGConfig::get('SG_DISABLE_ADS');
	$closeFreeBanner = SGConfig::get('SG_CLOSE_FREE_BANNER');

	if (!$isAdsEnabled && !$closeFreeBanner) {
		include_once(SG_NOTICE_TEMPLATES_PATH.'banner.php');
	}

	SGNotice::getInstance()->renderAll();
?>

<div class="sg-spinner"></div>
<div class="sg-wrapper-less">
	<div id="sg-wrapper">
