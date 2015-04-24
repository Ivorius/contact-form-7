<?php
/**
** A base module for [quiz]
**/

/* Shortcode handler */

add_action( 'wpcf7_init', 'wpcf7_add_shortcode_quiz' );

function wpcf7_add_shortcode_quiz() {
	wpcf7_add_shortcode( 'quiz', 'wpcf7_quiz_shortcode_handler', true );
}

function wpcf7_quiz_shortcode_handler( $tag ) {
	$tag = new WPCF7_Shortcode( $tag );

	if ( empty( $tag->name ) )
		return '';

	$validation_error = wpcf7_get_validation_error( $tag->name );

	$class = wpcf7_form_controls_class( $tag->type );

	if ( $validation_error )
		$class .= ' wpcf7-not-valid';

	$atts = array();

	$atts['size'] = $tag->get_size_option( '40' );
	$atts['maxlength'] = $tag->get_maxlength_option();
	$atts['minlength'] = $tag->get_minlength_option();

	if ( $atts['maxlength'] && $atts['minlength'] && $atts['maxlength'] < $atts['minlength'] ) {
		unset( $atts['maxlength'], $atts['minlength'] );
	}

	$atts['class'] = $tag->get_class_option( $class );
	$atts['id'] = $tag->get_id_option();
	$atts['tabindex'] = $tag->get_option( 'tabindex', 'int', true );
	$atts['aria-required'] = 'true';
	$atts['aria-invalid'] = $validation_error ? 'true' : 'false';

	$pipes = $tag->pipes;

	if ( $pipes instanceof WPCF7_Pipes && ! $pipes->zero() ) {
		$pipe = $pipes->random_pipe();
		$question = $pipe->before;
		$answer = $pipe->after;
	} else {
		// default quiz
		$question = '1+1=?';
		$answer = '2';
	}

	$answer = wpcf7_canonicalize( $answer );

	$atts['type'] = 'text';
	$atts['name'] = $tag->name;

	$atts = wpcf7_format_atts( $atts );

	$html = sprintf(
		'<span class="wpcf7-form-control-wrap %1$s"><label><span class="wpcf7-quiz-label">%2$s</span> <input %3$s /></label><input type="hidden" name="_wpcf7_quiz_answer_%4$s" value="%5$s" />%6$s</span>',
		sanitize_html_class( $tag->name ),
		esc_html( $question ), $atts, $tag->name,
		wp_hash( $answer, 'wpcf7_quiz' ), $validation_error );

	return $html;
}


/* Validation filter */

add_filter( 'wpcf7_validate_quiz', 'wpcf7_quiz_validation_filter', 10, 2 );

function wpcf7_quiz_validation_filter( $result, $tag ) {
	$tag = new WPCF7_Shortcode( $tag );

	$name = $tag->name;

	$answer = isset( $_POST[$name] ) ? wpcf7_canonicalize( $_POST[$name] ) : '';
	$answer = wp_unslash( $answer );

	$answer_hash = wp_hash( $answer, 'wpcf7_quiz' );

	$expected_hash = isset( $_POST['_wpcf7_quiz_answer_' . $name] )
		? (string) $_POST['_wpcf7_quiz_answer_' . $name]
		: '';

	if ( $answer_hash != $expected_hash ) {
		$result->invalidate( $tag, wpcf7_get_message( 'quiz_answer_not_correct' ) );
	}

	return $result;
}


/* Ajax echo filter */

add_filter( 'wpcf7_ajax_onload', 'wpcf7_quiz_ajax_refill' );
add_filter( 'wpcf7_ajax_json_echo', 'wpcf7_quiz_ajax_refill' );

