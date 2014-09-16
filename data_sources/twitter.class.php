<?php
/**
 * Retrieves and formats recent tweets for use in a pbpNewsticker instance
 * 
 */

class pbpNewsticker_Twitter extends pbpNewsticker_Data_Source {

    /**
     * Instantiate object
     * @return void
     */
    public function __construct($pbpNewsticker = null) {
        parent::__construct( $pbpNewsticker );
        $this->id = 'pbpNews_source_twitter';
        $this->title = __('Twitter', 'pbpNews');
        $this->name = 'pbpNewsticker_Twitter';
        $this->assist = __('Displays most recent tweets', 'pbpNews');
        $this->allows_extra = true;
        $this->extra_instance_text = __('Add another Twitter feed', 'pbpNews');
        $this->variables = array(
            'author' => array(
                'description' => __('Display username', 'pbpNews'),
                'method' => 'get_item_author'
            ),
            'excerpt' => array(
                'description' => __('Display excerpt of tweet. Note that links are not preserved in excerpts.', 'pbpNews'),
                'method' => 'get_item_excerpt'
            ),
            'content' => array(
                'description' => __('Display full tweet', 'pbpNews'), 
                'method' => 'get_item_content'
            ),
            'profile_link' => array(
                'description' => __('Display link to Twitter profile', 'pbpNews'),
                'method' => 'get_profile_url'
            ),
            'link' => array(
                'description' => __('Display link to individual tweet', 'pbpNews'),
                'method' => 'get_item_url'
            ),
            'date' => array(
                'description' => __('Display date tweet was made', 'pbpNews'),
                'method' => 'get_item_date'
            ),
            'time_ago' => array(
                'description' => __('Display the time elapsd since the tweet was made', 'pbpNews'),
                'method' => 'get_time_ago'
            )
        );

        $this->set_default_format( sprintf( __( "%s posted on %s", 'pbpNews' ), '%excerpt%', '<a href="%link%">'.__( 'Twitter', 'pbpNews' ).'</a> %time_ago%' ) );

        add_filter('wp_feed_cache_transient_lifetime', array(&$this, 'set_feed_cache_lifetime'), 10, 2);
    }

