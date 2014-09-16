<?php
/**
 * Retrieves and formats items from Facebook page feed for use in a pbpNewsticker instance
 * 

 */

class pbpNewsticker_Facebook extends pbpNewsticker_Rss {

    /**
     * Instantiate object
     * @return void
     */
    public function __construct($pbpNewsticker = null) {
        parent::__construct( $pbpNewsticker );        
        $this->id = 'pbpNews_source_facebook';
        $this->title = __('Facebook Page', 'pbpNews');
        $this->name = 'pbpNewsticker_Facebook';
        $this->assist = __('Displays most items from a Facebook page', 'pbpNews');
        $this->allows_extra = true;
        $this->variables = array(
            'author' => array(
                'description' => __('Display name of author', 'pbpNews'),
                'method' => 'get_item_author'
            ),
            'excerpt' => array(
                'description' => __('Display excerpt of item', 'pbpNews'),
                'method' => 'get_item_excerpt'
            ),
            'link' => array(
                'description' => __('Display link to item', 'pbpNews'),
                'method' => 'get_item_url'
            ),
            'profile_link' => array(
                'description' => __('Display link to Facebook page', 'pbpNews'),
                'method' => 'get_profile_url'
            ),
            'date' => array(
                'description' => __('Display date item was posted', 'pbpNews'),
                'method' => 'get_item_date'
            ),
            'time_ago' => array(
                'description' => __('Display the time elapsd since the item was posted', 'pbpNews'),
                'method' => 'get_time_ago'
            )
        );

        $this->set_default_format( sprintf( __( "%s posted on %s", 'pbpNews' ), '%excerpt%', '<a href="%link%">'.__( 'Facebook', 'pbpNews' ).'</a> %time_ago%' ) );
    }

    /**
     * Filter function executed when settings are saved
     * @param array $settings
     * @param int $ticker_id
     * @return array
     */
    public function pbpNewsticker_ticker_settings($settings, $ticker_id) {
        if ( $settings['data_sources']['pbpNewsticker_Facebook']['enable'] == 'on' ) {
            foreach ( $settings['data_sources']['pbpNewsticker_Facebook']['instance'] as $instance => $instance_settings ) {

                // Get the Facebook profile ID if it's not set
                if ( !isset( $instance_settings['profile'] ) ) {

                    $profile = self::get_profile( $instance_settings['profile_name'] );
                    if ( $profile === false ) {
                        return $settings;
                    }

                    $instance_settings['profile'] = $profile;
                    $instance_settings['feed_url'] = self::get_feed_url( $instance_settings );
                }

                $settings['data_sources']['pbpNewsticker_Facebook']['instance'][$instance] = $instance_settings;
            }
        }
        
        return $settings;
    }    
    
    /**
     * Get feed url 
     * @param array $instance_settings
     * @return string
     */
    public static function get_feed_url($instance_settings) {
        return 'https://www.facebook.com/feeds/page.php?id=' . $instance_settings['profile']->id . '&format=rss20';;
    }  

    /**
     * Return items for newsticker
     * @return array
     */
    public function get_items() {
        $all_items = array();
        $settings = $this->get_instance_settings();

        foreach ( $settings['instance'] as $instance => $instance_settings ) {
            if ( !isset( $instance_settings['profile_name'] ) ) {
                return $all_items;
            }

            if ( !isset( $instance_settings['profile'] ) ) {
                $instance_settings['profile'] = self::get_profile( $instance_settings['profile_name'] );

                // Check for errors in retrieving Facebook profile
                if ( $instance_settings['profile'] === false ) {
                    return $all_items;
                }
            }

            // Check for cached items
            $transient_key = 'pbpNewsticker_'.$this->pbpNewsticker->get_ticker_id().'_facebook_'.$instance;
            $items = get_transient($transient_key );
            
            // Nothing in the cache, so we get items from feed
            if ( $items === false ) {                
                $count = isset( $instance_settings['count']) ? $instance_settings['count'] : 5;                        
                $url = array_key_exists( 'feed_url', $instance_settings ) ? $instance_settings['feed_url'] : self::get_feed_url($instance_settings['feed_url']);
                $rss = fetch_feed( $url );

                // Check that the object is created correctly 
                if ( is_wp_error( $rss ) ) { 
                    return $all_items;
                }

                // Figure out how many total items there are, but limit it to 5. 
                $max_items = $rss->get_item_quantity( $count ); 

                // Build an array of all the items, starting with element 0 (first element).
                $items = $rss->get_items( 0, $max_items ); 
                
                $cache_time = isset( $instance_settings['cache'] ) ? $instance_settings['cache'] : 5;
                set_transient( $transient_key, $items, 60 * $cache_time );
            }

            $all_items = array_merge( $this->get_item_array( $items, $instance ), $all_items );
        }

        return $all_items;
    }
    
