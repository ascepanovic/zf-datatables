/**
 * Usage:
 *
 * 1. Install Jeditable: http://www.appelsiini.net/projects/jeditable
 * 2. Add the code below to your javascript.
 * 3. Call it like this:
 *
 * $('p').editable('/edit', {
 *   type:   'checkbox',
 *   cancel: 'Cancel',
 *   submit: 'OK',
 *   checkbox: { trueValue: 'Yes', falseValue: 'No' }
 * });
 *
 * Upon clicking on the <p>, it's content will be replaced by a checkbox.
 * If the text within the paragraph is 'Yes', the checkbox will be checked
 * by default, otherwise it will be unchecked.
 *
 * trueValue is submitted when the checkbox is checked and falseValue otherwise.
 *
 * Have fun!
 *
 * Peter BÃ¼cker (spam.naag@gmx.net)
 * http://www.pastie.org/893364
 */

$.editable.addInputType('checkbox', {
	element : function(settings, original) {
        var input = $('<input type="checkbox"/>');
        $(this).append(input);

        // Update <input>'s value when clicked
        $(input).click(function() {
                  var value = $(input).attr("checked") ? 'Yes' : 'No';
                  $(input).val(value);
        });
        return(input);
  },
  content : function(string, settings, original) {
          var checked = string == "Yes" ? 1 : 0;
          var input = $(':input:first', this);
          $(input).attr("checked", checked);
          var value = $(input).attr("checked") ? 'Yes' : 'No';
          $(input).val(value);
  }
});

