// Minimal enhancement: prevent empty search submissions and demo handling
document.addEventListener('DOMContentLoaded', () => {
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
