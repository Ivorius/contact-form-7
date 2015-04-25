<?php
add_action( 'wpcf7_init', 'form7_multiplefiles' );
 
function form7_multiplefiles() {
    wpcf7_add_shortcode( array( 'multiplefiles', 'multiplefiles*'), 'form7_multiplefiles_handler', true );
}
 
function form7_multiplefiles_handler( $tag ) {
 $tag = new WPCF7_Shortcode( $tag );
 
 	if ( empty( $tag->name ) )
		return '';

	$validation_error = wpcf7_get_validation_error( $tag->name );
	
	$class = wpcf7_form_controls_class( $tag->type );

	if ( $validation_error )
		$class .= ' wpcf7-not-valid';

	$atts = array();

	$atts['size'] = $tag->get_size_option( '40' );
	$atts['class'] = $tag->get_class_option( $class );
	$atts['id'] = $tag->get_id_option();
	$atts['tabindex'] = $tag->get_option( 'tabindex', 'int', true );

	if ( $tag->is_required() )
		$atts['aria-required'] = 'true';

	$atts['aria-invalid'] = $validation_error ? 'true' : 'false';

	$atts['type'] = 'file';
	$atts['name'] = $tag->name . "[]";
	$atts['value'] = '1';
 
	$atts = wpcf7_format_atts( $atts );
	
	$html = sprintf(
		'<span class="wpcf7-form-control-wrap %1$s"><input %2$s multiple="multiple" />%3$s</span>',
		sanitize_html_class( $tag->name ), $atts, $validation_error );

	return $html;
}


/* Encode type filter */

add_filter( 'wpcf7_form_enctype', 'multiplefile_form_enctype_filter' );

function multiplefile_form_enctype_filter( $enctype ) {
	$multipart = (bool) wpcf7_scan_shortcode( array( 'type' => array( 'multiplefiles', 'multiplefiles*' ) ) );

	if ( $multipart ) {
		$enctype = 'multipart/form-data';
	}

	return $enctype;
}


function reArrayFiles(&$file_post) {

    $file_ary = array();
    $file_count = count($file_post['name']);
    $file_keys = array_keys($file_post);

    for ($i=0; $i<$file_count; $i++) {
        foreach ($file_keys as $key) {
            $file_ary[$i][$key] = $file_post[$key][$i];
        }
    }

    return $file_ary;
	
	/**
	 * Change $_FILES to this better Array
		(
			[0] => Array
				(
					[name] => foo.txt
					[type] => text/plain
					[tmp_name] => /tmp/phpYzdqkD
					[error] => 0
					[size] => 123
				)

			[1] => Array
				(
					[name] => bar.txt
					[type] => text/plain
					[tmp_name] => /tmp/phpeEwEWG
					[error] => 0
					[size] => 456
				)
		)
	 */
}

/* Validation + upload handling filter */

add_filter( 'wpcf7_validate_multiplefiles', 'multiplefiles_validation_filter', 10, 2 );
add_filter( 'wpcf7_validate_multiplefiles*', 'multiplefiles_validation_filter', 10, 2 );

