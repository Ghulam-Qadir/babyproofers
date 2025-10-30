<?php
class babyproofers {
	private static $_instance = null;
	public static function instance() {
		if ( ! isset( self::$_instance ) ) {
			self::$_instance = new self;
		}
		return self::$_instance;
	}

	public function __construct() {
		$this->load();
	}

	public function load() {
		// Product render hooks
		add_filter( 'gform_pre_render_4', [$this, 'populate_image_choices'] );
		add_filter( 'gform_pre_validation_4', [$this, 'populate_image_choices'] );
		add_filter( 'gform_admin_pre_render_4', [$this, 'populate_image_choices'] );
		add_filter( 'gform_pre_submission_filter_4', [$this, 'populate_image_choices'] );
		// Ajex Call functions
		add_action( 'wp_ajax_get_product_cat', [$this, 'callback_function_get_product_cat'] );
		add_action( 'wp_ajax_nopriv_get_product_cat', [$this, 'callback_function_get_product_cat'] );
		add_action( 'wp_ajax_get_product_cat_child', [$this, 'callback_function_get_product_cat_child'] );
		add_action( 'wp_ajax_nopriv_get_product_cat_child', [$this, 'callback_function_get_product_cat_child'] );

		add_action( 'wp_enqueue_scripts', [$this, 'theme_prefix_enqueue_script'] );
		// Add filters
		add_filter( 'gform_field_input', [$this, 'custom_multiselect_html'], 10, 5 );

		add_filter( 'gform_pre_submission_4', [$this, 'custom_multiselect_pre_submission'] );
		add_filter( 'gform_save_field_value', [$this, 'custom_multiselect_save_value'], 10, 4 );

		add_action( 'wp_enqueue_scripts', [$this, 'enqueue_select2_for_gravity_forms'] );

		add_filter( 'gform_ajax_spinner_url', [$this, 'return_empty_string'] );
		// form submission hooks
		add_action( 'gform_after_submission_4', [$this, 'gf_add_to_cart'], 10, 2 );
		// info field validations
		add_filter( 'gform_field_validation_4_155', [$this, 'custom_conditional_validation_form_155'], 10, 4 );
		add_filter( 'gform_field_validation_4_156', [$this, 'custom_conditional_validation_form_156'], 10, 4 );
		add_filter( 'gform_field_validation_4_157', [$this, 'custom_conditional_validation_form_157'], 10, 4 );

		add_filter( 'gform_notification_events', [$this, 'add_event'] );

		// add_shortcode( 'entry_get', [$this, 'entry_data_get'] );
	}

	// public function entry_data_get() {

	// 	// $updatedEntry = GFAPI::get_entry( 117 );
	// 	// $form         = GFAPI::get_form( 4 );
	// 	// GFAPI::send_notifications( $form, $updatedEntry, 'manual_notification' );

	// }

	/**
	 ************************************************************
	 * 	 Create custom event for notification triger
	 ************************************************************
	 */
	public function add_event( $notification_events ) {
		$notification_events['manual_notification_user_create'] = __( 'On New User', 'gravityforms' );
		$notification_events['manual_notification_user_update'] = __( 'On field update', 'gravityforms' );
		return $notification_events;
	}

	public function gf_create_or_get_user_and_update_entry( $entry_id, $username, $email, $password, $user_field_id ) {
		$status = ''; // Track status message

		// 1. Check if the user already exists by email or username
		$user_id = email_exists( $email );
		if ( ! $user_id ) {
			$user_id = username_exists( $username );
		}

		// 2. If the user does not exist, create one
		if ( ! $user_id ) {
			$user_id = wp_create_user( $username, $password, $email );

			// Handle error during user creation
			if ( is_wp_error( $user_id ) ) {
				return [
					'status'  => 'error',
					'message' => $user_id->get_error_message(),
				];
			}

			// Set default role
			$user = new WP_User( $user_id );
			$user->set_role( 'subscriber' );

			$status = 'new';

			// 3. Update the entry with the user ID and password (if GFAPI is available)
			if ( class_exists( 'GFAPI' ) ) {
				GFAPI::update_entry_field( $entry_id, $user_field_id, $user_id );
				GFAPI::update_entry_field( $entry_id, 1302, $password );
			}

		} else {
			$status = 'exists';
		}

		// 4. Return user info with status
		$user_data = get_userdata( $user_id );
		return [
			'status'   => $status,
			'user_id'  => $user_id,
			'username' => $user_data->user_login,
			'email'    => $user_data->user_email,
		];
	}

