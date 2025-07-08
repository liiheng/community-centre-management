const slides = document.querySelectorAll(".bg-slide");
let current = 0;

function showNextSlide() {
    slides[current].classList.remove("active");
    current = (current + 1) % slides.length;
    slides[current].classList.add("active");
}

setInterval(showNextSlide, 5000); 
