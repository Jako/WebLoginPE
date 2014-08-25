$(document).ready(function() {
	$('.parameter').corner();
	$('.example').corner();
	$('.servicesHead').corner('top');
});

function viewTemplate(template)
{
	var url = '../templates/default/' + template + '.html';
	window.open(url, template);
}

function viewTemplateAsText(template)
{
	var url = '../templates/default/' + template + '.html.txt';
	window.open(url, template);
}