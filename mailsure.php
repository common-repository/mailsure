<?php
/*
Plugin Name: Mailsure
Plugin URI: https://mailsure.app
Description: Test email sending, SPF, DKIM & DMARC
Author: CoryTrevor
Version: 1.0
License: GPLv2 or later
Text Domain: mailsure
*/

if (! defined( 'ABSPATH' )) exit; // Exit if accessed directly

register_activation_hook( __FILE__, 'mailsure_plugin_activate' );

// Check if user shoud be redirected on activation
function mailsure_can_redirect_on_activation() {
	
	// If plugin is activated in network admin options, skip redirect.
	if ( is_network_admin() ) {
		return false;
	}

	// Skip redirect if WP_DEBUG is enabled
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		return false;
	}

	// Determine if multi-activation is enabled
	$maybe_multi = filter_input( INPUT_GET, 'activate-multi', FILTER_VALIDATE_BOOLEAN );
	if ( $maybe_multi ) {
		return false;
	}

	return true;
}


// Add an option on activation to read in later when redirecting
function mailsure_plugin_activate() {
	
	if ( mailsure_can_redirect_on_activation() ) {
		add_option( 'mailsure_do_activation_redirect', sanitize_text_field( __FILE__ ) );
	}
}

add_action( 'admin_init', 'mailsure_activate_redirect' );

// Redirect to Mailsure page
function mailsure_activate_redirect() {
	
	if ( mailsure_can_redirect_on_activation() && is_admin() ) {
		// Read in option value.
		if ( __FILE__ === get_option( 'mailsure_do_activation_redirect' ) ) {

			// Delete option value so no more redirects.
			delete_option( 'mailsure_do_activation_redirect' );

			// Get redirect URL.
			$redirect_url = admin_url( 'tools.php?page=mailsure' );
			wp_safe_redirect(
				esc_url( $redirect_url )
			);
			exit;
		}
	}
}

// Add "Settings" link on the plugins page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'mailsure_settings_link');

function mailsure_settings_link($links) {
	$settings_link = '<a href="tools.php?page=mailsure">Settings</a>';
	// Add the "Settings" link at the beginning of the array
	array_unshift($links, $settings_link);
	return $links;
}

// Hook the admin menu creation function to the "Tools" menu
add_action('admin_menu', 'mailsure_add_to_tools_menu');

// Function to add submenu item to "Tools" menu
function mailsure_add_to_tools_menu() {
	
	add_submenu_page(
		'tools.php',
		'Mailsure',
		'Mailsure',
		'manage_options',
		'mailsure',
		'mailsure_page'
	);
}

// Enqueue the separate CSS file
function mailsure_enqueue_admin_styles_and_scripts() {
	
	// Get the current screen object
	$current_screen = get_current_screen();

	// Check if the current admin page is the Mailsure page
	if ($current_screen->id === 'tools_page_mailsure') {
		// Get plugin data
		$plugin_data = get_plugin_data( __FILE__ );
		// Get plugin version
		$plugin_version = $plugin_data['Version'];
		// Enqueue style with version as query string
		wp_enqueue_style( 'mailsure-admin-style', plugin_dir_url( __FILE__ ) . 'assets/admin-style.css', array(), $plugin_version );
		
		// Check if test button is submitted
		if (isset($_POST['test_auth']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['test_auth_nonce_field'])), 'test_auth_nonce_action')) {		
			add_action('admin_print_scripts', 'mailsure_check_result_script');
		}
	}	
}

add_action('admin_enqueue_scripts', 'mailsure_enqueue_admin_styles_and_scripts');

