/*
 * Datepicker for Jeditable (currently buggy, not for production)
 *
 * Copyright (c) 2007-2008 Mika Tuupola
 *
 * Licensed under the MIT license:
 *   http://www.opensource.org/licenses/mit-license.php
 *
 * Depends on Datepicker jQuery plugin by Kelvin Luck:
 *   http://kelvinluck.com/assets/jquery/datePicker/v2/demo/
 *
 * Project home:
 *   http://www.appelsiini.net/projects/jeditable
 *
 * Revision: $Id$
 *
 */
 
$.editable.addInputType('datepicker', {
    /* create input element */
    element : function(settings, original) {
        var input = $('<input>');
        $(this).append(input);
        //$(input).css('opacity', 0.01);
        return(input);
    },
    /* attach 3rd party plugin to input element */
    plugin : function(settings, original) {
        /* Workaround for missing parentNode in IE */
        var form = this;
        settings.onblur = 'ignore';
        $("input", this)
        .datepick({showTrigger: ''},
        		onClose: function(dateText) {
                    original.reset.apply(form, [settings, original]);
                    $(original).addClass( settings.cssdecoration );
                    })
//        .bind('click', function() {
//            //$(this).blur();
//            $(this).dpDisplay();
//            return false;
//        })
//        .bind('dateSelected', function(e, selectedDate, $td) {
//            $(form).submit();
//        })
//        .bind('dpClosed', function(e, selected) {
//            /* TODO: unneseccary calls reset() */
//            //$(this).blur();
//        })
        .trigger('change')
        .click();
    },
    
    /* Call before submit hook. */
    submit: function (settings, original) {
        /* Collect hour, minute and am/pm from pulldowns. Create a string from */
        /* them. Set value of hidden input field to this string.               */
//        var value = $('#h_').val() + ':' + $('#m_').val() + "" + $('#p_').val();
        console.log($('input', this).val());
        return false;
    },    
    
});