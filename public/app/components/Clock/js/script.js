// Get the clock canvas element by its ID
var canvas = document.getElementById("com_clock_canvas");

// Set the canvas dimensions to match its offset dimensions
canvas.width = canvas.offsetWidth;
canvas.height = canvas.offsetHeight;

// Adjust the canvas dimensions to ensure a square shape
if (canvas.height < canvas.width) canvas.width = canvas.height;
if (canvas.width < canvas.height) canvas.height = canvas.width;

// Get the 2D rendering context for the canvas
var ctx = canvas.getContext("2d");

// Set the clock radius to half of the canvas height
var radius = canvas.height / 2;

// Translate the canvas origin to the center
ctx.translate(radius, radius);

// Reduce the effective radius for drawing clock components
radius = radius * 0.90;

// Function to draw the clock components
function drawClock() {
    drawFace(ctx, radius);
    drawNumbers(ctx, radius - 7);
    drawTime(ctx, radius);
}

// Function to draw the clock face
function drawFace(ctx, radius) {
    var grad;

    // Draw the clock circle
    ctx.beginPath();
    ctx.arc(0, 0, radius, 0, 2 * Math.PI);
    ctx.fillStyle = 'white';
    ctx.fill();

    // Create a radial gradient for the clock border
    grad = ctx.createRadialGradient(0, 0, radius * 0.95, 0, 0, radius * 1.05);
    grad.addColorStop(0, '#666');
    grad.addColorStop(0.5, '#444');
    grad.addColorStop(1, '#666');

    // Apply the gradient to the clock border
    ctx.strokeStyle = grad;
    ctx.lineWidth = radius * 0.1;
    ctx.stroke();

    // Draw the center circle
    ctx.beginPath();
    ctx.arc(0, 0, radius * 0.1, 0, 2 * Math.PI);
    ctx.fillStyle = '#333';
    ctx.fill();
}

// Function to draw the clock numbers
function drawNumbers(ctx, radius) {
    var ang;
    var num;

    // Set font properties for clock numbers
    ctx.font = radius * 0.25 + "px arial";
    ctx.textBaseline = "middle";
    ctx.textAlign = "center";

    // Draw numbers around the clock
    for (num = 1; num < 13; num++) {
        ang = num * Math.PI / 6;
        ctx.rotate(ang);
        ctx.translate(0, -radius * 0.85);
        ctx.rotate(-ang);
        ctx.fillText(num.toString(), 0, 0);
        ctx.rotate(ang);
        ctx.translate(0, radius * 0.85);
        ctx.rotate(-ang);
    }
}

// Function to draw the clock hands
function drawTime(ctx, radius) {
    var now = new Date();
    var hour = now.getHours();
    var minute = now.getMinutes();
    var second = now.getSeconds();

    // Normalize hours to 12-hour format
    hour = hour % 12;

    // Calculate the angles for the clock hands
    hour = (hour * Math.PI / 6) + (minute * Math.PI / (6 * 60)) + (second * Math.PI / (360 * 60));
    minute = (minute * Math.PI / 30) + (second * Math.PI / (30 * 60));
    second = (second * Math.PI / 30);

    // Draw the clock hands
    drawHand(ctx, hour, radius * 0.5, radius * 0.07, '#333'); // hour hand
    drawHand(ctx, minute, radius * 0.8, radius * 0.07, '#333'); // minute hand
    drawHand(ctx, second, radius * 0.9, radius * 0.02, '#cf0000'); // second hand
}

// Function to draw a clock hand
function drawHand(ctx, pos, length, width, color) {
    ctx.strokeStyle = color;
    ctx.beginPath();
    ctx.lineWidth = width;
    ctx.lineCap = "round";
    ctx.moveTo(0, 0);
    ctx.rotate(pos);
    ctx.lineTo(0, -length);
    ctx.stroke();
    ctx.rotate(-pos);
}

// Call the drawClock function initially
drawClock();

// Update the clock every second
setInterval(() => {
    drawClock();
}, 1000);
