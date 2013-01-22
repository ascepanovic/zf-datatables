// extend datatables so we can update fields without refreshing table
$.fn.dataTableExt.oApi.fnStandingRedraw = function(oSettings) {
	//redraw to account for filtering and sorting
	// concept here is that (for client side) there is a row got inserted at the end (for an add)
	// or when a record was modified it could be in the middle of the table
	// that is probably not supposed to be there - due to filtering / sorting
	// so we need to re process filtering and sorting
	// BUT - if it is server side - then this should be handled by the server - so skip this step
	if(oSettings.oFeatures.bServerSide === false){
		var before = oSettings._iDisplayStart;
		oSettings.oApi._fnReDraw(oSettings);
		//iDisplayStart has been reset to zero - so lets change it back
		oSettings._iDisplayStart = before;
		oSettings.oApi._fnCalculateEnd(oSettings);
	}

	//draw the 'current' page
	oSettings.oApi._fnDraw(oSettings);
};

//extend datatables so that we can reset all filters
$.fn.dataTableExt.oApi.fnResetAllFilters = function (oSettings, bDraw/*default true*/) {
    for(iCol = 0; iCol < oSettings.aoPreSearchCols.length; iCol++) {
            oSettings.aoPreSearchCols[ iCol ].sSearch = '';
    }
    oSettings.oPreviousSearch.sSearch = '';

    if(typeof bDraw === 'undefined') bDraw = true;
    if(bDraw) this.fnDraw();
}

$.fn.dataTableExt.oApi.fnSetFilteringDelay = function ( oSettings, iDelay ) {
    var _that = this;

    if ( iDelay === undefined ) {
        iDelay = 750;
    }

    this.each( function ( i ) {
        $.fn.dataTableExt.iApiIndex = i;
        var
            $this = this,
            oTimerId = null,
            sPreviousSearch = null,
            anControl = $( 'input', _that.fnSettings().aanFeatures.f );

        anControl.unbind( 'keyup' ).bind( 'keyup', function() {
            var $$this = $this;

            if (sPreviousSearch === null || sPreviousSearch != anControl.val()) {
                window.clearTimeout(oTimerId);
                sPreviousSearch = anControl.val();
                oTimerId = window.setTimeout(function() {
                    $.fn.dataTableExt.iApiIndex = i;
                    _that.fnFilter( anControl.val() );
                }, iDelay);
            }
        });

        return this;
    } );
    return this;
};

//store nodes after datatables gets initialize
var nNodes = null;
var asInitVals = new Array();