// Function to render the content of the plugin's main submenu page
function mailsure_page() {
	
	// Sanitize the input
	$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'test';

	// Validate the input
	$valid_tabs = array('test', 'tools');
	if (!in_array($active_tab, $valid_tabs)) {
	    $active_tab = 'test';
	}

	echo '<h1>Mailsure</h1>';
	echo '<div class="wrap">';
	echo '<h2 class="nav-tab-wrapper">';

	// Escape output
	echo '<a href="' . esc_url(add_query_arg('tab', 'test', admin_url('admin.php?page=mailsure'))) . '" class="nav-tab ' . ($active_tab === 'test' ? 'nav-tab-active' : '') . '">Authentication Test</a>';
	echo '<a href="' . esc_url(add_query_arg('tab', 'tools', admin_url('admin.php?page=mailsure'))) . '" class="nav-tab ' . ($active_tab === 'tools' ? 'nav-tab-active' : '') . '">Tools</a>';

	echo '</h2>';
	echo '<div id="tab_container">';
	
	// Content based on the active tab
	switch ($active_tab) {
		case 'tools':
			mailsure_tools_tab();
			break;

		default:
			mailsure_test_email_tab();
			break;
	}
	
	echo '</div>';
	echo '</div>';
}

// Define global variable for mail error message
$mailsure_mail_error_message;
$mailsure_mail_error_message = '';

// Get mail error message
function mailsure_get_wp_mail_error_as_global($wp_error) {   
	
	global $mailsure_mail_error_message;
	$mailsure_mail_error_message = $wp_error->get_error_message();
}

function mailsure_tools_tab() {
	
	echo '<br><h3>Send a test email</h3>';
	
	if(isset($_POST['email_to']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['test_email_nonce_field'])),'test_email_nonce_action')) {
		$to = sanitize_email($_POST['email_to']);
		// Validate email
		if (!is_email($to)) {
			echo 'Invalid email!';
			return;
		}
		$subject = "Test email";
		$content ="Hi, this is a test mail sent from the Mailsure plugin on ".get_bloginfo('name').".";
		$headers = array('Content-Type: text/html; charset=UTF-8'); 
		// Hook in to capture any error message
	    add_action('wp_mail_failed', 'mailsure_get_wp_mail_error_as_global');
		$test_mail_result = wp_mail( $to, $subject, $content );
		remove_action('wp_mail_failed', 'mailsure_get_wp_mail_error_as_global'); 
	    	if ($test_mail_result) {
	        	echo '<p>Email sent.</p>';
	    	} else {
	        	// Echo wp mail error
	        	global $mailsure_mail_error_message;
	        	if ($mailsure_mail_error_message) {
					echo '<p>Error sending email: ' . esc_html( $mailsure_mail_error_message ) . '</p>';
	        	}     
	     		// Get full PHP error 
	        	$last_error = error_get_last();
	        	if ($last_error !== null) {
					echo esc_html( $last_error['message'] ) . '<br><br>';
	        	} 
	   	}		
	}
	
	echo '<form id="mailsure-send-test-email" method="post">'; 
	echo '<label for="email"></label>';
	echo '<input type="email" name="email_to" placeholder="Email address" style="min-width: 250px;" required>';
 	wp_nonce_field('test_email_nonce_action', 'test_email_nonce_field');
	submit_button('Send Email', 'primary', 'send_email');
	echo '</form>';
	
	echo '<br><h3>Blacklist Check</h3>';
	// Retrieve the serialized data from the database
	$result = get_option('mailsure_latest_test_result');
	
	if (is_array($result) && isset($result['clientIp'])) {	
		$mxtoolboxURL = 'https://mxtoolbox.com/SuperTool.aspx?action=blacklist:' . esc_html($result['clientIp']);
		echo '<p>Run an MXToolBox.com Blacklist Check on your mail server\'s IP: ' . esc_html($result['clientIp']) . '</p>';
		?>
		<a href="<?php echo esc_url( $mxtoolboxURL ); ?>" target="_blank">
		  <button type="button" class="button button-primary">Run Blacklist Check</button>
		</a>
		<?php
  	  	
	} else {
		echo 'Run an authentication test first from <a href="tools.php?page=mailsure&tab=test">here</a> to get the email server\'s IP.';
	}
}

