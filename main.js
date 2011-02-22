var sfb_properties = {};
var sfb_printouts = {};

// Ugly.
function redirectByHash() {
	if (window.location.hash != '') {
		window.location.href = window.location.hash;
	}	
}

/**
 * Toggles the input field area visibility for a property div.
 */
function togglePropBox() {	
	$(this).children("span").toggleClass("ui-icon-triangle-1-e ui-icon-triangle-1-s");
	$(this).parent().next().animate({height: 'toggle'}, 100);
	
	return false;
}

/**
 * Given a prop-box div, checks descendant inputs to see whether the property should be
 * included as a printout and what it's value or min/max should be. 
 */
function processPropertyBox(element) {	
	propertyName = $(element).attr('data-prop');		
	status = $(element).find('.property-status').attr('checked');
	value = $(element).find('.property-value').val();
	
	//alert(propertyName + status + value);
	
	//alert(propertyName);
	
	// add property to printouts list if appropriate
	if (propertyName != 'Category') {
		if (status == "true") {
			sfb_printouts[propertyName] = propertyName;		// we'll implement labels later
		} else {
			delete sfb_printouts[propertyName];
		}
	} else if (value != "") {
		window.location.href = value;
	}
	
	// add a restriction for this property's value if appropriate	
	if ($.trim(value) != '') {
		sfb_properties[propertyName] = value;
	} else {
		min = $(element).find('.min').value;
		max = $(element).find('.max').value;
		
		if ($.trim(min) != '' || $.trim(max) != '') {
			sfb_properties[propertyName] = "min:" + min + ";max:" + max;
		} else {			
			// unset this property's entry in case it existed before
			delete sfb_properties[propertyName];
		}
	}
}

/**
 * Updates the search results based on modified property inputs.  Iff the element changedPropertyBox
 * is defined, it will be assumed that only that element has changed since the last update.
 * Respects $wgUseAjax.
 */
function updateResults(changedPropertyBox) {
	
	// update properties and printouts from form elements
	if (changedPropertyBox != null) {
		if (wg_use_ajax == false) {
			return;
		}
		processPropertyBox(changedPropertyBox);
	} else {
		$( ".prop-box" ).each( function(index, element) {
			processPropertyBox(element);
		});
	}
	
	// get category name
	category = $(".property-value").first().val();	
	
	// build url
	url = category + "?";
	for (propName in sfb_properties) {
		val = sfb_properties[propName];
		if (val != "SFB_DELETED") {
			url += "p[" + propName + "]=" + val + "&";
		}
	}
	for (propName in sfb_printouts) {
		label = sfb_printouts[propName];
		if (label != "SFB_DELETED") {
			url += "po[" + propName + "]=" + label + "&";
		}
	}
	
	if (wg_use_ajax == false) {
		window.location.href = url;		
	} else {
		window.location.hash = url;		
	}
	
	//alert(url);
	
	$.ajax({
		url: url + "&ajax=1",
		success: function(data) {
			$("#sfb_results").html(data);
		}
	});	
}



$(document).ready( function() {	
	redirectByHash();
	
	$( "#sfb_sidebar" ).find("input").change(function() {
		updateResults($(this).parent().parent());
	});
	
	
	// set up datepickers
	//$.datepicker.setDefaults( $.datepicker.regional[ datepicker_locale ] );	
	//$( ".datepicker" ).datepicker();
	
	// set up property box visibility toggling
	$( ".prop-box-clicker" ).click(togglePropBox);
	
	// set up show more properties button
	$("#show_extra_props").click(function() {
		$(this).html("Less");
		$("#props_extra").animate({height: 'toggle'}, 100);
		return false;
	});
	
	// bind autocomplete data values to properties
	for (id in sfb_property_data) {
		$( "#value-"+id ).autocomplete({
			minLength: 0,
			source: sfb_property_data[id]["values"],
		})
		.data( "autocomplete" )._renderItem = function(ul, item) {
			return $( "<li></li>" )
					.data( "item.autocomplete", item )
					.append( "<a>" + item.value 
							+ (item.count == undefined ? "" : " (" + item.count + ")</a>") )
					.appendTo( ul );
		};
	}
	
});
