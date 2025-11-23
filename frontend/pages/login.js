// Grid hover effects
let gridElements = document.querySelectorAll(".grid-element");

gridElements.forEach((element) => { 
    element.addEventListener("mouseenter", () => { 
        element.classList.add("hover-effect"); 
    });

    element.addEventListener("mouseleave", () => { 
        setTimeout(() => {
            element.classList.remove("hover-effect");
        }, 300);
    });
});

// Login form handling
document.querySelector('form').addEventListener('submit', (e) => {
    e.preventDefault();
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    
    console.log('Login attempt:', { email, password });
    
    // Add your login logic here
    alert('Login functionality - to be implemented');
});
