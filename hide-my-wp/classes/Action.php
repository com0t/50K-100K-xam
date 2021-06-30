<?php
defined( 'ABSPATH' ) || die( 'Cheatin\' uh?' );

/**
 * Set the ajax action and call for wordpress
 */
class HMW_Classes_Action extends HMW_Classes_FrontController {

	/** @var array with all form and ajax actions */
	var $actions = array();

	/** @var array from core config */
	private static $config;


	/**
	 * The hookAjax is loaded as custom hook in hookController class
	 *
	 * @return void
	 */
	public function hookInit() {
		if ( HMW_Classes_Tools::isAjax() ) {
			$this->getActions( true );
		}
	}

	/**
	 * Hook the actions in the Frontend
	 */
	public function hookFrontinit() {
		/* Only if post */
		if ( HMW_Classes_Tools::isAjax() ) {
			$this->getActions();
		}
	}

	/**
	 * The hookSubmit is loaded when action si posted
	 *
	 * @return void
	 */
	public function hookMenu() {
		/* Only if post */
		if ( ! HMW_Classes_Tools::isAjax() ) {
			$this->getActions();
		}
	}

	/**
	 * Hook the actions for Multisite Network
	 */
	public function hookMultisiteMenu() {
		/* Only if post */
		if ( ! HMW_Classes_Tools::isAjax() ) {
			$this->getActions();
		}
	}


	/**
	 * Get the list with all the plugin actions
	 * @return array
	 */
	public function getActionsTable() {
		return array(
			array(
				"name"    => "HMW_Controllers_Settings",
				"actions" => array(
					"action" => array(
						"hmw_settings",
						"hmw_tweakssettings",
						"hmw_confirm",
						"hmw_newpluginschange",
						"hmw_mappsettings",
						"hmw_logout",
						"hmw_abort",
						"hmw_manualrewrite",
						"hmw_advsettings",
						"hmw_backup",
						"hmw_restore",
						"hmw_support",
						"hmw_connect",
						"hmw_dont_connect"
					)
				),
				"active"  => "1"
			),
			array(
				"name"    => "HMW_Controllers_Plugins",
				"actions" => array(
					"action" => array(
						"hmw_plugin_install"
					)
				),
				"active"  => "1"
			),
			array(
				"name"    => "HMW_Controllers_SecurityCheck",
				"actions" => array(
					"action" => array(
						"hmw_securitycheck",
						"hmw_securityexclude",
						"hmw_resetexclude"
					)
				),
				"active"  => "1"
			),
			array(
				"name"    => "HMW_Controllers_Brute",
				"actions" => array(
					"action" => array(
						"hmw_brutesettings",
						"hmw_blockedips",
						"hmw_deleteip",
						"hmw_deleteallips"
					)
				),
				"active"  => "1"
			),
			array(
				"name"    => "HMW_Controllers_Widget",
				"actions" => array(
					"action" => "hmw_widget_securitycheck"
				),
				"active"  => "1"
			),
			array(
				"name"    => "HMW_Controllers_Notice",
				"actions" => array(
					"action" => array(
						"hmw_disable_notice",
						"hmw_ignore_notice"
					)
				),
				"active"  => "1"
			)
		);
	}

	/**
	 * Get all actions from config.json in core directory and add them in the WP
	 *
	 * @param boolean $ajax
	 *
	 * @return void
	 */
	public function getActions( $ajax = false ) {

		if ( ! is_admin() && ! is_network_admin() ) {
			return;
		}

		$this->actions = array();
		$action        = HMW_Classes_Tools::getValue( 'action' );
		$nonce         = HMW_Classes_Tools::getValue( 'hmw_nonce' );

		if ( $action == '' || $nonce == '' ) {
			return;
		}

		$actions = $this->getActionsTable();

		foreach ( $actions as $block ) {
			if ( isset( $block['active'] ) && $block['active'] == 1 ) {
				/* if there is a single action */
				if ( isset( $block['actions']['action'] ) ) {
					/* if there are more actions for the current block */
					if ( ! is_array( $block['actions']['action'] ) ) {
						/* add the action in the actions array */
						if ( $block['actions']['action'] == $action ) {
							$this->actions[] = array( 'class' => $block['name'] );
						}
					} else {
						/* if there are more actions for the current block */
						foreach ( $block['actions']['action'] as $value ) {
							/* add the actions in the actions array */
							if ( $value == $action ) {
								$this->actions[] = array( 'class' => $block['name'] );
							}
						}
					}
				}
			}
		}

		if ( $ajax ) {
			check_ajax_referer( _HMW_NONCE_ID_, 'hmw_nonce' );
		} else {
			check_admin_referer( $action, 'hmw_nonce' );
		}
		/* add the actions in WP */
		foreach ( $this->actions as $actions ) {
			HMW_Classes_ObjController::getClass( $actions['class'] )->action();
		}
	}

}