function mailsure_check_result_script() {
    ?>
    <script type='text/javascript'>
		// Function to hide elements with class 'display-results' using CSS
	    function hideDisplayResults() {
	        const style = document.createElement('style');
	        style.type = 'text/css';
	        style.innerHTML = '.display-results { display: none; }';
	        document.head.appendChild(style);
	    }
		
        let errorCount = 0; // Initialize error count
        function longPoll(attempts = 0) {
            if (attempts >= 2) {
                console.log('Maximum retry attempts reached. Stopping long polling.');
                return;
            }
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 30000); // Timeout after 30 seconds if no result received
            fetch(ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'mailsure_refresh_when_result_received',
                    }),
                    signal: controller.signal,
                })
                .then(response => {
                    clearTimeout(timeoutId); // Clear the timeout if the request succeeds
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.message === 'New API data available') {
                        console.log("New API Data:", data.message);
                        location.reload(true); // Refresh the page when new data is received
                        return;
                    } else if (data.message === 'No new API data') {
                        console.log("No new API data. Continuing long polling.");
                        // Continue long-polling with incremented attempts
                        longPoll(attempts + 1);
                        return;
                    } else {
                        console.error('Unexpected response:', data);
                    }
                })
                .catch(error => {
                    if (errorCount === 0) { // If it's the first error
                        console.error('Error during long polling:', error);
                        console.log('Retrying long polling');
                        errorCount++; // Increment error count
                        // Restart long-polling
                        longPoll();
                    } else {
                        if (error.name === 'AbortError') {
                            console.log('Long poll aborted after both attempts timed out.');
                            // You can handle the timeout here if needed
                        } else {
                            console.error('Error during long polling:', error);
                            console.log('Maximum retry attempts reached.');
                            // Additional error handling code here if needed
                        }
                    }
                });
        }
	    hideDisplayResults();
        setTimeout(longPoll, 2000);
    </script>
    <?php	
}

// Function to render the content of the test email tab
function mailsure_test_email_tab() {
	
	echo '<br><h3>Test email sending, SPF, DKIM & DMARC</h3>';
	echo '<div class="wrap" id="test-results">';
	if (!isset($_POST['test_auth'])) {	
		// Display the form 
		echo '<form method="post">';
		wp_nonce_field('test_auth_nonce_action', 'test_auth_nonce_field');
		submit_button('Run Authentication Test', 'primary', 'test_auth');
		echo '</form>';
	}

	// Check if button is submitted
	if (isset($_POST['test_auth']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['test_auth_nonce_field'])), 'test_auth_nonce_action')) {
		// Test email authentication
		mailsure_send_on_demand_test();
	}

	mailsure_display_latest_test_result();
}

