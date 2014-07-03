<?php
/**
 * init.php
 *
 * WordPress hooks and functions for the 'wp-admin'.
 *
 * @package   Revisr
 * @license   GPLv3
 * @link      https://revisr.io
 * @copyright 2014 Expanded Fronts
 */

include "functions.php";
include "class.settings.php";

class RevisrInit
{
	

	private $dir;
	private $options;
	private $table_name;
	private $branch;

	public function __construct()
	{
		global $wpdb;

		$this->wpdb = $wpdb;
		$this->options = get_option('revisr_settings');

		$this->table_name = $wpdb->prefix . "revisr";
		$this->branch = current_branch();
		$this->dir = plugin_dir_path( __FILE__ );

		if ( is_admin() ) {
			add_action( 'init', array($this, 'post_types') );
			$revisr_settings = new RevisrSettings();
			add_action( 'load-edit.php', array($this, 'default_views') );
			add_action( 'load-post.php', array($this, 'meta') );
			add_action( 'load-post-new.php', array($this, 'meta') );
			add_action( 'pre_get_posts', array($this, 'filters') );
			add_action( 'views_edit-revisr_commits', array($this, 'custom_views') );
			add_action( 'post_row_actions', array($this, 'custom_actions') );
			add_action( 'admin_menu', array($this, 'menus'), 2 );
			add_action( 'manage_edit-revisr_commits_columns', array($this, 'columns') );
			add_action( 'manage_revisr_commits_posts_custom_column', array($this, 'custom_columns') );
			add_action( 'admin_enqueue_scripts', array($this, 'styles') );
			add_action( 'admin_enqueue_scripts', array($this, 'scripts') );
			add_action( 'admin_bar_menu', array($this, 'admin_bar'), 999 );
			add_action( 'admin_enqueue_scripts', array($this, 'disable_autodraft') );
			add_filter( 'post_updated_messages', array($this, 'revisr_commits_custom_messages') );
			add_filter( 'bulk_post_updated_messages', array($this, 'revisr_commits_bulk_messages'), 10, 2 );
			add_filter( 'custom_menu_order', array($this, 'revisr_commits_submenu_order') );
		}
	}

	public function post_types()
	{
		$labels = array(
			'name'                => 'Commits',
			'singular_name'       => 'Commit',
			'menu_name'           => 'Commits',
			'parent_item_colon'   => '',
			'all_items'           => 'Commits',
			'view_item'           => 'View Commit',
			'add_new_item'        => 'New Commit',
			'add_new'             => 'New Commit',
			'edit_item'           => 'Edit Commit',
			'update_item'         => 'Update Commit',
			'search_items'        => 'Search Commits',
			'not_found'           => 'No commits found yet, why not create a new one?',
			'not_found_in_trash'  => 'No commits in trash.',
		);
		$capabilities = array(
			'edit_post'           => 'activate_plugins',
			'read_post'           => 'activate_plugins',
			'delete_post'         => 'activate_plugins',
			'edit_posts'          => 'activate_plugins',
			'edit_others_posts'   => 'activate_plugins',
			'publish_posts'       => 'activate_plugins',
			'read_private_posts'  => 'activate_plugins',
		);
		$args = array(
			'label'               => 'revisr_commits',
			'description'         => 'Commits made through Revisr',
			'labels'              => $labels,
			'supports'            => array( 'title', 'author'),
			'hierarchical'        => false,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => 'revisr',
			'show_in_nav_menus'   => true,
			'show_in_admin_bar'   => true,
			'menu_position'       => 5,
			'menu_icon'           => '',
			'can_export'          => true,
			'has_archive'         => true,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'capabilities'        => $capabilities,
		);
		register_post_type( 'revisr_commits', $args );
	}

	public function meta()
	{
		if (isset($_GET['action'])) {
			if ($_GET['action'] == 'edit') {
				add_meta_box( 'revisr_committed_files', 'Committed Files', array($this, 'committed_files_meta'), 'revisr_commits', 'normal', 'high' );
			}			
		}
		
		else {
			add_meta_box( 'revisr_pending_files', 'Untracked Files', array($this, 'pending_files_meta'), 'revisr_commits', 'normal', 'high' );
		}
	}

	public function menus()
	{
		$menu = add_menu_page( 'Dashboard', 'Revisr', 'manage_options', 'revisr', array($this, 'revisr_dashboard'), plugins_url( 'revisr/assets/img/white_18x20.png' ) );
		add_submenu_page( 'revisr', 'Revisr - Dashboard', 'Dashboard', 'manage_options', 'revisr', array($this, 'revisr_dashboard') );
		$settings_hook = add_submenu_page( 'revisr', 'Revisr - Settings', 'Settings', 'manage_options', 'revisr_settings', array($this, 'revisr_settings') );
		add_action( 'admin_print_styles-' . $menu, array($this, 'styles') );
		add_action( 'admin_print_scripts-' . $menu, array($this, 'scripts') );
		remove_meta_box('authordiv', 'revisr_commits', 'normal');
	}

