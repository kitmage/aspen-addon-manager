<?php
defined( 'ABSPATH' ) || exit;

class Aspen_Membership_Addon_Rules_Purchase_Restrictions {
	private $eligibility;
	private $notices = array();

	public function __construct( Aspen_Membership_Addon_Rules_Eligibility $eligibility ) { $this->eligibility = $eligibility; }

	public function register() {
		add_filter( 'woocommerce_is_purchasable', array( $this, 'purchasable' ), 20, 2 );
		add_filter( 'woocommerce_variation_is_purchasable', array( $this, 'purchasable' ), 20, 2 );
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'classic_add_to_cart' ), 20, 6 );
		add_action( 'woocommerce_check_cart_items', array( $this, 'validate_entire_cart' ) );
		add_action( 'woocommerce_store_api_validate_add_to_cart', array( $this, 'store_api_add_to_cart' ), 20, 2 );
		add_action( 'woocommerce_store_api_validate_cart_item', array( $this, 'store_api_cart_item' ), 20, 2 );
		add_filter( 'woocommerce_loop_add_to_cart_link', array( $this, 'archive_link' ), 20, 3 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'single_message' ), 31 );
	}

	public function purchasable( $purchasable, $product ) {
		$rules = $this->eligibility->get_rules_for_product( $product );
		return $rules && ! $this->eligibility->qualifies_for_any( $rules ) ? false : $purchasable;
	}

	public function classic_add_to_cart( $passed, $product_id, $quantity = 1, $variation_id = 0 ) {
		$product = wc_get_product( $variation_id ?: $product_id );
		$error = $this->validation_error( $product );
		if ( $error ) { wc_add_notice( $error, 'error' ); return false; }
		return $passed;
	}

	public function validate_entire_cart() {
		if ( ! WC()->cart ) { return; }
		foreach ( WC()->cart->get_cart() as $item ) {
			$product = isset( $item['data'] ) ? $item['data'] : wc_get_product( $item['variation_id'] ?: $item['product_id'] );
			$error = $this->validation_error( $product, true );
			if ( $error && ! isset( $this->notices[ md5( $error ) ] ) ) { wc_add_notice( $error, 'error' ); $this->notices[ md5( $error ) ] = true; }
		}
	}

	public function store_api_add_to_cart( $product, $request = null ) { $this->throw_if_invalid( $product ); }

	public function store_api_cart_item( $cart_item, $cart_item_key = '' ) {
		$product = is_array( $cart_item ) && isset( $cart_item['data'] ) ? $cart_item['data'] : null;
		$this->throw_if_invalid( $product, true );
	}

	private function throw_if_invalid( $product, $cart_message = false ) {
		$error = $this->validation_error( $product, $cart_message );
		if ( ! $error ) { return; }
		if ( class_exists( '\\Automattic\\WooCommerce\\StoreApi\\Exceptions\\RouteException' ) ) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'aspen_membership_required', $error, 400 );
		}
		throw new Exception( $error );
	}

	private function validation_error( $product, $cart_message = false ) {
		if ( ! $product instanceof WC_Product ) { return ''; }
		$rules = $this->eligibility->get_rules_for_product( $product );
		if ( ! $rules || $this->eligibility->qualifies_for_any( $rules ) ) { return ''; }
		if ( ! $cart_message ) { return $this->eligibility->message( $rules ); }
		$rule = reset( $rules );
		$plan = wc_memberships_get_membership_plan( absint( $rule['membership_plan_id'] ) );
		$plan_name = $plan && method_exists( $plan, 'get_name' ) ? $plan->get_name() : __( 'required', 'aspen-membership-addon-rules' );
		return sprintf( __( '“%1$s” requires an active %2$s membership or a qualifying membership product in your cart.', 'aspen-membership-addon-rules' ), $product->get_name(), $plan_name );
	}

	public function archive_link( $html, $product, $args ) {
		$rules = $this->eligibility->get_rules_for_product( $product );
		if ( ! $rules || $this->eligibility->qualifies_for_any( $rules ) ) { return $html; }
		return sprintf( '<a href="%1$s" class="button">%2$s</a>', esc_url( $product->get_permalink() ), esc_html__( 'View requirements', 'aspen-membership-addon-rules' ) );
	}

	public function single_message() {
		global $product;
		if ( ! $product instanceof WC_Product || ! $product->is_in_stock() ) { return; }
		$rules = $this->eligibility->get_rules_for_product( $product );
		if ( ! $rules || $this->eligibility->qualifies_for_any( $rules ) ) { return; }
		$message = esc_html( $this->eligibility->message( $rules ) );
		$links = array();
		foreach ( $rules as $rule ) {
			foreach ( $this->eligibility->get_plan_access_product_ids( $rule['membership_plan_id'] ) as $id ) {
				$access_product = wc_get_product( $id );
				if ( $access_product && 'publish' === $access_product->get_status() ) { $links[ $id ] = sprintf( '<a href="%s">%s</a>', esc_url( $access_product->get_permalink() ), esc_html( $access_product->get_name() ) ); }
			}
		}
		if ( $links ) { $message .= ' ' . sprintf( esc_html__( 'Qualifying products: %s', 'aspen-membership-addon-rules' ), implode( ', ', $links ) ); }
		echo '<div class="woocommerce-info aspen-membership-requirement">' . wp_kses_post( $message ) . '</div>';
	}
}
