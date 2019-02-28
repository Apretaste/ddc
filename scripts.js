$(document).ready(function() {
	$('.modal').modal();
});

function formatDate(dateStr) {
	var date = new Date(dateStr);
	var year = date.getFullYear();
	var month = (1 + date.getMonth()).toString().padStart(2, '0');
	var day = date.getDate().toString().padStart(2, '0');
	return day + '/' + month + '/' + year;
}

function showToast(text) {
    M.toast({html: text});
}

function sendSearch() {
    let query = $('#searchQuery').val().trim();
    if(query.length >= 2) {
        apretaste.send({
            'command':'DIARIODECUBA BUSCAR',
            'data':{searchQuery: query}
        });
    }
    else showToast('Minimo 2 caracteres');
}