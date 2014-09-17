<?php
/**
 * Retrieves and formats items from RSS feed for use in a pbpNewsticker instance
 * 
 *
 */

class pbpNewsticker_Rss extends pbpNewsticker_Data_Source {

    /**
     * Instantiate object
     * @return void
     */
    public function __construct($pbpNewsticker = null) {
        parent::__construct( $pbpNewsticker );        

        // Include required files
        require_once(ABSPATH . WPINC . '/feed.php');
        require_once(ABSPATH . WPINC . '/class-simplepie.php');

        $this->id = 'pbpNews_source_rss';
        $this->title = __('RSS Feed', 'pbpNews');
        $this->name = 'pbpNewsticker_Rss';
        $this->assist = __('Displays most items from an RSS feed', 'pbpNews');
        $this->allows_extra = true;
        $this->variables = array(
            'author' => array(
                'description' => __('Display name of author', 'pbpNews'),
                'method' => 'get_item_author'
            ),
            'title' => array(
                'description' => __('Display item title', 'pbpNews'),
                'method' => 'get_item_title'
            ),
            'excerpt' => array(
                'description' => __('Display excerpt of item', 'pbpNews'),
                'method' => 'get_item_excerpt'
            ),
            'link' => array(
                'description' => __('Display link to item', 'pbpNews'),
                'method' => 'get_item_url'
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

        $this->set_default_format( sprintf( __( "%s posted %s", 'pbpNews' ), '<a href="%link%">%title%</a>', '%time_ago%' ) );        

        add_filter('wp_feed_cache_transient_lifetime', array(&$this, 'set_feed_cache_lifetime'), 10, 2);
    }
    
    /**
     * Return items for newsticker
     * @return array
     */
    public function get_items() {
        $all_items = array();
        $settings = $this->get_instance_settings();

        foreach ( $settings['instance'] as $instance => $instance_settings ) {
            if ( !isset( $instance_settings['feed_url'] ) || !isset( $instance_settings['formatting'] ) ) {
                return $all_items;
            }        

            // Check for cached items
            $transient_key = 'pbpNewsticker_'.$this->pbpNewsticker->get_ticker_id().'_rss_'.$instance;
            $items = get_transient($transient_key );
            
            // Nothing in the cache, so we get items from feed
            if ( $items === false ) {
                $count = isset( $instance_settings['count']) ? $instance_settings['count'] : 5;

                $rss = fetch_feed( $instance_settings['feed_url'] );

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

        // Feed URL
        $settings[] = array(
            'id' => 'feed_url',
            'title' => __('Feed URL', 'pbpNews'),
            'type' => 'text'
        );

        // Count
        $settings[] = array(
            'id' => 'count',
            'title' => __('Number of feed items to display', 'pbpNews'),
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
            'title' => __('How many minutes should feed results be cached for?', 'pbpNews'),
            'type' => 'number',
            'default' => 5
        );
        
        return $settings;
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
        if ( is_object( $author ) ) {
            return $author->get_name();
        }        
        return '';
    }

    /** 
     * Return item title
     * @param SimplePie_Item $item
     * @return string
     */
    public function get_item_title( SimplePie_Item $item ) {
        return $item->get_title();
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