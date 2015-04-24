<?php
/**
 * Retrieves and formats recent posts for use in a pbpNewsticker instance
 * 
 * 
 */

class pbpNewsticker_Recent_Posts extends pbpNewsticker_Data_Source {

    /**
     * Instantiate object
     * @return void
     */
    public function __construct($pbpNewsticker = null) {
        parent::__construct( $pbpNewsticker );
        $this->id = 'pbpNews_source_recent_posts';
        $this->title = __('Recent Posts', 'pbpNews');
        $this->name = 'pbpNewsticker_Recent_Posts';
        $this->assist = __('Displays the most recent posts', 'pbpNews');
        $this->variables = array(
            'title' => array(
                'description' => __('Display post title', 'pbpNews'),
                'method' => 'get_item_title'
            ),
            'excerpt' => array(
                'description' => __('Display post excerpt', 'pbpNews'),
                'method' => 'get_item_excerpt'
            ),
            'author' => array(
                'description' => __('Display post author', 'pbpNews'),
                'method' => 'get_item_author'
            ),
            'link' => array(
                'description' => __('Display post URL', 'pbpNews'),
                'method' => 'get_item_url'
            ),
            'date' => array(
                'description' => __('Display post date', 'pbpNews'),
                'method' => 'get_item_date'
            ),
            'time_ago' => array(
                'description' => __('Display the time elapsed since the post was created', 'pbpNews' ),
                'method' => 'get_time_ago'
            )
        );
        $this->settings = $this->get_instance_settings();
        $this->set_default_format( sprintf( __( "%s posted %s", 'pbpNews' ), '<a href="%link%">%title%</a>', '%time_ago%' ) );        
    }
    
    /**
     * Return items for newsticker
     * @return array
     */
    public function get_items() {        
        
        $args = array(
            'post_type' => 'post', 
            'posts_per_page' => isset($this->settings['count']) ? $this->settings['count'] : 5
        );

        // Taxonomies for posts
        $taxonomies = get_object_taxonomies( 'post', 'objects' );
        $tax_query = array();
        foreach ( $taxonomies as $taxonomy => $details ) {
            if ( isset( $this->settings['taxonomy_'.$taxonomy] ) ) {
                $terms = $this->settings['taxonomy_'.$taxonomy];
                $tax_query[] = array(
                    'taxonomy' => $taxonomy,
                    'field' => 'id',
                    'terms' => $terms,
                    'operator' => 'IN'
                );
            }            
        }

        $args['tax_query'] = $tax_query;

        $items = new WP_Query( $args );        
        
        return $this->get_item_array( $items->posts );
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
            'title' => __('Number of posts to display', 'pbpNews'),
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
        
        // Taxonomies for posts
        $taxonomies = get_object_taxonomies( 'post', 'objects' );
        foreach ( $taxonomies as $taxonomy => $details ) {

            $options = array();
            foreach ( get_terms( $taxonomy ) as $term ) {
                $options[$term->term_id] = $term->name;
            }

            if ( count( $options ) ) {
                $settings[] = array(
                    'id' => 'taxonomy_'.$taxonomy,
                    'title' => $details->labels->name, 
                    'type' => 'multi_checkbox', 
                    'options' => $options
                );
            }
        }        
        
        return $settings;
    }    
    
    /**
     * Return item's post date as timestamp
     * @param stdObject $item
     * @return int
     */
    public function get_item_timestamp( $item ) {
	    return strtotime( $item->post_date );
    }
    
    /**
     * Return post title 
     * @param stdOject $post
     * @return string
     */
    public function get_item_title( $post ) {
        return $post->post_title;
    }

    /** 
     * Return post excerpt
     * @param stdObject $post
     * @return string
     */
    public function get_item_excerpt( $post ) {
        $excerpt = strlen( $post->post_excerpt ) ? $post->post_excerpt : $post->post_content;
        $excerpt_length = isset( $this->settings['excerpt_length'] ) ? $this->settings['excerpt_length'] : 12; 
        return $this->get_trimmed_string( $excerpt, $excerpt_length );
    }
    
    /**
     * Return post title 
     * @param stdOject $post
     * @return string
     */
    public function get_item_author( $post ) {
        $author = new WP_User( $post->post_author );
        return $author->display_name;
    }
    
    /**
     * Return post permalink
     * @param stdOject $post
     * @return string
     */
    public function get_item_url( $post ) {
        return get_permalink( $post->ID );
    }        
    
    /**
     * Return post date
     * @param stdObject $post
     * @return string
     */
    public function get_item_date( $post ) {
        $format = apply_filters( 'pbpNewsticker_item_date_format', 'M d' );
        return date( $format, strtotime( $post->post_date ) );
    }

    /**
     * Return time since post was created
     * @param stdObject $post
     * @return string
     */
    public function get_time_ago( $post ) {        
        return apply_filters( 'pbpNewsticker_item_time_ago_format', $post->post_date, $this );
    }    
}