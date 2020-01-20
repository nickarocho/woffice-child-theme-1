<?php
function woffice_child_scripts() {
	if ( ! is_admin() && ! in_array( $GLOBALS['pagenow'], array( 'wp-login.php', 'wp-register.php' ) ) ) {
		$theme_info = wp_get_theme();
		wp_enqueue_style( 'woffice-child-stylesheet', get_stylesheet_uri(), array(), WOFFICE_THEME_VERSION );
	}
}
add_action('wp_enqueue_scripts', 'woffice_child_scripts', 30);

add_action('after_setup_theme', function () {

	// Load custom translation file for the parent theme
	load_theme_textdomain( 'woffice', get_stylesheet_directory() . '/languages/' );

	// Load translation file for the child theme
	load_child_theme_textdomain( 'woffice', get_stylesheet_directory() . '/languages' );
});



// -------------
// Arcadia Stuff
// -------------

require_once get_template_directory() .'/inc/init.php';
define('WOFFICE_THEME_VERSION', '2.8.7');

// -------
// helpers
// -------

function aup_is_project($post) {
	return get_post_type($post) == 'project';
}

function aup_get_project_members_from_comment($comment_id) {
	//returns an array of project member IDs based on a comment posted in that project

	$comment = get_comment($comment_id);
	$post_id = $comment->comment_post_ID;

	$project_details = get_post_meta($post_id, 'fw_options', true);
	$project_member_ids = isset($project_details['project_members']) ? $project_details['project_members'] : '';

	return $project_member_ids;
}

function aup_is_commenter($potential_author, $comment_id) {
	$comment = get_comment($comment_id);
	$comment_author = $comment->comment_author;

	return $potential_author == $comment_author;
}

// -----
// hooks
// -----

function aup_notify_all_followers()
{
	//priority before other function
	//check post for comment
	//if post is a project, then get all the 'followers'
	//send email to all followers

	
}

function aup_comment_moderation_recipients($emails, $comment_id) {
	// Email code inspired by: http://www.sourcexpress.com/customize-wordpress-comment-notification-emails/
	// Woffice functions: see helpers.php in woffice-core > extensions > woffice-projects
	/*$comment = get_comment($comment_id);
	$post_id = $comment->comment_post_ID;
	$project_member_ids = woffice_get_project_members($post_id); //returns array with user IDs
	$project_members = ( !empty($post_id) ) ? woffice_get_project_members($post_id) : array();

	echo "post id: ".$post_id;
	echo "<br />function exists: ".function_exists('woffice_get_project_members');
	echo "<br />project member ids: ".count($project_members);*/

	$project_member_ids = aup_get_project_members_from_comment($comment_id);
	
	foreach ($project_member_ids as $project_member_id) {
		$user = get_user_by('id', $project_member_id);
		$email = $user->user_email;
		$name = $user->display_name;

		if (!empty($email) && !in_array($email, $emails) && !aup_is_commenter($name, $comment_id)) {
			$emails[] = $email;
		}
	}    

    return $emails;
}
add_filter('comment_moderation_recipients', 'aup_comment_moderation_recipients', 11, 2);
add_filter('comment_notification_recipients', 'aup_comment_moderation_recipients', 11, 2);


function aup_comment_notification_text($notify_message, $comment_id) {
	//inspired by https://www.webhostinghero.com/how-to-change-the-comment-notification-email-in-wordpress/

	$comment = get_comment($comment_id);
	$post_id = $comment->comment_post_ID;

	if (aup_is_project($post_id)) {
		$post = get_post($post_id);

		$aup_message = $comment->comment_author." posted a new comment in \"".$post->post_title."\":\r\n\r\n";
		$aup_message .= $comment->comment_content."\r\n\r\n";
		$aup_message .= get_permalink($post_id)."#project-content-comments";

		return $aup_message;
	} else {
		return $notify_message;
	}
}
add_filter('comment_notification_text', 'aup_comment_notification_text', 10,2);


function aup_add_bp_notification($comment_id, $comment_approved) {
	$comment = get_comment($comment_id);
	$project_post_id = $comment->comment_post_ID;

	if (aup_is_project($project_post_id)) {
		$user_ids_to_notify = aup_get_project_members_from_comment($comment_id);

		$comment = get_comment($comment_id);
		$comment_author = get_user_by('email', $comment->comment_author_email);
		$comment_author_id = $comment_author->ID;

		foreach ($user_ids_to_notify as $user_id_to_notify) {
			$user_to_notify = get_user_by('id', $user_id_to_notify);
			$name_to_notify = $user->display_name;

			if (!aup_is_commenter($name_to_notify, $comment_id)) {
				$notification_args = array (
					'user_id'			=> $user_id_to_notify,
					'item_id'			=> $project_post_id,
					'secondary_item_id' => $comment_author_id,
					'component_name'    => 'woffice_project',
					'component_action'  => 'woffice_project_comment',
				);

				$notification_id = bp_notifications_add_notification($notification_args);
			}
		}
	} else {
		return false;
	}
}
add_action('comment_post', 'aup_add_bp_notification', 10, 2);
//add_action('transition_comment_status', 'aup_add_bp_notification', 10, 3);
//add_action( 'edit_comment', 'aup_add_bp_otification', 10    );