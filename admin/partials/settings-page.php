<div class="wrap doodle-mac-settings">
	<h1>Manager Access Control Settings</h1>
	
	<?php 
	// Check if manager role exists
	$manager_role_exists = function_exists('mac_manager_role_exists') && mac_manager_role_exists();
	?>
	
	<!-- Main settings form -->
	<form method="post" action="options.php">
		<?php 
		settings_fields('doodle_mac_settings_group');
		do_settings_sections('manager-access-control');
		submit_button('Save Settings', 'primary'); 
		?>
	</form>
	
	<!-- Success message after setup -->
	<?php if ($manager_role_exists) : ?>
		<div class="mac-info-box">
			<h3>Manager Role Setup Complete</h3>
			<p>The Manager role has been successfully configured with all recommended permissions.</p>
			<p>You can further customize permissions in the <a href="<?php echo admin_url('admin.php?page=aam'); ?>">AAM dashboard</a>.</p>
		</div>
	<?php endif; ?>
</div>