$(document).ready(function() {
	/* Init the table */
	oTable = $('#'+dt_tableId).dataTable( dt_options );

if (dt_makeEditable) {
	makeEditable();

    $('#deleteRowDialog').html('Are you sure you want to delete the selected row?');
    $("#deleteRowDialog").dialog({ buttons:{
        'Ok':{
            text:'Ok',
            'class':'fg-button ui-state-default ui-priority-primary ui-corner-all',
            click:function () {
                var fnDeleteRow = $(this).data('fnDeleteRow');
                var id = $(this).data('id');

//                                var list = new Array();
//                                $.each($('table#'+dt_tableId+' .DTTT_selected'), function(index, value) {
//                                        list.push(oTable.fnGetData(value)[0]);
//                                    });

//                if (list.length > 1) {
//                    var id = list.join(';');
//                } else {
//                    var id = list[0];
//                }
                fnDeleteRow(id);
                $(this).dialog("close");
                }
            },
            'Cancel':{
                text:'Cancel',
                'class':'fg-button ui-state-default ui-priority-primary ui-corner-all',
                click:function () {
                $(this).dialog("close");
                }
            }
            },
            draggable:false,
            modal:true,
            width:500,
            maxHeight:400,
            autoOpen:false
    });
}

    var delay = (function(){
        var timer = 0;
        return function(callback, ms){
           clearTimeout (timer);
           timer = setTimeout(callback, ms);
        };
	})();

	$('#'+dt_tableId+' '+dt_columnSearchPosition+' input').keyup(function() {
		var myThis = this;
	    delay(function(){
	    	/* Filter on the column (the index) of this element */
	    	cell = $(myThis);
	    	oTable.fnFilter( cell.val(), cell.parent().parent('tr').children().index(cell.parent("td")) );
	      }, 750 );
	  });

    /**
     * Support functions to provide a little bit of 'user friendlyness' to the textboxes in
     * the footer
     */
    $('#'+dt_tableId+' '+dt_columnSearchPosition+' input').each( function (i) {
    	asInitVals[i] = this.value;
    } );

    $('#'+dt_tableId+' '+dt_columnSearchPosition+' input').focus( function () {
    	if ( $(this).is('.search_init') )
    	{
        	$(this).removeClass('search_init');
    		$(this).val("");
    	}
    } );

    $('#'+dt_tableId+' '+dt_columnSearchPosition+' input').blur( function (i) {
    	if ( !$(this).is('.search_init') && $(this).val() == "")
    	{
        	$(this).addClass('search_init');
    		$(this).val(asInitVals[$('#'+dt_tableId+' '+dt_columnSearchPosition+' input').index(this)]);
    	}
    } );

     /* Add event listeners to the two range filtering inputs */
    $('#'+dt_tableId+' select').change( function() {
    	cell = $(this);
    	oTable.fnFilter( $(this).val(), cell.parent().parent('tr').children().index(cell.parent("td")) );
    } );

    // Add button to allow users to clear all search filters
    // useful is something goes awry.
    $('<div />').addClass('clearFilters').css({'float' : 'left'}).attr({'id' : 'clearFilters'}).prependTo($('#'+dt_tableId+'_filter'));
    $('<button />').attr({
        'id' : 'clearFilters'
    })
    .html('Clear Search')
    .appendTo($('#clearFilters'));
    $("button", ".clearFilters").button();

    $('#clearFilters').click( function () {
        //hook
        if(typeof cnsDatatablesClearFilter == 'function') {
            cnsDatatablesClearFilter();
        }
        //reset search all columns
        $('#'+dt_tableId+'_filter input').val('');
        //reset search columns
        $('#'+dt_tableId+' '+dt_columnSearchPosition+' td :input').each(function (index,val) {
            $(this).val('');
        } );
        //put search columns back to initial state
        $('#'+dt_tableId+' '+dt_columnSearchPosition+' input').each( function (index,item) {
        	$(item).val(asInitVals[$('#'+dt_tableId+' '+dt_columnSearchPosition+' input').index(item)]);
            $(this).addClass('search_init');
    	} );
        //hook
        if(typeof cnsSetFilterStatus == 'function') {
            cnsSetFilterStatus();
        }
        //tell datatables to clear filters and reload
        oTable.fnResetAllFilters();
	});

    //remove "processing" text at top of datatable
//    $('#'+dt_tableId+'_processing').remove();

    // make only one row selectable
    $('#'+dt_tableId+' tbody').click(function(event) {
        if ($(event.target.parentNode).hasClass('DTTT_selected')) {
            $(event.target.parentNode).removeClass('DTTT_selected');
        } else {
            $(oTable.fnSettings().aoData).each(function () {
                $(this.nTr).removeClass('DTTT_selected');
            });
        }
    });

    //set filtering delay on search all columns text input
    oTable.dataTable().fnSetFilteringDelay();
});

/* Get the rows which are currently selected */
function fnGetSelected( oTableLocal )
{
    var aReturn = new Array();
    var aTrs = oTableLocal.fnGetNodes();

    for ( var i=0 ; i<aTrs.length ; i++ )
    {
        if ( $(aTrs[i]).hasClass('row_selected') )
        {
            aReturn.push( aTrs[i] );
        }
    }
    return aReturn;
}

function confirmDelete(id, fnDeleteRow) {
    if (dt_delete) {
        $('#deleteRowDialog').data('fnDeleteRow', fnDeleteRow).data('id', id).dialog("open");
    }
}

$(window).bind('resize', function () {
    $('#'+dt_tableId).css('width', '100%');
    //.dataTable().fnAdjustColumnSizing();
});
