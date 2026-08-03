<?php
defined( 'ABSPATH' ) || exit;

class Aspen_Membership_Addon_Rules_Repository {
	const OPTION = 'aspen_membership_addon_rules';
	private $rules;

	public function all() {
		if ( null === $this->rules ) {
			$value = get_option( self::OPTION, array() );
			$this->rules = isset( $value['rules'] ) && is_array( $value['rules'] ) ? array_values( $value['rules'] ) : array();
		}
		return $this->rules;
	}

	public function enabled() {
		return array_values( array_filter( $this->all(), function ( $rule ) { return ! empty( $rule['enabled'] ); } ) );
	}

	public function find( $id ) {
		foreach ( $this->all() as $rule ) {
			if ( hash_equals( (string) $rule['id'], (string) $id ) ) {
				return $rule;
			}
		}
		return null;
	}

	public function save( array $rule ) {
		$rules = $this->all();
		$found = false;
		foreach ( $rules as $index => $existing ) {
			if ( $existing['id'] === $rule['id'] ) {
				$rules[ $index ] = $rule;
				$found = true;
			}
		}
		if ( ! $found ) {
			$rules[] = $rule;
		}
		$this->persist( $rules );
	}

	public function delete( $id ) {
		$this->persist( array_values( array_filter( $this->all(), function ( $rule ) use ( $id ) { return $rule['id'] !== $id; } ) ) );
	}

	public function duplicate_enabled( $plan_id, $tag_id, $except_id = '' ) {
		foreach ( $this->enabled() as $rule ) {
			if ( $rule['id'] !== $except_id && (int) $rule['membership_plan_id'] === $plan_id && (int) $rule['product_tag_term_id'] === $tag_id ) {
				return true;
			}
		}
		return false;
	}

	private function persist( array $rules ) {
		$this->rules = $rules;
		update_option( self::OPTION, array( 'version' => 1, 'rules' => $rules ), false );
	}
}
