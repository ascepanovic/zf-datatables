jQuery(document).ready(function($) {
	// Extend jQuery Validate
	$.validator.addMethod("zipcode", function(postalcode, element) {
	    //removes placeholder from string
	    postalcode = postalcode.split("_").join("");
	
	    //Checks the length of the zipcode now that placeholder characters are removed.
	    if (postalcode.length === 6) {
	        //Removes hyphen
	        postalcode = postalcode.replace("-", "");
	    }
	    //validates postalcode.
	    return this.optional(element) || postalcode.match(/^\d{5}$|^\d{5}\-\d{4}$/);
	}, "Please specify a valid zip code");
});