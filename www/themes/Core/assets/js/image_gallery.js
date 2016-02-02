$(document).ready(function () {
    /*
    * GALLERY EXAMPLES
    */

    $("a#example1").fancybox();

    $("a#example2").fancybox({
        'overlayShow': false,
        'transitionIn': 'elastic',
        'transitionOut': 'elastic'
    });

    $("a#example3").fancybox({
        'transitionIn': 'none',
        'transitionOut': 'none'
    });

    $("a#example4").fancybox({
        'opacity': true,
        'overlayShow': false,
        'transitionIn': 'elastic',
        'transitionOut': 'none'
    });

    $("a#example5").fancybox();

    $("a#example6").fancybox({
        'titlePosition': 'outside',
        'overlayColor': '#000',
        'overlayOpacity': 0.9
    });

    $("a#example7").fancybox({
        'titlePosition': 'inside'
    });

    $("a#example8").fancybox({
        'titlePosition': 'over'
    });


});