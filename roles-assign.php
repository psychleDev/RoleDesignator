<?php
/**
 * Plugin Name: Role Designator
 * Description: Manages custom roles for Men of Fire and Ignite30 users
 * Version: 1.0.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) {
    exit;
}

class RoleDesignator {
    public function __construct() {
        // Register activation and deactivation hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        // Initialize roles on plugin load
        add_action('init', [$this, 'initialize_roles']);
        
        // Add role management to admin menu
        add_action('admin_menu', [$this, 'add_admin_menu']);
        
        // Add bulk actions for role management
        add_filter('bulk_actions-users', [$this, 'add_bulk_actions']);
        add_filter('handle_bulk_actions-users', [$this, 'handle_bulk_actions'], 10, 3);
        
        // Add admin notice for bulk actions
        add_action('admin_notices', [$this, 'bulk_action_admin_notice']);
    }

    public function activate() {
        // Create roles if they don't exist
        $this->create_menoffire_role();
        $this->create_ignite30_role();
        flush_rewrite_rules();
    }

    public function deactivate() {
        // Optionally remove roles on deactivation
        // remove_role('menoffire');
        // remove_role('ignite30');
        flush_rewrite_rules();
    }

    private function create_menoffire_role() {
        remove_role('menoffire');
        add_role(
            'menoffire',
            'Men of Fire',
            [
                'read' => true,
                'edit_posts' => false,
                'delete_posts' => false,
                'publish_posts' => false,
                'upload_files' => false,
                'view_journal' => true,
                'view_archive' => true,
                'level_0' => true
            ]
        );
    }

    private function create_ignite30_role() {
        remove_role('ignite30');
        add_role(
            'ignite30',
            'Ignite 30',
            [
                'read' => true,
                'edit_posts' => false,
                'delete_posts' => false,
                'publish_posts' => false,
                'upload_files' => false,
                'view_journal' => true,  // Can view journal
                'view_archive' => false, // Cannot view archive
                'level_0' => true
            ]
        );
    }

    public function initialize_roles() {
        // Ensure roles exist and have correct capabilities
        $menoffire_role = get_role('menoffire');
        $ignite30_role = get_role('ignite30');

        if (!$menoffire_role) {
            $this->create_menoffire_role();
        }

        if (!$ignite30_role) {
            $this->create_ignite30_role();
        }
    }

    public function add_admin_menu() {
        add_menu_page(
            'Role Designator',
            'Role Designator',
            'manage_options',
            'role-designator',
            [$this, 'render_admin_page'],
            'dashicons-groups',
            30
        );
    }

    public function render_admin_page() {
        // Get all users with our custom roles
        $users = get_users([
            'role__in' => ['menoffire', 'ignite30']
        ]);

        ?>
        <div class="wrap">
            <h1>Role Designator</h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo esc_html($user->user_login); ?></td>
                            <td><?php echo esc_html($user->user_email); ?></td>
                            <td><?php echo esc_html(implode(', ', $user->roles)); ?></td>
                            <td>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=role-designator&action=switch_role&user_id=' . $user->ID), 'switch_role'); ?>">
                                    Switch Role
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function add_bulk_actions($bulk_actions) {
        $bulk_actions['set_menoffire'] = 'Set as Men of Fire';
        $bulk_actions['set_ignite30'] = 'Set as Ignite 30';
        return $bulk_actions;
    }

    public function handle_bulk_actions($redirect_to, $action, $user_ids) {
        if ($action !== 'set_menoffire' && $action !== 'set_ignite30') {
            return $redirect_to;
        }

        $role = str_replace('set_', '', $action);
        $updated = 0;

        foreach ($user_ids as $user_id) {
            $user = get_user_by('id', $user_id);
            if ($user) {
                $user->set_role($role);
                $updated++;
            }
        }

        return add_query_arg('bulk_role_updated', $updated, $redirect_to);
    }

    public function bulk_action_admin_notice() {
        if (!empty($_REQUEST['bulk_role_updated'])) {
            $updated = intval($_REQUEST['bulk_role_updated']);
            printf(
                '<div class="updated notice is-dismissible"><p>%d users updated.</p></div>',
                $updated
            );
        }
    }

    // Helper function to check if user has access to specific functionality
    public static function can_access($feature) {
        $user = wp_get_current_user();
        
        switch ($feature) {
            case 'journal':
                return in_array('menoffire', (array) $user->roles) || 
                       in_array('ignite30', (array) $user->roles) || 
                       current_user_can('administrator');
            
            case 'archive':
                return in_array('menoffire', (array) $user->roles) || 
                       current_user_can('administrator');
            
            default:
                return false;
        }
    }
}

// Initialize the plugin
$role_designator = new RoleDesignator();

// Utility function for other plugins to use
function rd_can_access($feature) {
    return RoleDesignator::can_access($feature);
}