    /**
     * Configure specific settings for this data source
     * @return array
     */
    public function get_settings() {
        $settings = array();

        // Facebook profile ID
        $settings[] = array(
            'id' => 'profile_name',
            'title' => __('Facebook username', 'pbpNews'),
            'type' => 'text'
        );

        // Count
        $settings[] = array(
            'id' => 'count',
            'title' => __('Number of items to display', 'pbpNews'),
            'type' => 'number',
            'default' => 5
        );

        // Excerpt length
        $settings[] = array(
            'id' => 'excerpt_length',
            'title' => __('Length of excerpt (words)', 'pbpNews'),
            'type' => 'number',
            'default' => 12
        );

        // Cache
        $settings[] = array(
            'id' => 'cache',
            'title' => __('How many minutes should results be cached for?', 'pbpNews'),
            'type' => 'number',
            'default' => 5
        );
        
        return $settings;
    }    

    /** 
     * Return Facebook ID based on page/person username
     * @param string $username
     * @return false|stdObject
     */
    protected static function get_profile( $username ) {
        $result = wp_remote_get( "https://graph.facebook.com/$username" );

        if ( $result instanceof WP_Error ) {
            return false;
        }

        return json_decode( $result['body'] );
    }

    /**
     * Return item's post date as timestamp
     * @param SimplePie_Item $item
     * @return int
     */
    public function get_item_timestamp( $item ) {
        return $item->get_date('U');
    }
    
    /**
     * Return item's author
     * @param SimplePie_Item $item
     * @return string
     */
    public function get_item_author( SimplePie_Item $item ) {
        $author = $item->get_author();
        return $author->get_name();
    }

    /**
     * Return excerpt from item
     * @param SimplePie_Item $item
     * @return string
     */
    public function get_item_excerpt( SimplePie_Item $item ) {
        $excerpt_length = isset( $this->settings['excerpt_length'] ) ? $this->settings['excerpt_length'] : 12; 
        return $this->get_trimmed_string( $item->get_description(), $excerpt_length );
    }

    /**
     * Return link to item
     * @param SimplePie_Item $item
     * @return string
     */
    public function get_item_url( SimplePie_Item $item ) {
        return $item->get_permalink();
    }  

    /** 
     * Return link to profile
     * @param 
     * @return string
     */
    public function get_profile_url( SimplePie_Item $item ) {
        $settings = $this->get_instance_settings();
        return $settings['instance'][$item->pbpNews_instance]['profile']->link;
    }
    
    /**
     * Return item's post date
     * @param SimplePie_Item $item
     * @return string
     */
    public function get_item_date( SimplePie_Item $item ) {
        $format = apply_filters( 'pbpNewsticker_item_date_format', 'M d' );
        return $item->get_date( $format );
    }

    /**
     * Return time since item was posted
     * @param SimplePie_Item $item
     * @return string
     */
    public function get_time_ago( SimplePie_Item $item ) {        
        return apply_filters( 'pbpNewsticker_item_time_ago_format', $item->get_date(), $this );
    }
}