function mailsure_display_latest_test_result() {
	
	// Retrieve the serialized data from the database
	$result = get_option('mailsure_latest_test_result');
	
	if (is_array($result)) {
		
		// Get the WordPress site's timezone setting
		$result['date'] = mailsure_convert_to_wp_time($result['date']);
		
		// Replace curly braces around email address
		$result['from'] = str_replace(['{', '}'], ['<', '>'], $result['from']);

		echo '<div class="display-results">';
		echo '<p>Results for email sent on ' . esc_html($result['date']) . '</p>';
		echo '<p><b>From</b>: ' . esc_html($result['from']) . '</p>';
		echo '<p><b>Return path</b>: ' . esc_html($result['mailFrom']) . '<p>';
		echo '<p><b>Delivered by</b>: ' . esc_html($result['clientIp']) . ' (' . esc_html($result['helo']) . ')</p>';

		echo '<div class="display-results">';

		// Start the table
		echo '<table>';

		// Display email received
		echo '<tr>';
		echo '<td class="icon" style="color: green;">✓</td>';
		echo '<td class="auth">Sending</td>';
		echo '<td style="color: green;"><span class="resultant">Pass</span></td>';
		echo '<td class="info">email received</td>';
		echo '</tr>';

		// Display SPF status and information
		echo '<tr>';
		echo '<td class="icon" style="color: ' . (esc_html($result['spf']) == 'pass' ? 'green' : 'red') . ';">' . ($result['spf'] == 'pass' ? '✓' : '✕') . '</td>';
		echo '<td class="auth">SPF</td>';
		echo '<td style="color: ' . (esc_html($result['spf']) == 'pass' ? 'green' : 'red') . ';"><span class="resultant">' . esc_html(ucfirst($result['spf'])) . '</span></td>';
		echo '<td class="info">' . esc_html($result['spfInfo']) . '</td>';
		echo '</tr>';

		// Display DKIM status and information 
		echo '<tr>';
		if ($result['dkim'] == 'none') {
		    echo '<td class="icon">ⓘ</td>'; 
		    echo '<td class="auth">DKIM</td>';
		    echo '<td style="color: inherit;"><span class="resultant">None</span></td>';
		    echo '<td class="info">' . esc_html( $result['dkimInfo'] ) . '</td>';
		} else {
		    echo '<td class="icon" style="color: ' . ( esc_html( $result['dkim'] ) == 'pass' ? 'green' : 'red' ) . ';">' . ( esc_html( $result['dkim'] ) == 'pass' ? '✓' : '✕' ) . '</td>';
		    echo '<td class="auth">DKIM</td>';
		    echo '<td style="color: ' . ( esc_html( $result['dkim'] ) == 'pass' ? 'green' : 'red' ) . ';"><span class="resultant">' . esc_html( ucfirst( $result['dkim'] ) ) . '</span></td>';
		    echo '<td class="info">' . esc_html( $result['dkimInfo'] ) . '</td>';      
		}
		echo '</tr>';

		// Display DMARC status and information
		echo '<tr>';
		echo '<td class="icon" style="color: ' . ( esc_html( $result['dmarc'] ) == 'pass' ? 'green' : ( esc_html( $result['dmarc'] ) == 'none' ? 'inherit' : 'red' ) ) . ';">' . ( esc_html( $result['dmarc'] ) == 'pass' ? '✓' : ( esc_html( $result['dmarc'] ) == 'none' ? 'ⓘ' : '✕' ) ) . '</td>';
		echo '<td class="auth">DMARC</td>';
		echo '<td style="color: ' . ( esc_html( $result['dmarc'] ) == 'pass' ? 'green' : ( esc_html( $result['dmarc'] ) == 'none' ? 'inherit' : 'red' ) ) . ';"><span class="resultant">' . esc_html( ucfirst( $result['dmarc'] ) ) . '</span></td>';
		echo '<td class="info">' . esc_html( $result['dmarcInfo'] ) . '</td>';
		echo '</tr>';
		echo '</tr>';

		echo '</table>';

		echo '</div>'; // end display-results div
		echo '</div>'; // end test-results div
	}
}

add_action('rest_api_init', 'mailsure_register_on_demand_test_notification_endpoint');

function mailsure_register_on_demand_test_notification_endpoint() {
	
	register_rest_route('mailsure/v2', '/on-demand-result/', array(
		'methods' => 'POST',
		'callback' => 'mailsure_receive_on_demand_result',
		'permission_callback' => 'mailsure_check_test_id_auth',
	));
}


function mailsure_check_test_id_auth($request) {
	
	$json_data = $request->get_body(); // Get the JSON data from the request body
    	$result = json_decode($json_data, true);
	if (empty($result) || empty($result['testId']) || empty($result['origin']) ) {	
		return false;
	}
	
	$testId = get_option('mailsure_initiated_on_demand_test');
	// Check test id 
	if ($testId == $result['testId']) {		
		return true;
	} else {
		return false;
	}
}

