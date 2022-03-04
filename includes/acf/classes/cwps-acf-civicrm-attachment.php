<?php
/**
 * CiviCRM Attachment Class.
 *
 * Handles CiviCRM Attachment functionality.
 *
 * @package CiviCRM_WP_Profile_Sync
 * @since 0.5.2
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



/**
 * CiviCRM Profile Sync CiviCRM Attachment Class.
 *
 * A class that encapsulates CiviCRM Attachment functionality.
 *
 * @since 0.5.2
 */
class CiviCRM_Profile_Sync_ACF_CiviCRM_Attachment {

	/**
	 * Plugin object.
	 *
	 * @since 0.5.2
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * ACF Loader object.
	 *
	 * @since 0.5.2
	 * @access public
	 * @var object $acf_loader The ACF Loader object.
	 */
	public $acf_loader;

	/**
	 * Parent (calling) object.
	 *
	 * @since 0.5.2
	 * @access public
	 * @var object $civicrm The parent object.
	 */
	public $civicrm;

	/**
	 * Mapper hooks registered flag.
	 *
	 * @since 0.5.2
	 * @access public
	 * @var bool $mapper_hooks The Mapper hooks registered flag.
	 */
	public $mapper_hooks = false;

	/**
	 * CiviCRM hooks registered flag.
	 *
	 * @since 0.5.2
	 * @access public
	 * @var bool $civicrm_hooks The CiviCRM hooks registered flag.
	 */
	public $civicrm_hooks = false;

	/**
	 * WordPress Attachment meta key.
	 *
	 * @since 0.5.2
	 * @access public
	 * @var string $attachment_wp_key The Attachment meta key.
	 */
	public $attachment_wp_key = '_cwps_attachment_wordpress_file';

	/**
	 * CiviCRM Attachment meta key.
	 *
	 * @since 0.5.2
	 * @access public
	 * @var string $attachment_wp_key The Attachment meta key.
	 */
	public $attachment_civicrm_key = '_cwps_attachment_civicrm_file';

	/**
	 * Public Attachment Fields.
	 *
	 * Mapped to their corresponding ACF Field Types.
	 *
	 * @since 0.5.2
	 * @access public
	 * @var array $attachment_fields The array of public Attachment Fields.
	 */
	public $attachment_fields = [
		'file' => 'file',
		'content' => 'text',
	];



	/**
	 * Constructor.
	 *
	 * @since 0.5.2
	 *
	 * @param object $parent The parent object reference.
	 */
	public function __construct( $parent ) {

		// Store references to objects.
		$this->plugin = $parent->acf_loader->plugin;
		$this->acf_loader = $parent->acf_loader;
		$this->civicrm = $parent;

		// Init when the ACF CiviCRM object is loaded.
		add_action( 'cwps/acf/civicrm/loaded', [ $this, 'register_hooks' ] );

	}



	/**
	 * Register WordPress hooks.
	 *
	 * @since 0.5.2
	 */
	public function register_hooks() {

		// Always register Mapper hooks.
		$this->register_mapper_hooks();

		// Add CiviCRM listeners once CiviCRM is available.
		add_action( 'civicrm_config', [ $this, 'civicrm_config' ], 10, 1 );

	}



	/**
	 * Register callbacks for Mapper events.
	 *
	 * @since 0.5.2
	 */
	public function register_mapper_hooks() {

		// Bail if already registered.
		if ( $this->mapper_hooks === true ) {
			return;
		}

		// Intercept before a set of ACF Fields has been updated.
		add_action( 'cwps/acf/mapper/acf_fields/saved/pre', [ $this, 'acf_fields_pre_save' ], 10 );

		// Declare registered.
		$this->mapper_hooks = true;

	}



	/**
	 * Unregister callbacks for Mapper events.
	 *
	 * @since 0.5.2
	 */
	public function unregister_mapper_hooks() {

		// Bail if already unregistered.
		if ( $this->mapper_hooks === false ) {
			return;
		}

		// Remove all Mapper listeners.
		remove_action( 'cwps/acf/mapper/acf_fields/saved/pre', [ $this, 'acf_fields_pre_save' ], 10 );

		// Declare unregistered.
		$this->mapper_hooks = false;

	}



	/**
	 * Callback for "civicrm_config".
	 *
	 * @since 0.5.2
	 *
	 * @param object $config The CiviCRM config object.
	 */
	public function civicrm_config( &$config ) {

		// Add CiviCRM listeners once CiviCRM is available.
		$this->register_civicrm_hooks();

	}



	/**
	 * Add listeners for CiviCRM Entity Tag operations.
	 *
	 * @since 0.5.2
	 */
	public function register_civicrm_hooks() {

		// Bail if already registered.
		if ( $this->civicrm_hooks === true ) {
			return;
		}

		// Add callback for CiviCRM "preDelete" hook.
		Civi::service( 'dispatcher' )->addListener(
			'civi.dao.preDelete',
			[ $this, 'entity_tag_pre_delete' ],
			-100 // Default priority.
		);

		// Declare registered.
		$this->civicrm_hooks = true;

	}



