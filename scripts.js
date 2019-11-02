// Variables

categories = [
	{"id": "1", "name": "Cuba"}, {"id": "2", "name": "Internacional"},
	{"id": "3", "name": "Derechos Humanos"}, {"id": "4", "name": "Cultura"},
	{"id": "5", "name": "Ocio"}, {"id": "6", "name": "Deportes"},
	{"id": "7", "name": "De Leer"}
];

// On load function

$(function () {
	$('select').formSelect();

	$('#categories').change(function (event) {
		var selected = $('#categories option:selected').val();
		return apretaste.send({
			"command": "DDC",
			"data": {"category": selected}
		});
	});
});

// Main functions

function toggleWriteModal() {
	var status = $('#writeModal').attr('status');

	if (status == "closed") {
		if ($('.container:not(#writeModal) > .navbar-fixed').length == 1) {
			var h = $('.container:not(#writeModal) > .navbar-fixed')[0].clientHeight + 2;
			$('#writeModal').css('height', 'calc(100% - ' + h + 'px)');
		}

		$('#writeModal').slideToggle({
			direction: "up"
		}).attr('status', 'opened');
		$('#note').focus();
	} else {
		$('#writeModal').slideToggle({
			direction: "up"
		}).attr('status', 'closed');
	}
}

function getRandomColor() {
	var letters = '0123456789ABCDEF';
	var color = '#';
	for (var i = 0; i < 6; i++) {
		color += letters[Math.floor(Math.random() * 16)];
	}
	return color;
}

// Request functions

function sendComment() {
	var comment = $('#comment').val().trim();

	if (comment.length >= 2) {
		apretaste.send({
			'command': 'DDC COMENTAR',
			'data': {
				'comment': comment,
				'article': id
			},
			'redirect': false,
			'callback': {
				'name': 'sendCommentCallback',
				'data': comment.escapeHTML()
			}
		});
	} else {
		showToast('Escriba algo');
	}
}

// Callback functions

function sendCommentCallback(comment) {
	var element = "<li class=\"collection-item avatar\" id=\"last\">" +
		"<span style=\"background-color: " + getRandomColor() + "\" class=\"circle\">" + myUsername[0].toUpperCase() + "</span>" +
		"<span class=\"title\">@" + myUsername + " &middot; " + Date.prototype.nowFormated() + " </span>" +
		"<p>" + comment + "</p>" +
		"</li>";

	$('#comments').append(element);
	$('#comment').val('');
	$('html, body').animate({
		scrollTop: $("#last").offset().top
	}, 1000);

	toggleWriteModal();
}

// Prototype functions

String.prototype.escapeHTML = function () {
	var htmlEscapes = {
		'&': '&amp;',
		'<': '&lt;',
		'>': '&gt;',
		'"': '&quot;',
		"'": '&#x27;',
		'/': '&#x2F;'
	};
	var htmlEscaper = /[&<>"'\/]/g;
	return ('' + this).replace(htmlEscaper, function (match) {
		return htmlEscapes[match];
	});
};

Date.prototype.nowFormated = function () {
	var now = new Date(); // This current millisecond on user's computer.

	var format = "{D}/{M}/{Y} · {h}:{m}{ap}";
	var Month = now.getMonth() + 1;
	format = format.replace(/\{M\}/g, Month);
	var Mday = now.getDate();
	format = format.replace(/\{D\}/g, Mday);
	var Year = now.getFullYear().toString().slice(2);
	format = format.replace(/\{Y\}/g, Year);
	var h = now.getHours();
	var pm = h > 11;

	if (h > 12) {
		h -= 12;
	}

	;
	var ap = pm ? "pm" : "am";
	format = format.replace(/\{ap\}/g, ap);
	var hh = h;
	format = format.replace(/\{h\}/g, hh);
	var mm = now.getMinutes();

	if (mm < 10) {
		mm = "0" + mm;
	}

	format = format.replace(/\{m\}/g, mm);
	return format;
};

/**/

function sendSearch() {
	var query = $('#query').val().trim();
	if (query.length >= 3) {
		apretaste.send({
			'command': 'DDC BUSCAR',
			'data': {query: query}
		});
	} else {
		M.toast({html: 'Inserte mínimo 3 caracteres'});
	}
}

String.prototype.replaceAll = function (search, replacement) {
	return this.split(search).join(replacement);
};
