<?php

/**
 * Base data source class
 * 
 * 
 */

abstract class pbpNewsticker_Data_Source {
    
    /**
     * HTML id of data source
     * @var string
     */
    protected $id;
    
    /**
     * Title of data source
     * @var string
     */
    protected $title;
    
    /**
     * HTML form name of data source
     * @var string
     */
    protected $name;

    /** 
     * Whether the data source type can be used multiple times 
     * @var bool
     */
    protected $allows_extra = false;
    
    /** 
     * Text of button to create extra instance
     * @var string|empty
     */
    protected $extra_instance_text;

    /**
     * Instance of data source for newsticker. Used only by data sources that can be added multiple times
     * @var int
     */
    protected $instance_count = -1;

    /**
     * Assist text to explain what data source will display
     * @var string
     */
    protected $assist;
    
    /**
     * An array of variables that can be used for custom formatting
     * @var array
     */ 
    protected $variables = array();
    
    /**
     * pbpNewsticker instance that the data source is being called on
     * @var pbpNewsticker|null
     */
    protected $pbpNewsticker = null;

    /**
     * Default format
     * @var string
     */
    protected $default_format = '';

    /**
     * Instance settings
     * @var array
     */
    protected $settings;

    /**
     * Create object instance
     * @return void
     */
    public function __construct($pbpNewsticker = null) {        
        $this->pbpNewsticker = $pbpNewsticker;        
    }

    /**
     * Set feed cache lifetime
     * @param int $seconds
     * @param string $url
     * @return int
     */
    public function set_feed_cache_lifetime($seconds, $url) {
        $settings = $this->get_instance_settings();
        foreach ( $settings['instance'] as $instance => $instance_settings ) {
            if ( isset( $instance_settings['feed_url'] ) && $url == $instance_settings['feed_url'] ) {
                return 60 * ( isset( $instance_settings['cache'] ) ? $instance_settings['cache'] : 5 );
            }
        }
        return $seconds;
    }

    /**
     * Executed when a news ticker is saved
     * @param int|false $id
     * @param array $settings
     * @return void
     */
    public function save_ticker_settings( $id, $settings ) {
        // Make sure this is an update
        if ( is_numeric( $id ) ) {

            // Cached data sources
            $cached = array(
                'pbpNewsticker_'.$id.'_rss' => 'pbpNewsticker_Rss', 
                'pbpNewsticker_'.$id.'_facebook' => 'pbpNewsticker_Facebook', 
                'pbpNewsticker_'.$id.'_twitter' => 'pbpNewsticker_Twitter'
            );

            $data_sources = $settings['data_sources'];

            foreach ( $cached as $transient_key => $name ) {
                if ( $data_sources[$name]['enable'] == 'on' ) {
                    foreach ( $data_sources[$name]['instance'] as $instance => $instance_settings ) {
                        delete_transient( $transient_key.'_'.$instance );
                    }                    
                }
            }  
        } 
    }    
    
    /**
     * Return HTML id of data source
     * @return string
     */
    public function get_source_id() {
        return $this->id;
    }
    
    /**
     * Return title of data source
     * @return string
     */
    public function get_source_title() {
        return $this->title;
    }
    
    /**
     * Return HTML form name of data source
     * @return string
     */
    public function get_source_name() {
        return $this->name;
    }
    
    /**
     * Return assist text for data source
     * @return string
     */
    public function get_source_assist() {
        return $this->assist;
    }

    /**
     * Return whether source allows multiple cases per newsticker
     * @return bool
     */
    public function allows_extra() {
        return $this->allows_extra;
    }

    /** 
     * Return text for button displaying call to add extra instance (if multiple instances allowed)
     * @return string
     */
    public function get_extra_instance_text() {
        if ( strlen( $this->extra_instance_text ) ) {
            return $this->extra_instance_text;
        }
        return sprintf( __( 'Add another %s' ), $this->get_source_title() ); 
    }

