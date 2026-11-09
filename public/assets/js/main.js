
// Customer account dropdown

const accountMenuToggle = document.getElementById('accountMenuToggle');
const accountDropdown = document.getElementById('accountDropdown');

if (accountMenuToggle && accountDropdown) {
    accountMenuToggle.addEventListener('click', function () {
        const isOpen = accountDropdown.classList.toggle('show');

        accountMenuToggle.setAttribute('aria-expanded', isOpen);
    });

    document.addEventListener('click', function (event) {
        if (!event.target.closest('.account-menu')) {
            accountDropdown.classList.remove('show');
            accountMenuToggle.setAttribute('aria-expanded', 'false');
        }
    });
}



// CubeTech Live Product Search and Filtering
const filterForm = document.getElementById('filterForm');

if (filterForm) {
    const searchInput = document.getElementById('searchInput');
    const productResults = document.getElementById('productResults');
    const productCount = document.getElementById('productCount');

    let searchTimeout;

    async function updateProducts() {
        const formData = new FormData(filterForm);
        const params = new URLSearchParams(formData);

        try {
            const response = await fetch(
                `index.php?${params.toString()}`,
                {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }
            );

            if (!response.ok) {
                throw new Error('Unable to load products.');
            }

            const html = await response.text();
            const parser = new DOMParser();
            const newDocument = parser.parseFromString(html, 'text/html');

            const newResults = newDocument.getElementById('productResults');
            const newCount = newDocument.getElementById('productCount');

            if (newResults && newCount) {
                productResults.innerHTML = newResults.innerHTML;
                productCount.textContent = newCount.textContent;

                history.replaceState(null, '', `index.php?${params.toString()}`);
            }

        } catch (error) {
            console.error('Product filtering failed:', error);
        }
    }

    // Search automatically while typing.
    searchInput.addEventListener('input', function () {
        clearTimeout(searchTimeout);

        searchTimeout = setTimeout(updateProducts, 300);
    });

    // Update when category or sorting changes.
    filterForm.querySelectorAll('select').forEach(function (select) {
        select.addEventListener('change', updateProducts);
    });

    // Prevent the page from reloading when Apply is clicked.
    filterForm.addEventListener('submit', function (event) {
        event.preventDefault();
        clearTimeout(searchTimeout);
        updateProducts();
    });
}


// Admin Mobile Navigation
const adminMenuToggle = document.getElementById('adminMenuToggle');
const adminSidebarContent = document.getElementById('adminSidebarContent');

if (adminMenuToggle && adminSidebarContent) {

    adminMenuToggle.addEventListener('click', function () {

        const isOpen = adminSidebarContent.classList.toggle('show');

        adminMenuToggle.textContent = isOpen ? '✕' : '☰';

        adminMenuToggle.setAttribute('aria-expanded', isOpen);
    });
}