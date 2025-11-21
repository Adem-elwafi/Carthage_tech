// Minimal enhancement: prevent empty search submissions and demo handling
document.addEventListener('DOMContentLoaded', () => {
	// Hamburger Menu Toggle
	const menuToggle = document.querySelector('.menu-toggle');
	const mainNav = document.querySelector('.main-nav');
	
	if (menuToggle && mainNav) {
		menuToggle.addEventListener('click', () => {
			menuToggle.classList.toggle('active');
			mainNav.classList.toggle('active');
			
			// Update aria-expanded for accessibility
			const isExpanded = menuToggle.classList.contains('active');
			menuToggle.setAttribute('aria-expanded', isExpanded);
			
			// Prevent body scroll when menu is open
			if (isExpanded) {
				document.body.style.overflow = 'hidden';
			} else {
				document.body.style.overflow = '';
			}
		});
		
		// Close menu when clicking outside
		document.addEventListener('click', (e) => {
			if (!menuToggle.contains(e.target) && !mainNav.contains(e.target) && mainNav.classList.contains('active')) {
				menuToggle.classList.remove('active');
				mainNav.classList.remove('active');
				menuToggle.setAttribute('aria-expanded', 'false');
				document.body.style.overflow = '';
			}
		});
		
		// Close menu when clicking on a nav link
		const navLinks = mainNav.querySelectorAll('a');
		navLinks.forEach(link => {
			link.addEventListener('click', () => {
				menuToggle.classList.remove('active');
				mainNav.classList.remove('active');
				menuToggle.setAttribute('aria-expanded', 'false');
				document.body.style.overflow = '';
			});
		});
	}

	const form = document.querySelector('.search');
	const input = form ? form.querySelector('input[type="search"]') : null;

	if (form && input) {
		form.addEventListener('submit', (e) => {
			const q = String(input.value || '').trim();
			if (!q) {
				e.preventDefault();
				input.focus();
				input.setAttribute('aria-invalid', 'true');
				input.placeholder = 'Entrez un terme de recherche…';
				return;
			}

			// Demo: prevent navigation and log query. Replace with real search handling.
			e.preventDefault();
			console.log('Recherche:', q);
		});

		input.addEventListener('input', () => {
			if (input.getAttribute('aria-invalid') === 'true' && input.value.trim()) {
				input.removeAttribute('aria-invalid');
			}
  
				// Add to cart demo interactions
				const cartButtons = document.querySelectorAll('.add-to-cart');
				cartButtons.forEach((btn) => {
					btn.addEventListener('click', (e) => {
						e.preventDefault();
						const original = btn.textContent;
						btn.disabled = true;
						btn.textContent = 'Ajouté !';
						btn.classList.add('added');
						setTimeout(() => {
							btn.disabled = false;
							btn.textContent = original;
							btn.classList.remove('added');
						}, 1200);
						const name = btn.getAttribute('data-product') || 'Produit';
						console.log(`Ajout au panier: ${name}`);
					});
				});
		});
	}

	// Animated grid background hover effects
	const gridElements = document.querySelectorAll('.hero .grid-element');
	
	gridElements.forEach((element) => { 
		element.addEventListener('mouseenter', () => { 
			element.classList.add('hover-effect'); 
		});

		element.addEventListener('mouseleave', () => { 
			setTimeout(() => {
				element.classList.remove('hover-effect');
			}, 300);
		});
	});
});