    /**
     * Filter function executed when settings are saved
     * @param array $settings
     * @param int $ticker_id
     * @return array
     */
    public function pbpNewsticker_ticker_settings($settings, $ticker_id) {
        if ( $settings['data_sources']['pbpNewsticker_Twitter']['enable'] == 'on' ) {
            foreach ( $settings['data_sources']['pbpNewsticker_Twitter']['instance'] as $instance => $instance_settings ) {
                $instance_settings['feed_url'] = self::get_feed_url($instance_settings);
                $settings['data_sources']['pbpNewsticker_Twitter']['instance'][$instance] = $instance_settings;
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
        $include_retweets = isset( $instance_settings['include_retweets'] ) 
                            ? $instance_settings['include_retweets'] == 'on' ? 1 : 0
                            : 0;

        $exclude_replies = isset( $instance_settings['include_replies'] ) 
                            ? $instance_settings['include_replies'] == 'on' ? 0 : 1
                            : 1;

        $count = isset( $instance_settings['count']) ? $instance_settings['count'] : 5;

        $parameters = array();        
        $parameters[] = 'screen_name='.$instance_settings['username'];
        $parameters[] = 'count='.$count;
        $parameters[] = 'include_entities=1';
        $parameters[] = 'include_rts='.$include_retweets;
        $parameters[] = 'exclude_replies='.$exclude_replies;

        $url = "http://api.twitter.com/1/statuses/user_timeline.json?";
        $url .= implode( '&', $parameters );
        return $url;
    }    

    /**
     * Return items for newsticker
     * @return array
     */
    public function get_items() {
        $all_items = array();
        $settings = $this->get_instance_settings();

        foreach ( $settings['instance'] as $instance => $instance_settings ) {
        
            if ( !isset( $instance_settings['username'] ) ) {
                return $all_items;
            }                   

            // Check for cached tweets
            $transient_key = 'pbpNewsticker_'.$this->pbpNewsticker->get_ticker_id().'_twitter_'.$instance;
            $tweets = get_transient($transient_key );
            $tweets = false;
            
            // Nothing in the cache, so we get tweets from Twitter
            if ( $tweets === false ) {
                $url = array_key_exists( 'feed_url', $instance_settings ) ? $instance_settings['feed_url'] : self::get_feed_url($instance_settings['feed_url']);                
                $response = wp_remote_get( $url );

                if ( $response instanceof WP_Error ) {
                    return $all_items;
                }

                $tweets = json_decode( $response['body'], true );

                // When rate limit is exceeded, the first array element is a string
                if ( !is_array( $tweets['0'] ) ) {
                    return $all_items;
                }

                $cache_time = isset( $instance_settings['cache'] ) ? $instance_settings['cache'] : 5;

                set_transient( $transient_key, $tweets, 60 * $cache_time );
            }
            
            $all_items = array_merge( $this->get_item_array( $tweets, $instance ), $all_items );            
        }

        return $all_items;
    }
    
    /**
     * Configure specific settings for this data source
     * @return array
     */
    public function get_settings() {
        $settings = array();

        // Twitter username
        $settings[] = array(
            'id' => 'username',
            'title' => __('Twitter username', 'pbpNews'),
            'type' => 'text'
        );
        
        // Include retweets
        $settings[] = array(
            'id' => 'include_retweets',
            'title' => __('Include retweets?', 'pbpNews'),
            'type' => 'checkbox',
            'default' => 'on'
        );

        // Include replies
        $settings[] = array(
            'id' => 'include_replies',
            'title' => __('Include replies?', 'pbpNews'),
            'type' => 'checkbox',
            'default' => false
        );

        // Count
        $settings[] = array(
            'id' => 'count',
            'title' => __('Number of tweets to display', 'pbpNews'),
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
            'title' => __('How many minutes should tweets be cached for?', 'pbpNews'),
            'type' => 'number',
            'default' => 5
        );
        
        return $settings;
    }    

    /** 
     * Return the parsed Twitter link     
     * @return string
     */
    protected function parse_twitter_link( $entity ) {
        $settings = $this->get_instance_settings();        
        $target = apply_filters( 'pbpNewsticker_open_links_in_new_window', false ) === true ? 'target="_blank"' : '';        

        switch ( $entity['type'] ) {
            case 'urls': 
                return '<a href="' . $entity['url'] . '" ' . $target . '>' . $entity['display_url'] . '</a>';
                break;
            case 'user_mentions':
                return '<a href="https://twitter.com/#!/' . $entity['screen_name'] . '" ' . $target . '>@' . $entity['screen_name'] . '</a>';
                break;
            case 'hashtags':
                return '<a href="https://twitter.com/#!/search/' . $entity['text'] . '" ' . $target . '>#' . $entity['text'] . '</a>';                
                break; 
        }
    }
    /**
     * Return tweet's post date as timestamp
     * @param stdObject $tweet
     * @return int
     */
    public function get_item_timestamp( $tweet ) {
        return strtotime( $tweet['created_at'] );
    }
    
    /**
     * Return commenter's username
     * @param array $tweet
     * @return string
     */
    public function get_item_author( $tweet ) {
        return $tweet['user']['screen_name'];
    }

    /**
     * Return tweet excerpt
     * @param stdOject $tweet
     * @return string
     */
    public function get_item_excerpt( $tweet ) {
        $excerpt_length = isset( $this->settings['excerpt_length'] ) ? $this->settings['excerpt_length'] : 12; 
        return $this->get_trimmed_string( $tweet['text'], $excerpt_length );
    }
    
    /**
     * Return full tweet
     * @param stdOject $tweet
     * @return string
     */
    public function get_item_content( $tweet ) {
        $tweet_text = $tweet['text'];
        $entity_array = array();
        foreach ( $tweet['entities'] as $type => $entities ) {
            if ( count( $entities ) ) {
                foreach ( $entities as $entity ) {
                    $entity['type'] = $type;
                    $entity_array[] = $entity;
                }
            } 
        }

        usort( $entity_array, array( &$this, 'sort_tweet_entities' ));
        
        foreach ( $entity_array as $entity ) {        
            $url = $this->parse_twitter_link( $entity );
            $before = substr( $tweet_text, 0, $entity['indices'][0] );
            $after = substr( $tweet_text, $entity['indices'][1] );
            $tweet_text = $before . $url . $after; 
        }
    
        return $tweet_text;
    }

    /** 
     * Sort tweet entities
     */
    public function sort_tweet_entities( $a, $b ) {
        $a = $a['indices'][0];
        $b = $b['indices'][0];

        if ($a == $b) {
            return 0;
        }

        return ($a > $b) ? -1 : 1;
    }

    /**
     * Return link to tweet
     * @param stdOject $tweet
     * @return string
     */
    public function get_item_url( $tweet ) {
        return 'https://twitter.com/#!/' . $tweet['user']['screen_name'] . '/statuses/'. $tweet['id_str'];
    }        

    /**
     * Return link to Twitter profile
     * @param stdOject $tweet
     * @return string
     */
    public function get_profile_url( $tweet ) {        
        return 'https://twitter.com/#!/' . $tweet['user']['screen_name'];
    }        
    
    /**
     * Return tweet date
     * @param stdObject $tweet
     * @return string
     */
    public function get_item_date( $tweet ) {
        $format = apply_filters( 'pbpNewsticker_item_date_format', 'M d' );
        return date( $format, $this->get_item_timestamp( $tweet ) );
    }

    /**
     * Return time since tweet was made
     * @param stdObject $tweet
     * @return string
     */
    public function get_time_ago( $tweet ) {        
        return apply_filters( 'pbpNewsticker_item_time_ago_format', $tweet['created_at'], $this );
    }
}