    /**
     * Return settings for current instance
     * @return array
     */
    public function get_instance_settings() {
        if (is_null($this->pbpNewsticker)) {
            return;
        }
        
        $data_sources = $this->pbpNewsticker->get_ticker_data_sources();
        
        if (!isset($data_sources[$this->name])) {
            return;
        }
        
        return $data_sources[$this->name];
    }

    /**
     * Set default format
     * @param string $format
     * @return void
     */
    public function set_default_format( $format ) {
        $this->default_format = htmlspecialchars( $format );
    }

    /**
     * Display configuration form
     * @return void
     */
    public function display_form( pbpNewsticker $pbpNewsticker, $instance_count = '' ) {
        $this->instance_count = strlen( $instance_count ) ? $instance_count : $this->instance_count + 1;
        ?>

        <div class="<?php if ( $instance_count == 0 ) echo 'hidden' ?> pbpNews_data_source_settings" data-instance="<?php echo $this->instance_count ?>"> 
            <div class="title_wrap">
                <h4><?php echo $this->get_source_title() ?></h4>
                <?php if ( $this->instance_count > 0 ) : ?>
                    <a href="#" class="remove-instance"><?php _e( 'Remove' ) ?></a>
                <?php endif ?>
            </div>    
            <?php foreach ($this->get_settings() as $setting) : ?>
                <div>
                    <?php echo $this->display_setting( $setting, $pbpNewsticker ) ?>
                </div>
            <?php endforeach ?>
            
        <?php if ($this->has_custom_formatting()) : ?>
            <div>
                <?php echo $this->display_custom_formatting( $pbpNewsticker ) ?>
            </div>                
        <?php endif ?>
        </div>

        <?php
    }

    /** 
     * Get form field name for setting
     * @param array $setting
     * @return string
     */
    public function get_setting_name( array $setting) {
        return $this->allows_extra === true 
            ? 'data_sources['.$this->name.'][instance]['.$this->instance_count.']['.$setting['id'].']' 
            : 'data_sources['.$this->name.']['.$setting['id'].']';
    }

    /** 
     * Get form field ID for setting
     * @param array $setting
     * @return string
     */
    public function get_setting_id( array $setting ) { 
        return $this->allows_extra === true     
            ? $this->id.'_'.$setting['id'].'_'.$this->instance_count 
            : $this->id.'_'.$setting['id'];
    }    

    /**
     * Display setting
     * @param array $setting
     * @param pbpNewsticker $pbpNewsticker
     * @return string
     */
    public function display_setting(array $setting, pbpNewsticker $pbpNewsticker) {
        if ( !isset($setting['type']) || !isset($setting['id']) ) {
            return;
        }
        
        switch ($setting['type']) {
            case 'text': 
                return $this->get_text_field_html($setting, $pbpNewsticker);
                break;
            
            case 'number':                
                return $this->get_text_field_html($setting, $pbpNewsticker, 'number');
                break;
             
            case 'multi_checkbox':
                return $this->get_multi_checkbox_field_html($setting, $pbpNewsticker);
                break;

            case 'checkbox':
                return $this->get_checkbox_field_html($setting, $pbpNewsticker);
                break;
        }        
    }
    
    /**
     * Return the HTML for a text field
     * @param array $setting
     * @param pbpNewsticker $pbpNewsticker
     * @param string $type
     * @return string
     */
    public function get_text_field_html(array $setting, pbpNewsticker $pbpNewsticker, $type = 'text') {
        $html = '';
        if ( isset($setting['title']) ) {
            $html .= '<label for="'.$this->id.'_'.$setting['id'].'">'.$setting['title'].'</label>';
        }                    

        $class = array_key_exists( 'class', $setting ) ? $setting['class'] : '';
        $value = $pbpNewsticker->get_source_value(
            $setting['id'], 
            $this->name, 
            array(
                'default' => isset( $setting['default'] ) ? $setting['default'] : '',  
                'escape_html' => true,
                'instance_count' => $this->allows_extra ? $this->instance_count : false
            ) );
        
        $html .= '<input type="'.$type.'" 
                         value="'.$value.'" 
                         name="'.$this->get_setting_name( $setting ).'" 
                         id="'.$this->get_setting_id( $setting ).'"
                         class="'.$class.'" />';

        if ( isset($setting['assist']) ) {
            $html .= '<span class="pbpNews_assist">'.$setting['assist'].'</span>';
        }
        
        return $html;
    }
    
