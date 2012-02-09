jQuery(document).ready(function($) {
	$.editable.addInputType('masked', {
        element : function(settings, original) {
        	var attribs = '';
            
            if (parseInt(settings.minlength) > 0) { attribs = attribs + ' minlength="' + parseInt(settings.minlength) + '"'; }
            if (parseInt(settings.maxlength) > 0) { attribs = attribs + ' maxlength="' + parseInt(settings.maxlength) + '"'; }

            var input = $('<input type="text" ' + attribs + '/>').mask(settings.mask);
            $(this).append(input);
            return(input);
        },
	});
});