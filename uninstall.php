<?php
if (! defined('WP_UNINSTALL_PLUGIN')) { exit; }
foreach (['administrator','editor'] as $role_name) { $role = get_role($role_name); if ($role) { foreach (['verbum_access','verbum_manage','verbum_manage_settings'] as $cap) { $role->remove_cap($cap); } } }
