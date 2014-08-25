window.onload = function () {
    var buttons = document.getElementsByTagName('button');
    for (var i = 0; i < buttons.length; i++) {
        var button = buttons.item(i);
        if (button.type == 'submit') {
            var o = button.attributes.getNamedItem('value');
            if (o && o.value) {
                button.onclick = function () {
                    this.value = o.value;
                };
            }
        }
    }
}





