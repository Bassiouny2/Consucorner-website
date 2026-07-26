<?php
/**
 * Forminator installer for the profile Report & Support form.
 *
 * @package ConsuCorner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Return the option name that stores the Report & Support Forminator form ID.
 *
 * @return string
 */
function consucorner_profile_report_form_option_name() {
	return 'consucorner_forminator_report_form_id';
}

/**
 * Return the stored Report & Support Forminator form ID only when it points to
 * a real published Forminator form.
 *
 * @return int
 */
function consucorner_profile_get_report_form_id() {
	$form_id = absint( get_option( consucorner_profile_report_form_option_name() ) );
	if ( ! $form_id ) {
		return 0;
	}

	$form = get_post( $form_id );
	if ( ! $form || 'forminator_forms' !== $form->post_type || 'publish' !== $form->post_status ) {
		return 0;
	}

	return $form_id;
}

/**
 * Create the default Report & Support form once when Forminator is available.
 *
 * This is administrator-only and idempotent:
 * - if the option was deliberately set to 0 in wp-admin, it will not recreate;
 * - if the option points to a valid form, it does nothing;
 * - if a previously created theme form exists, it reuses that form;
 * - otherwise it creates a new Forminator form using Forminator's public API.
 */
function consucorner_profile_maybe_create_report_form() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( ! class_exists( 'Forminator_API' ) || ! class_exists( 'Forminator_Form_Model' ) ) {
		return;
	}

	$option_name = consucorner_profile_report_form_option_name();
	$stored_id   = get_option( $option_name, null );

	if ( null !== $stored_id ) {
		if ( absint( $stored_id ) && consucorner_profile_get_report_form_id() ) {
			return;
		}

		// Respect an intentional "0" value from the My Account meta box.
		if ( ! absint( $stored_id ) ) {
			return;
		}
	}

	$existing = get_posts(
		array(
			'post_type'      => 'forminator_forms',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_consucorner_profile_report_support_form',
			'meta_value'     => '1',
		)
	);

	if ( ! empty( $existing ) ) {
		update_option( $option_name, absint( $existing[0] ) );
		return;
	}

	$form_id = Forminator_API::add_form(
		'Report & Support',
		consucorner_profile_report_form_fields(),
		consucorner_profile_report_form_settings(),
		Forminator_Form_Model::STATUS_PUBLISH
	);

	if ( is_wp_error( $form_id ) || ! $form_id ) {
		update_option( 'consucorner_forminator_report_form_create_error', is_wp_error( $form_id ) ? $form_id->get_error_message() : 'Unknown Forminator error.' );
		return;
	}

	$form_id = absint( $form_id );
	update_post_meta( $form_id, '_consucorner_profile_report_support_form', '1' );
	update_option( $option_name, $form_id );
	delete_option( 'consucorner_forminator_report_form_create_error' );
}
add_action( 'init', 'consucorner_profile_maybe_create_report_form', 30 );

/**
 * Build the Forminator field wrappers for the profile support form.
 *
 * @return array
 */
