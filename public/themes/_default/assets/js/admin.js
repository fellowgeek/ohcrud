// Framework7 app instance
var app = null;
// Assign Dom7 to $$ for easier DOM manipulation
var $$ = Dom7;


// Execute the following code when the DOM is fully loaded
document.addEventListener('DOMContentLoaded', function () {

    // Initialize Framework7 app with iOS theme
    var theme = 'ios';
    app = new Framework7({
        el: '#app', // Root element
        theme, // App theme
        name: 'ohCRUD', // App name
        panel: {
            swipe: false,
            resizable: false,
        }, // Enable swipe panel
        routes: [ // Define app routes
            {
                name: 'home',
                path: '/',
                // url: 'pages/home.html',
            }
        ]
    });

});

function notify(options = {}) {
    /*
    icon: '<i class="f7-icons icon color-red">exclamationmark_triangle_fill</i>',
    title: 'Framework7',
    titleRightText: 'now',
    subtitle: 'Notification with close on click',
    text: 'Click me to close',
    closeOnClick: true,
    */
    let notificationCloseOnClick = app.notification.create(options);
    notificationCloseOnClick.open();
}
