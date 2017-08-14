$(document).ready(function() {

	console.log('Ready.');

$('.docs-sidebar').affix({
  offset: {
    top: 100,
    bottom: function () {
      return (this.bottom = $('.footer').outerHeight(true))
    }
  }
});

/*
----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----
INITALIZATION
----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----
*/

	$('.max-height').css('height', window.innerHeight);


/*
----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----
DEBUG MODE
----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- ----- -----
*/

	$('#debug').click(function() {
		$('.outline').toggleClass('debug');
	});

});