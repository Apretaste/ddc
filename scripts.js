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

function search() {
	apretaste.send({command:'DIARIODECUBA BUSCAR'})
}