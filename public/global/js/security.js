// Manage only the signed-in user's authenticator; never render server messages as HTML.
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('securityForm');
    if (form === null) return;
    const message = document.getElementById('securityMessage');
    const enrollment = document.getElementById('securityEnrollment');
    const password = document.getElementById('securityPassword');
    const currentCode = document.getElementById('securityCurrentCode');
    async function request(method, values) {
        const response = await fetch(__OHCRUD_BASE_API_ROUTE__ + '/users/' + method + '/', {
            method: 'POST', credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({...values, CSRF: __CSRF__})
        });
        const result = await response.json();
        if (response.ok === false || result.success !== true) {
            throw new Error((result.errors || ['Unable to update authenticator.']).join(' '));
        }
        return result.data;
    }
    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        enrollment.hidden = true;
        try {
            const data = await request('beginTOTP', {PASSWORD: password.value, TOTP: currentCode.value});
            const uri = new URL(data.QR_CODE);
            document.getElementById('securityKey').value = uri.searchParams.get('secret');
            const qr = new QRCode({content: data.QR_CODE, container: 'svg-viewbox', padding: 0, join: true});
            document.getElementById('securityQR').innerHTML = qr.svg();
            enrollment.hidden = false;
            message.textContent = 'Scan the code and confirm before leaving this page.';
        } catch (error) {
            message.textContent = error.message;
        } finally {
            password.value = '';
            currentCode.value = '';
        }
    });
    document.getElementById('securityConfirmForm').addEventListener('submit', async function (event) {
        event.preventDefault();
        const code = document.getElementById('securityNewCode');
        try {
            await request('confirmTOTP', {TOTP: code.value});
            enrollment.hidden = true;
            document.getElementById('securityKey').value = '';
            document.getElementById('securityQR').textContent = '';
            message.textContent = 'Authenticator enabled. Other sessions have been invalidated.';
        } catch (error) {
            message.textContent = error.message;
        } finally {
            code.value = '';
        }
    });
    document.getElementById('securityDisable').addEventListener('click', async function () {
        try {
            await request('disableTOTP', {PASSWORD: password.value, TOTP: currentCode.value});
            enrollment.hidden = true;
            document.getElementById('securityKey').value = '';
            document.getElementById('securityQR').textContent = '';
            message.textContent = 'Authenticator disabled. Other sessions have been invalidated.';
        } catch (error) {
            message.textContent = error.message;
        } finally {
            password.value = '';
            currentCode.value = '';
        }
    });
});
