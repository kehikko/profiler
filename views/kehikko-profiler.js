$(document).ready(function() {
	/* sortable table */
	$('body').on('click', '.th-sortable', tableSortableSort);
	/* search timer */
	setInterval(function() {
		$('.table-search-input').each(function() {
			var current = $(this).val();
			var previous = $(this).attr('search-previous');
			if (previous == undefined) {
				$(this).attr('search-previous', current);
			} else if (current != previous) {
				var target = $(this).attr('search-target');
				tableSearchableSearch(target, current);
				$(this).attr('search-previous', current);
			}
		});
		$('.ul-search-input').each(function() {
			var current = $(this).val();
			var previous = $(this).attr('search-previous');
			if (previous == undefined) {
				$(this).attr('search-previous', current);
			} else if (current != previous) {
				var target = $(this).attr('search-target');
				ulSearchableSearch(target, current);
				$(this).attr('search-previous', current);
			}
		});
	}, 333);
	$('#profiler-callgraph').on('load', function() {
		$('html').css('height', '100%');
		$('body').css('height', '100%');
		svgPanZoom('#profiler-callgraph');
	});
});

/* search from a table */
function tableSearchableSearch(target, search) {
	$('#' + target + ' tbody tr td[searchable="yes"]').each(function() {
		var text = $(this).attr('search-value');
		if (text == undefined) {
			text = $(this).text();
		}
		var n = text.toLowerCase().indexOf(search.toLowerCase());
		if (n >= 0) {
			$(this).parent().show();
		} else {
			$(this).parent().hide();
		}
	});
}
/* sort a table */
function tableSortableSort() {
	var field = $(this).attr('sort-id');
	if (field == undefined) {
		return;
	}
	var order = $(this).attr('sort-order');
	if (order == undefined) {
		order = 1;
	} else {
		order = -order;
	}
	if (order != 1 && order != -1) {
		order = 1;
	}
	$(this).attr('sort-order', order);
	var table = $(this).parents('.table-sortable');
	var numeric = $(this).attr('sort-type') == 'number';
	$(table).children('tbody').each(function() {
		var tbody = this;
		$(this).children('tr').sort(function(a, b) {
			var a_field = $(a).children('td[sort-id="' + field + '"]');
			var b_field = $(b).children('td[sort-id="' + field + '"]');
			if (a_field.length < 1 || b_field.length < 1) {
				return 0;
			}
			var a_val = $(a_field).attr('sort-value');
			var b_val = $(b_field).attr('sort-value');
			if (a_val == undefined) {
				a_val = $(a_field).text();
			}
			if (b_val == undefined) {
				b_val = $(b_field).text();
			}
			if (numeric) {
				a_val = parseFloat(a_val);
				b_val = parseFloat(b_val);
			}
			if (a_val < b_val) {
				return -order;
			}
			if (a_val > b_val) {
				return order;
			}
			for (var i = 1; i < 10; i++) {
				a_field = $(a).children('td[sort-order="' + i + '"]');
				b_field = $(b).children('td[sort-order="' + i + '"]');
				if (a_field.length < 1 || b_field.length < 1) {
					break;
				}
				a_val = $(a_field).attr('sort-value');
				b_val = $(b_field).attr('sort-value');
				if (a_val == undefined) {
					a_val = $(a_field).text();
				}
				if (b_val == undefined) {
					b_val = $(b_field).text();
				}
				// if (numeric) {
				//  a_val = parseFloat(a_val);
				//  b_val = parseFloat(b_val);
				// }
				if (a_val < b_val) {
					return -1;
				}
				if (a_val > b_val) {
					return 1;
				}
			}
			a_field = $(a).children('td:first');
			b_field = $(b).children('td:first');
			if (a_field.length < 1 || b_field.length < 1) {
				return 0;
			}
			a_val = $(a_field).attr('sort-value');
			b_val = $(b_field).attr('sort-value');
			if (a_val == undefined) {
				a_val = $(a_field).text();
			}
			if (b_val == undefined) {
				b_val = $(b_field).text();
			}
			// if (numeric) {
			//  a_val = parseFloat(a_val);
			//  b_val = parseFloat(b_val);
			// }
			if (a_val < b_val) {
				return -1;
			}
			if (a_val > b_val) {
				return 1;
			}
			return 0;
		}).each(function() {
			$(tbody).append($(this));
		});
	});
}