	public function revisr_commits_submenu_order($menu_ord)
	{
		global $submenu;

	    $arr = array();
	    $arr[] = $submenu['revisr'][0];
	    $arr[] = $submenu['revisr'][2];
	    $arr[] = $submenu['revisr'][1];
	    $submenu['revisr'] = $arr;

	    return $menu_ord;
	}

	public function revisr_dashboard()
	{
		include_once $this->dir . "../templates/dashboard.php";
	}

	public function revisr_settings()
	{
		include_once $this->dir . "../templates/settings.php";
	}

	public function view_diff()
	{
		include_once $this->dir . "../templates/view_diff.php";
	}

	public function custom_actions($actions)
	{
		if (get_post_type() == 'revisr_commits')
		{
			if (isset($actions)) {
				unset( $actions['edit'] );
		        unset( $actions['view'] );
		        unset( $actions['trash'] );
		        unset( $actions['inline hide-if-no-js'] );

		        $id = get_the_ID();
		        $url = get_admin_url() . "post.php?post=" . get_the_ID() . "&action=edit";

		        $actions['view'] = "<a href='{$url}'>View</a>";
		        $branch_meta = get_post_custom_values('branch', get_the_ID());
		        $db_hash = get_post_custom_values('db_hash', get_the_ID());
		        $commit_hash = get_hash($id);
		        $actions['revert'] = "<a href='" . get_admin_url() . "admin-post.php?action=revert&commit_hash={$commit_hash}&branch={$branch_meta[0]}&post_id=" . get_the_ID() ."'>Revert Files</a>";
	          	
		        if ($db_hash[0] != '') {
	          		$actions['revert_db'] = "<a href='" . get_admin_url() . "admin-post.php?action=revert_db&db_hash={$db_hash[0]}&branch={$branch_meta[0]}&post_id=" . get_the_ID() ."'>Revert Database</a>";
		        }
		    	
			}
		}
		return $actions;
	}

	public function filters($commits)
	{
		if (isset($_GET['post_type']) && $_GET['post_type'] == "revisr_commits") {
			if ( isset($_GET['branch']) && $_GET['branch'] != "all" ) {
				$commits->set( 'meta_key', 'branch' );
				$commits->set( 'meta_value', $_GET['branch'] );
				$commits->set('post_type', 'revisr_commits');
			}
		}

		return $commits;
	}

	public function count_commits($branch)
	{
		if ($branch == "all") {
			$num_commits = $this->wpdb->get_results("SELECT * FROM " . $this->wpdb->prefix . "postmeta WHERE meta_key = 'branch'");
		}
		else {
			$num_commits = $this->wpdb->get_results("SELECT * FROM " . $this->wpdb->prefix . "postmeta WHERE meta_key = 'branch' AND meta_value = '".$branch."'");
		}
		return count($num_commits);
	}

	public function custom_views($views)
	{

		$output = git("branch");

		global $wp_query;

		foreach ($output as $key => $value) {
			$branch = substr($value, 2);
    	    $class = ($wp_query->query_vars['meta_value'] == $branch) ? ' class="current"' : '';
	    	$views["$branch"] = sprintf(__('<a href="%s"'. $class .'>' . ucwords($branch) . ' <span class="count">(%d)</span></a>'),
	        admin_url('edit.php?post_type=revisr_commits&branch='.$branch),
	        $this->count_commits($branch));
		}
		if ($_GET['branch'] == "all") {
			$class = 'class="current"';
		}
		else {
			$class = '';
		}
		$views['all'] = sprintf(__('<a href="%s"' . $class . '>All Branches <span class="count">(%d)</span></a>' ),
			admin_url('edit.php?post_type=revisr_commits&branch=all'),
			$this->count_commits("all"));
		unset($views['publish']);
		//unset($views['trash']);
		if (isset($views)) {
			return $views;
		}
	}

	public function default_views()
	{
		if(!isset($_GET['branch']) && isset($_GET['post_type']) && $_GET['post_type'] == "revisr_commits") {
			$_GET['branch'] = current_branch();
		}
	}

	public function styles()
	{
		wp_enqueue_style( 'revisr_css', plugins_url() . '/revisr/assets/css/revisr.css' );
		wp_enqueue_style('thickbox');
	}

