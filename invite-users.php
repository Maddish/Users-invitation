<?php
/*
Plugin Name: Invite users
Plugin URI: http://inauditas.com
Description: Limit wordpress registration to invited users. You can blk invite invite them. Users must use the same email account where they received the invitation
Version: The Plugin's Version Number, e.g.: 1.0
Author: Maddish
Author URI: http://inauditas.com
License:  GPL2
*/
function invite_users_plugin_install(){
	    //Do some installation work

	    
}
register_activation_hook(__FILE__,'invite_users_plugin_install');

function user_invite_prefix() {
	global $wpdb;
	if ( !empty( $wpdb->base_prefix ) ) return $wpdb->base_prefix;
	return $wpdb->prefix;
}
//SCRIPTS
function invite_users_scripts(){
	    wp_register_script('invite_users_script',plugin_dir_url( __FILE__ ).'relcopy.js');
	    wp_enqueue_script('invite_users_script');
	    wp_register_script('admin-scripts',plugin_dir_url( __FILE__ ).'admin-scripts.js');
	    wp_enqueue_script('admin-scripts');
}
add_action('admin_enqueue_scripts','invite_users_scripts');
add_filter('admin_head','ShowTinyMCE');
function ShowTinyMCE() {
	// conditions here
	wp_enqueue_script( 'common' );
	wp_enqueue_script( 'jquery-color' );
	wp_print_scripts('editor');
	//if (function_exists('add_thickbox')) add_thickbox();
	wp_print_scripts('media-upload');
	//if (function_exists('wp_editor')) wp_editor();
	wp_admin_css();
	wp_enqueue_script('utils');
	do_action("admin_print_styles-post-php");
	do_action('admin_print_styles');
}
// get the base prefix
function database_invite_prefix() {
	global $wpdb;
	if ( !empty( $wpdb->base_prefix ) ) return $wpdb->base_prefix;
	return $wpdb->prefix;
}
add_action( 'admin_init', 'mails_otions_settings' );

function mails_otions_settings() {
	register_setting( 'mails_otions_settings_group', 'mail_title' );
	register_setting( 'mails_otions_settings_group', 'mail_body' );
}

add_action( 'init', 'secure_invite_check_table' );
	function secure_invite_check_table() {
	global $wpdb;
	$table_name = "usersinvitations";
	if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
		//table is not created. you may create the table here.

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		// include the file with the required database manipulation functions
		// create the table
		$sql = "CREATE TABLE ".user_invite_prefix()."usersinvitations (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		user_id mediumint(9),
		invited_email varchar(255),
		datestamp datetime,
		activated tinyint(1),
		PRIMARY KEY  (id)
		);";
				dbDelta($sql);
	}
}

function user_invite_email_exists( $email ) {
	$email=sanitize_email( $email );
	$existing_user=false;
	$already_registered=sprintf(_("The email %s is already used by a registered user"),$email);
	global $wpdb;
	//$wpdb->show_errors = true;
	//check if the user is already registred
	if( function_exists('email_exists') ) {
		$exists_user = email_exists( $email );
		if ( $exists_user ) {
                        $existing_user = true;
                        $existing_message=$already_registered;

		} else {
                        $existing_user = false;
                        $existing_message="";
		}	
		//$existing_message=$already_registered;

	} else {
		$sql = $wpdb->prepare( "select user_email from " . $wpdb->users . " where user_email = %s;", $email );
		$saved_email = $wpdb->get_var( $sql );
		if ( $saved_email ==$email ) {
			$existing_user = true;
			$existing_message=$already_registered;
		} else {
			$existing_user = false;
			$existing_message="";
		}
	}
	$wpdb->flush();	
	//If not registered, also check if the usar has already been invited
	if (!$existing_user){
		$sql2 = $wpdb->prepare( "SELECT id FROM " . $wpdb->prefix . "usersinvitations WHERE invited_email = %s;", $email );
		$found_emails = $wpdb->get_results( $sql2 );
//		$already_invited = false;
		if ( count($found_emails) > 0 ) {
			$existing_user= true;
			$existing_message= sprintf(_("User %s has already been invited. Please, remove it from the list of invited users, if you wish to send an invitation again"),$email);
		}
	}
	/*
	if ( $existing_user || $already_invited ) {
		return true;
		return $existing_message;
	}
	*/
	return array('user_exists' => $existing_user, 'message' => $existing_message);
	//return false;
}

