(function() {
    'use strict';
    function loadTemplate(templatePath, placeholderId) {
        return fetch(templatePath)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Erreur lors du chargement du template: ${response.status}`);
                }
                return response.text();
            })
            .then(data => {
                const placeholder = document.getElementById(placeholderId);
                if (placeholder) {
                    placeholder.innerHTML = data;
                    const event = new CustomEvent('templateLoaded', {
                        detail: { template: templatePath, placeholder: placeholderId }
                    });
                    document.dispatchEvent(event);
                }
            })
            .catch(error => {
            });
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            loadTemplate('templates/header.html', 'header-placeholder');
            loadTemplate('templates/footer.html', 'footer-placeholder');
        });
    } else {
        loadTemplate('templates/header.html', 'header-placeholder');
        loadTemplate('templates/footer.html', 'footer-placeholder');
    }
})();
