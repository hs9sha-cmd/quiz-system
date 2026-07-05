// js/app.js

const API_BASE = 'api/';

// Utility to render the global footer
function renderFooter() {
    const footer = document.createElement('footer');
    footer.className = 'custom-footer';
    footer.innerHTML = `
        <p><span>HS9SHA</span> - Wednesday By M&M Service</p>
        <p>081-738-1914</p>
    `;
    document.body.appendChild(footer);
}

// Utility for fetching API
async function apiCall(endpoint, data = null) {
    const options = {
        method: data ? 'POST' : 'GET',
        credentials: 'same-origin'
    };
    
    if (data) {
        if (data instanceof FormData) {
            options.body = data;
        } else {
            const formData = new URLSearchParams();
            for (const key in data) {
                formData.append(key, data[key]);
            }
            options.body = formData;
            options.headers = {
                'Content-Type': 'application/x-www-form-urlencoded'
            };
        }
    }

    try {
        const response = await fetch(`${API_BASE}${endpoint}`, options);
        return await response.json();
    } catch (err) {
        console.error('API Error:', err);
        return { success: false, message: 'Network error' };
    }
}

function showAlert(message, type = 'error', containerId = 'alert-container') {
    const container = document.getElementById(containerId);
    if (container) {
        container.innerHTML = `<div class="alert ${type}">${message}</div>`;
        setTimeout(() => { container.innerHTML = ''; }, 5000);
    } else {
        alert(message);
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    renderFooter();
});
