jQuery(document).ready(function($) {
	$.editable.addInputType('text', {
        element : function(settings, original) {
            var input = $('<input />');
            if (settings.minlength) { input.attr('minlength', settings.minlength); }
            if (settings.maxlength) { input.attr('maxlength', settings.maxlength); }
            if (settings.width  != 'none') { input.width(settings.width);  }
            if (settings.height != 'none') { input.height(settings.height); }
            /* https://bugzilla.mozilla.org/show_bug.cgi?id=236791 */
            //input[0].setAttribute('autocomplete','off');
            input.attr('autocomplete','off');
            $(this).append(input);
            return(input);
        }
	});
});