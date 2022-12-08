var square = document.createElement('div');
square.style.width = '100px';
square.style.height = '100px';
square.style.backgroundColor = 'blue';
document.body.appendChild(square);
/* create a red circle */
var circle = document.createElement('div');
circle.style.width = '100px';
circle.style.height = '100px';
circle.style.borderRadius = '50px';
circle.style.backgroundColor = 'red';
document.body.appendChild(circle);
/* make circle perspective of 1000px */
circle.style.perspective = '1000px';
/* create an animation where circle rotate on Y axis */
var animation = document.createElement('div');
animation.style.animation = 'spin 2s infinite linear';
animation.style.transformStyle = 'preserve-3d';
circle.appendChild(animation);