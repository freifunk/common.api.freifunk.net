calHeatmap = new CalHeatMap();
function sendParseRequest() {
	var text = $('#icstext').val();
	$.ajax({
		url : 'ajax/icsprocessor.php',
		data : {text : text, dest : 'parser' },
		method: 'POST',
	}).done(function(msg) {
		var json = JSON.parse(msg);
		$('#full-calendar').fullCalendar('removeEvents');
		$('#full-calendar').fullCalendar('addEventSource', json.fullCalendar);
		updateInfoPanel(json.metainfo);
		console.log(json.calHeatmap);
		calHeatmap.update(json.calHeatmap, true);
		// persist change
		calHeatmap.options.data = json.calHeatmap;
	});
}

function sendMergeRequest() {
	var text0 = $('#icstext-0').val();
	var text1 = $('#icstext-1').val();
	$.ajax({
		url : 'ajax/icsprocessor.php',
		data : {text0 : text0, text1 : text1, dest : 'merger' },
		method: 'POST',
	}).done(function(msg) {
		var json = JSON.parse(msg);
		$('#full-calendar').fullCalendar('removeEvents');
		$('#full-calendar').fullCalendar('addEventSource', json.fullCalendar);
		updateInfoPanel(json.metainfo);
		console.log(json.calHeatmap);
		calHeatmap.update(json.calHeatmap, true);
		// persist change
		calHeatmap.options.data = json.calHeatmap;
	});
}

function updateInfoPanel(infoArray) {
	var infoTable = $('#infoPanel').empty().append('<table></table>').find('table');
	$.each(infoArray, function(index, value) {
		infoTable.append("<tr><td class=\"key\">" + index + "</td><td class=\"value\">" + value + "</td><tr>");
	});
}

$(document).ready(function() {
	calHeatmap.init({
		start: new Date(2015, 0),
		domain: "month",
		subDomain: "day",
		data : {},
		dataType: "json",
		domainLabelFormat: "%m-%Y"											
	});
	$('#prevYear').click(function() {
		calHeatmap.previous(12);
	});
	$('#prev').click(function() {
		calHeatmap.previous();
	});
	$('#current').click(function() {
		calHeatmap.rewind();
	});
	$('#next').click(function() {
		calHeatmap.next();
	});
	$('#nextYear').click(function() {
		calHeatmap.next(12);
	});
	
	$('#full-calendar').fullCalendar({
	    header: {
			left: 'prevYear,prev,next,nextYear today',
			center: 'title',
			right: 'month,agendaWeek,agendaDay'
		},
		events : fullCalendarData,
		eventRender : function(event, element) {
			element.qtip({
				content : (event.description ? event.description.replace(/\\n/g, "<br />") : event.title.replace(/\\n/g, "<br />"))
			});
		},
	    aspectRatio : 2
    });

	$('#parseBtn').click(function(e) {
		e.preventDefault();
		$('#mergePanel').addClass('hidden');
		$('#parserPanel').toggleClass('hidden');
		return false;
	});
	$('#mergeBtn').click(function(e) {
		e.preventDefault();
		$('#parserPanel').addClass('hidden');
		$('#mergePanel').toggleClass('hidden');
	});

	$('#examplesPanel select#parseSelect').click(function(e) {
		$.ajax({
			url : 'ajax/geticscontent.php',
			data : {filename : e.target.value},
			method: 'POST',
		}).done(function(msg) {
			$('#icstext').val(msg);
		});
	});

	$('#examplesPanel select#mergeSelect').click(function(e) {
		$.ajax({
			url : 'ajax/geticscontent.php',
			data : {filename : e.target.value},
			method: 'POST',
		}).done(function(msg) {
			$('#icstext-' + e.target.dataset.id).val(msg);
		});
	});
});