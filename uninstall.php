<?php

if (!defined('ABSPATH') || !defined('WP_UNINSTALL_PLUGIN')) exit; // Exit if accessed directly

// Delete options
delete_option('mailsure_new_data_flag');
delete_option('mailsure_latest_test_result');
delete_option('mailsure_initiated_on_demand_test');
