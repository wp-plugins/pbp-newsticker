(function($) {

    /**
     * Backwards compatibility. Older versions of Wordpress use versions of jQuery that don't have 'on' and 'off' defined.
     */
    if ( $.fn.on === undefined ) {
        $.fn.on = function( event, fn ) {
            this.bind( event, fn );
        }
    }
    
    if ( $.fn.off === undefined ) {
        $.fn.off = function( event, fn ) {
            return this.unbind( event, fn );
        }
    }
    
    /**
     * Hides animation settings if visible
     */
    var hideAnimationSettings = function(el) {
        if (el.hasClass('hidden') === false) {
            el.addClass('hidden');
        }        
    }
    
    /**
     * Reveal selected animation's settings
     */
    var displayAnimationSettings = function() {        
        var style = $('#pbpNews_style').val(), 
            section_class = 'pbpNews_'+style+'_settings';
            
        // Hide currently visible animation settings
        $('.pbpNews_animation_settings').each( function() {            
            hideAnimationSettings( $(this) );
        });
        
        // Display selected animation's settings'
        $('.'+section_class).removeClass('hidden');        
    }
    
    /**
     * Reveal settings for data source
     */
    var toggleDataSourceSettings = function(el) {
        var checked = el.is(':checked'),
            parent = el.parent(),
            settings = parent.children('.pbpNews_data_source_settings'),
            copy_button = parent.children('.add_data_source');
        
        settings.toggleClass('hidden', checked === false);        
        copy_button.toggleClass('hidden', checked === false);
    }    

    /** 
     * Create new instance of data source
     */
    var createDataSourceInstance = function(el, e) {
        var current_instance = el.prev('.pbpNews_data_source_settings'),
            data = {};

        e.preventDefault();

        data.action = 'create_data_source_instance';
        data.instance_id = current_instance.data('instance') + 1;
        data.source = el.data('source');
        data.ticker_id = el.data('ticker_id');

        $.post( ajaxurl, data, function( response ){
            // Insert into DOM
            $(response).insertAfter( current_instance );

            // Remove the event handler and re-attach it so the new object is included
            $('.remove-instance').off('click');
            $('.remove-instance').on('click', function(e) {
                removeDataSourceInstance( $(this), e );
            });
        });        
    }
    
    /**
     * Remove an instance of a data source
     */
    var removeDataSourceInstance = function(el, e) {
        var instance = el.parent().parent();

        e.preventDefault();
        instance.remove();
    }

    /**
     * Functions executed when the page is finished loading
     */
    $(document).ready( function() {
       
        // Display pre-select animation option
        displayAnimationSettings();
       
        // When style is changed, display correct animation settings
        $('#pbpNews_style').on( 'change', function() {
            displayAnimationSettings();
        });
       
        // When a data source is enabled or disabled, reveal its settings container
        $('.pbpNews_data_source .enable').on( 'click', function() {
            toggleDataSourceSettings( $(this) );
        });
       
        // On page load, check through each data source and reveal the 
        // settings object for any that are enabled
        $('.pbpNews_data_source .enable').each( function() {
            toggleDataSourceSettings( $(this) );
        });

        // Create an extra instance of a data source
        $('.add_data_source').on('click', function(e) {
            createDataSourceInstance( $(this), e );        
        });

        // Remove a data source instance
        $('.remove-instance').on('click', function(e) {
            removeDataSourceInstance( $(this), e );
        });

        // Apply date & timepicker
        $('.timepicker').datetimepicker();
    });
    
}(jQuery));
