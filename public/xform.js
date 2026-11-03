(function () {
    'use strict';
    document.addEventListener('submit', async function (event) {
        var form = event.target.closest('[data-xform-form]');
        if (!form) return;
        event.preventDefault();
        var wrap = form.closest('[data-xform-wrap]');
        var status = wrap ? wrap.querySelector('[data-xform-status]') : null;
        var button = form.querySelector('button[type="submit"],input[type="submit"]');
        if (button) button.disabled = true;
        if (status) { status.textContent = 'Sending…'; status.className = 'xform-status'; }
        try {
            var response = await fetch(XFormConfig.endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-XForm-Nonce': XFormConfig.nonce, 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                body: new URLSearchParams(new FormData(form)).toString()
            });
            var result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'Submission failed.');
            if (status) { status.textContent = result.message; status.classList.add('xform-success'); }
            form.reset();
        } catch (error) {
            if (status) { status.textContent = error.message || 'Please try again.'; status.classList.add('xform-error'); }
        } finally {
            if (button) button.disabled = false;
        }
    });
})();