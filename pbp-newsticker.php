<?php
/*
Plugin Name: PBP Newsticker
Plugin URI: http://projoktibangla.net
Description: Create as many news tickers as you want and display them using shortcodes, widgets or PHP.
Version: 1.3.2
Author: projoktibangla
Author URI: http://projoktibangla.net
*/

class pbpNewsticker_Bootstrap {
    
    /**
     * pbpNewsticker_Bootstrap instance
     * @static
     * @access private
     * @var pbpNewsticker_Bootstrap
     */
    private static $instance = null;
    
    /**
     * The stored value in pbpNewsticker in wp_options
     * @static
     * @access private
     * @var array
     */
    private static $settings = array();
    
    /**
     * Data sources
     * @static
     * @access private
     * @var array
     */
    private static $data_sources = array();

    /**
     * Skins
     * @static
     * @access private
     * @var array
     */
    private static $skins = array();    

    /**
     * Ticker instance
     * @static
     * @access private
     * @var int
     */
    private static $ticker_instance = 0;
    
    /**
     * Create object. pbpNewsticker_Bootstrap instance should be retrieved through pbpNewsticker_Bootstrap::get_instance() method.
     * @access private
     */
    private function __construct() {
        
        // Set up multi-lingualism
        load_plugin_textdomain( 'pbpNews', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

        // Load newsticker data
        self::$settings = get_option('pbpNewsticker');
        
        // Include required files
        require_once( 'pbpnewsticker.class.php' );
        require_once( 'data_source.class.php' );
        require_once( 'data_sources/recent_posts.class.php' );
        require_once( 'data_sources/recent_comments.class.php' );
        require_once( 'data_sources/recent_custom_post_types.class.php' );
        require_once( 'data_sources/twitter.class.php' );
        require_once( 'data_sources/rss.class.php' );
        require_once( 'data_sources/facebook.class.php' );
        require_once( 'data_sources/free_form.class.php' );
        
        // Register hooks
        register_deactivation_hook( __FILE__, array(&$this, 'deactivation') );
        add_action( 'init', array(&$this, 'init') );
        add_action( 'admin_init', array(&$this, 'admin_init'));
        add_action( 'wp_enqueue_scripts', array(&$this, 'enqueue_scripts') );
        add_action( 'admin_enqueue_scripts', array(&$this, 'admin_enqueue_scripts') );
        add_action( 'admin_menu', array(&$this, 'admin_menu') );
        add_action( 'wp_ajax_create_data_source_instance', array(&$this, 'create_data_source_instance'));

        add_filter( 'pbpNewsticker_item_time_ago_format', array(&$this, 'time_ago_format') );        
        add_filter( 'pbpNewsticker_string', 'wptexturize' );
        add_filter( 'pbpNewsticker_string', 'convert_smilies' );
        add_filter( 'pbpNewsticker_string', 'convert_chars' );
        add_filter( 'pbpNewsticker_string', 'shortcode_unautop' );
        add_filter( 'pbpNewsticker_string', 'prepend_attachment' );        

        add_shortcode('newsticker', array(&$this, 'shortcode') );
    }
    
    /**
     * Retrieve object instance
     * @return pbpNewsticker
     */
    public static function get_instance() {
        if ( is_null(self::$instance) ) {
            self::$instance = new pbpNewsticker_Bootstrap();
        }
        return self::$instance;
    }

    /**
     * On plugin deactivation, delete pbpNewsticker fields from wp_options
     */
    public function deactivation() {
        delete_option('pbpNewsticker');
    }
    
    /**
     * Return pbpNewsticker settings
     * @static
     * @return array
     */
    public static function get_settings() {
        if ( is_null(self::$instance) ) {
            self::get_instance();
        }
        return self::$settings;
    }
    
    /**
     * Update pbpNewsticker settigns
     * @static
     * @return void
     */
    public static function update_settings() {
        self::$settings = get_option('pbpNewsticker');
    }

    /**
     * Get ticker instance
     * @return int
     */
    public static function get_ticker_instance() {
        $ticker_instance = self::$ticker_instance;
        self::$ticker_instance += 1;
        return $ticker_instance;
    }

    /**
     * Return all pbpNewsticker instances
     * @return array
     * @static
     */
    public static function get_all_tickers() {
        return isset( self::$settings['tickers'] ) ? self::$settings['tickers'] : array();
    }
    
    /**
     * Register a pbpNewsticker data source
     * @static
     * @param string $class_name
     * @return void
     */
    public static function register_data_source($class_name) {
        if (!in_array($class_name, self::$data_sources)) {
            self::$data_sources[] = $class_name;
        }
    }
    
    /**
     * Return all pbpNewsticker data sources
     * @static
     * @return array
     */
    public static function get_data_sources() {
        return self::$data_sources;
    }

    /**
     * Register a pbpNewsticker skin
     * @static
     * @param string $name
     * @param string $src
     * @return void
     */    
    public static function register_skin($name, $src) {
        if (!in_array($name, self::$skins)) {
            self::$skins[$src] = $name;
        }
    }

    /**
     * Return all pbpNewsticker data sources
     * @static
     * @return array
     */
    public static function get_skins() {
        return self::$skins;
    }    
    
    /**
     * Perform actions on init hook
     * @return void
     */
    public function init() {
        // Register data sources
        pbpNewsticker_register_data_source('pbpNewsticker_Free_Form');
        pbpNewsticker_register_data_source('pbpNewsticker_Recent_Posts');
        pbpNewsticker_register_data_source('pbpNewsticker_Recent_Custom_Post_Types');
        pbpNewsticker_register_data_source('pbpNewsticker_Recent_Comments');
        pbpNewsticker_register_data_source('pbpNewsticker_Twitter');
        pbpNewsticker_register_data_source('pbpNewsticker_Rss');
        pbpNewsticker_register_data_source('pbpNewsticker_Facebook');

        // Register skins
        pbpNewsticker_register_skin('Dark', plugins_url('media/pbpNewsticker/skins/dark.css', __FILE__));
        pbpNewsticker_register_skin('Green', plugins_url('media/pbpNewsticker/skins/green.css', __FILE__));
        pbpNewsticker_register_skin('Blue', plugins_url('media/pbpNewsticker/skins/blue.css', __FILE__));
        pbpNewsticker_register_skin('Red', plugins_url('media/pbpNewsticker/skins/red.css', __FILE__));
        pbpNewsticker_register_skin('Yellow', plugins_url('media/pbpNewsticker/skins/yellow.css', __FILE__));
        
    }    

    /** 
     * Perform actions on admin init hook
     * @return void
     */
    public function admin_init() {
        if ( isset($_GET['page']) && $_GET['page'] == 'pbpNewsticker' ) {

            // Saving a news ticker
            if ( isset( $_POST['save_pbpNewsticker'] ) ) {
                pbpNewsticker::save_from_post();
            }
            // Deleting a news ticker
            elseif ( isset( $_POST['delete_pbpNewsticker'] ) ) {
                if ( wp_verify_nonce( $_POST['pbpNewsticker'], 'delete_pbpNewsticker' ) && isset( $_POST['ticker_id'] ) ) {
                    pbpNewsticker::delete_ticker( $_POST['ticker_id'] );
                    wp_redirect( add_query_arg( 'm', 'ticker_deleted', 'options-general.php?page=pbpNewsticker'));
                    exit();
                }
            }
            // Saving news ticker skin
            elseif ( isset( $_POST['save_pbpNewsticker_skin'] ) ) {
                if ( wp_verify_nonce( $_POST['pbpNewsticker'], 'save_pbpNewsticker_skin' ) ) {
                    $settings = self::$settings;
                    $settings['skin'] = $_POST['skin'];
                    update_option( 'pbpNewsticker', $settings );
                    wp_redirect( add_query_arg( 'm', 'skin_updated', 'options-general.php?page=pbpNewsticker'));
                    exit();
                }
            }
        }
    }
    
    /**
     * Enqueue news ticker stylesheet and scripts
     * @return void
     */
    public function enqueue_scripts() {
        wp_register_script('pbpNewsticker', plugins_url('media/pbpNewsticker/newsticker.jquery.js', __FILE__), array('jquery'));
        wp_enqueue_script('pbpNewsticker');     

        if ( isset( self::$settings['skin'] ) && strlen(self::$settings['skin']) && count( self::get_all_tickers() ) ) {
            wp_register_style('pbpNewsticker_css', self::$settings['skin'] );
            wp_enqueue_style('pbpNewsticker_css');
        }
    }    

    /**
     * Enqueue admin-area scripts & stylesheets
     * @param string $hook
     */
    public function admin_enqueue_scripts($hook) {
        if ($hook == 'settings_page_pbpNewsticker') {
            wp_register_style('pbpNewsticker-admin-css', plugins_url('media/admin.css', __FILE__));
            wp_enqueue_style('pbpNewsticker-admin-css');

            wp_register_script('jquery-ui-timepicker-addon', plugins_url('media/timepicker/jquery-ui-timepicker-addon.js', __FILE__ ), array('jquery', 'jquery-ui-datepicker', 'jquery-ui-slider'));
            
            wp_register_script('pbpNewsticker-admin-js', plugins_url('media/admin.js', __FILE__), array('jquery-ui-timepicker-addon'));
            wp_enqueue_script('pbpNewsticker-admin-js');

            wp_register_script('pbpNewsticker', plugins_url('media/pbpNewsticker/newsticker.jquery.js', __FILE__), array('jquery'));
            wp_enqueue_script('pbpNewsticker');     

            wp_register_style('jquery_ui', plugins_url('media/ui-lightness/jquery-ui-1.7.3.custom.css', __FILE__ ) );
            wp_enqueue_style('jquery_ui');

            if ( isset( self::$settings['skin'] ) && strlen(self::$settings['skin']) && count( self::get_all_tickers() ) ) {
                wp_register_style('pbpNewsticker_css', self::$settings['skin'] );
                wp_enqueue_style('pbpNewsticker_css');
            }
        }
    }    


    /**
     * Newsticker shortcode
     * @param array $args
     * @return string
     */
    public function shortcode($args) {
        $defaults = array( 'id' => 0 );
        $args = wp_parse_args( $args, $defaults );

        $pbpNewsticker = new pbpNewsticker($args['id']);
        return $pbpNewsticker->display( false );        
    }    

    /**
     * Add admin menu item under Settings
     * @return void
     */
    public function admin_menu() {
        add_options_page(__('PBP Newsticker', 'pbpNews'), __('PBP Newsticker', 'pbpNews'), 'activate_plugins', 'pbpNewsticker', array(&$this, 'admin_page') );
    }    

    /**
     * Return default formatting for time_ago variables
     * @param string $date_posted
     * @param mixed $data_source
     * @return string
     */
    public function time_ago_format( $date_posted ) {
        $time_posted = strtotime( $date_posted );
        $elapsed = date('U') - $time_posted;

        // Less than an hour
        if ( $elapsed < 3600 ) {
            $minutes = ceil( $elapsed / 60 );
            $timeago = $minutes > 1 ? sprintf( '%d '. __( 'minutes ago', 'pbpNews' ), $minutes ) : __( 'a minute ago', 'pbpNews' );
        }
        // Less than a day
        elseif ( $elapsed < 86400 ) {
            $hours = ceil( $elapsed / 3600 );
            $timeago = $hours > 1 ? sprintf( '%d '. __( 'hours ago', 'pbpNews' ), $hours ) : __( 'an hour ago', 'pbpNews' );
        }
        // Everything else
        else {
            $days = ceil( $elapsed / 86400 );
            $timeago = $days > 1 ? sprintf( '%d '. __( 'days ago', 'pbpNews' ), $days ) : __( 'yesterday', 'pbpNews' );
        }

        return $timeago;
    }

    /** 
     * Create a new data source instance. Called via Ajax.
     * @return void
     */
    public function create_data_source_instance() {
        $newsticker = new pbpNewsticker( $_POST['ticker_id'] );
        $data_source = new $_POST['source'];
        echo $data_source->display_form( $newsticker, $_POST['instance_id'] );
        exit();
    }

    /**
     * The HTML for the pbpNewsticker admin page
     * @return void
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h2><?php _e('PBP Newsticker Option Panel', 'pbpNews') ?></h2>        
            
            <?php $action = isset( $_GET['action'] ) ? $_GET['action'] : 'index' ?>

            <?php if ( $action == 'edit' ) : ?>
            
                <?php $newsticker = new pbpNewsticker( $_GET['ticker_id'] ) ?>
                <?php echo $newsticker->display_form() ?>
            
            <?php elseif ( $action == 'create' ) : ?>
            
                <?php $newsticker = new pbpNewsticker() ?>
                <?php echo $newsticker->display_form() ?>
                                
            <?php else : ?>
                
                <?php if ( isset( $_GET['m'] ) ) : ?>
                    <div class="<?php echo in_array( $_GET['m'], array( 'ticker_update_failed' ) ) ? 'error' : 'updated' ?>">
                        <p>                            
                        <?php switch ( $_GET['m'] ) : 
                            case 'ticker_deleted' :
                                _e( 'News ticker has been deleted.', 'pbpNews' );
                                break;

                            case 'ticker_created' : 
                                _e( 'News ticker has been created', 'pbpNews' );
                                break;

                            case 'skin_updated' :
                                _e( 'Skin has been updated', 'pbpNews' );
                                break;
                        endswitch ?>
                        </p>
                    </div>
                <?php endif ?>

                <?php // Overview of newsticker instances ?>            
                <?php $newstickers = $this->get_all_tickers() ?>    

                <?php if ( count($newstickers) ) : ?>

                    <table class="widefat">
                        <thead>
                            <tr>
                                <th class="manage-column"><?php _e('ID', 'pbpNews') ?></th>
                                <th class="manage-column"><?php _e('Title', 'pbpNews') ?></th>
                                <th class="manage-column"><?php _e('Style', 'pbpNews') ?></th>
                                <th class="manage-column"><?php _e('Data sources', 'pbpNews') ?></th>
                                <th colspan=2></th>
                            </tr>
                        </thead>
                        <tfoot>
                            <tr>
                                <td colspan="6">
                                    <a href="<?php echo admin_url('options-general.php?page=pbpNewsticker&action=create') ?>" class="button-primary"><?php _e( '+ Create Newsticker', 'pbpNews' ) ?></a>
                                </td>
                            </tr>
                        </tfoot>
                        <tbody>
                        <?php foreach ( $newstickers as $newsticker_id => $newsticker ) : ?>
                            <tr>
                                <td><?php echo $newsticker_id ?></td>
                                <td><?php echo isset( $newsticker['internalTitle'] ) && strlen($newsticker['internalTitle']) ? $newsticker['internalTitle'] : sprintf( '<em>%s</em>', __( 'None set', 'pbpNews') ) ?></td>
                                <td><?php echo $newsticker['style'] ?></td>
                                <td>
                                    <?php if ( count($newsticker['data_sources']) ) : ?>
                                    <ul>
                                    <?php foreach ( $newsticker['data_sources'] as $class_name => $settings ) : ?>
                                        <?php if ( isset( $settings['enable'] ) && $settings['enable'] == 'on' ) : ?>
                                            <?php $source = new $class_name() ?>
                                            <li><?php echo $source->get_source_title() ?></li>                                    
                                        <?php endif ?>
                                    <?php endforeach ?>
                                    </ul>
                                    <?php endif ?>                                
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('options-general.php?page=pbpNewsticker&action=edit&ticker_id='.$newsticker_id) ?>" class="button"><?php _e('Edit', 'pbpNews') ?></a>
                                </td>
                                <td>
                                    <form method="post" action="<?php echo admin_url('options-general.php?page=pbpNewsticker&action=delete') ?>">
                                        <?php wp_nonce_field('delete_pbpNewsticker', 'pbpNewsticker') ?>
                                        <input type="hidden" name="ticker_id" value="<?php echo $newsticker_id ?>"  />    
                                        <button class="button" name="delete_pbpNewsticker"><?php _e('Delete', 'pbpNews') ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>

                <?php else : ?>

                <p><?php _e( 'You haven\'t created any Newstickers yet. Get started by creating one.' ) ?></p>
                <p><a href="<?php echo admin_url('options-general.php?page=pbpNewsticker&action=create') ?>" class="button-primary"><?php _e( '+ Create Newsticker', 'pbpNews' ) ?></a></p>

                <?php endif ?>

				<?php $skins = pbpNewsticker_Bootstrap::get_skins() ?>
                <?php $skins[] = __('None', 'pbpNews') ?>
            	<?php $skin = isset( self::$settings['skin'] ) ? self::$settings['skin'] : '' ?>
								
                <section class="general-options">

                    <h3><?php _e( 'General Options', 'pbpNews' ) ?></h3>
					<h4>Please select Skin for showing attractive design of front-end. </h4>
                    <form method="post" action="<?php echo admin_url('options-general.php?page=pbpNewsticker&action=skin_updated') ?>">                        
                        <?php wp_nonce_field('save_pbpNewsticker_skin', 'pbpNewsticker') ?>
                        <label for="pbpNewsticker_skin"><?php _e('Select skin:') ?></label>
                        <select id="pbpNewsticker_skin" name="skin">
                            <?php foreach ($skins as $src => $name) : ?>
                            <option value="<?php echo $src ?>" <?php selected( $skin, $src ) ?>><?php echo $name ?></option>
                            <?php endforeach ?>
                        </select>

                        <footer class="button-container">
                            <button class="button" name="save_pbpNewsticker_skin"><?php _e('Save Skin', 'pbpNews') ?></button>
                        </footer>
                    </form>
					
                </section>

            <?php endif ?>
			
			<span style="color:red; font-size:15px;"><br/> </br>We will publish "PBP Newsticker PRO" with some exclusive feature recently. Please wait for us. Thank you.</span>
        </div>            
        <?php
    }
}

pbpNewsticker_Bootstrap::get_instance();

/**
 * Register a new data source
 * @param string $class_name
 * @return void
 */
function pbpNewsticker_register_data_source($class_name) {
    if ( !class_exists($class_name) ) {
        throw new Exception( sprintf( _e('No class with class name "%s" found', 'pbpNews'), $class_name ) );        
    }
    
    pbpNewsticker_Bootstrap::register_data_source($class_name);
}

/**
 * Register a new skin
 * @param string $name
 * @param string $src
 * @return void
 */
function pbpNewsticker_register_skin($name, $src) {    
    pbpNewsticker_Bootstrap::register_skin($name, $src);
}

/**
 * Display a news ticker
 * @param id $ticker_id     Set to 0 by default
 * @return string
 */
function pbpNewsticker_display($ticker_id = 0) {
    $pbpNewsticker = new pbpNewsticker($ticker_id);
    return $pbpNewsticker->display();
}
