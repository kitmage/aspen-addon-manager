<?php
defined( 'ABSPATH' ) || exit;

class Aspen_Membership_Addon_Rules_Notices {
	public function register() {
		add_action( 'admin_notices', array( $this, 'dependency_notice' ) );
	}

	public function dependency_notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$missing = array();
		if ( ! class_exists( 'WooCommerce' ) ) {
			$missing[] = 'WooCommerce';
		}
		if ( ! function_exists( 'wc_memberships_get_membership_plan' ) ) {
			$missing[] = 'WooCommerce Memberships';
		}
		if ( ! class_exists( 'WC_Subscriptions' ) && ! function_exists( 'wcs_get_subscriptions' ) ) {
			$missing[] = 'WooCommerce Subscriptions';
		}
		if ( $missing ) {
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( sprintf( __( 'Aspen Membership Add-on Rules is inactive because these required plugins are missing: %s.', 'aspen-membership-addon-rules' ), implode( ', ', $missing ) ) ) );
		}
	}
}
