<?php
	if (!defined('ABSPATH') && !defined('WP_UNINSTALL_PLUGIN')) {exit();}
	delete_option('blog_old_post_interval');
	delete_option('blog_old_post_log');
	delete_option('blog_old_post_settings');
	wp_clear_scheduled_hook('blog_old_post_cron');

?>