function wpcf7_quiz_ajax_refill( $items ) {
	if ( ! is_array( $items ) )
		return $items;

	$fes = wpcf7_scan_shortcode( array( 'type' => 'quiz' ) );

	if ( empty( $fes ) )
		return $items;

	$refill = array();

	foreach ( $fes as $fe ) {
		$name = $fe['name'];
		$pipes = $fe['pipes'];

		if ( empty( $name ) )
			continue;

		if ( $pipes instanceof WPCF7_Pipes && ! $pipes->zero() ) {
			$pipe = $pipes->random_pipe();
			$question = $pipe->before;
			$answer = $pipe->after;
		} else {
			// default quiz
			$question = '1+1=?';
			$answer = '2';
		}

		$answer = wpcf7_canonicalize( $answer );

		$refill[$name] = array( $question, wp_hash( $answer, 'wpcf7_quiz' ) );
	}

	if ( ! empty( $refill ) )
		$items['quiz'] = $refill;

	return $items;
}


/* Messages */

add_filter( 'wpcf7_messages', 'wpcf7_quiz_messages' );

function wpcf7_quiz_messages( $messages ) {
	return array_merge( $messages, array( 'quiz_answer_not_correct' => array(
		'description' => __( "Sender doesn't enter the correct answer to the quiz", 'contact-form-7' ),
		'default' => __( 'Your answer is not correct.', 'contact-form-7' )
	) ) );
}


/* Tag generator */

add_action( 'admin_init', 'wpcf7_add_tag_generator_quiz', 40 );

function wpcf7_add_tag_generator_quiz() {
	$tag_generator = WPCF7_TagGenerator::get_instance();
	$tag_generator->add( 'quiz', __( 'quiz', 'contact-form-7' ),
		'wpcf7_tag_generator_quiz' );
}

function wpcf7_tag_generator_quiz( $contact_form, $args = '' ) {
	$args = wp_parse_args( $args, array() );
	$description = __( "Generate a form-tag for a question-answer pair.", 'contact-form-7' );

?>
<div class="control-box">
<fieldset>
<legend><?php echo esc_html( $description ); ?><br /><span class="dashicons dashicons-external"></span> <?php echo sprintf( '<a href="%1$s" target="_blank">%2$s</a>', esc_url( __( 'http://contactform7.com/quiz/', 'contact-form-7' ) ), esc_html( __( 'Quiz', 'contact-form-7' ) ) ); ?></legend>
<table>
<tr><td><?php echo esc_html( __( 'Name', 'contact-form-7' ) ); ?><br /><input type="text" name="name" class="tg-name oneline" /></td><td></td></tr>

<tr>
<td><code>id</code> (<?php echo esc_html( __( 'optional', 'contact-form-7' ) ); ?>)<br />
<input type="text" name="id" class="idvalue oneline option" /></td>

<td><code>class</code> (<?php echo esc_html( __( 'optional', 'contact-form-7' ) ); ?>)<br />
<input type="text" name="class" class="classvalue oneline option" /></td>
</tr>

<tr>
<td><code>size</code> (<?php echo esc_html( __( 'optional', 'contact-form-7' ) ); ?>)<br />
<input type="number" name="size" class="numeric oneline option" min="1" /></td>

<td><code>maxlength</code> (<?php echo esc_html( __( 'optional', 'contact-form-7' ) ); ?>)<br />
<input type="number" name="maxlength" class="numeric oneline option" min="1" /></td>
</tr>

<tr>
<td><?php echo esc_html( __( 'Quizzes', 'contact-form-7' ) ); ?><br />
<textarea name="values"></textarea><br />
<span style="font-size: smaller"><?php echo esc_html( __( "* quiz|answer (e.g. 1+1=?|2)", 'contact-form-7' ) ); ?></span>
</td>
</tr>
</table>
</fieldset>
</div>

<div class="insert-box">
<div class="tg-tag"><?php echo esc_html( __( "Copy this code and paste it into the form left.", 'contact-form-7' ) ); ?><br /><input type="text" name="quiz" class="tag wp-ui-text-highlight code" readonly="readonly" onfocus="this.select()" /></div>
</div>
<?php
}