	/**
	 * Remove listeners from CiviCRM Entity Tag operations.
	 *
	 * @since 0.5.2
	 */
	public function unregister_civicrm_hooks() {

		// Bail if already unregistered.
		if ( $this->civicrm_hooks === false ) {
			return;
		}

		// Remove callback for CiviCRM "preDelete" hook.
		Civi::service( 'dispatcher' )->removeListener(
			'civi.dao.preDelete',
			[ $this, 'entity_tag_pre_delete' ]
		);

		// Declare unregistered.
		$this->civicrm_hooks = false;

	}



	// -------------------------------------------------------------------------



	/**
	 * Create a CiviCRM Attachment.
	 *
	 * If you want to "create" an Attachment, do not pass $data['id'] in. The
	 * presence of an ID will cause an update to that Attachment.
	 *
	 * @since 0.5.2
	 *
	 * @param string $data The Attachment data.
	 * @return array|bool $attachment The array of Attachment data, or false on failure.
	 */
	public function create( $data ) {

		// Init return.
		$attachment = false;

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $attachment;
		}

		// Define params to create new Attachment.
		$params = [
			'version' => 3,
		] + $data;

		// Call the API.
		$result = civicrm_api( 'Attachment', 'create', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) && $result['is_error'] == 1 ) {
			return $attachment;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $attachment;
		}

		// The result set should contain only one item.
		$attachment = array_pop( $result['values'] );

		// --<
		return $attachment;

	}



	/**
	 * Update a CiviCRM Attachment with a given set of data.
	 *
	 * This is an alias of `self::create()` except that we expect an ID to have
	 * been set in the data.
	 *
	 * @since 0.5.2
	 *
	 * @param array $data The CiviCRM Attachment data.
	 * @return array|bool The array of data from the CiviCRM API, or false on failure.
	 */
	public function update( $data ) {

		// Log and bail if there's no Attachment ID.
		if ( empty( $data['id'] ) ) {
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'message' => __( 'A numerical ID must be present to update an Attachment.', 'civicrm-wp-profile-sync' ),
				'data' => $data,
				'backtrace' => $trace,
			], true ) );
			return false;
		}

		// Pass through.
		return $this->create( $data );

	}



	/**
	 * Deletes a CiviCRM Attachment.
	 *
	 * @since 0.5.2
	 *
	 * @param array $data The numeric ID of the CiviCRM Attachment.
	 * @return bool $success True if successfully deleted, or false on failure.
	 */
	public function delete( $attachment_id ) {

		// Init return.
		$success = false;

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $success;
		}

		// Define params to delete this Phone Record.
		$params = [
			'version' => 3,
			'id' => $attachment_id,
		];

		// Call the API.
		$result = civicrm_api( 'Attachment', 'delete', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) && $result['is_error'] == 1 ) {
			return $success;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $success;
		}

		// The result set should contain only one item.
		$success = ( $result['values'] == '1' ) ? true : false;

		// --<
		return $success;

	}



	// -------------------------------------------------------------------------



	/**
	 * Make a renamed copy of a WordPress File for CiviCRM to copy.
	 *
	 * The CiviCRM API moves a File to the secure directory, so we need to make
	 * a copy of the File so that the WordPress Attachment remains valid.
	 *
	 * @since 0.5.2
	 *
	 * @param string $file The path to the File.
	 * @return string|bool $new_file The path to the copied File, or false on failure.
	 */
	public function file_copy_for_civicrm( $file ) {

		/*
		$e = new \Exception();
		$trace = $e->getTraceAsString();
		error_log( print_r( array(
			'method' => __METHOD__,
			'file' => $file,
			//'backtrace' => $trace,
		), true ) );
		*/

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return false;
		}

		// Extract the filename so we can rename it in CiviCRM style.
		$filename = pathinfo( $file, PATHINFO_BASENAME );
		$new_name = CRM_Utils_File::makeFileName( $filename );

		// Build path for new File.
		$new_file = str_replace( $filename, $new_name, $file );

		/*
		$e = new \Exception();
		$trace = $e->getTraceAsString();
		error_log( print_r( array(
			'method' => __METHOD__,
			'filename' => $filename,
			'new_name' => $new_name,
			'new_file' => $new_file,
			//'backtrace' => $trace,
		), true ) );
		*/

		// Try and copy the File.
		if ( ! copy( $file, $new_file ) ) {
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'message' => __( 'Could not copy File.', 'civicrm-wp-profile-sync' ),
				'file' => $file,
				'new_file' => $new_file,
				'backtrace' => $trace,
			], true ) );
			return false;
		}

		// --<
		return $new_file;

	}



	/**
	 * Make a renamed copy of a CiviCRM File for WordPress to copy.
	 *
	 * The WordPress moves a File to the uploads directory, so we need to make
	 * a copy of the File so that the CiviCRM Attachment remains valid.
	 *
	 * @since 0.5.2
	 *
	 * @param string $file The path to the File.
	 * @return string|bool $new_file The path to the copied File, or false on failure.
	 */
	public function file_copy_for_acf( $file ) {

		/*
		$e = new \Exception();
		$trace = $e->getTraceAsString();
		error_log( print_r( array(
			'method' => __METHOD__,
			'file' => $file,
			//'backtrace' => $trace,
		), true ) );
		*/

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return false;
		}

		// Use CiviCRM's method to duplicate.
		$new_file = CRM_Utils_File::duplicate( $file );

		/*
		$e = new \Exception();
		$trace = $e->getTraceAsString();
		error_log( print_r( array(
			'method' => __METHOD__,
			'new_file' => $new_file,
			//'backtrace' => $trace,
		), true ) );
		*/

		// --<
		return $new_file;

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the data for an Attachment.
	 *
	 * @since 0.5.2
	 *
	 * @param integer $attachment_id The numeric ID of the Attachment.
	 * @return array $attachment The array of Attachment data, or empty if none.
	 */
	public function get_by_id( $attachment_id ) {

		// Init return.
		$attachment = [];

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $attachment;
		}

		// Construct API query.
		$params = [
			'version' => 3,
			'id' => $attachment_id,
		];

		// Get Attachment details via API.
		$result = civicrm_api( 'Attachment', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) && $result['is_error'] == 1 ) {
			return $attachment;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $attachment;
		}

		// The result set should contain only one item.
		$attachment = (object) array_pop( $result['values'] );

		// --<
		return $attachment;

	}



	/**
	 * Gets the Attachments for a given Entity ID.
	 *
	 * @since 0.5.2
	 *
	 * @param integer $entity The "name" of the CiviCRM Entity.
	 * @param integer $entity_id The numeric ID of the CiviCRM Entity.
	 * @return array $attachment_data The array of Attachment data for the CiviCRM Contact.
	 */
	public function get_for_entity( $entity, $entity_id ) {

		// Init return.
		$attachment_data = [];

		// Bail if we have no Entity ID.
		if ( empty( $entity_id ) ) {
			return $attachment_data;
		}

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $attachment_data;
		}

		// Define params to get queried Attachments.
		$params = [
			'version' => 3,
			'sequential' => 1,
			'entity_table' => 'civicrm_' . $entity,
			'entity_id' => $entity_id,
			'options' => [
				'limit' => 0, // No limit.
			],
		];

		// Call the API.
		$result = civicrm_api( 'Attachment', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) && $result['is_error'] == 1 ) {
			return $attachment_data;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $attachment_data;
		}

		// The result set it what we want.
		$attachment_data = $result['values'];

		// --<
		return $attachment_data;

	}



	// -------------------------------------------------------------------------



	/**
	 * Gets the data for a CiviCRM File by its ID.
	 *
	 * The ID is the same although the Attachment API provides more data.
	 *
	 * @since 0.5.2
	 *
	 * @param string $filename The name of the File on disk.
	 * @return array $file The array of File data, or empty if none.
	 */
	public function file_get_by_id( $file_id ) {

		// Init return.
		$file = [];

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $file;
		}

		// Construct API query.
		$params = [
			'version' => 3,
			'id' => $file_id,
		];

		// Get File details via API.
		$result = civicrm_api( 'File', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) && $result['is_error'] == 1 ) {
			return $file;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $file;
		}

		// The result set should contain only one item.
		$file = (object) array_pop( $result['values'] );

		// --<
		return $file;

	}



	/**
	 * Gets the data for a CiviCRM File by its filename.
	 *
	 * We can't use the Attachment API to search for an entry by filename, but
	 * we can use the File API *happy face*. The ID of the Entity is the same,
	 * though the Attachment API provides more data.
	 *
	 * @since 0.5.2
	 *
	 * @param string $filename The name of the File on disk.
	 * @return array $file The array of File data, or empty if none.
	 */
	public function file_get_by_name( $filename ) {

		// Init return.
		$file = [];

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $file;
		}

		// Construct API query.
		$params = [
			'version' => 3,
			'uri' => $filename,
		];

		// Get File details via API.
		$result = civicrm_api( 'File', 'get', $params );

		// Bail if there's an error.
		if ( ! empty( $result['is_error'] ) && $result['is_error'] == 1 ) {
			return $file;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $file;
		}

		// The result set should contain only one item.
		$file = (object) array_pop( $result['values'] );

		// --<
		return $file;

	}



	// -------------------------------------------------------------------------



	/**
	 * Gets the CiviCRM Attachment Fields.
	 *
	 * @since 0.5.2
	 *
	 * @param string $filter The token by which to filter the array of Fields.
	 * @return array $fields The array of Field names.
	 */
	public function civicrm_fields_get( $filter = 'none' ) {

		// Only do this once per Field Type and filter.
		static $pseudocache;
		if ( isset( $pseudocache[ $filter ] ) ) {
			return $pseudocache[ $filter ];
		}

		// Init return.
		$fields = [];

		// Try and init CiviCRM.
		if ( ! $this->civicrm->is_initialised() ) {
			return $fields;
		}

		// Construct params.
		$params = [
			'version' => 3,
			'options' => [
				'limit' => 0, // No limit.
			],
		];

		// Call the API.
		$result = civicrm_api( 'Attachment', 'getfields', $params );

		// Override return if we get some.
		if ( $result['is_error'] == 0 && ! empty( $result['values'] ) ) {

			// Check for no filter.
			if ( $filter == 'none' ) {

				// Grab all of them.
				$fields = $result['values'];

			// Check public filter.
			} elseif ( $filter == 'public' ) {

				// Skip all but those defined in our public Attachment Fields array.
				foreach ( $result['values'] as $key => $value ) {
					if ( array_key_exists( $value['name'], $this->attachment_fields ) ) {
						$fields[] = $value;
					}
				}

			}

		}

		// Maybe add to pseudo-cache.
		if ( ! isset( $pseudocache[ $filter ] ) ) {
			$pseudocache[ $filter ] = $fields;
		}

		// --<
		return $fields;

	}



	// -------------------------------------------------------------------------



	/**
	 * Fires just before a set of ACF Fields have been updated.
	 *
	 * We need to find out if any of the ACF Fields are of type 'file' because
	 * there is no other way to find out whether it has been severed from its
	 * WordPress Attachment.
	 *
	 * @since 0.5.2
	 *
	 * @param array $args The array of WordPress params.
	 */
	public function acf_fields_pre_save( $args ) {

		/*
		$e = new \Exception();
		$trace = $e->getTraceAsString();
		error_log( print_r( array(
			'method' => __METHOD__,
			'args' => $args,
			//'backtrace' => $trace,
		), true ) );
		*/

		// When the ACF "Post ID" is numeric.
		if ( is_numeric( $args['post_id'] ) ) {

			// We need the Post itself.
			$post = get_post( $args['post_id'] );

			// Bail if this is a revision.
			if ( $post->post_type == 'revision' ) {
				return;
			}

		}

		/*
		 * Get existing Field values.
		 *
		 * These are actually the *old* values because we are hooking in *before*
		 * the Fields have been saved.
		 */
		$fields = get_fields( $args['post_id'], false );

		// Loop through the Fields and store any File Fields.
		$this->file_fields = [];
		foreach ( $fields as $selector => $value ) {
			$settings = get_field_object( $selector, $args['post_id'] );
			if ( $settings['type'] === 'file' ) {
				$this->file_fields[] = [
					'post_id' => $args['post_id'],
					'selector' => $selector,
					'attachment_id' => $value,
				];
			}
		}

		/*
		$e = new \Exception();
		$trace = $e->getTraceAsString();
		error_log( print_r( array(
			'method' => __METHOD__,
			'file_fields' => $this->file_fields,
			//'backtrace' => $trace,
		), true ) );
		*/

	}



	/**
	 * Gets the data for an ACF Field before it was saved.
	 *
	 * @since 0.5.2
	 *
	 * @param string $selector The ACF Field selector.
	 * @param integer $post_id The numeric ID of the WordPress Post.
	 * @return array|bool $file_field The data for the ACF Field, or false if not found.
	 */
	public function acf_field_pre_save_get( $selector = '', $post_id = 0 ) {

		// Init return.
		$file_field = false;

		// Bail if there are no File Fields.
		if ( empty( $this->file_fields ) ) {
			return $file_field;
		}

		// Was there a File before the ACF Field was saved?
		foreach ( $this->file_fields as $field ) {

			// Skip if the data doesn't match.
			if ( $field['selector'] !== $selector ) {
				continue;
			}
			if ( (int) $field['post_id'] !== (int) $post_id ) {
				continue;
			}

			// We've got it.
			$file_field = $field;
			break;

		}

		// --<
		return $file_field;

	}



	// -------------------------------------------------------------------------



	/**
	 * Get the value of an ACF "File" Field formatted for CiviCRM.
	 *
	 * The only kind of sync that an ACF File Field can do at the moment is to
	 * sync with a CiviCRM Custom Field.
	 *
	 * Other CiviCRM Entities can also accept files as "Attachments" but we'll
	 * return to that later.
	 *
	 * The ACF File Field return format can be either 'array', 'url' or 'id' so
	 * we need to extract the appropriate file info to send to CiviCRM.
	 *
	 * @since 0.5.2
	 *
	 * @param integer|null $value The Field value (the Attachment data).
	 * @param array $settings The ACF Field settings.
	 * @param array $args Any additional arguments.
	 * @return string $value The path of the file.
	 */
	public function value_get_for_civicrm( $value, $settings, $args ) {

		// Find the data from before the save operation.
		$file_field = $this->acf_field_pre_save_get( $args['selector'], $args['post_id'] );

		/*
		$e = new \Exception();
		$trace = $e->getTraceAsString();
		error_log( print_r( array(
			'method' => __METHOD__,
			'value' => $value,
			'settings' => $settings,
			'args' => $args,
			'file_field' => $file_field,
			//'backtrace' => $trace,
		), true ) );
		*/

		// When value is empty.
		if ( empty( $value ) ) {

			// Return early if it was previously empty.
			if ( empty( $file_field['attachment_id'] ) ) {

				/*
				$e = new \Exception();
				$trace = $e->getTraceAsString();
				error_log( print_r( array(
					'method' => __METHOD__,
					'message' => '========================= NO FILE BEFORE OR AFTER ===================================',
					//'backtrace' => $trace,
				), true ) );
				*/

				return '';
			}

			/*
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( array(
				'method' => __METHOD__,
				'message' => '========================= ACF FILE HAS BEEN DELETED ===================================',
				//'backtrace' => $trace,
			), true ) );
			*/

			// The ACF Field has been "unlinked" from the Attachment.

			// Look for previous Attachment metadata.
			$meta = $this->metadata_get( (int) $file_field['attachment_id'] );
			if ( empty( $meta['civicrm_file'] ) ) {
				return '';
			}

			// Try and delete the previous CiviCRM Attachment.
			$filename = pathinfo( $meta['civicrm_file'], PATHINFO_BASENAME );
			$civicrm_file = $this->file_get_by_name( $filename );
			if ( ! empty( $civicrm_file ) ) {

				// It seems the Custom Field is cleared by doing this.
				$this->delete( $civicrm_file->id );

				/*
				$e = new \Exception();
				$trace = $e->getTraceAsString();
				error_log( print_r( array(
					'method' => __METHOD__,
					'message' => '========================= CiviCRM Attachment DELETED ===================================',
					'meta' => $meta,
					'civicrm_file' => $civicrm_file,
					//'backtrace' => $trace,
				), true ) );
				*/

				// Mimic "civicrm_custom" for reverse syncs.
				$this->mimic_civicrm_custom( $settings, $args );

			}

			// Okay, bail.
			return '';

		// If it's an array, the WordPress Attachment ID is in the array.
		} elseif ( is_array( $value ) ) {
			$attachment_id = $value['id'];

		// When it's numeric, it's the ID of the Attachment.
		} elseif ( is_numeric( $value ) ) {
			$attachment_id = $value;

		// When it's a string, it must be the URL.
		} elseif ( is_string( $value ) ) {

			// Try and get the existing Attachment ID.
			$attachment_id = attachment_url_to_postid( $value );

		}

		// Bail if something is amiss.
		if ( empty( $attachment_id ) ) {
			return '';
		}

		// Make sure we have an integer.
		$attachment_id = (int) $attachment_id;

		// Get the current WordPress File.
		$file = get_attached_file( $attachment_id, true );

		// Get the current Attachment metadata.
		$meta = $this->metadata_get( $attachment_id );

		/*
		$e = new \Exception();
		$trace = $e->getTraceAsString();
		error_log( print_r( array(
			'method' => __METHOD__,
			'attachment_id' => $attachment_id,
			'file' => $file,
			'meta' => $meta,
			//'backtrace' => $trace,
		), true ) );
		*/

		// If we get metadata for the current Attachment.
		if ( ! empty( $meta ) ) {

			// Skip the update if there is no change.
			if ( $meta['wordpress_file'] === $file ) {

				/*
				$e = new \Exception();
				$trace = $e->getTraceAsString();
				error_log( print_r( array(
					'method' => __METHOD__,
					'message' => '========================= FILE EXISTS BUT HAS NOT CHANGED ===================================',
					//'backtrace' => $trace,
				), true ) );
				*/

				return '';
			}

		}

		// Check if the Attachment has been switched.
		if ( ! empty( $file_field['attachment_id'] ) ) {
			if ( (int) $file_field['attachment_id'] !== $attachment_id ) {

				/*
				$e = new \Exception();
				$trace = $e->getTraceAsString();
				error_log( print_r( array(
					'method' => __METHOD__,
					'message' => '========================= FILE HAS BEEN CHANGED ===================================',
					//'backtrace' => $trace,
				), true ) );
				*/

				// Try and delete the previous CiviCRM Attachment.
				$previous_meta = $this->metadata_get( (int) $file_field['attachment_id'] );
				if ( ! empty( $previous_meta['civicrm_file'] ) ) {
					$filename = pathinfo( $previous_meta['civicrm_file'], PATHINFO_BASENAME );
					$civicrm_file = $this->file_get_by_name( $filename );
					if ( ! empty( $civicrm_file ) ) {

						// It seems the Custom Field is cleared by doing this.
						$this->delete( $civicrm_file->id );

						/*
						$e = new \Exception();
						$trace = $e->getTraceAsString();
						error_log( print_r( array(
							'method' => __METHOD__,
							'message' => '========================= Previous CiviCRM Attachment DELETED ===================================',
							'previous_meta' => $previous_meta,
							'civicrm_file' => $civicrm_file,
							//'backtrace' => $trace,
						), true ) );
						*/

						// Mimic "civicrm_custom" for reverse syncs.
						$this->mimic_civicrm_custom( $settings, $args );

					}
				}

			}
		}

		// Make a copy of the file for CiviCRM to move.
		$new_file = $this->file_copy_for_civicrm( $file );
		if ( $new_file === false ) {
			return '';
		}

		// Save metadata.
		$data = [
			'wordpress_file' => $file,
			'civicrm_file' => $new_file,
		];

		// Store some Attachment metadata.
		$this->metadata_set( $attachment_id, $data );

		// Finally, build array for CiviCRM.
		$value = [
			'name' => $new_file,
			'type' => get_post_mime_type( $attachment_id ),
		];

		/*
		$e = new \Exception();
		$trace = $e->getTraceAsString();
		error_log( print_r( array(
			'method' => __METHOD__,
			'value-AFTER' => $value,
			//'backtrace' => $trace,
		), true ) );
		*/

		// --<
		return $value;

	}



	// -------------------------------------------------------------------------



	/**
	 * Gets the value of a "File" Field as required by an ACF Field.
	 *
	 * @since 0.5.2
	 *
	 * @param mixed $value The Custom Field value (the filename).
	 * @param array $field The Custom Field data.
	 * @param string $selector The ACF Field selector.
	 * @param mixed $post_id The ACF "Post ID".
	 * @return mixed $value The formatted Field value.
	 */
	public function value_get_for_acf( $value, $field, $selector, $post_id ) {

		/*
		$e = new \Exception();
		$trace = $e->getTraceAsString();
		error_log( print_r( [
			'method' => __METHOD__,
			'value' => $value,
			'field' => $field,
			'selector' => $selector,
			'post_id' => $post_id,
			//'backtrace' => $trace,
		], true ) );
		*/

		// Grab the raw data (Attachment ID) from the ACF Field.
		$existing = get_field( $selector, $post_id, false );

		/*
		$e = new \Exception();
		$trace = $e->getTraceAsString();
		error_log( print_r( [
			'method' => __METHOD__,
			'existing' => $existing,
			//'backtrace' => $trace,
		], true ) );
		*/

		// Assume no sync necessary.
		$sync = false;

		// If there's no ACF data.
		if ( empty( $existing ) ) {

			// We're good to sync.
			$sync = true;

		} else {

			// Get the current Attachment metadata.
			$meta = $this->metadata_get( (int) $existing );

			/*
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( [
				'method' => __METHOD__,
				'message' => '========================= HAS META ===================================',
				'meta' => $meta,
				//'backtrace' => $trace,
			], true ) );
			*/

			// If we get metadata for the current Attachment.
			if ( ! empty( $meta['civicrm_file'] ) ) {

				// Sync the new File if the filename has changed.
				$filename = pathinfo( $meta['civicrm_file'], PATHINFO_BASENAME );
				if ( ! empty( $filename ) && $filename !== $value ) {
					$sync = true;
				}

			}

		}

		/*
		$e = new \Exception();
		$trace = $e->getTraceAsString();
		error_log( print_r( [
			'method' => __METHOD__,
			'sync' => $sync ? 'y' : 'n',
			//'backtrace' => $trace,
		], true ) );
		*/

		// Bail if no sync is necessary.
		if ( $sync === false ) {

			// TODO: Is this right?

			/*
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			error_log( print_r( array(
				'method' => __METHOD__,
				'message' => '========================= SYNC NOT NECESSARY ===================================',
				//'backtrace' => $trace,
			), true ) );
			*/

			// The Attachment ID is the existing value.
			$value = (int) $existing;

			// Return early.
			return $value;

		}

		// Return early if we find an existing Attachment ID.
		$possible_id = $this->query_by_file( $value, 'civicrm' );
		if ( ! empty( $possible_id ) ) {
			$value = (int) $possible_id;
			return $value;
		}

		// Get CiviCRM config.
		$config = CRM_Core_Config::singleton();

		// Copy the File for WordPress to move.
		$tmp_name = $this->file_copy_for_acf( $config->customFileUploadDir . $value );

		/*
		$e = new \Exception();
		$trace = $e->getTraceAsString();
		error_log( print_r( array(
			'method' => __METHOD__,
			'file-to-duplicate' => $config->customFileUploadDir . $value,
			'duplicate-tmp_name' => $tmp_name,
			//'backtrace' => $trace,
		), true ) );
		*/

		// Find the name of the new File.
		$name = pathinfo( $tmp_name, PATHINFO_BASENAME );

		// Find the mime type of the File.
		$mime_type = wp_check_filetype( $tmp_name );

		// Find the filesize in bytes.
		$size = filesize( $tmp_name );

		/*
		 * Normally this is used to store an error should the upload fail.
		 * Since we aren't actually building an instance of $_FILES, we can
		 * default to zero instead.
		 */
		$error = 0;

		// Create an array that mimics $_FILES.
		$files = [
			'name' => $name,
			'type' => $mime_type,
			'tmp_name' => $tmp_name,
			'error' => $error,
			'size' => $size,
		];

		/*
		$e = new \Exception();
		$trace = $e->getTraceAsString();
		error_log( print_r( array(
			'method' => __METHOD__,
			'files-to-sideload' => $files,
			//'backtrace' => $trace,
		), true ) );
		*/

		// Only assign to a Post if the ACF "Post ID" is numeric.
		if ( ! is_numeric( $post_id ) ) {
			$target_post_id = null;
		} else {
			$target_post_id = $post_id;
		}

		// Possibly include the required files.
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Transfer the CiviCRM File to WordPress and grab ID.
		$attachment_id = media_handle_sideload( $files, $target_post_id );

		/*
		$e = new \Exception();
		$trace = $e->getTraceAsString();
		error_log( print_r( array(
			'method' => __METHOD__,
			'attachment_id' => $attachment_id,
			//'backtrace' => $trace,
		), true ) );
		*/

		// Save metadata.
		$data = [
			'wordpress_file' => get_attached_file( $attachment_id, true ),
			'civicrm_file' => $config->customFileUploadDir . $value,
		];

		// Store some Attachment metadata.
		$this->metadata_set( $attachment_id, $data );

		// Get the Attachment for the ID we've determined.
		$attachment = acf_get_attachment( $attachment_id );

		// The value in ACF is the Attachment ID.
		$value = $attachment['ID'];

		// --<
		return $value;

	}



	/**
	 * Mimics the values broadcast by the CiviCRM "civicrm_custom" hook.
	 *
	 * @since 0.5.2
	 *
	 * @param array $settings The ACF Field settings.
	 * @param array $args Any additional arguments.
	 */
	public function mimic_civicrm_custom( $settings, $args ) {

		// Get Field Group for this Field's parent.
		$field_group = $this->acf_loader->acf->field_group->get_for_field( $settings );

		/**
		 * Query for the Entity Tables that this ACF Field Group is mapped to.
		 *
		 * This filter sends out a request for other classes to respond with an
		 * Entity Table if they detect that the ACF Field Group maps to an
		 * Entity Type that they are responsible for.
		 *
		 * Internally, this is used by:
		 *
		 * @see CiviCRM_Profile_Sync_ACF_CiviCRM_Contact::query_entity_table()
		 * @see CiviCRM_Profile_Sync_ACF_CiviCRM_Activity::query_entity_table()
		 * @see CiviCRM_Profile_Sync_ACF_CiviCRM_Participant_CPT::query_entity_table()
		 * @see CiviCRM_Profile_Sync_ACF_CiviCRM_Participant::query_entity_table()
		 * @see CiviCRM_Profile_Sync_ACF_User::query_entity_table()
		 *
		 * Also used by CiviCRM Event Organiser:
		 *
		 * @see CiviCRM_WP_Event_Organiser_CWPS::query_entity_table()
		 *
		 * @since 0.5.2
		 *
		 * @param array $entity_tables Empty array, since we're asking for Entity Tables.
		 * @param array $field_group The array of ACF Field Group params.
		 */
		$entity_tables = apply_filters( 'cwps/acf/query_entity_table', [], $field_group );

		// Get the CiviCRM Custom Field.
		$custom_field = $this->plugin->civicrm->custom_field->get_by_id( $args['custom_field_id'] );

		/*
		$e = new \Exception();
		$trace = $e->getTraceAsString();
		error_log( print_r( array(
			'method' => __METHOD__,
			'field_group' => $field_group,
			'entity_tables' => $entity_tables,
			'custom_field' => $custom_field,
			//'backtrace' => $trace,
		), true ) );
		*/

		// Handle all Entity Tables.
		foreach ( $entity_tables as $entity_table ) {

			// Build Custom Field array.
			$custom_fields = [];
			$custom_fields[] = [
				'entity_table' => $entity_table,
				'entity_id' => (int) $args['entity_id'],
				'value' => '',
				'type' => 'File',
				'custom_field_id' => (int) $args['custom_field_id'],
				'custom_group_id' => (int) $custom_field['custom_group_id'],
				'is_multiple' => 0,
				'serialize' => 0,
				'file_id' => '',
			];

			// Mimic the CiviCRM params.
			$op = 'edit';
			$group_id = (int) $custom_field['custom_group_id'];
			$entity_id = (int) $args['entity_id'];

			/**
			 * Use ACF Mapper to broadcast that an Attachment has been deleted.
			 *
			 * We have to fire this action because "File" CiviCRM Custom Fields
			 * behave differently to other kinds of CiviCRM Custom Fields.
			 *
			 * They do not get updated because their value is empty and they are
			 * therefore not included in the array of edited Fields passed to the
			 * `civicrm_custom` hook.
			 *
			 * This means that any "reverse sync" processes do not occur and so
			 * any ACF Fields attached to other WordPress Entities do not receive
			 * the event that clears their value.
			 */
			$this->acf_loader->mapper->custom_edited( $op, $group_id, $entity_id, $custom_fields );

		}

	}



	// -------------------------------------------------------------------------



	/**
	 * Queries WordPress Attachments for a given File.
	 *
	 * @since 0.5.2
	 *
	 * @param string $filename The name of the File.
	 * @param string $source Either 'wp' or 'civicrm'. Default is 'civicrm'.
	 * @return integer|bool $attachment_id The ID of the WordPress Attachment, or false if none is found.
	 */
	public function query_by_file( $filename, $source = 'civicrm' ) {

		// Init return.
		$attachment_id = false;

		// Choose meta key.
		$meta_key = $this->attachment_civicrm_key;
		if ( $source !== 'civicrm' ) {
			$meta_key = $this->attachment_wp_key;
		}

		// Build arguments for query.
		$args = [
			'post_type' => 'attachment',
			'post_status' => 'any',
			'no_found_rows' => true,
			'meta_key' => $meta_key,
			'meta_value' => $filename,
			'meta_compare' => 'LIKE',
			'posts_per_page' => -1,
			'order' => 'ASC',
		];

		// Do the query.
		$query = new WP_Query( $args );

		/*
		$e = new \Exception();
		$trace = $e->getTraceAsString();
		error_log( print_r( array(
			'method' => __METHOD__,
			'args' => $args,
			//'query' => $query,
			//'backtrace' => $trace,
		), true ) );
		*/

		// There should be only one Attachment ID.
		if ( $query->have_posts() ) {
			foreach ( $query->get_posts() as $found ) {
				$attachment_id = $found->ID;
				break;
			}
		}

		// Reset Post data just in case.
		wp_reset_postdata();

		/*
		$e = new \Exception();
		$trace = $e->getTraceAsString();
		error_log( print_r( array(
			'method' => __METHOD__,
			'attachment_id' => $attachment_id,
			//'backtrace' => $trace,
		), true ) );
		*/

		// --<
		return $attachment_id;

	}



	// -------------------------------------------------------------------------



	/**
	 * Callback for the CiviCRM 'civi.dao.preDelete' hook.
	 *
	 * This is the only hook that fires when a File is deleted via the CiviCRM UI.
	 * All other deletions are direct SQL queries.
	 *
	 * We can't use the Attachment API to search for an entry by ID during the
	 * File deletion process because the "Entity File" has already been deleted.
	 * We can use the File API, however *happy face*.
	 *
	 * @since 0.5.2
	 *
	 * @param object $event The event object.
	 * @param string $hook The hook name.
	 */
	public function entity_tag_pre_delete( $event, $hook ) {

		// Extract CiviCRM Entity Tag for this hook.
		$entity_tag =& $event->object;

		// Bail if this isn't the type of object we're after.
		if ( ! ( $entity_tag instanceof CRM_Core_BAO_EntityTag ) ) {
			return;
		}

		// Make sure we have an Entity Table.
		if ( empty( $entity_tag->entity_table ) ) {
			return;
		}

		// Bail if this doesn't refer to a "File".
		if ( $entity_tag->entity_table !== 'civicrm_file' ) {
			return;
		}

		// Bail if there's no Entity ID.
		if ( empty( $entity_tag->entity_id ) ) {
			return;
		}

		/*
		$e = new \Exception();
		$trace = $e->getTraceAsString();
		error_log( print_r( array(
			'method' => __METHOD__,
			'entity_tag' => $entity_tag,
			//'backtrace' => $trace,
		), true ) );
		*/

		// The Entity ID happens to be the CiviCRM File ID.

		// Get the CiviCRM File being deleted.
		$civicrm_file = $this->file_get_by_id( $entity_tag->entity_id );
		if ( $civicrm_file === false ) {
			return;
		}

		/*
		$e = new \Exception();
		$trace = $e->getTraceAsString();
		error_log( print_r( array(
			'method' => __METHOD__,
			'civicrm_file' => $civicrm_file,
			//'backtrace' => $trace,
		), true ) );
		*/

		// Let's try and find a WordPress Attachment.
		$attachment_id = $this->query_by_file( $civicrm_file->uri, 'civicrm' );
		if ( empty( $attachment_id ) ) {
			return;
		}

		/*
		$e = new \Exception();
		$trace = $e->getTraceAsString();
		error_log( print_r( array(
			'method' => __METHOD__,
			'attachment_id' => $attachment_id,
			//'backtrace' => $trace,
		), true ) );
		*/

		/*
		 * We have found our way to the WordPress Attachment.
		 *
		 * What do we want to do now?
		 *
		 * We could delete it, which would at least guarantee that all ACF Fields
		 * that relate to it will refer to a non-existent Attachment.
		 *
		 * We could alter its metadata and trigger a change next time the ACF Field
		 * or CiviCRM Field gets updated.
		 *
		 * Hmm...
		 *
		 * @see CiviCRM_Profile_Sync_ACF_CiviCRM_Contact_Field::image_attachment_deleted()
		 */

		// Save metadata.
		$data = [
			'wordpress_file' => get_attached_file( $attachment_id, true ),
			'civicrm_file' => '',
		];

		/*
		$e = new \Exception();
		$trace = $e->getTraceAsString();
		error_log( print_r( array(
			'method' => __METHOD__,
			'data' => $data,
			//'backtrace' => $trace,
		), true ) );
		*/

		// Force-delete the Attachment.
		wp_delete_attachment( $attachment_id, true );

	}



	// -------------------------------------------------------------------------



	/**
	 * Gets the metadata for a given WordPress Attachment ID.
	 *
	 * @since 0.5.2
	 *
	 * @param integer $attachment_id The numeric ID of the WordPress Attachment.
	 * @return array $metadata The CiviCRM Attachment data, empty values if none exists.
	 */
	public function metadata_get( $attachment_id ) {

		// Init return.
		$metadata = [
			'wordpress_file' => '',
			'civicrm_file' => '',
		];

		// Get the WordPress File.
		$existing_wp = get_post_meta( $attachment_id, $this->attachment_wp_key, true );
		if ( ! empty( $existing_wp ) ) {
			$metadata['wordpress_file'] = $existing_wp;
		}

		// Get the CiviCRM File.
		$existing_civicrm = get_post_meta( $attachment_id, $this->attachment_civicrm_key, true );
		if ( ! empty( $existing_civicrm ) ) {
			$metadata['civicrm_file'] = $existing_civicrm;
		}

		// --<
		return $metadata;

	}



	/**
	 * Sets the metadata for a given WordPress Attachment ID.
	 *
	 * @since 0.5.2
	 *
	 * @param integer $attachment_id The numeric ID of the WordPress Attachment.
	 * @param array $metadata The CiviCRM Attachment data.
	 */
	public function metadata_set( $attachment_id, $metadata ) {

		// Store the Attachment metadata.
		update_post_meta( $attachment_id, $this->attachment_wp_key, $metadata['wordpress_file'] );
		update_post_meta( $attachment_id, $this->attachment_civicrm_key, $metadata['civicrm_file'] );

	}



	/**
	 * Deletes the metadata for a given WordPress Attachment ID.
	 *
	 * @since 0.5.2
	 *
	 * @param integer $attachment_id The numeric ID of the WordPress Attachment.
	 */
	public function metadata_delete( $attachment_id ) {

		// Delete the Attachment metadata.
		delete_post_meta( $attachment_id, $this->attachment_wp_key );
		delete_post_meta( $attachment_id, $this->attachment_civicrm_key );

	}



} // Class ends.