function consucorner_profile_report_form_fields() {
	return array(
		array(
			'wrapper_id' => 'wrapper-report-issue',
			'fields'     => array(
				array(
					'element_id'  => 'select-1',
					'type'        => 'select',
					'cols'        => '6',
					'required'    => 'true',
					'field_label' => __( 'Issue Category', 'consucorner' ),
					'placeholder' => __( 'Select a category', 'consucorner' ),
					'options'     => consucorner_profile_report_form_options(
						array(
							'order_issue'     => __( 'Order Issue', 'consucorner' ),
							'payment_issue'   => __( 'Payment Issue', 'consucorner' ),
							'shipping_delay'  => __( 'Shipping / Delivery Delay', 'consucorner' ),
							'wrong_item'      => __( 'Wrong Item Received', 'consucorner' ),
							'damaged_item'    => __( 'Damaged / Defective Item', 'consucorner' ),
							'refund_request'  => __( 'Refund Request', 'consucorner' ),
							'account_access'  => __( 'Account Access Problem', 'consucorner' ),
							'technical_issue' => __( 'Website / Technical Issue', 'consucorner' ),
							'other'           => __( 'Other', 'consucorner' ),
						)
					),
				),
				array(
					'element_id'  => 'text-1',
					'type'        => 'text',
					'cols'        => '6',
					'required'    => false,
					'field_label' => __( 'Related Order #', 'consucorner' ),
					'placeholder' => __( 'e.g. #410184730', 'consucorner' ),
				),
			),
		),
		array(
			'wrapper_id' => 'wrapper-report-subject',
			'fields'     => array(
				array(
					'element_id'  => 'select-2',
					'type'        => 'select',
					'cols'        => '6',
					'required'    => false,
					'field_label' => __( 'Priority', 'consucorner' ),
					'options'     => consucorner_profile_report_form_options(
						array(
							'normal' => __( 'Normal', 'consucorner' ),
							'high'   => __( 'High - Affects my ability to order', 'consucorner' ),
							'urgent' => __( 'Urgent - Financial / undelivered order', 'consucorner' ),
						)
					),
				),
				array(
					'element_id'  => 'text-2',
					'type'        => 'text',
					'cols'        => '6',
					'required'    => 'true',
					'field_label' => __( 'Subject', 'consucorner' ),
					'placeholder' => __( 'Brief description of the issue', 'consucorner' ),
					'limit'       => '120',
					'limit_type'  => 'characters',
				),
			),
		),
		array(
			'wrapper_id' => 'wrapper-report-message',
			'fields'     => array(
				array(
					'element_id'  => 'textarea-1',
					'type'        => 'textarea',
					'cols'        => '12',
					'required'    => 'true',
					'field_label' => __( 'Description', 'consucorner' ),
					'placeholder' => __( 'Describe the issue in detail so our support team can assist you promptly...', 'consucorner' ),
					'input_type'  => 'paragraph',
					'limit_type'  => 'characters',
				),
			),
		),
		array(
			'wrapper_id' => 'wrapper-report-attachment',
			'fields'     => array(
				array(
					'element_id'      => 'upload-1',
					'type'            => 'upload',
					'cols'            => '12',
					'required'        => false,
					'field_label'     => __( 'Attachment', 'consucorner' ),
					'description'     => __( 'Accepted: JPG, PNG, PDF - Max 5 MB', 'consucorner' ),
					'filetypes'       => array( 'jpg', 'jpeg', 'png', 'pdf' ),
					'custom-files'    => 'true',
					'file-type'       => 'single',
					'file-limit'      => 'custom',
					'file-limit-input' => '1',
					'upload-limit'    => '5',
					'filesize'        => 'MB',
				),
				array(
					'element_id'    => 'hidden-1',
					'type'          => 'hidden',
					'field_label'   => __( 'Account User ID', 'consucorner' ),
					'default_value' => 'user_id',
				),
				array(
					'element_id'    => 'hidden-2',
					'type'          => 'hidden',
					'field_label'   => __( 'Account Email', 'consucorner' ),
					'default_value' => 'user_email',
				),
				array(
					'element_id'    => 'hidden-3',
					'type'          => 'hidden',
					'field_label'   => __( 'Account Name', 'consucorner' ),
					'default_value' => 'user_name',
				),
			),
		),
	);
}

/**
 * Convert value => label pairs into Forminator select options.
 *
 * @param array $options Options.
 * @return array
 */
function consucorner_profile_report_form_options( array $options ) {
	$items = array();
	foreach ( $options as $value => $label ) {
		$items[] = array(
			'label' => $label,
			'value' => $value,
			'limit' => '',
			'key'   => function_exists( 'forminator_unique_key' ) ? forminator_unique_key() : wp_generate_uuid4(),
		);
	}
	return $items;
}

/**
 * Build the default Forminator settings for the report form.
 *
 * @return array
 */
function consucorner_profile_report_form_settings() {
	return array(
		'form-type'            => 'default',
		'formName'             => 'Report & Support',
		'version'              => defined( 'FORMINATOR_VERSION' ) ? FORMINATOR_VERSION : '',
		'submission-behaviour' => 'behaviour-thankyou',
		'thankyou-message'     => __( 'Thanks. Your report has been submitted and our support team will contact you shortly.', 'consucorner' ),
		'submitData'           => array(
			'custom-submit-text'          => __( 'Submit Report', 'consucorner' ),
			'custom-invalid-form-message' => __( 'Please fix the highlighted fields and try again.', 'consucorner' ),
		),
		'enable-ajax'          => 'true',
		'validation-inline'    => true,
		'fields-style'         => 'open',
		'basic-fields-style'   => 'open',
		'form-expire'          => 'no_expire',
		'store_submissions'    => '1',
		'submission-file'      => 'delete',
		'cform-color-option'   => 'theme',
		'payment_require_ssl'  => false,
	);
}