    /**
     * Return the HTML for a multi-select list of checkboxes
     * @param array $setting
     * @param pbpNewsticker $pbpNewsticker
     * @return string
     */
    public function get_multi_checkbox_field_html(array $setting, pbpNewsticker $pbpNewsticker) {
        $html = '';
        if ( isset($setting['title']) ) {
            $html .= '<label for="'.$this->id.'_'.$setting['id'].'">'.$setting['title'].'</label>';
        }                    

        $defaults = isset( $setting['default'] ) ? $setting['default'] : array();

        $source_value = $pbpNewsticker->get_source_value(
                $setting['id'], 
                $this->name, 
                array( 
                    'default' => $defaults, 
                    'escape_html' => false,
                    'instance_count' => $this->allows_extra ? $this->instance_count : false,
                    'is_array' => true
                ) );

        $html .= '<ul class="checkbox_list" id="'.$this->id.'_'.$setting['id'].'">';
        
        foreach ( $setting['options'] as $value => $label ) {            
                    
            $html .= '<li><input type="checkbox" 
                                 value="'.$value.'" 
                                 name="'.$this->get_setting_name( $setting ).'[]" 
                                 '. checked( in_array( $value, $source_value ), true, false ) . ' /> '.$label.'</li>';            
        }        

        if ( isset($setting['assist']) ) {
            $html .= '<span class="pbpNews_assist">'.$setting['assist'].'</span>';
        }
        
        return $html;
    }    

    /**
     * Return the HTML for a checkbox field
     * @param array $setting
     * @param pbpNewsticker $pbpNewsticker
     * @return string
     */
    public function get_checkbox_field_html(array $setting, pbpNewsticker $pbpNewsticker) {
        $html = '';
        if ( isset($setting['title']) ) {
            $html .= '<label for="'.$this->id.'_'.$setting['id'].'">'.$setting['title'].'</label>';
        }         

        $value = $pbpNewsticker->get_source_value(
            $setting['id'], 
            $this->name, 
            array(
                'default' => isset( $setting['default'] ) ? $setting['default'] : '', 
                'instance_count' => $this->allows_extra ? $this->instance_count : false,
                'is_checkbox' => true
            ) );
        
        $html .= '<input type="checkbox" 
                 name="'.$this->get_setting_name( $setting ).'" 
                 id="'.$this->get_setting_id( $setting ).'"
                 '.checked( $value, 'on', false ).' />';

        if ( isset($setting['assist']) ) {
            $html .= '<span class="pbpNews_assist">'.$setting['assist'].'</span>';
        }
        
        return $html;
    }    

    /**
     * Returns whether the data source has custom formatting
     * @return bool
     */
    public function has_custom_formatting() {
        return count( $this->variables ) > 0 ? true : false;
    }
    
    /**
     * Returns the HTML for the formatting field
     * @param pbpNewsticker $pbpNewsticker
     * @return string
     */ 
    public function display_custom_formatting( pbpNewsticker $pbpNewsticker ) {
        
        foreach ( $this->variables as $variable => $details ) {
            $string = "<li><strong>%$variable%</strong>";
            if ( isset( $details['description'] ) ) {
                $string .= ': '.$details['description'];
            }
            $string .= "</li>";
            $variables[] = $string;
        }

        $value = $pbpNewsticker->get_source_value( 
            'formatting', 
            $this->name, 
            array(
                'default' => $this->default_format,
                'escape_html' => true, 
                'instance_count' => $this->allows_extra ? $this->instance_count : false 
            ) );

        $html = '<label for="'.$this->id.'_formatting">'.__( 'Formatting', 'pbpNews' ).'</label>';
        $html .= '<input type="text" 
                         class="medium"
                         value="'.$value.'"
                         name="'.$this->get_setting_name( array( 'id' => 'formatting' ) ).'"
                         id="'.$this->get_setting_name( array( 'id' => 'formatting' ) ).'" />';
        $html .= '<span class="pbpNews_assist">'.sprintf( __('Available variables: %s', 'pbpNews'), '<ul>'. implode( ' ', $variables ) . '</ul>' ).'</span>';
        return $html;
    }