function multiplefiles_validation_filter( $result, $tag ) {
	$tag = new WPCF7_Shortcode( $tag );
	
	$name = str_replace("[]", "", $tag->name);
	$id = $tag->get_id_option();

	if(isset($_FILES[$name]) && is_array($_FILES[$name])) {
		$FILES = reArrayFiles($_FILES[$name]);
	} elseif($tag->is_required()) {
		$result->invalidate( $tag, wpcf7_get_message( 'invalid_required' ) );
		return $result;
	}
		
	if($FILES)
	foreach($FILES AS $i => $file) {
	
		if ( $file['error'] && UPLOAD_ERR_NO_FILE != $file['error'] ) {
			$result->invalidate( $tag, wpcf7_get_message( 'upload_failed_php_error' ) );
			return $result;
		}

		if ( empty( $file['tmp_name'] ) && $tag->is_required() ) {
			$result->invalidate( $tag, wpcf7_get_message( 'invalid_required' ) );
			return $result;
		}

		if ( ! is_uploaded_file( $file['tmp_name'] ) )
			return $result;

		$allowed_file_types = array();

		if ( $file_types_a = $tag->get_option( 'filetypes' ) ) {
			foreach ( $file_types_a as $file_types ) {
				$file_types = explode( '|', $file_types );

				foreach ( $file_types as $file_type ) {
					$file_type = trim( $file_type, '.' );
					$file_type = str_replace( array( '.', '+', '*', '?' ),
						array( '\.', '\+', '\*', '\?' ), $file_type );
					$allowed_file_types[] = $file_type;
				}
			}
		}

		$allowed_file_types = array_unique( $allowed_file_types );
		$file_type_pattern = implode( '|', $allowed_file_types );

		$allowed_size = 10*1048576; // default size 10*1 MB

		if ( $file_size_a = $tag->get_option( 'limit' ) ) {
			$limit_pattern = '/^([1-9][0-9]*)([kKmM]?[bB])?$/';

			foreach ( $file_size_a as $file_size ) {
				if ( preg_match( $limit_pattern, $file_size, $matches ) ) {
					$allowed_size = (int) $matches[1];

					if ( ! empty( $matches[2] ) ) {
						$kbmb = strtolower( $matches[2] );

						if ( 'kb' == $kbmb )
							$allowed_size *= 1024;
						elseif ( 'mb' == $kbmb )
							$allowed_size *= 1024 * 1024;
					}

					break;
				}
			}
		}

		/* File type validation */

		// Default file-type restriction
		if ( '' == $file_type_pattern )
			$file_type_pattern = 'jpg|jpeg|png|gif|pdf|doc|docx|ppt|pptx|odt|avi|ogg|m4a|mov|mp3|mp4|mpg|wav|wmv';

		$file_type_pattern = trim( $file_type_pattern, '|' );
		$file_type_pattern = '(' . $file_type_pattern . ')';
		$file_type_pattern = '/\.' . $file_type_pattern . '$/i';

		if ( ! preg_match( $file_type_pattern, $file['name'] ) ) {
			$result->invalidate( $tag, wpcf7_get_message( 'upload_file_type_invalid' ) );
			return $result;
		}

		/* File size validation */

		if ( $file['size'] > $allowed_size ) {
			$result->invalidate( $tag, wpcf7_get_message( 'upload_file_too_large' ) );
			return $result;
		}

		wpcf7_init_uploads(); // Confirm upload dir
		$uploads_dir = wpcf7_upload_tmp_dir();
		$uploads_dir = wpcf7_maybe_add_random_dir( $uploads_dir );

		$filename = $file['name'];
		$filename = wpcf7_canonicalize( $filename );
		$filename = sanitize_file_name( $filename );
		$filename = wpcf7_antiscript_file_name( $filename );
		$filename = wp_unique_filename( $uploads_dir, $filename );

		$new_file = trailingslashit( $uploads_dir ) . $filename;

		if ( false === @move_uploaded_file( $file['tmp_name'], $new_file ) ) {
			$result->invalidate( $tag, wpcf7_get_message( 'upload_failed' ) );
			return $result;
		}

		// Make sure the uploaded file is only readable for the owner process
		@chmod( $new_file, 0400 );

		if ( $submission = WPCF7_Submission::get_instance() ) {
			$submission->add_uploaded_file( $name . "_" . $i, $new_file );
		}
		
	}
	
	return $result;
}


/* Messages */

add_filter( 'wpcf7_messages', 'multiplefiles_messages' );

function multiplefiles_messages( $messages ) {
	return array_merge( $messages, array(
		'upload_failed' => array(
			'description' => __( "Uploading a file fails for any reason", 'contact-form-7' ),
			'default' => __( 'Failed to upload file.', 'contact-form-7' )
		),

		'upload_file_type_invalid' => array(
			'description' => __( "Uploaded file is not allowed file type", 'contact-form-7' ),
			'default' => __( 'This file type is not allowed.', 'contact-form-7' )
		),

		'upload_file_too_large' => array(
			'description' => __( "Uploaded file is too large", 'contact-form-7' ),
			'default' => __( 'This file is too large.', 'contact-form-7' )
		),

		'upload_failed_php_error' => array(
			'description' => __( "Uploading a file fails for PHP error", 'contact-form-7' ),
			'default' => __( 'Failed to upload file. Error occurred.', 'contact-form-7' )
		)
	) );
}

/* Warning message */

add_action( 'wpcf7_admin_notices', 'multiplefiles_display_warning_message' );

function multiplefiles_display_warning_message() {
	if ( ! $contact_form = wpcf7_get_current_contact_form() ) {
		return;
	}

	$has_tags = (bool) $contact_form->form_scan_shortcode(
		array( 'type' => array( 'multiplefiles', 'multiplefiles*' ) ) );

	if ( ! $has_tags )
		return;

	$uploads_dir = wpcf7_upload_tmp_dir();
	wpcf7_init_uploads();

	if ( ! is_dir( $uploads_dir ) || ! wp_is_writable( $uploads_dir ) ) {
		$message = sprintf( __( 'This contact form contains file uploading fields, but the temporary folder for the files (%s) does not exist or is not writable. You can create the folder or change its permission manually.', 'contact-form-7' ), $uploads_dir );

		echo '<div class="error"><p><strong>' . esc_html( $message ) . '</strong></p></div>';
	}
}


/* File uploading functions */

add_action( 'template_redirect', 'multiple_cleanup_upload_files', 20 );
function multiple_cleanup_upload_files() {
	if ( is_admin() || 'GET' != $_SERVER['REQUEST_METHOD']
	|| is_robots() || is_feed() || is_trackback() ) {
		return;
	}

	$dir = trailingslashit( wpcf7_upload_tmp_dir() );

	if ( ! is_dir( $dir ) || ! is_readable( $dir ) || ! wp_is_writable( $dir ) ) {
		return;
	}

	if ( $handle = @opendir( $dir ) ) {
		while ( false !== ( $file = readdir( $handle ) ) ) {
			if ( $file == "." || $file == ".." || $file == ".htaccess" ) {
				continue;
			}

			$mtime = @filemtime( $dir . $file );

			if ( $mtime && time() < $mtime + 60 ) { // less than 60 secs old
				continue;
			}

			wpcf7_rmdir_p( path_join( $dir, $file ) );
		}

		closedir( $handle );
	}
}