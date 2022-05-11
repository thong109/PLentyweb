/*
 * common.js
 *
 *  version --- 3.6
 *  updated --- 2016/09/26
 */
/* !stack ------------------------------------------------------------------- */
$(document).ready(function () {
    //checkSizeWindow
    var sizeCheck = function () {
        if (window.matchMedia('(max-width:1366px)').matches) {
            return 'MB';
        } else {
            return 'PC';
        }
    };

    $(".menu > li").on('click', function (event) {
        $(this).find('.sub__menu').toggleClass('show');
    });

    $(".btn-menu").on('click', function (event) {
        $("#gNavi").addClass('show');
        $("#wrapper").append('<div class="overlay">')
    });
    $("body").on('click', '.overlay', function (event) {
        $("#gNavi").removeClass('show');
        $(this).remove();
    });

    $(".btn-close-menu").on('click', function (event) {
        $("#gNavi").removeClass('show');
        $(".overlay").remove();
    });

    $(".backTop").click(function () {
        $("html, body").animate({scrollTop: 0}, "slow");
        return false;
    });

    $('.shareTW').on('click', function () {
        var href = window.location.href;
        window.open('https://twitter.com/share?text=rentio&amp;url=' + href + '', 'Twitter-dialog', 'width=626,height=436');
        return false;
    })

    $('.shareFB').on('click', function () {
        var href = window.location.href;
        window.open('https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent('' + href + ''), 'facebook-share-dialog', 'width=626,height=436');
        return false;
    })

});
