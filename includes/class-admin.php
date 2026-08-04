<?php
defined( 'ABSPATH' ) || exit;

class Aspen_Membership_Addon_Rules_Admin {
	private $repository;
	private $eligibility;
	public function __construct( $repository, $eligibility ) { $this->repository = $repository; $this->eligibility = $eligibility; }
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_aspen_membership_addon_rule_save', array( $this, 'save' ) );
		add_action( 'admin_post_aspen_membership_addon_rule_action', array( $this, 'row_action' ) );
	}
	public function menu() { add_submenu_page( 'woocommerce', __( 'Membership Add-on Rules', 'aspen-membership-addon-rules' ), __( 'Membership Add-on Rules', 'aspen-membership-addon-rules' ), 'manage_woocommerce', 'aspen-membership-addon-rules', array( $this, 'render' ) ); }
	private function authorize( $action ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( esc_html__( 'You are not allowed to manage these rules.', 'aspen-membership-addon-rules' ), 403 ); }
		check_admin_referer( $action );
	}
	private function redirect( $message, $error = false ) { wp_safe_redirect( add_query_arg( array( 'page' => 'aspen-membership-addon-rules', $error ? 'amar_error' : 'amar_success' => rawurlencode( $message ) ), admin_url( 'admin.php' ) ) ); exit; }
	public function save() {
		$this->authorize( 'amar_save_rule' );
		$id = isset( $_POST['id'] ) ? sanitize_key( wp_unslash( $_POST['id'] ) ) : '';
		$rule = array(
			'id' => $id ?: wp_generate_uuid4(),
			'name' => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'enabled' => ! empty( $_POST['enabled'] ),
			'membership_plan_id' => absint( $_POST['membership_plan_id'] ?? 0 ),
			'product_tag_term_id' => absint( $_POST['product_tag_term_id'] ?? 0 ),
			'restriction_message' => sanitize_textarea_field( wp_unslash( $_POST['restriction_message'] ?? '' ) ),
		);
		if ( ! $rule['name'] || ! $rule['membership_plan_id'] || ! $rule['product_tag_term_id'] ) { $this->redirect( __( 'Rule name, membership plan, and product tag are required.', 'aspen-membership-addon-rules' ), true ); }
		$plan = wc_memberships_get_membership_plan( $rule['membership_plan_id'] );
		$tag = get_term( $rule['product_tag_term_id'], 'product_tag' );
		if ( ! $plan || 'publish' !== get_post_status( $rule['membership_plan_id'] ) || ! $tag || is_wp_error( $tag ) ) { $this->redirect( __( 'Choose a valid published membership plan and existing product tag.', 'aspen-membership-addon-rules' ), true ); }
		if ( $rule['enabled'] && $this->repository->duplicate_enabled( $rule['membership_plan_id'], $rule['product_tag_term_id'], $rule['id'] ) ) { $this->redirect( __( 'An enabled rule already uses that membership plan and product tag.', 'aspen-membership-addon-rules' ), true ); }
		$this->repository->save( $rule );
		$this->redirect( __( 'Rule saved.', 'aspen-membership-addon-rules' ) );
	}
	public function row_action() {
		$this->authorize( 'amar_rule_action' );
		$id = sanitize_key( wp_unslash( $_GET['id'] ?? '' ) );
		$operation = sanitize_key( wp_unslash( $_GET['operation'] ?? '' ) );
		$rule = $this->repository->find( $id );
		if ( ! $rule ) { $this->redirect( __( 'Rule not found.', 'aspen-membership-addon-rules' ), true ); }
		if ( 'delete' === $operation ) { $this->repository->delete( $id ); $this->redirect( __( 'Rule deleted.', 'aspen-membership-addon-rules' ) ); }
		if ( in_array( $operation, array( 'enable', 'disable' ), true ) ) {
			$rule['enabled'] = 'enable' === $operation;
			if ( $rule['enabled'] && $this->repository->duplicate_enabled( (int) $rule['membership_plan_id'], (int) $rule['product_tag_term_id'], $id ) ) { $this->redirect( __( 'An enabled rule already uses that membership plan and product tag.', 'aspen-membership-addon-rules' ), true ); }
			$this->repository->save( $rule ); $this->redirect( $rule['enabled'] ? __( 'Rule enabled.', 'aspen-membership-addon-rules' ) : __( 'Rule disabled.', 'aspen-membership-addon-rules' ) );
		}
		$this->redirect( __( 'Invalid action.', 'aspen-membership-addon-rules' ), true );
	}
	public function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( esc_html__( 'You are not allowed to manage these rules.', 'aspen-membership-addon-rules' ), 403 ); }
		$editing = isset( $_GET['edit'] ) ? $this->repository->find( sanitize_key( wp_unslash( $_GET['edit'] ) ) ) : null;
		$rule = $editing ?: array( 'id' => '', 'name' => '', 'enabled' => true, 'membership_plan_id' => 0, 'product_tag_term_id' => 0, 'restriction_message' => '' );
		$plans = get_posts( array( 'post_type' => 'wc_membership_plan', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		$tags = get_terms( array( 'taxonomy' => 'product_tag', 'hide_empty' => false ) );
		echo '<div class="wrap"><h1>' . esc_html__( 'Membership Add-on Rules', 'aspen-membership-addon-rules' ) . '</h1>';
		if ( isset( $_GET['amar_success'] ) ) { echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['amar_success'] ) ) ) . '</p></div>'; }
		if ( isset( $_GET['amar_error'] ) ) { echo '<div class="notice notice-error"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['amar_error'] ) ) ) . '</p></div>'; }
		echo '<p class="description">' . esc_html__( 'These rules must own the purchase restriction. Remove overlapping Memberships “Only Members Can Purchase” rules from tagged add-ons.', 'aspen-membership-addon-rules' ) . '</p>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Rule', 'aspen-membership-addon-rules' ) . '</th><th>' . esc_html__( 'Status', 'aspen-membership-addon-rules' ) . '</th><th>' . esc_html__( 'Membership plan', 'aspen-membership-addon-rules' ) . '</th><th>' . esc_html__( 'Product tag / published products', 'aspen-membership-addon-rules' ) . '</th><th>' . esc_html__( 'Diagnostics', 'aspen-membership-addon-rules' ) . '</th><th>' . esc_html__( 'Actions', 'aspen-membership-addon-rules' ) . '</th></tr></thead><tbody>';
		foreach ( $this->repository->all() as $item ) {
			$plan = wc_memberships_get_membership_plan( absint( $item['membership_plan_id'] ) );
			$tag = get_term( absint( $item['product_tag_term_id'] ), 'product_tag' );
			$count = ( $tag && ! is_wp_error( $tag ) ) ? $this->published_tag_count( $tag->term_id ) : 0;
			$warnings = array();
			if ( ! $plan || 'publish' !== get_post_status( $item['membership_plan_id'] ) ) { $warnings[] = __( 'Plan is missing or unpublished.', 'aspen-membership-addon-rules' ); }
			elseif ( ! $this->eligibility->get_plan_access_product_ids( $item['membership_plan_id'] ) ) { $warnings[] = __( 'Plan has no access-granting products.', 'aspen-membership-addon-rules' ); }
			if ( ! $tag || is_wp_error( $tag ) ) { $warnings[] = __( 'Product tag was deleted.', 'aspen-membership-addon-rules' ); }
			elseif ( ! $count ) { $warnings[] = __( 'No published products use this tag.', 'aspen-membership-addon-rules' ); }
			$base = array( 'action' => 'aspen_membership_addon_rule_action', 'id' => $item['id'], '_wpnonce' => wp_create_nonce( 'amar_rule_action' ) );
			$toggle = add_query_arg( $base + array( 'operation' => empty( $item['enabled'] ) ? 'enable' : 'disable' ), admin_url( 'admin-post.php' ) );
			$delete = add_query_arg( $base + array( 'operation' => 'delete' ), admin_url( 'admin-post.php' ) );
			$edit = add_query_arg( array( 'page' => 'aspen-membership-addon-rules', 'edit' => $item['id'] ), admin_url( 'admin.php' ) );
			echo '<tr><td><strong>' . esc_html( $item['name'] ) . '</strong></td><td>' . esc_html( ! empty( $item['enabled'] ) ? __( 'Enabled', 'aspen-membership-addon-rules' ) : __( 'Disabled', 'aspen-membership-addon-rules' ) ) . '</td><td>' . esc_html( $plan ? $plan->get_name() : '#' . absint( $item['membership_plan_id'] ) ) . '</td><td>' . esc_html( ( $tag && ! is_wp_error( $tag ) ? $tag->name : '#' . absint( $item['product_tag_term_id'] ) ) . ' (' . $count . ')' ) . '</td><td>' . ( $warnings ? esc_html( implode( ' ', $warnings ) ) : esc_html__( 'OK', 'aspen-membership-addon-rules' ) ) . '</td><td><a href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit', 'aspen-membership-addon-rules' ) . '</a> | <a href="' . esc_url( $toggle ) . '">' . esc_html( empty( $item['enabled'] ) ? __( 'Enable', 'aspen-membership-addon-rules' ) : __( 'Disable', 'aspen-membership-addon-rules' ) ) . '</a> | <a href="' . esc_url( $delete ) . '" onclick="return confirm(\'' . esc_js( __( 'Delete this rule?', 'aspen-membership-addon-rules' ) ) . '\')">' . esc_html__( 'Delete', 'aspen-membership-addon-rules' ) . '</a></td></tr>';
		}
		if ( ! $this->repository->all() ) { echo '<tr><td colspan="6">' . esc_html__( 'No rules configured.', 'aspen-membership-addon-rules' ) . '</td></tr>'; }
		echo '</tbody></table><hr><h2>' . esc_html( $editing ? __( 'Edit rule', 'aspen-membership-addon-rules' ) : __( 'Add rule', 'aspen-membership-addon-rules' ) ) . '</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'amar_save_rule' ); echo '<input type="hidden" name="action" value="aspen_membership_addon_rule_save"><input type="hidden" name="id" value="' . esc_attr( $rule['id'] ) . '"><table class="form-table"><tr><th><label for="amar-name">' . esc_html__( 'Rule name', 'aspen-membership-addon-rules' ) . '</label></th><td><input required class="regular-text" id="amar-name" name="name" value="' . esc_attr( $rule['name'] ) . '"></td></tr><tr><th>' . esc_html__( 'Enabled', 'aspen-membership-addon-rules' ) . '</th><td><label><input type="checkbox" name="enabled" value="1" ' . checked( ! empty( $rule['enabled'] ), true, false ) . '> ' . esc_html__( 'Apply this rule', 'aspen-membership-addon-rules' ) . '</label></td></tr>';
		echo '<tr><th><label for="amar-plan">' . esc_html__( 'Membership plan', 'aspen-membership-addon-rules' ) . '</label></th><td><select class="wc-enhanced-select" required id="amar-plan" name="membership_plan_id"><option value="">' . esc_html__( 'Select a plan', 'aspen-membership-addon-rules' ) . '</option>'; foreach ( $plans as $plan_post ) { echo '<option value="' . absint( $plan_post->ID ) . '" ' . selected( $rule['membership_plan_id'], $plan_post->ID, false ) . '>' . esc_html( $plan_post->post_title ) . '</option>'; } echo '</select></td></tr>';
		echo '<tr><th><label for="amar-tag">' . esc_html__( 'Add-on product tag', 'aspen-membership-addon-rules' ) . '</label></th><td><select class="wc-enhanced-select" required id="amar-tag" name="product_tag_term_id"><option value="">' . esc_html__( 'Select a tag', 'aspen-membership-addon-rules' ) . '</option>'; if ( ! is_wp_error( $tags ) ) { foreach ( $tags as $tag_option ) { echo '<option value="' . absint( $tag_option->term_id ) . '" ' . selected( $rule['product_tag_term_id'], $tag_option->term_id, false ) . '>' . esc_html( $tag_option->name . ' (' . $this->published_tag_count( $tag_option->term_id ) . ')' ) . '</option>'; } } echo '</select></td></tr>';
		echo '<tr><th><label for="amar-message">' . esc_html__( 'Restriction message', 'aspen-membership-addon-rules' ) . '</label></th><td><textarea class="large-text" rows="3" id="amar-message" name="restriction_message" placeholder="' . esc_attr__( Aspen_Membership_Addon_Rules_Eligibility::DEFAULT_MESSAGE, 'aspen-membership-addon-rules' ) . '">' . esc_textarea( $rule['restriction_message'] ) . '</textarea></td></tr></table>';
		submit_button( $editing ? __( 'Update rule', 'aspen-membership-addon-rules' ) : __( 'Add rule', 'aspen-membership-addon-rules' ) ); echo '</form></div>';
	}
	private function published_tag_count( $term_id ) {
		$query = new WP_Query( array( 'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids', 'tax_query' => array( array( 'taxonomy' => 'product_tag', 'field' => 'term_id', 'terms' => absint( $term_id ) ) ) ) );
		return (int) $query->found_posts;
	}
}
