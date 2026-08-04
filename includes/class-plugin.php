<?php
defined( 'ABSPATH' ) || exit;

final class Aspen_Membership_Addon_Rules_Plugin {
	private static $instance;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		load_plugin_textdomain( 'aspen-membership-addon-rules', false, dirname( plugin_basename( AMAR_FILE ) ) . '/languages' );
		$notices = new Aspen_Membership_Addon_Rules_Notices();
		$notices->register();
		$repository = new Aspen_Membership_Addon_Rules_Repository();
		$eligibility = new Aspen_Membership_Addon_Rules_Eligibility( $repository );
		( new Aspen_Membership_Addon_Rules_Admin( $repository, $eligibility ) )->register();

		if ( ! $this->dependencies_available() ) {
			return;
		}

		( new Aspen_Membership_Addon_Rules_Purchase_Restrictions( $eligibility ) )->register();
	}

	public function dependencies_available() {
		return class_exists( 'WooCommerce' )
			&& function_exists( 'wc_memberships_get_membership_plan' )
			&& ( class_exists( 'WC_Subscriptions' ) || function_exists( 'wcs_get_subscriptions' ) );
	}
}