// Receive result data 
function mailsure_receive_on_demand_result($request) {
	
	$json_data = $request->get_body(); // Get the JSON data from the request body
	$result = json_decode($json_data, true);
		
	$result['received'] = 'Yes';
	
	// Sanitize each element of the array
	$sanitized_result = array_map('sanitize_text_field', $result);

	update_option('mailsure_latest_test_result', $sanitized_result, false);

	update_option('mailsure_new_data_flag', true, false); // Set the flag on receiving data
	
	update_option('mailsure_initiated_on_demand_test', '', false);
   
	// Return a success code 
	return new WP_REST_Response(['success' => true], 200);    
}


add_action('wp_ajax_mailsure_refresh_when_result_received', 'mailsure_refresh_when_result_received');

function mailsure_refresh_when_result_received() {
	
	$timeout = 28; 
	$startTime = time();

	while (time() - $startTime < $timeout) { 
		wp_cache_delete('mailsure_new_data_flag', 'options'); 
		$newDataFlag = get_option('mailsure_new_data_flag');

		if ($newDataFlag) {
			update_option('mailsure_new_data_flag', false, false);
			echo wp_json_encode(['message' => 'New API data available']);
			exit; // Make sure to exit after sending the response
		}
	
		sleep(2); // Check every 2 seconds
	} 

	// If no new data found within the timeout, return an empty response
	echo wp_json_encode(['message' => 'No new API data']);
	exit;
}

// Decrypt the email so raw email not visible to code repo scanning bots
function mailsure_decode_address($address) {
	
	$val = base64_decode($address);
	return $val;
}

function mailsure_send_on_demand_test() {
	
	// Reset flag just in case 
	update_option('mailsure_new_data_flag', false, false); 
	
	$destination = 'cGx1Z2lub25kZW1hbmRAbWFpbHN1cmUuYXBw';
	
	$site_url = site_url();
	$site_domain = wp_parse_url($site_url, PHP_URL_HOST);
	$timestamp = gmdate("YmdHis");
	$testId = $site_domain . $timestamp;
	
	update_option('mailsure_initiated_on_demand_test', $testId, false);
		
	$headers = array(
		'Content-Type: text/plain; charset=UTF-8',
		'Mailsure-Test-ID: ' . $testId,
		'Mailsure-Origin: ' . $site_url,
	);

	$message =  "Hi, this is a test email from the Mailsure plugin. \r\n" .
				"Its purpose is to verify email sending and authenticaton. \r\n" .
				"|---Test-ID---|: {$testId} \r\n" .
				"|---Origin---|: {$site_url} \r\n";
		
	// Hook in to capture any error message
	add_action('wp_mail_failed', 'mailsure_get_wp_mail_error_as_global', 10, 1);
	// Try to send the email
	$mail_result = wp_mail(mailsure_decode_address($destination), 'Authentication check from Mailsure', $message, $headers); 
	// Remove the hook after the email attempt
	remove_action('wp_mail_failed', 'mailsure_get_wp_mail_error_as_global', 10); 

	// Check if the email was sent successfully
	if ($mail_result) {
		echo '<p>Email sent!<p><p>This page will automatically refresh when results are received.<p>';
		echo '<div id="loading-spinner"></div>';
		echo '<p>If no results appear after a minute, try refreshing the page and check that the REST API is available in <a href="/wp-admin/site-health.php" target="_blank">Tools > Site Health</a>.</p>';
		echo '<p>You can send yourself a test email from the <a href="tools.php?page=mailsure&tab=tools" target="_blank">Tools</a> tab to test that WordPress is able to send email.</p>';
	} else {
		// Echo wp mail error
		global $mailsure_mail_error_message;
		if ($mailsure_mail_error_message) {
			echo "Error sending email: " . esc_html( $mailsure_mail_error_message ) . "<br><br>";
		}     
		// Get full PHP error 
		$last_error = error_get_last();
		if ($last_error !== null) {
			echo esc_html( $last_error['message'] );
		} 
	}
}

function mailsure_convert_to_wp_time($time) {

	$datetime = new DateTime($time);
	// Set the wp timezone
	$datetime->setTimezone(new DateTimeZone( wp_timezone_string() ));
	
	return $datetime->format('D, d M Y H:i:s T');	
}
