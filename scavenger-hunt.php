<?php
/**
 * Plugin Name: BFC Scavenger Hunt
 * Description: A scavenger hunt game framework for WordPress.
 * Version: 1.2
 * Author: StratLab Marketing
 */

// Ensure direct script access is prevented
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

function scavenger_clue_shortcode($atts = [], $content = null) {
    // Ensure the user is logged in
    if(!is_user_logged_in()) {
        return '';
    }
    // Ensure "number" attribute is set
    if(!isset($atts['number']) || !isset($atts['label'])) {
        return 'Scavenger Clue Shortcode Error: "number" or "label" attribute not set.';
    }
    
    $clue_number = intval($atts['number']);
    $label = sanitize_text_field($atts['label']);
    
    // Check if user can view this clue
    $user_id = get_current_user_id();
    $current_clue_number = intval(get_user_meta($user_id, '_scavenger_hunt_clue_number', true));
    
    // Ensure this clue is the next one for the user
    if($clue_number === $current_clue_number + 1) {
        $output  = '<div id="clue'.$clue_number.'" class="scavenger-clue" data-clue-number="'.$clue_number.'" data-content="'.esc_attr($content).'">';
        $output .= esc_html($label);
        $output .= '</div>';
        return $output;
    }
    return '';
}

add_shortcode('scavenger_clue', 'scavenger_clue_shortcode');

/* Usage example:
[scavenger_clue number="1" label="Click me"]
Your first clue goes here.
[/scavenger_clue]

[scavenger_clue number="2" label="Click me"]
Your second clue goes here.
[/scavenger_clue]
*/

function scavenger_hunt_scripts() {
    // Enqueue the script and ensure jQuery is a dependency since we'll use it in our JS file.
    wp_enqueue_script('scavenger-hunt-script', plugin_dir_url(__FILE__) . 'scavenger-hunt.js', array('jquery'), '1.0', true);
    
    // Localize the script to pass data into our JS file.
    wp_localize_script('scavenger-hunt-script', 'scavenger_hunt_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
}
add_action('wp_enqueue_scripts', 'scavenger_hunt_scripts');

function clue_clicked_callback() {
    // Ensure a clue number has been passed.
    if(isset($_POST['clue_number']) && is_user_logged_in()) {
        $clue_number = intval($_POST['clue_number']); // Ensure the passed number is an integer.
        $user_id = get_current_user_id(); // Get the current user's ID.
        
        // Ensure the passed clue number is the next expected clue for this user.
        $current_clue_number = intval(get_user_meta($user_id, '_scavenger_hunt_clue_number', true));
        if($clue_number === $current_clue_number + 1) {
            update_user_meta($user_id, '_scavenger_hunt_clue_number', $clue_number);
            echo 'success';
        } else {
            echo 'invalid_clue_number';
        }
    } else {
        echo 'failure';
    }
    wp_die(); // Ensure to terminate AJAX properly.
}
add_action('wp_ajax_clue_clicked', 'clue_clicked_callback');

function scavenger_hunt_admin_menu() {
    add_menu_page('Scavenger Hunt', 'Scavenger Hunt', 'manage_options', 'scavenger_hunt', 'scavenger_hunt_admin_page');
}
add_action('admin_menu', 'scavenger_hunt_admin_menu');

function scavenger_hunt_admin_page() {
	// Handle saving the final clue number
    if(isset($_POST['final_clue'])) {
        update_option('final_clue_number', $_POST['final_clue']);
    }
    ?>
    <div class="wrap">
        <h2>BFC Scavenger Hunt</h2>
        
        <!-- Final Clue Form -->
        <form method="post" action="">
            <label for="final_clue">Final Clue Number: </label>
            <input type="number" id="final_clue" name="final_clue" value="<?php echo get_option('final_clue_number'); ?>">
            <input type="submit" class="button button-primary" value="Save">
        </form>
        <hr>
		
		<!-- Export CSV Form -->
        <form method="post" action="">
			<?php wp_nonce_field('export_scavenger_hunt', 'scavenger_hunt_export_nonce'); ?>
			<p>
				<input type="submit" name="export_csv" class="button button-secondary" value="Export User Progress to CSV">
			</p>
		</form>
		<hr>
		
        <!-- Reset Scavenger Hunt Form -->
        <form method="post" action="">
            <?php wp_nonce_field('reset_scavenger_hunt', 'scavenger_hunt_nonce'); ?>
            <p>
                <input type="submit" name="reset_scavenger_hunt" class="button button-primary" value="Reset Scavenger Hunt" onclick="return confirm('Are you sure? This will reset the scavenger hunt for all users!');">
            </p>
        </form>
    </div>
    <?php

    // Check if the reset button was clicked and validate nonce
    if (isset($_POST['reset_scavenger_hunt']) && wp_verify_nonce($_POST['scavenger_hunt_nonce'], 'reset_scavenger_hunt')) {
        // Check user permissions
        if (current_user_can('manage_options')) {
            global $wpdb;
            $wpdb->delete($wpdb->usermeta, ['meta_key' => '_scavenger_hunt_clue_number']);
            echo '<div class="updated"><p>All user progress has been reset.</p></div>';
        } else {
            echo '<div class="error"><p>You do not have sufficient permissions to reset the scavenger hunt.</p></div>';
        }
    }
}


function scavenger_hunt_export_csv() {
    if(isset($_POST['export_csv']) && wp_verify_nonce($_POST['scavenger_hunt_export_nonce'], 'export_scavenger_hunt')) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=scavenger-hunt-data.csv');

        $output = fopen('php://output', 'w');
        fputcsv($output, array('Username', 'Email', 'Current Clue Number'));

        $users = get_users(array(
            'meta_key' => '_scavenger_hunt_clue_number',
            'fields' => array('ID', 'user_login', 'user_email')
        ));

        foreach ($users as $user) {
            $clue_number = get_user_meta($user->ID, '_scavenger_hunt_clue_number', true);
            fputcsv($output, array($user->user_login, $user->user_email, $clue_number));
        }

        fclose($output);
        exit();
    }
}
add_action('admin_init', 'scavenger_hunt_export_csv');