	public function scripts($hook)
	{
		
		wp_enqueue_script('alerts', plugins_url() . '/revisr/assets/js/dashboard.js');
		wp_enqueue_script('thickbox');

		if ($hook == 'post-new.php') {
			wp_enqueue_script('pending_files', plugins_url() . '/revisr/assets/js/pending_files.js');
		}
		if ($hook == 'post.php') {
			wp_enqueue_script('committed_files', plugins_url() . '/revisr/assets/js/committed_files.js');
			if (isset($_GET['post'])) {
				wp_localize_script('committed_files', 'committed_vars', array(
					'post_id' => $_GET['post'])
				);			
			}
		}
	}

	public function admin_bar($wp_admin_bar)
	{

		$options = get_option('revisr_settings');

		if (isset($options['revisr_admin_bar'])) {

			if (count_pending() == "1") {
				$text = "1 Untracked File";
			}
			else {
				$text = count_pending() . " Untracked Files";
			}
			$args = array(
				'id'    => 'revisr',
				'title' => $text,
				'href'  => get_admin_url() . 'post-new.php?post_type=revisr_commits',
				'meta'  => array( 'class' => 'revisr_commits' )
			);
			$wp_admin_bar->add_node( $args );
		}

	}

	public function disable_autodraft()
	{
		if ("revisr_commits" == get_post_type()) {
			wp_dequeue_script( 'autosave' );
		}
	}

	public function committed_files_meta()
	{
		echo "<div id='committed_files_result'></div>";
	}

	public function pending_files_meta()
	{
		$output = git("status --short");
		add_post_meta( get_the_ID(), 'committed_files', $output );
		add_post_meta( get_the_ID(), 'files_changed', count($output) );
		echo "<div id='message'></div>
		<div id='pending_files_result'></div>";
	}

	public function columns()
	{
		$columns = array (
			'cb' => '<input type="checkbox" />',
			'hash' => __('ID'),
			'title' => __('Commit'),
			'branch' => __('Branch'),			
			'files_changed' => __('Files Changed'),
			'date' => __('Date'));
		return $columns;
	}

	public function custom_columns($column)
	{
		global $post;

		$post_id = get_the_ID();
		switch ($column) {
			case "hash": 
				echo get_hash($post_id);
			break;
			case "branch":
				$branch_meta = get_post_meta( $post_id, "branch" );
				if ( isset($branch_meta[0]) ) {
					echo $branch_meta[0];
				}
			break;			
			case "files_changed":
				$files_meta = get_post_meta( $post_id, "files_changed" );
				if ( isset($files_meta[0]) ) {
					echo $files_meta[0];
				}
			break;
		}

	}

	public function revisr_commits_custom_messages($messages)
	{
		$post             = get_post();
		$post_type        = get_post_type( $post );

		$messages['revisr_commits'] = array(
		0  => '', // Unused. Messages start at index 1.
		1  => __( 'Commit updated.', 'revisr_commits' ),
		2  => __( 'Custom field updated.', 'revisr_commits' ),
		3  => __( 'Custom field deleted.', 'revisr_commits' ),
		4  => __( 'Commit updated.', 'revisr_commits' ),
		/* translators: %s: date and time of the revision */
		5  => isset( $_GET['revision'] ) ? sprintf( __( 'Commit restored to revision from %s', 'revisr_commits' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
		6  => __( 'Committed files on branch <strong>' . current_branch() . '</strong>.', 'revisr_commits' ),
		7  => __( 'Commit saved.', 'revisr_commits' ),
		8  => __( 'Commit submitted.', 'revisr_commits' ),
		9  => sprintf(
			__( 'Commit scheduled for: <strong>%1$s</strong>.', 'revisr_commits' ),
			// translators: Publish box date format, see http://php.net/date
			date_i18n( __( 'M j, Y @ G:i', 'revisr_commits' ), strtotime( $post->post_date ) )
		),
		10 => __( 'Commit draft updated.', 'revisr_commits' ),
		);

		return $messages;
	}

	public function revisr_commits_bulk_messages($bulk_messages, $bulk_counts)
	{
		$bulk_messages['revisr_commits'] = array(
			'updated' => _n( '%s commit updated.', '%s commits updated.', $bulk_counts['updated'] ),
			'locked'    => _n( '%s commit not updated, somebody is editing it.', '%s commits not updated, somebody is editing them.', $bulk_counts['locked'] ),
			'deleted'   => _n( '%s commit permanently deleted.', '%s commits permanently deleted.', $bulk_counts['deleted'] ),
			'trashed'   => _n( '%s commit moved to the Trash.', '%s commits moved to the Trash.', $bulk_counts['trashed'] ),
        	'untrashed' => _n( '%s commit restored from the Trash.', '%s commits restored from the Trash.', $bulk_counts['untrashed'] )
        	);
		return $bulk_messages;
	}


}