    /** 
     * Return array of items with timestamps added in
     * @param array $items
     * @param string $formatting
     * @param int $instance
     * @return array
     */
    public function get_item_array( $items, $instance = -1 ) {
        $array = array();
        foreach ( $items as $item ) {

            if ( is_array( $item ) ) {
                $item['pbpNews_timestamp'] = $this->get_item_timestamp( $item );
                $item['pbpNews_datasource'] = $this;
                $item['pbpNews_instance'] = $instance;
            } 
            elseif ( is_object( $item ) ) {                
                $item->pbpNews_timestamp = $this->get_item_timestamp( $item );
                $item->pbpNews_datasource = $this;
                $item->pbpNews_instance = $instance;
            }                    
            else {
                echo get_class( $item );
            }
            $array[] = $item;
        }
        return $array;
    }
    
    /**
     * Display item
     * @param mixed $item_data
     * @return string
     */
    public function display_item( $item_data ) {
        if ( is_null( $this->pbpNewsticker ) ) {
            return;
        }

        $instance_count = is_array( $item_data ) ? $item_data['pbpNews_instance'] : $item_data->pbpNews_instance;
        $html = $this->pbpNewsticker->get_source_value('formatting', $this->name, array( 'instance_count' => $this->allows_extra ? $instance_count : false ) );
        
        foreach ( $this->variables as $variable => $details ) {
            if ( strpos( $html, $variable )) {
                $html = str_replace( "%$variable%", $this->$details['method']( $item_data ), $html );
            }            
        }            

        return $html;
    }

    /**
     * Trims string down to set number of words. Based on Wordpress implementation in wp_trim_excerpt and wp_trim_words
     * @param string $text
     * @param int $excerpt_length
     * @return string
     */
    public function get_trimmed_string( $text, $excerpt_length = 12 ) {
        $text = strip_shortcodes( $text );
        $text = apply_filters('pbpNewsticker_string', $text);
        $text = str_replace(']]>', ']]&gt;', $text);                
        $strip_tags = apply_filters('pbpNewsticker_strip_excerpt_tags', true, $this);
        if ( $strip_tags === true ) {
            $text = str_replace( array('<br />', '<br>', '<br/>'), ' ', $text);
            $text = wp_strip_all_tags( $text );
        }        

        $excerpt_more = apply_filters('pbpNewsticker_trim_string_more', '...', $this);        

        // Split into words
        $words_array = preg_split( "/[\n\r\t ]+/", $text, $excerpt_length + 1, PREG_SPLIT_NO_EMPTY );
            
        if ( count( $words_array ) > $excerpt_length ) {
            array_pop( $words_array );
            $text = implode( ' ', $words_array );
            $text = $text . $excerpt_more;
        } else {            
            $text = implode( ' ', $words_array );
        }
        return $text;
    }

    /**
     * Return items for newsticker
     * @abstract
     * @return array
     */
    abstract public function get_items();
    
    /**
     * Configure specific settings for this data source
     * @abstract
     * @return array
     */
    abstract public function get_settings();
    
    /**
     * Each data source must provide a way of retrieving an item's timestamp
     * @abstract
     * @return int
     */
     abstract public function get_item_timestamp( $item );
}

// Register action hooks
add_action( 'pbpNewsticker_save_settings', array('pbpNewsticker_Data_Source', 'save_ticker_settings' ), 10, 2 );

// Register filters
add_filter( 'pbpNewsticker_ticker_settings', array( 'pbpNewsticker_Facebook', 'pbpNewsticker_ticker_settings' ), 10, 2);
add_filter( 'pbpNewsticker_ticker_settings', array( 'pbpNewsticker_Twitter', 'pbpNewsticker_ticker_settings' ), 10, 2);