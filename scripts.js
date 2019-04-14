$(document).ready(function() {
	// focus input when modal opens
	$('.modal').modal({
		onOpenEnd: function() {
			$('#searchQuery').focus();
		}
	});

	// submit search on enter
	$('#searchQuery').keypress(function (e) {
		if (e.which == 13) {
			sendSearch();
			return false;
		}
	});
});

function sendSearch() {
	var query = $('#searchQuery').val().trim();
	if(query.length >= 5) {
		apretaste.send({
			'command':'DIARIODECUBA BUSCAR',
			'data':{searchQuery: query}
		});
	}
	else M.toast({html: 'Inserte mínimo 5 caracteres'});
}