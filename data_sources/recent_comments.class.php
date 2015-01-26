<?php
/**
 * Retrieves and formats recent comments for use in a pbpNewsticker instance
 * 
 * 
 */

class pbpNewsticker_Recent_Comments extends pbpNewsticker_Data_Source {

    /**
     * Stores an array of posts that have been commented on
     * @var array
     */
    protected $posts = array();

    /**
     * Instantiate object
     * @return void
     */
    public function __construct($pbpNewsticker = null) {
        parent::__construct( $pbpNewsticker );
        $this->id = 'pbpNews_source_recent_comments';
        $this->title = __('Recent Comments', 'pbpNews');
        $this->name = 'pbpNewsticker_Recent_Comments';
        $this->assist = __('Displays the most recent comments', 'pbpNews');
        $this->variables = array(
            'author' => array(
                'description' => __('Display comment author', 'pbpNews'),
                'method' => 'get_item_author'
            ),
            'excerpt' => array(
                'description' => __('Display excerpt of comment', 'pbpNews'),
                'method' => 'get_item_excerpt',
            ),
            'post_title' => array(
                'description' => __('Display post title', 'pbpNews'),
                'method' => 'get_item_post_title'
            ),            
            'comment_link' => array(
                'description' => __('Display post URL', 'pbpNews'),
                'method' => 'get_item_url'
            ),
            'post_link' => array(
                'description' => __('Display post URL', 'pbpNews'),
                'method' => 'get_item_post_url'
            ),
            'comment_date' => array(
                'description' => __('Display date comment was made', 'pbpNews'),
                'method' => 'get_item_date'
            ),
            'time_ago' => array(
                'description' => __('Display the time since the comment was made', 'pbpNews'),
                'method' => 'get_time_ago'
            )
        );
        $this->set_default_format( sprintf( __( "%s commented on %s", 'pbpNews' ), '%author%', '<a href="%post_link%">%post_title%</a> %time_ago%' ) );
        $this->settings = $this->get_instance_settings();
    }
    
    /**
     * Return items for newsticker
     * @return array
     */
    public function get_items() {        
        $args = array(
            'status' => 'approve',
            'number' => isset( $this->settings['count']) ? $this->settings['count'] : 5
        );

        // Unless trackbacks/pingbacks are included, we only show regular comments
        if ( !isset( $this->settings['include_trackbacks'] ) || $this->settings['include_trackbacks'] != 'on' ) {
            $args['type'] = 'comment';
        }        

        $comments = get_comments( $args );        
        return $this->get_item_array( $comments );        
    }
    
    /**
     * Configure specific settings for this data source
     * @return array
     */
    public function get_settings() {
        $settings = array();
        
        // Count
        $settings[] = array(
            'id' => 'count',
            'title' => __('Number of comments to display', 'pbpNews'),
            'type' => 'number',
            'default' => 5
        );
        
        // Include trackbacks
        $settings[] = array(
            'id' => 'include_trackbacks',
            'title' => __('Include trackpacks and pingbacks', 'pbpNews'),
            'type' => 'checkbox',
            'default' => false
        );

        // Excerpt length
        $settings[] = array(
            'id' => 'excerpt_length',
            'title' => __('Length of excerpt (words)', 'pbpNews'),
            'type' => 'number',
            'default' => 12
        );

        return $settings;
    }    
    
    /**
     * Return item's post date as timestamp
     * @param stdObject $item
     * @return int
     */
    public function get_item_timestamp( $comment ) {
	    return strtotime( $comment->comment_date );
    }
    
    /**
     * Return post title 
     * @param stdOject $comment
     * @return string
     */
    public function get_item_post_title( $comment ) {
        return get_the_title( $comment->comment_post_ID );  
    }    
    
    /**
     * Return commenter's name
     * @param stdOject $comment
     * @return string
     */
    public function get_item_author( $comment ) {
        return $comment->comment_author;
    }

    /**
     * Return comment excerpt
     * @param stdOject $comment
     * @return string
     */
    public function get_item_excerpt( $comment ) {
        $excerpt_length = isset( $this->settings['excerpt_length'] ) ? $this->settings['excerpt_length'] : 12; 
        return $this->get_trimmed_string( $comment->comment_content, $excerpt_length );
    }
    
    /**
     * Return comment permalink
     * @param stdOject $comment
     * @return string
     */
    public function get_item_url( $comment ) {
        return get_permalink( $comment->comment_post_ID ) . '#comment-'. $comment->comment_ID;
    }        

    /**
     * Return post URL
     * @param stdOject $comment
     * @return string
     */
    public function get_item_post_url( $comment ) {        
        return get_permalink( $comment->comment_post_ID );
    }        
    
    /**
     * Return comment date
     * @param stdObject $comment
     * @return string
     */
    public function get_item_date( $comment ) {
        $format = apply_filters( 'pbpNewsticker_item_date_format', 'M d' );
        return date( $format, $this->get_item_timestamp( $comment ) );
    }

    /**
     * Return time since comment was made
     * @param stdObject $comment
     * @return string
     */
    public function get_time_ago( $comment ) {        
        return apply_filters( 'pbpNewsticker_item_time_ago_format', $comment->comment_date, $this );
    }
}