(function () {
    'use strict';

    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') ?? '' : '';

    /**
     * Wrapper around fetch that automatically appends the CSRF header.
     */
    window.csrfFetch = function csrfFetch(input, init = {}) {
        const options = { ...init };
        const headers = new Headers(init && init.headers ? init.headers : undefined);

        if (csrfToken) {
            if (!headers.has('X-CSRF-Token')) {
                headers.set('X-CSRF-Token', csrfToken);
            }
        }

        options.headers = headers;

        return fetch(input, options);
    };

    /**
     * Enhance the account theme selector with live preview messaging.
     */
    function enhanceThemeSelector() {
        const select = document.querySelector('[data-theme-select]');

        if (!select) {
            return;
        }

        const message = document.querySelector('[data-theme-select-message]');
        const defaultLabel = select.getAttribute('data-theme-default-label') || 'site default theme';
        const body = document.body;
        const initialValue = (select.value || '').trim();

        function selectedOption() {
            return select.options[select.selectedIndex] || null;
        }

        function updateMessage() {
            const option = selectedOption();
            const selectedValue = option ? option.value.trim() : '';
            const optionLabel = option ? (option.dataset.themeLabel || option.textContent || '').trim() : '';

            if (body) {
                if (selectedValue && selectedValue !== initialValue) {
                    body.setAttribute('data-preview-theme', selectedValue);
                } else {
                    body.removeAttribute('data-preview-theme');
                }
            }

            if (message) {
                if (selectedValue) {
                    if (selectedValue === initialValue) {
                        message.textContent = `Using the ${optionLabel || selectedValue} theme.`;
                    } else {
                        message.textContent = `Previewing “${optionLabel || selectedValue}”. Save to apply it permanently.`;
                    }
                } else {
                    message.textContent = `Using the ${defaultLabel}.`;
                }
            }
        }

        select.addEventListener('change', updateMessage);
        updateMessage();
    }

    document.addEventListener('DOMContentLoaded', () => {
        enhanceThemeSelector();
    });
})();