function has_user_completed_scavenger_hunt($user_id, $final_clue_number) {
    $last_clue_clicked = get_user_meta($user_id, '_scavenger_hunt_clue_number', true);
    return ($last_clue_clicked == $final_clue_number);
}

function get_username_from_url() {
    $url = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    $parsed_url = parse_url($url);
    $path = $parsed_url['path'];
    $url_segments = explode('/', rtrim($path, '/'));
    return end($url_segments);
}


function display_scavenger_hunt_badge() {
    $final_clue_number = get_option('final_clue_number', 0);
    $username = get_username_from_url(); // Or use an Ultimate Member hook if available
    $user = get_user_by('login', $username);

    if ($user && has_user_completed_scavenger_hunt($user->ID, $final_clue_number)) {
        echo '<div class="scavenger-hunt-badge">Scavenger Hunt Completed</div>';
    }
}

function shortcode_scavenger_hunt_badge() {
    ob_start();  // Buffer output

    $final_clue_number = get_option('final_clue_number', 0);
    $username = get_username_from_url();
    $user = get_user_by('login', $username);
    if ($user) {
        if (has_user_completed_scavenger_hunt($user->ID, $final_clue_number)) {
            echo '<div class="scavenger-hunt-badge badge"><span>Scavenger Hunt Completed!</span></div>';
        } else {
            echo '<div class="scavenger-hunt-badge no-badge"><span>No Badge Earned Yet</span></div>';
        }
    } else {
        echo '<div class="scavenger-hunt-badge no-badge"><span>User Not Found</span></div>';
    }

    return ob_get_clean();  // Return buffered output
}

add_shortcode('scavenger_hunt_badge', 'shortcode_scavenger_hunt_badge');

?>
