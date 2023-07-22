document.addEventListener('DOMContentLoaded', function () {
    var sidebar = document.querySelector('.bs-docs-sidebar');
    Stickyfill.add(sidebar);

    // TODO: Make this vanilla and drop jQuery
    $('body').scrollspy({
        target: '.bs-docs-sidebar .nav',
    });
});

function trianglify(color1, color2) {
    var header = $('#jumbotron-header'),
        t = new Trianglify({
            cellsize: 90,
            noiseIntensity: 0,
            x_gradient: [color1, color2]
        }),
        pattern = t.generate(window.screen.width | header.outerWidth(), header.outerHeight()*1.2);

    header.css('background-image', pattern.dataUrl);
}
