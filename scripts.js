$(document).ready(function() {
	// focus input when modal opens
	$('.modal').modal({
		onOpenEnd: function() {
			$('#query').focus();
		}
	});

	// submit search on enter
	$('#query').keypress(function (e) {
		if (e.which == 13) {
			sendSearch();
			return false;
		}
	});
});

function sendSearch() {
	var query = $('#query').val().trim();
	if(query.length >= 3) {
		apretaste.send({
			'command':'DIARIODECUBA BUSCAR',
			'data':{query: query}
		});
	}
	else M.toast({html: 'Inserte mínimo 3 caracteres'});
}