function user_invite_send($name)
{
	global $current_site, $current_user, $blog_id, $wpdb;
	// check the user can invite
	if (is_admin())
	{
		// check this email address isn't already registered
		$check_user_exist=user_invite_email_exists($name );
		 if( !$check_user_exist['user_exists'] ){
			$usernickname = $current_user->display_name;
			$to = $pname = sanitize_email($name);
			$from = $current_user->display_name . ' <' . $current_user->user_email . '>';
			$site_name = stripslashes( get_site_option('blogname') );
			//if ( $site_name == "" ){ $site_name = stripslashes( get_option( 'blogname' ) ); }
			
			// save the invitation 
			$sql = $wpdb->prepare("insert into ".database_invite_prefix()."usersinvitations
		(user_id, invited_email, datestamp)
		values
		(%d, %s, now());", $current_user->ID, $to);
			$wpdb->print_error();
			$query = $wpdb->query($sql);
			// if the invitation could be saved
			if ($query)
			{
			$mail_options=get_option( 'mail_options_set' );

			$subject=  isset( $mail_options['title'] ) ? $mail_options['title'] : 'Invitation';
			$mail_message=  isset($mail_options['m_body'] )? $mail_options['m_body'] : 'Hi there ';
			$mail_message= preg_replace("/\r\n|\r|\n/",'<br/>',$mail_message);
			$headers = 'From: ' . $from.  "\r\n";
			$headers  .='Reply-To: ' . $from.  "\r\n";
			$headers .= "MIME-Version: 1.0\r\n";
			$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
			$message = '<html><body>';
			$message .=str_replace("%user_email%", $to, $mail_message);
			$message .= '</body></html>';

			$sent_email = wp_mail($to, $subject, $message, $headers);
			if ($sent_email) {
			//	return true;
				printf(_("Invitation successfully sent to %s"),$name);
			} else {
				printf(_('The user %s has been correctly addeed to the list of invited users, but the email could not be sent. If you are using an SMTP extension, please check that the sender email credentials are correct'),$name);
				}
			} else {
				$headers = 'From: '. $from . "\r\n" . 
							'Reply-To: ' . $from;
				wp_mail(stripslashes( get_site_option("admin_email") ), "Secure invite failure for ".$from, "A user just tried to invite someone to join ".$site_name.". The following SQL query could not be completed:\n\n".$sql."\n\nThe error reported was:\n\n".$query_error."\n\nThis is an automatic email sent by the Secure Invites plugin.", $headers);
			}
		} else {
			echo $check_user_exist['message'];	
		//echo 'The email ' .$name . ' has been already invited;';
		
		}
	}
	return false;
}
// add actual admin page
function invite_users_options() { ?>
	<div class="wrap">
		<h1>Invite users to register to your site</h1>
		<form method="post">
			<p class="clone">
				<label> Email: </label>
				<input type="text" name="name[]" />
			</p>
			<p><a href="#" class="name" rel=".clone">Add Name</a></p>
			<p><input type="submit" value="Submit" /></p>
		</form>
	</div>

		<script>
		jQuery(document).ready(function(){
   var removeName = '<a href="#" onClick="jQuery(this).parent().slideUp( function(){jQuery(this).remove()}); return false">remove</a>';
jQuery('a.name').relCopy({append:removeName});
});
		</script>
		

<?php

$names=!empty( $_POST['name'] ) ?$_POST['name'] :"";
if(!$names=='') {
foreach($names as $name) {
	if(strlen($name)>0) {
			user_invite_send($name);
		} else {
			echo 'Oops no value to be inserted.';
		}
	}
}
}

//setiings 
function invite_users_settings() { ?>
 <?php settings_fields( 'mails_otions_settings_group' ); ?>
 <?php do_settings_sections( 'mails_otions_settings_group' ); ?>
	<div class="wrap">
		<h1>Settings</h1>
		<h3> </h3>
		<form method="post" >
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><label><?php _e('Email title '); ?></label></th>
						<td>
							<input type="text" style="width:100%;" name="mail_title" value="<?php echo get_option('mail_title'); ?>" />
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><label><?php _e('Email content '); ?></label></th>

						<td>
							<textarea id="mail_body" style="width:100%;" name="mail_body" rows="20" cols="70"  value="<?php echo get_option('mail_body'); ?>"></textarea>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"></th>
							<td>
								

						</td>
					</tr>
				</table>
															<input class="button-primary" type="submit" name="Save" value="<?php _e('Save Options', 'wti-like-post'); ?>" />
		</form>
	</div>

<?php
	global $wpdb;
	if (isset($_POST['Save'])){
		update_option('mail_title', $_POST['mail_title']);
		update_option('mail_body', $_POST['mail_body']);
	}
}
/*
Plugin Name: Test List Table Example
*/

if( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Invited_Users_List_Table extends WP_List_Table {


    function __construct(){
    global $status, $page;

        parent::__construct( array(
            'singular'  => __( 'user', 'invitationslist' ),     //singular name of the listed records
            'plural'    => __( 'users', 'invitationslist' ),   //plural name of the listed records
            'ajax'      => true        //does this table support ajax?

    ) );

    add_action( 'admin_head', array( &$this, 'admin_header' ) );            

    }

  function admin_header() {
    $page = ( isset($_GET['page'] ) ) ? esc_attr( $_GET['page'] ) : false;
    if( 'user_list' != $page )
    return;
    echo '<style type="text/css">';
    echo '.wp-list-table .column-id { width: 5%; }';
    echo '.wp-list-table .column-email { width: 40%; }';
    echo '.wp-list-table .column-date { width: 35%; }';
    echo '.wp-list-table .column-activated { width: 15%; }';
    echo '</style>';
  }

  function no_items() {
    _e( 'No invitations found!' );
  }

  function column_default( $item, $column_name ) {
    switch( $column_name ) { 
        case 'email':
        case 'date':

            return $item-> datestamp;
        case 'activated':
			$is_active= $item->activated;
			if ($is_active==1){
			return 'yes';
			}else {
			return $item->activated;
			}
        default:
            return print_r( $item, true ) ; //Show the whole array for troubleshooting purposes
    }
  }

  function get_sortable_columns() {
	$sortable_columns = array(
		'email'  => array('email',false),
		'date' => array('date',false),
		'activated' => array('activated',false)
	);
	return $sortable_columns;
 }

  function get_columns(){
	$columns = array(
		'cb'        => '<input type="checkbox" />',
		'email' => __( 'E-mail', 'invitationslist' ),
		'date'    => __( 'Invitation Date', 'invitationslist' ),
		'activated'    => __( 'Activated', 'invitationslist' )
	);
		return $columns;
 }

  function usort_reorder( $a, $b ) {
	// If no sort, default to title
	$orderby = ( ! empty( $_GET['orderby'] ) ) ? $_GET['orderby'] : 'invited_email';
	// If no order, default to asc
	$order = ( ! empty($_GET['order'] ) ) ? $_GET['order'] : 'asc';
	// Determine sort order
	$result = strcmp( $a[$orderby], $b[$orderby] );
	// Send final sort direction to usort
	return ( $order === 'asc' ) ? $result : -$result;
  }

  function column_email($item){
	$element_id = 'delete_' . $item->id . '_' . wp_create_nonce('delete_' . $item->id );
	$actions = array(
			'delete'      => sprintf('<a href="?page=%s&action=%s&id=%s">Delete</a>',$_REQUEST['page'],'delete',$item->id),
		);
	return sprintf('%1$s %2$s', $item->invited_email, $this->row_actions($actions) );
  }

 function delete_invitation() {
	global $wpdb;
	$sql = $wpdb->prepare( "delete from ". database_invite_prefix(). "usersinvitations where id = %s;", intval($_POST['id']) );
 }
/*function get_bulk_actions() {
  $actions = array(
    'delete'    => 'Delete'
  );
  return $actions;
}
*/
  function column_cb($item) {
	return sprintf(
		'<input type="checkbox" name="book[]" value="%s" />', $item->id
	);    
 }

  function prepare_items() {
	global $wpdb;
	$customquery="";
	$columns  = $this->get_columns();
	$delete_true = !empty($_GET["action"]) ? sanitize_key($_GET["action"]) : '';
	if ($delete_true=='delete'){
		$delete_us_id= !empty($_GET["id"]) ? intval($_GET["id"]) : '';
		if(!empty($delete_us_id)){
			$query_del = "DELETE FROM " . $wpdb->prefix . "usersinvitations where id = " . $delete_us_id . " limit 1";
			$wpdb->query($query_del);
		}
	}
	$hidden   = array();
	$sortable = $this->get_sortable_columns();
	$this->_column_headers = array( $columns, $hidden, $sortable );


	$per_page = 10;
	$user = get_current_user_id();
	$screen = get_current_screen();
	$option = $screen->get_option('per_page', 'option');
	 
	$per_page = get_user_meta($user, $option, true);
 
	if ( empty ( $per_page) || $per_page < 1 ) {
 
	    $per_page = $screen->get_option( 'per_page', 'default' );
 
	}
	$current_page = $this->get_pagenum();
	$example_data = $this->get_sql_results($customquery="");
	empty($example_data) AND $example_data = array();
	$total_items = count( $example_data );

	//Which page is this?
	$paged = !empty($_GET["paged"]) ? intval($_GET["paged"]) : '';
	//Page Number
	if(empty($paged) || !is_numeric($paged) || $paged<=0 ){ $paged=1; }
	//How many pages do we have in total?
	$totalpages = ceil($total_items/$per_page);
	//adjust the query to take pagination into account
	if(!empty($paged) && !empty($per_page)){
		$offset=($paged-1)*$per_page;
		$customquery=' LIMIT '.(int)$offset.','.(int)$per_page;
	}


	$this->set_pagination_args( array(
		'total_items' => $total_items,                  //WE have to calculate the total number of items
		'per_page'    => $per_page                     //WE have to determine how many items to show on a page
	) );
	// $this->items = $this->found_data;

	$this->items =  $this->get_sql_results($customquery);
 }

 private function get_sql_results($customquery){

	$orderby = ( ! empty( $_GET['orderby'] ) ) ? $_GET['orderby'] : 'invited_email';
	if ($orderby=='date')$orderby='datestamp';
	if ($orderby=='email')$orderby='invited_email';
	// If no order, default to asc
	$order = ( ! empty($_GET['order'] ) ) ? $_GET['order'] : 'ASC';
	global $wpdb;
	$args = array('id,invited_email', 'datestamp','activated');
	$sql_select = implode(', ', $args);
	//		$sql = $wpdb->prepare( "select user_email from " . $wpdb->users . " where user_email = %s;", $email );
	//$saved_email = $wpdb->get_var( $sql );
	$table=$wpdb->prefix . 'usersinvitations';
	$querystr = "
		SELECT $sql_select
		FROM $table		
	";
	 if( isset($_GET['s']) ){
		 $search=trim($_GET['s']);
		 $querystr.=" WHERE $table.invited_email  LIKE  '%$search%'";
	 }
	$querystr.=" ORDER BY $orderby $order";
	if($customquery)$querystr.=$customquery;
	$sql_results = $wpdb->get_results($querystr, OBJECT);
	return $sql_results;

	}
 
	
} //class

function my_add_menu_items(){

	$hook = add_menu_page( 'Users Invitations', 'Invited Users List', 'activate_plugins', 'user_list', 'my_render_list_page' );
	add_action( "load-$hook", 'add_options' );
	add_submenu_page( 'user_list', 'Invite New Users', 'Invite New Users', 'manage_options', 'invite_users', 'invite_users_options');

}

function add_options() {
  global $myListTable;
  $option = 'per_page';
  $args = array(
         'label' => 'Invitations per page',
         'default' => 10,
         'option' => 'users_per_page'
         );
  add_screen_option( $option, $args );
  $myListTable = new Invited_Users_List_Table();
}
add_action( 'admin_menu', 'my_add_menu_items' );

add_filter('set-screen-option', 'users_lkists_set_option', 10, 3);
 
function users_lkists_set_option($status, $option, $value) {
	if ( 'users_per_page' == $option ) return $value;
	return $status;
}


function my_render_list_page(){
	global $myListTable;
	echo '</pre><div class="wrap"><h1>Active Invitations <a class="page-title-action" href="';
	echo admin_url( 'admin.php?page=invite_users');
	echo '">Add New</a></h1>'; 

	$myListTable->prepare_items(); 
?>
	<form action="<?php echo network_admin_url('admin.php?page=user_list'); ?>" method="get">
	<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>">
	<?php
	$myListTable->search_box( 'search', 'user-email' );

	$myListTable->display(); 
	echo '</form></div>'; 
}
//SETTINGS PAGE
class MySettingsPage
{
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    /**
     * Start up
     */
    public function __construct()
    {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
    }

    /**
     * Add options page
     */
    public function add_plugin_page()
    {
        add_submenu_page( 'user_list', 'Settings', 'Settings', 'manage_options', 'invitation-settings', array( $this, 'create_admin_page'));

    }

    /**
     * Options page callback
     */
    public function create_admin_page()
    {
        // Set class property
        $this->options = get_option( 'mail_options_set' );
        ?>
        <div class="wrap">
            <h2> Settings</h2>           
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'mail_option_group' );   
                do_settings_sections( 'invitation-settings' );
                submit_button(); 
            ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    public function page_init()
    {        
        register_setting(
            'mail_option_group', // Option group
            'mail_options_set', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'setting_section_id', // ID
            'Invitation Mail Custom Settings', // Title
            array( $this, 'print_section_info' ), // Callback
            'invitation-settings' // Page
        );  
        add_settings_field(
            'title', 
            'Mail Title', 
            array( $this, 'title_callback' ), 
            'invitation-settings', 
            'setting_section_id'
        );      
        add_settings_field(
            'm_body', // ID
            'Mail Content', // Title 
            array( $this, 'm_body_callback' ), // Callback
            'invitation-settings', // Page
            'setting_section_id' // Section           
        );      


    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input )
    {
        $new_input = array();
        if( isset( $input['m_body'] ) )
            //$new_input['m_body'] = sanitize_text_field( $input['m_body'] );
           //$new_input['m_body']= str_replace('<br />', PHP_EOL, $new_input['m_body']);
           // $new_input['m_body']  = nl2br($input['m_body']);
//$new_input['m_body']  = stripslashes($your_form_text);
           $new_input['m_body'] = nl2br(htmlentities($input['m_body'], ENT_QUOTES, 'UTF-8'));
           $new_input['m_body']  = preg_replace('/\r\n|\r/', "\n", $input['m_body']);  

        if( isset( $input['title'] ) )
            $new_input['title'] = sanitize_text_field( $input['title'] );

        return $new_input;
    }

    /** 
     * Print the Section text
     */
    public function print_section_info()
    {
        print 'You can customize the title and content of the e-mail that will be send to invited users<br>';
        print 'You can iclude %user_email% in the text. This will print the invited user email in the sent message';
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function m_body_callback()
    {
       /* printf(
           // '<input type="text" id="m_body" name="mail_options_set[m_body]" value="%s" />',
          //'<textarea  id="m_body" rows="10" cols="90" name="mail_options_set[m_body]">%s</textarea>',
            isset( $this->options['m_body'] ) ? esc_attr( $this->options['m_body']) : ''
        );*/
        $content=  isset( $this->options['m_body'] ) ?  $this->options['m_body'] : '';
         wp_editor($content, 'mbody',  array('textarea_name' => 'mail_options_set[m_body]') ); 
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function title_callback()
    {
        printf(
            '<input type="text" id="title" size="100" name="mail_options_set[title]" value="%s" />',
            isset( $this->options['title'] ) ? esc_attr( $this->options['title']) : ''
        );
    }
}//end class MySettingsPage

if( is_admin() )
	$my_settings_page = new MySettingsPage();
// check if email that the user inserted in the registration form is in database  
function validate_email_invitation(){

	global $bp,$wpdb;
	$email = $bp->signup->email;
	$sql= $wpdb->prepare( "select invited_email from " . $wpdb->prefix . "usersinvitations where invited_email = %s;", $email );
	$saved_email = $wpdb->get_var( $sql );
	//var_dump($saved_email);
	if ( empty($saved_email)) {

		$bp->signup->errors['signup_email'] = 'Sorry, You need an invitation to register a new user.';
	}

}

add_action('bp_signup_validate','validate_email_invitation');


function disable_validation( $user_id ) {
	global $bp, $wpdb;
	$user = false;
	$sql = 'select meta_value from '. $wpdb->prefix  .'usermeta where meta_key="activation_key" and user_id=' . $user_id ;

	$activationey = $wpdb->get_var($sql);
	$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->users SET user_status = 0 WHERE ID = %d", $user_id 	) );
		wp_cache_delete( 'bp_total_member_count', 'bp' );
	$user_info=get_userdata( $user_id );
	$user_email=$user_info->user_email;
	$user_name=$user_info->user_login;
	$wpdb->query( $wpdb->prepare( "UPDATE " . $wpdb->prefix . "usersinvitations SET activated  = 1 WHERE invited_email= %s", $user_email	) );
	$date= current_time( 'mysql', true );
	$wpdb->query( $wpdb->prepare( "UPDATE " . $wpdb->prefix . "signups SET active=1 WHERE user_login = %s",$user_name	) );
		wp_cache_delete( 'bp_total_member_count', 'bp' );
	delete_user_meta( $user_id, 'activation_key' );

}
add_action( 'bp_core_signup_user', 'disable_validation' );

function fix_signup_form_validation_text() {
	return false;
}
add_filter( 'bp_registration_needs_activation', 'fix_signup_form_validation_text' );

function disable_activation_email() {
	return false;
}
add_filter( 'bp_core_signup_send_activation_key', 'disable_activation_email' );

	//redirect user tu custom page after register
function bp_redirect($user) {

	$redirect_url= get_permalink(20);
    bp_core_redirect($redirect_url);

}
add_action('bp_core_signup_user', 'bp_redirect', 100, 1);

?>