	public function replace_merge_tags( $text, $entry, $form ) {

		foreach ( $form['fields'] as $field ) {
			$field_id        = $field->id;
			$field_label     = rgar( $field, 'label' );
			$field_value     = rgar( $entry, (string) $field_id ) ?: '';
			$merge_tag_id    = "{" . $field_id . "}";
			$merge_tag_label = "{" . $field_id . ":" . $field_label . "}";
			$text            = str_replace( [$merge_tag_id, $merge_tag_label], $field_value, $text );
		}
		return $text;
	}

	public function entery_get( $Id ) {
		$entry_id   = $Id;
		$result     = GFAPI::get_entry( $entry_id );
		$formObject = GFAPI::get_form( $result['form_id'] );
		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'gf_error', $result->get_error_message() );
		}

		$product_fields_id = $this->get_products_fields_id( $formObject );
		$values            = [];
		foreach ( $result as $key => $value ) {
			$base_id = explode( '.', $key )[0];
			if ( in_array( $base_id, $product_fields_id ) && trim( $value ) !== '' ) {
				$values[] = $value;
			}
		}
		return array_count_values( $values );
	}

	public function gf_add_to_cart( $entry, $form ) {

		$action_add    = isset( $entry['154.2'] ) && 'add-to-cart' === $entry['154.2'];
		$action_report = isset( $entry['154.1'] ) && 'download-report' === $entry['154.1'];

		// 🛒 ADD TO CART
		if ( $action_add ) {
			$product_id_qty = $this->entery_get( $entry['id'] );
			$this->add_products_to_cart( $product_id_qty );
			return;
		}

		// 📄 DOWNLOAD REPORT + USER HANDLING
		if ( $action_report ) {

			$username      = rgar( $entry, '155.3' );
			$email         = rgar( $entry, '156' );
			$password      = wp_generate_password();
			$user_field_id = 1301;

			$result = $this->gf_create_or_get_user_and_update_entry( $entry['id'], $username, $email, $password, $user_field_id );

			if ( is_wp_error( $result ) ) {
				error_log( 'GF User Creation Error: ' . $result->get_error_message() );
				return;
			}

			wp_set_current_user( $result['user_id'] );
			wp_set_auth_cookie( $result['user_id'], true );
			do_action( 'wp_login', $result['username'], get_userdata( $result['user_id'] ) );

			$product_id_qty = $this->entery_get( $entry['id'] );
			$this->add_products_to_cart( $product_id_qty );

			$updated_entry = GFAPI::get_entry( $entry['id'] );

			$notification_type = ( 'new' === $result['status'] )
			? 'manual_notification_user_create'
			: 'manual_notification_user_update';

			GFAPI::send_notifications( $form, $updated_entry, $notification_type );

			return;
		}
	}

