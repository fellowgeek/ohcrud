(function ($) {
    "use strict";


    var windows = $(window);
	var screenSize = windows.width();
	var sticky = $('.header-sticky');
	var $html = $('html');
	var $body = $('body');  

    windows.on('load', function () {
		dataBackgroundImage();
	});


    /*=============================================
    =       Menu sticky & Scroll to top          =
    =============================================*/
	

	windows.on('scroll', function () {
		var scroll = windows.scrollTop();
		var headerHeight = sticky.height();

		if (screenSize >= 320) {
			if (scroll < headerHeight) {
				sticky.removeClass('is-sticky');
			} else {
				sticky.addClass('is-sticky');
            }
		}

    });

    /*======================================
    =              Wow Active               =
    =======================================*/

    new WOW().init();

    /*=============================================
    =               Scroll to top                 =
    =============================================*/
    function scrollToTop() {
        var $scrollUp = $('#scroll-top'),
            $lastScrollTop = 0,
            $window = $(window);

        $window.on('scroll', function () {
            var st = $(this).scrollTop();
            if (st > $lastScrollTop) {
                $scrollUp.removeClass('show');
            } else {
                if ($window.scrollTop() > 200) {
                    $scrollUp.addClass('show');
                } else {
                    $scrollUp.removeClass('show');
                }
            }
            $lastScrollTop = st;
        });

        $scrollUp.on('click', function (evt) {
            $('html, body').animate({scrollTop: 0}, 600);
            evt.preventDefault();
        });
    }
    scrollToTop();



    /*----------------------------------------*/
	/*  Toolbar Button
    /*----------------------------------------*/
	var $overlay = $('.global-overlay');
	$('.toolbar-btn').on('click', function (e) {
		e.preventDefault();
		e.stopPropagation();
		var $this = $(this);
		var target = $this.attr('href');
		var prevTarget = $this.parent().siblings().children('.toolbar-btn').attr('href');
		$(target).toggleClass('open');
		$(prevTarget).removeClass('open');
		$($overlay).addClass('overlay-open');
	});/*----------------------------------------*/
	/*  Close Button Actions
    /*----------------------------------------*/
	$('.btn-close, .btn-close-2').on('click', function (e) {
		var dom = $('.main-wrapper').children();
		e.preventDefault();
		var $this = $(this);
		$this.parents('.open').removeClass('open');
		dom.find('.global-overlay').removeClass('overlay-open');
	});

	/*----------------------------------------*/
	/*  Offcanvas
    /*----------------------------------------*/
	/*Variables*/
	var $offcanvasNav = $('.offcanvas-menu, .offcanvas-minicart_menu, .offcanvas-search_menu, .mobile-menu'),
		$offcanvasNavWrap = $(
			'.offcanvas-menu_wrapper, .offcanvas-minicart_wrapper, .offcanvas-search_wrapper, .mobile-menu_wrapper'
		),
		$offcanvasNavSubMenu = $offcanvasNav.find('.sub-menu'),
		$menuToggle = $('.menu-btn'),
		$menuClose = $('.btn-close');

	/*Close Off Canvas Sub Menu*/
	$offcanvasNavSubMenu.slideUp();



	$('.btn-close').on('click', function (e) {
		e.preventDefault();
		$('.mobile-menu .sub-menu').slideUp();
		$('.mobile-menu .menu-item-has-children').removeClass('menu-open');
	})


    
    

    /*=============================================
    =            offcanvas mobile menu            =
    =============================================*/
    var $offCanvasNav = $('.offcanvas-navigation'),
        $offCanvasNavSubMenu = $offCanvasNav.find('.sub-menu');
    
    /*Add Toggle Button With Off Canvas Sub Menu*/
    $offCanvasNavSubMenu.parent().prepend('<span class="menu-expand"><i></i></span>');
    
    /*Close Off Canvas Sub Menu*/
    $offCanvasNavSubMenu.slideUp();
    
    /*Category Sub Menu Toggle*/
    $offCanvasNav.on('click', 'li a, li .menu-expand', function(e) {
        var $this = $(this);
        if ( ($this.parent().attr('class').match(/\b(menu-item-has-children|has-children|has-sub-menu)\b/)) && ($this.attr('href') === '#' || $this.hasClass('menu-expand')) ) {
            e.preventDefault();
            if ($this.siblings('ul:visible').length){
                $this.parent('li').removeClass('active');
                $this.siblings('ul').slideUp();
            } else {
                $this.parent('li').addClass('active');
                $this.closest('li').siblings('li').removeClass('active').find('li').removeClass('active');
                $this.closest('li').siblings('li').find('ul:visible').slideUp();
                $this.siblings('ul').slideDown();
            }
        }
    });
    /*----------------------------------------*/
	/*  Offcanvas Inner Nav
    /*----------------------------------------*/
	$('.offcanvas-inner_nav li.has-sub > a, .frequently-item li.has-sub a, .pd-tab_item li.has-sub a').on('click', function () {
		$(this).removeAttr('href');
		var element = $(this).parent('li');
		if (element.hasClass('open')) {
			element.removeClass('open');
			element.find('li').removeClass('open');
			element.find('ul').slideUp();
		} else {
			element.addClass('open');
			element.children('ul').slideDown();
			element.siblings('li').children('ul').slideUp();
			element.siblings('li').removeClass('open');
			element.siblings('li').find('li').removeClass('open');
			element.siblings('li').find('ul').slideUp();
		}
	});


    /*==========================================
    =            mobile menu active            =
    ============================================*/
    
    $("#mobile-menu-trigger").on('click', function(){
        $("#mobile-menu-overlay").addClass("active");
        $body.addClass('no-overflow');
    });
    
    $("#mobile-menu-close-trigger").on('click', function(){
        $("#mobile-menu-overlay").removeClass("active");
        $body.removeClass('no-overflow');
    });
    
    // $("#mobile-menu-trigger--2").on('click', function(){
    //     $("#mobile-menu-overlay").removeClass("active");
    //     $body.removeClass('no-overflow');
    // });
    $("#mobile-menu-trigger-2").on('click', function(){
        $("#mobile-menu-overlay").addClass("active");
        $body.addClass('no-overflow');
    });

    /*=============================================
    =            search overlay active            =
    =============================================*/
    
    $("#search-overlay-trigger").on('click', function(){
        $("#search-overlay").addClass("active");
        $body.addClass('no-overflow');
    });
    
    $("#search-close-trigger").on('click', function(){
        $("#search-overlay").removeClass("active");
        $body.removeClass('no-overflow');
    });
    
    
    /*Close When Click Outside*/
    $body.on('click', function(e){
        var $target = e.target;
        if (!$($target).is('.mobile-menu-overlay__inner') && !$($target).parents().is('.mobile-menu-overlay__inner') && !$($target).is('#mobile-menu-trigger') && !$($target).is('#mobile-menu-trigger i')){
            $("#mobile-menu-overlay").removeClass("active");
            $body.removeClass('no-overflow');
        }
        if (!$($target).is('.search-overlay__inner') && !$($target).parents().is('.search-overlay__inner') && !$($target).is('#search-overlay-trigger') && !$($target).is('#search-overlay-trigger i')){
            $("#search-overlay").removeClass("active");
            $body.removeClass('no-overflow');
        }
    });
    


    /*===================================
    =        Background image           =
    ====================================-*/
    function dataBackgroundImage() {
        var bgSelector = $(".bg-img");
        bgSelector.each(function (index, elem) {
            var element = $(elem),
                bgSource = element.data('bg');
            element.css('background', 'url(' + bgSource + ')');
        });
    }

    /*--------------------------------
        Hero Slider one
    -----------------------------------*/
    $('.hero-slider-one').slick({
        dots: true,
        infinite: true,
        slidesToShow: 1,
        slidesToScroll: 1,
        autoplay: false,
        prevArrow: false,
        nextArrow: false,
        responsive: [
            {
                breakpoint: 1199,
                settings: {
                    slidesToShow: 1,
                }
            }
        ]
    });
    $('.hero-slider-two').slick({
        dots: false,
        infinite: true,
        slidesToShow: 1,
        slidesToScroll: 1,
        autoplay: false,
        prevArrow:'<span class="arrow-prv">Prev</span>',
        nextArrow:'<span class="arrow-next">Next</span>',
        responsive: [
            {
                breakpoint: 1199,
                settings: {
                    slidesToShow: 1,
                }
            }
        ]
    });
    $('.hero-slider-four').slick({
        dots: true,
        infinite: true,
        slidesToShow: 1,
        slidesToScroll: 1,
        autoplay: false,
        customPaging : function(slider, i) {
            var title = $(slider.$slides[i].innerHTML).find('div[data-title]').data('title');
            return '<a class="pager__item"> '+title+' </a>';
        },
        prevArrow:'<span class="arrow-prv"><i class="icon-chevron-left"></i></span>',
        nextArrow:'<span class="arrow-next"><i class="icon-chevron-right"></i></span>',
        responsive: [
            {
                breakpoint: 1199,
                settings: {
                    slidesToShow: 1,
                }
            }
        ]
    });

    $('.hero-slider-five').slick({
        dots: false,
        infinite: true,
        slidesToShow: 1,
        slidesToScroll: 1,
        autoplay: false,
        prevArrow:'<span class="arrow-prv"><i class="icon-chevron-left"></i></span>',
        nextArrow:'<span class="arrow-next"><i class="icon-chevron-right"></i></span>',
        responsive: [
            {
                breakpoint: 1199,
                settings: {
                    slidesToShow: 1,
                }
            }
        ]
    });
    $('.hero-slider-7').slick({
        dots: false,
        infinite: true,
        slidesToShow: 1,
        slidesToScroll: 1,
        autoplay: false,
        prevArrow:'<span class="arrow-prv"><i class="icon-chevron-left"></i></span>',
        nextArrow:'<span class="arrow-next"><i class="icon-chevron-right"></i></span>',
        responsive: [
            {
                breakpoint: 1199,
                settings: {
                    slidesToShow: 1,
                }
            }
        ]
    });
    $('.hero-slider-8').slick({
        dots: false,
        infinite: true,
        slidesToShow: 1,
        slidesToScroll: 1,
        autoplay: false,
        centerMode: true,
        centerPadding: '260px',
        
        prevArrow:'<span class="arrow-prv"><i class="icon-arrow-left"></i></span>',
        nextArrow:'<span class="arrow-next"><i class="icon-arrow-right"></i></span>',
        responsive: [
            {
                breakpoint: 1199,
                settings: {
                    slidesToShow: 1,
                    centerPadding: '100px',
                }
            },{
                breakpoint: 762,
                settings: {
                    slidesToShow: 1,
                    centerPadding: '60px',
                }
            },{
                breakpoint: 580,
                settings: {
                    slidesToShow: 1,
                    centerPadding: '0px',
                }
            }
        ]
    });
    $('.hero-slider-10').slick({
        dots: true,
        infinite: true,
        slidesToShow: 1,
        slidesToScroll: 1,
        autoplay: false,
        prevArrow: false,
        nextArrow: false,
        customPaging : function(slider, i) {
            var title = $(slider.$slides[i].innerHTML).find('div[data-title]').data('title');
            return '<a class="pager__item"> '+title+' </a>';
        },
        responsive: [
            {
                breakpoint: 1199,
                settings: {
                    slidesToShow: 1,
                }
            }
        ]
    });

    /*================================
       Product Slider one
    ================================*/
    $('.product-slider-active').slick({
        dots: false,
        infinite: true,
        slidesToShow: 4,
        slidesToScroll: 1,
        autoplay: false,
        prevArrow:'<i class="icon-arrow-left arrow-prv"></i>',
        nextArrow:'<i class="icon-arrow-right arrow-next"></i>',
        responsive: [{
                breakpoint: 992,
                settings: {
                    slidesToShow: 3,
                }
            },{
                breakpoint: 762,
                settings: {
                    slidesToShow: 2,
                }
            },{
                breakpoint: 576,
                settings: {
                    slidesToShow: 1,
                }
            }
        ]
    });

    /*================================
        Product slider active
    ================================*/   
        
    $('.quickview-product-active').slick({
        slidesToShow: 1,
        autoplay: false,
        slidesToScroll: 1,
        prevArrow:'<i class="icon-chevron-left arrow-prv"></i>',
        nextArrow:'<i class="icon-chevron-right arrow-next"></i>',
        button:false,
    });	    
    $('.modal').on('shown.bs.modal', function (e) {
        $('.quickview-product-active').resize();
    })   

    $('.brand-slider-active').slick({
        dots: false,
        infinite: true,
        slidesToShow: 5,
        slidesToScroll: 1,
        autoplay: false,
        prevArrow:'<i class="icon-arrow-left arrow-prv"></i>',
        nextArrow:'<i class="icon-arrow-right arrow-next"></i>',
        responsive: [{
                breakpoint: 992,
                settings: {
                    slidesToShow: 4,
                }
            },{
                breakpoint: 762,
                settings: {
                    slidesToShow: 3,
                    prevArrow: false,
                    nextArrow: false
                }
            },{
                breakpoint: 480,
                settings: {
                    slidesToShow: 2,
                    prevArrow: false,
                    nextArrow: false
                }
            }
        ]
    });


    /* Product Details 2 Images Slider */
    $('.product-details-images-2').each(function(){
        var $this = $(this);
        var $thumb = $this.siblings('.product-details-thumbs-2');
        $this.slick({
            arrows: false,
            slidesToShow: 1,
            slidesToScroll: 1,
            autoplay: false,
            autoplaySpeed: 5000,
            dots: false,
            infinite: true,
            centerMode: false,
            centerPadding: 0,
            asNavFor: $thumb,
        });
    });
    $('.product-details-thumbs-2').each(function(){
        var $this = $(this);
        var $details = $this.siblings('.product-details-images-2');
        $this.slick({
            arrows: true,
            slidesToShow: 4,
            slidesToScroll: 1,
            autoplay: false,
            autoplaySpeed: 5000,
            vertical:true,
            verticalSwiping:true,
            dots: false,
            infinite: true,
            focusOnSelect: true,
            centerMode: false,
            centerPadding: 0,
            prevArrow: false,
            nextArrow: false,
            asNavFor: $details,
            responsive: [
            {
              breakpoint: 1200,
              settings: {
                slidesToShow: 3,
              }
            },
            {
              breakpoint: 991,
              settings: {
                slidesToShow: 3,
              }
            },
            {
              breakpoint: 767,
              settings: {
                slidesToShow: 4,
                vertical: false
              }
            },
            {
              breakpoint: 479,
              settings: {
                slidesToShow: 3,
                vertical: false
              }
            }
        ]
        });
    });


    
    // full pages
    
    $('#fullpage').fullpage({
		//options here
        navigation: true,
		autoScrolling:true,
		scrollHorizontally: true
	});

    
    
    // Instantiate EasyZoom instances

    var $easyzoom = $('.easyzoom').easyZoom();



    // Magnific Popup Video

    $('.popup-youtube').magnificPopup({
        type: 'iframe',
        removalDelay: 300,
        mainClass: 'mfp-fade'
    });
    
   // Magnific Popup Image

    $('.poppu-img').magnificPopup({
        type: 'image',
        gallery:{
            enabled:true
        }
    });

    /*--
        Shop filter active 
    ---------------------------------- */
    $('.shop-filter-active , .filter-close').on('click', function(e) {
        e.preventDefault();
        $('.product-filter-wrapper').slideToggle();
    })
    
    var shopFiltericon = $('.shop-filter-active , .filter-close');
    shopFiltericon.on('click', function() {
        $('.shop-filter-active').toggleClass('active');
    })

    /*--
    	Mesonry Activation      
    ---------------------------------------*/
     $('.projects-masonary-wrapper,.masonry-activation').imagesLoaded(function () {

        // filter items on button click
        // $('.messonry-button').on('click', 'button', function () {
        //     var filterValue = $(this).attr('data-filter');
        //     $(this).siblings('.is-checked').removeClass('is-checked');
        //     $(this).addClass('is-checked');
        //     $grid.isotope({
        //         filter: filterValue
        //     });
        // });

        // Masonry
        // var $grid = $('.masonry-wrap').masonry({
        //     itemSelector: '.masonary-item',
        //     percentPosition: true,
        //     transitionDuration: '0.7s',
        //     //itemSelector: '.grid-item',
        //     columnWidth: '.masonary-sizer'
        // });

        // init Isotope
        var $grid = $('.mesonry-list').isotope({
            percentPosition: true,
            transitionDuration: '0.7s',
            layoutMode: 'masonry',/*
            masonry: {
                columnWidth: '.resizer',
            }*/
        });

    });



    /*=========================================
    =            One page nav active          =
    ===========================================*/
    
    var top_offset = $('.navigation-menu--onepage').height() - 60;
    $('.navigation-menu--onepage ul').onePageNav({
        currentClass: 'active',
        scrollOffset: top_offset,
    });
    
    var top_offset_mobile = $('.header-area').height();
    $('.offcanvas-navigation--onepage ul').onePageNav({
        currentClass: 'active',
        scrollOffset: top_offset_mobile,
    });
    

    /*----------------------------
    	Cart Plus Minus Button
    ------------------------------ */
    var CartPlusMinus = $('.cart-plus-minus');
    CartPlusMinus.prepend('<div class="dec qtybutton"><i class="decrease icon-minus"></i></i></div>');
    CartPlusMinus.append('<div class="inc qtybutton"><i class="increase icon-plus"></i></i></div>');
    $(".qtybutton").on("click", function() {
        var $button = $(this);
        var oldValue = $button.parent().find("input").val();
        if ($button.text() === "+") {
            var newVal = parseFloat(oldValue) + 1;
        } else {
            // Don't allow decrementing below zero
            if (oldValue > 0) {
                var newVal = parseFloat(oldValue) - 1;
            } else {
                newVal = 1;
            }
        }
        $button.parent().find("input").val(newVal);
    });


    
    /*======================================
    =       Countdown Activation          =     
    ======================================*/
	$('[data-countdown]').each(function () {
		var $this = $(this),
			finalDate = $(this).data('countdown');
		$this.countdown(finalDate, function (event) {
			$this.html(event.strftime('<div class="single-countdown"><span class="single-countdown__time">%D</span><span class="single-countdown__text">Days</span></div><div class="single-countdown"><span class="single-countdown__time">%H</span><span class="single-countdown__text">Hours</span></div><div class="single-countdown"><span class="single-countdown__time">%M</span><span class="single-countdown__text">Mints</span></div><div class="single-countdown"><span class="single-countdown__time">%S</span><span class="single-countdown__text">Secs</span></div>'));
		});
	});
    
    /*=============================================
    =            reveal footer active            =
    =============================================*/
    
    var revealId = $(".reveal-footer"),
        heightFooter = revealId.height(),
        windowWidth = $(window).width()
    if (windowWidth > 991) {
        $(".site-wrapper-reveal").css({
            'margin-bottom': heightFooter + 'px'
        });
    }
    
    //  instagramFeed

    $.instagramFeed({
        'username': 'portfolio.devitems',
        'container': "#instagramFeed",
        'display_profile': false,
        'display_biography': false,
        'display_gallery': true,
        'styling': false,
        'items': 6,
        "image_size": "400",
        'margin': 5
    });

        
    /*--- showlogin toggle function ----*/
    $('.checkout-click-login').on('click', function(e) {
        e.preventDefault();
        $('.checkout-login-info').slideToggle(1000);
    });
    $('.checkout-click').on('click', function(e) {
        e.preventDefault();
        $('.checkout-coupon-info').slideToggle(1000);
    });


    /*================================
        YTplayer Video Active
    ================================*/
	$(".youtube-bg").YTPlayer({
        videoURL:"https://youtu.be/elOLEDKFbf0",
        containment:'.youtube-bg',
        mute:true,
        loop:true,
        showControls: true
        
    });
    


})(jQuery);
