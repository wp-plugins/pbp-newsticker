<?php
/**
 * Allows users to set free form news items
 * 

 */

class pbpNewsticker_Free_Form extends pbpNewsticker_Data_Source {

	    /**
     * Instantiate object
     * @return void
     */
    public function __construct($pbpNewsticker = null) {
        parent::__construct( $pbpNewsticker );        

        $this->id = 'pbpNews_source_free_form';
        $this->title = __('Free Form News Item', 'pbpNews');
        $this->name = 'pbpNewsticker_Free_Form';
        $this->assist = __('Create your own news items', 'pbpNews');
        $this->allows_extra = true;
        $this->variables = array();
    }
    
    /**
     * Return items for newsticker
     * @return array
     */
    public function get_items() {
        $settings = $this->get_instance_settings();
        return $this->get_item_array( $settings['instance'] );
    }
    
    /**
     * Configure specific settings for this data source
     * @return array
     */
    public function get_settings() {
        $settings = array();

        // Text
        $settings[] = array(
            'id' => 'text',
            'title' => __('Text', 'pbpNews'),
            'type' => 'text'
        );

        // Date / time
        $settings[] = array(
            'id' => 'date',
            'title' => __('Date', 'pbpNews'),
            'type' => 'text',
            'class' => 'timepicker'
        );
        
        return $settings;
    }  

    /**
     * Get item timestamp
     */
    public function get_item_timestamp( $item ) {
    	return strtotime( $item['date'] );
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
        
        return stripslashes_deep( $item_data['text'] );
    }
}