/**
 * Add products to WooCommerce cart.
 *
 * @param array $product_id_qty Array of product IDs and quantities.
 */
	private function add_products_to_cart( $product_id_qty ) {
		if ( empty( $product_id_qty ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		WC()->cart->empty_cart();

		foreach ( $product_id_qty as $product_id => $qty ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				WC()->cart->add_to_cart( $product_id, $qty );
				do_action( 'woocommerce_add_to_cart', $product_id );
			}
		}
	}

	public function return_empty_string() {
		return '';
	}

	public function enqueue_select2_for_gravity_forms() {
		// Load Select2 CSS and JS from CDN
		wp_enqueue_style( 'select2-css', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/css/select2.min.css' );
		wp_enqueue_script( 'savestorage', get_stylesheet_directory_uri() . '/desolint/assets/savestorage.js', );
		wp_enqueue_script( 'select2-js', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/js/select2.min.js', array( 'jquery' ), null, true );

		wp_enqueue_style( 'babyproofers-custom-css', get_stylesheet_directory_uri() . '/desolint/assets/babyproofers.css', null, microtime() );
		wp_enqueue_script( 'babyproofers-js', get_stylesheet_directory_uri() . '/desolint/assets/babyproofers.js', null, microtime() );
	}

	public function custom_multiselect_html( $input, $field, $value, $lead_id, $form_id ) {

		// First check if $field is an object with the expected properties
		if ( ! is_object( $field ) || ! property_exists( $field, 'type' ) || 4 !== $form_id ) {
			return $input;
		}
		// Only proceed for select fields with multi-select enabled
		if ( 'select' !== $field->type ) {
			return $input;
		}

		$cssClass = $this->get_class( $field->cssClass, 'multiple-active' );
		if ( ! $cssClass ) {
			return $input;
		}

		try {
			// Convert value to array format consistently
			$selected_values = is_array( $value ) ? $value :
			( ! empty( $value ) ? array_map( 'trim', explode( ',', $value ) ) : [] );
			// Build select element
			$html = sprintf(
				'<select name="input_%d[]" id="input_%d_%d" multiple="multiple" class="gfield_select gform-select custom-class">',
				$field->id,
				$form_id,
				$field->id
			);
			// Add options if choices exist
			if ( ! empty( $field->choices ) && is_array( $field->choices ) ) {
				foreach ( $field->choices as $choice ) {
					if ( ! isset( $choice['value'] ) || ! isset( $choice['text'] ) ) {
						continue;
					}
					$html .= sprintf(
						'<option value="%s" %s>%s</option>',
						esc_attr( $choice['value'] ),
						in_array( $choice['value'], $selected_values ) ? 'selected="selected"' : '',
						esc_html( $choice['text'] )
					);
				}
			}
			$html .= '</select>';
			$html .= sprintf( '<input type="hidden" name="input_%d_isset" value="1">', $field->id );
			return $html;
		} catch ( Exception $e ) {
			error_log( 'Multi-select HTML generation error: ' . $e->getMessage() );
			return $input;
		}
	}

/**
 * [custom_multiselect_pre_submission ]
 * @param  [object] $form gravity form object
 * @return [array]     retrun a array number of ids
 */
	public function custom_multiselect_pre_submission( $form ) {
		if ( 3 !== $form['id'] ) {
			return $form;
		}
		try {
			foreach ( $form['fields'] as &$field ) {
				// Skip if not a select field or multi-select not enabled
				if ( ! isset( $field->type ) || 'select' !== $field->type ) {
					continue;
				}
				$field_id  = $field->id;
				$input_key = 'input_' . $field_id;
				// Only process if our hidden isset field was submitted
				if ( ! isset( $_POST[$input_key . '_isset'] ) ) {
					continue;
				}
				// Handle the multi-select values
				$_POST[$input_key] = isset( $_POST[$input_key] ) && is_array( $_POST[$input_key] )
				? implode( ',', array_map( 'sanitize_text_field', $_POST[$input_key] ) )
				: '';
			}
		} catch ( Exception $e ) {
			error_log( 'Multi-select pre-submission error: ' . $e->getMessage() );
		}
		return $form;
	}

/**
 * this is custom function which save a field type select mulitype values
 * @param  [object] $value
 * @param  [object] $field
 * @param  [object] $lead
 * @param  [object] $form
 * @return [array]
 */
	public function custom_multiselect_save_value( $value, $field, $lead, $form ) {
		// Basic validation checks
		if ( ! is_object( $field ) || ! property_exists( $field, 'type' ) || 4 !== $form['id'] ) {
			return $value;
		}
		if ( 'select' !== $field->type || empty( $field->enableMultiSelect ) ) {
			return $value;
		}
		return is_array( $value ) ? implode( ',', $value ) : $value;
	}

	function theme_prefix_enqueue_script() {
		$formObject        = GFAPI::get_form( 4 );
		$product_fields_id = $this->get_room_fields_id( $formObject );
		$localized_data    = array(
			'url'              => admin_url( 'admin-ajax.php' ),
			'product_field_id' => $product_fields_id,
		);
		wp_localize_script( 'jquery-migrate', 'var_babyproofers', $localized_data );
	}

/**
 * Filter WooCommerce products by room type meta field
 *
 * @param string $roomtype The room type to filter by
 * @return void Sends JSON response with product IDs
 */
	public function filter_product( $roomtype ) {
// Validate input
		if ( empty( $roomtype ) ) {
			return wp_send_json_error( 'Room type parameter is required' );
		}
// Get products with room type meta
		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids', // Only get IDs for better performance
			'meta_query' => array(
				array(
					'key'     => 'room_type',
					'value'   => sanitize_text_field( $roomtype ),
					'compare' => 'LIKE',
				),
			),
		);
		$terms       = [];
		$product_ids = get_posts( $args );
		foreach ( $product_ids as $key => $product_id ) {
			$terms[] = wp_get_post_terms( $product_id, 'product_cat' );
		}
		return $terms;
	}

	public function parent_term_get( $pro_cat ) {
		$data = [];
		foreach ( $pro_cat as $key => $parent_cat ) {
			foreach ( $parent_cat as $key => $single_cat ) {
				if ( 0 == $single_cat->parent ) {
					$data[] = [
						'name' => $single_cat->name,
						'id'   => $single_cat->term_id,
					];
				}
			}
		}
		return array_values( array_unique( $data, SORT_REGULAR ) );
	}

	public function child_term_get( $pro_id, $pro_room ) {
		$data = [];
		foreach ( $pro_room as $key => $parent_cat ) {
			foreach ( $parent_cat as $key => $single_cat ) {
				if ( in_array( $single_cat->parent, $pro_id ) ) {
					$data[] = [
						'name' => $single_cat->name,
						'id'   => $single_cat->term_id,
					];
				}
			}
		}
		return array_values( array_unique( $data, SORT_REGULAR ) );
	}

	public function callback_function_get_product_cat() {
		$orderRequestraw = $_REQUEST['formdata'];
		$decodedText     = wp_unslash( $orderRequestraw );
		$orderRequest    = json_decode( $decodedText, true );
		$pro_cat         = $this->filter_product( $orderRequest );
		$data            = $this->parent_term_get( $pro_cat );
		return wp_send_json( $data );
	}

	public function callback_function_get_product_cat_child() {
		$orderRequestraw = $_REQUEST['formdata'];
		$decodedText     = wp_unslash( $orderRequestraw );
		$orderRequest    = json_decode( $decodedText, true );
		$pro_cat         = $this->filter_product( $orderRequest['room'] );
		$data            = $this->child_term_get( $orderRequest['id'], $pro_cat );
		return wp_send_json( $data );
	}

	public function get_class( $class_list, $matchclass ) {
		$classes = explode( ' ', $class_list );
		foreach ( $classes as $cls ) {
			if ( strpos( $cls, $matchclass ) === 0 ) {
				return $cls; // return first match immediately
			}
		}
		return ''; // return empty string if no match found
	}
/**
 * [get_products_fields_id description]
 * @param  [type] $formObject [description]
 * @return [type]             [description]
 */
	public function get_products_fields_id( $formObject ) {
		$productIds = [];

		foreach ( $formObject['fields'] as $key => $field ) {
			$classList = $field->cssClass;
			if ( $this->get_class( $classList, 'product' ) ) {
				$productIds[] = $field->id;
			}

		}

		// Optionally: print or return
		return $productIds;
	}
/**
 * [get_room_fields_id description]
 * @param  [type] $formObject [description]
 * @return [type]             [description]
 */
	public function get_room_fields_id( $formObject ) {
		$roomIds = [];

		foreach ( $formObject['fields'] as $key => $field ) {
			$classList = $field->cssClass;
			if ( $this->get_class( $classList, 'room-box-select' ) ) {
				$roomIds[] = $field->id;
			}

		}

		// Optionally: print or return
		return $roomIds;
	}

/**
 * [conditionFieldObject description]
 * @param  [type] $formObject [description]
 * @return [type]             [description]
 */
	public function conditionFieldObject( $formObject ) {
		$objectdata = [];
		$currentObj = new stdClass();

		foreach ( $formObject as $key => $field ) {
			$classList = $field->cssClass;

			if ( $this->get_class( $classList, 'room-box-select' ) ) {
				$currentObj->room_type = $field->id;
			}
			if ( $this->get_class( $classList, 'room-parent-cat' ) ) {
				$currentObj->parent_cat = $field->id;
			}
			if ( $this->get_class( $classList, 'room-sub-cat' ) ) {
				$currentObj->sub_cat = $field->id;
			}
			if ( $this->get_class( $classList, 'product' ) ) {
				$currentObj->product_field_id = $field->id;
			}

			// Once all 4 properties are set, push and reset
			if (
				isset( $currentObj->room_type ) &&
				isset( $currentObj->parent_cat ) &&
				isset( $currentObj->sub_cat ) &&
				isset( $currentObj->product_field_id )
			) {
				$objectdata[] = $currentObj;
				$currentObj   = new stdClass(); // reset for next set
			}
		}

		// Optionally: print or return
		return $objectdata;
	}

	public function product_items( $form ) {
		$objectdata = [];

		$fields = $this->conditionFieldObject( $form['fields'] );

		foreach ( $fields as $key => $field ) {
			$ids_obj = new stdClass(); // ← instantiate inside loop

			$ids_obj->product_field_id = $field->product_field_id;
			$ids_obj->room_type        = rgpost( 'input_' . $field->room_type );
			$ids_obj->parent_cat       = rgpost( 'input_' . $field->parent_cat );
			$ids_obj->sub_cat          = rgpost( 'input_' . $field->sub_cat );

			$objectdata[] = $ids_obj;
		}

		return $objectdata;
	}

	public function populate_image_choices( $form ) {
		// Only run on frontend
		if ( is_admin() ) {
			return $form;
		}

		$form_fields_relation_group = $this->product_items( $form );

		foreach ( $form['fields'] as &$field ) {

			foreach ( $form_fields_relation_group as $key => $condition_data ) {

				$relation = ( ! empty( $condition_data->sub_cat ) ) ? 'AND' : "OR";

				if ( $condition_data->product_field_id == $field->id ) {

					$products = get_posts( [
						'post_type'      => 'product',
						'post_status'    => 'publish',
						'posts_per_page' => -1,
						'meta_query'     => [
							[
								'key'     => 'room_type',
								'value'   => sanitize_text_field( $condition_data->room_type ),
								'compare' => 'LIKE',
							],
						],
						'tax_query'      => [
							'relation' => $relation,
							[
								'taxonomy' => 'product_cat',
								'field'    => 'term_id',
								'terms'    => $condition_data->parent_cat, // array of parent category IDs
							],
							[
								'taxonomy' => 'product_cat',
								'field'    => 'term_id',
								'terms'    => $condition_data->sub_cat, // array of subcategory IDs
							],
						],
						'no_found_rows'  => true,
					] );

					$choices = [];
					foreach ( $products as $product ) {
						$product = wc_get_product( $product->ID );
						if ( ! $product ) {
							continue;
						}
						$selected_value = rgpost( 'input_' . $field->id );
						// $sub_cat = array_map( 'intval', (array) $condition_data->sub_cat );
						// Get product category IDs
						$product_cats = $product->get_category_ids();
						// Check if product has ANY of the selected categories

						$image_id  = $product->get_image_id();
						$choices[] = [
							'text'               => $product->get_name(),
							'value'              => (string) $product->get_id(),
							'imageChoices_image' => $image_id ? wp_get_attachment_url( $image_id ) : '/wp-content/uploads/2025/05/product-fallback-png.png',
							'isSelected'         => is_array( $selected_value )
							? in_array( (string) $product->get_id(), $selected_value )
							: ( (string) $product->get_id() === $selected_value ),
						];
					}
					$field->choices            = $choices;
					$field->enableChoiceImages = true;
					if ( 'checkbox' == $field->type ) {
						$inputs = [];
						foreach ( $choices as $i => $choice ) {
							$inputs[] = [
								'label' => $choice['text'],
								'name'  => '',
								'id'    => $field->id . '.' . ( $i + 1 ),
							];
						}
						$field->inputs = $inputs;
					}
				}
			}

			/*replace lable mergetag*/
			$entry = GFFormsModel::get_current_lead();

			if ( ! empty( $field->label ) ) {
				$field->label = $this->replace_merge_tags( $field->label, $entry, $form );
			}

		}
		return $form;
	}

	public function custom_conditional_validation_form_155( $result, $value, $form, $field ) {
		$entry       = GFFormsModel::get_current_lead();
		$first_name  = rgar( $value, $field->id . '.3' );
		$second_name = rgar( $value, $field->id . '.6' );

		if ( $result['is_valid'] && 'download-report' == $entry['154.1'] && empty( $first_name ) ) {
			$result['is_valid'] = false;
			$result['message']  = 'Please enter your full name.';
		}

		return $result;
	}

	public function custom_conditional_validation_form_156( $result, $value, $form, $field ) {

		$entry = GFFormsModel::get_current_lead();

		if ( $result['is_valid'] && 'download-report' == $entry['154.1'] && empty( $value ) ) {
			$result['is_valid'] = false;
			$result['message']  = 'Please enter your email address.';
		}

		return $result;
	}

	public function custom_conditional_validation_form_157( $result, $value, $form, $field ) {

		$entry = GFFormsModel::get_current_lead();

		if ( $result['is_valid'] && 'download-report' == $entry['154.1'] && empty( $value ) ) {
			$result['is_valid'] = false;
			$result['message']  = 'Please enter your phone number.';
		}

		return $result;
	}

} // class end
function babyproofersInstance() {
	$babyproofers = new babyproofers();
}
add_action( 'init', 'babyproofersInstance' );
