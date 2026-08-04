<?php
defined( 'ABSPATH' ) || exit;

class Aspen_Membership_Addon_Rules_Eligibility {
	const DEFAULT_MESSAGE = 'This add-on requires an active membership or a qualifying membership product in your cart.';
	private $repository;
	private $plan_products = array();
	private $memberships = array();

	public function __construct( Aspen_Membership_Addon_Rules_Repository $repository ) { $this->repository = $repository; }

	public function get_rules_for_product( $product ) {
		if ( ! $product instanceof WC_Product ) { return array(); }
		return array_values( array_filter( $this->repository->enabled(), function ( $rule ) use ( $product ) { return $this->product_matches_rule( $product, $rule ); } ) );
	}

	public function product_matches_rule( WC_Product $product, array $rule ) {
		$tag_id = absint( $rule['product_tag_term_id'] );
		$ids = array_filter( array( $product->get_id(), $product->get_parent_id() ) );
		foreach ( $ids as $id ) {
			if ( has_term( $tag_id, 'product_tag', $id ) ) { return true; }
		}
		return false;
	}

	public function user_has_active_plan( $user_id, $plan_id ) {
		$key = absint( $user_id ) . ':' . absint( $plan_id );
		if ( isset( $this->memberships[ $key ] ) ) { return $this->memberships[ $key ]; }
		$result = false;
		if ( $user_id && function_exists( 'wc_memberships_get_user_active_memberships' ) ) {
			foreach ( (array) wc_memberships_get_user_active_memberships( $user_id ) as $membership ) {
				if ( is_object( $membership ) && method_exists( $membership, 'get_plan_id' ) && absint( $membership->get_plan_id() ) === absint( $plan_id ) ) { $result = true; break; }
			}
		}
		return $this->memberships[ $key ] = $result;
	}

	public function get_plan_access_product_ids( $plan_id ) {
		$plan_id = absint( $plan_id );
		if ( isset( $this->plan_products[ $plan_id ] ) ) { return $this->plan_products[ $plan_id ]; }
		$plan = function_exists( 'wc_memberships_get_membership_plan' ) ? wc_memberships_get_membership_plan( $plan_id ) : false;
		$ids = $plan && method_exists( $plan, 'get_product_ids' ) ? array_map( 'absint', (array) $plan->get_product_ids() ) : array();
		return $this->plan_products[ $plan_id ] = array_values( array_unique( array_filter( $ids ) ) );
	}

	public function cart_contains_plan_access_product( $plan_id, $cart = null ) {
		$cart = $cart ?: ( function_exists( 'WC' ) ? WC()->cart : null );
		if ( ! $cart ) { return false; }
		$access_ids = $this->get_plan_access_product_ids( $plan_id );
		foreach ( $cart->get_cart() as $item ) {
			$ids = array_filter( array( absint( $item['product_id'] ?? 0 ), absint( $item['variation_id'] ?? 0 ) ) );
			$product = isset( $item['data'] ) && $item['data'] instanceof WC_Product ? $item['data'] : null;
			if ( $product && $product->get_parent_id() ) { $ids[] = $product->get_parent_id(); }
			if ( array_intersect( $access_ids, array_unique( $ids ) ) ) { return true; }
		}
		return false;
	}

	public function qualifies( array $rule ) {
		return $this->user_has_active_plan( get_current_user_id(), $rule['membership_plan_id'] ) || $this->cart_contains_plan_access_product( $rule['membership_plan_id'] );
	}

	public function qualifies_for_any( array $rules ) {
		foreach ( $rules as $rule ) { if ( $this->qualifies( $rule ) ) { return true; } }
		return false;
	}

	public function message( array $rules ) {
		foreach ( $rules as $rule ) { if ( ! empty( $rule['restriction_message'] ) ) { return $rule['restriction_message']; } }
		return __( self::DEFAULT_MESSAGE, 'aspen-membership-addon-rules' );
	}
}
