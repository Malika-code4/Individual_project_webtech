/**
 * Authentication Logic
 * Handles login, signup, and validation
 */

const API_Base = 'auth.php'; // Updated to use local auth.php

// State
let currentTab = 'login';

// DOM Elements
const loginForm = document.getElementById('loginForm');
const signupForm = document.getElementById('signupForm');
const alertBox = document.getElementById('alertBox');

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    // Check if already logged in
    const token = localStorage.getItem('session_token');
    if (token) {
        verifySession(token);
    }
});

// Tab Switching
function switchTab(tab) {
    currentTab = tab;

    const tabs = document.querySelectorAll('.tab-btn');
    const forms = document.querySelectorAll('.form-section');

    // Reset all
    tabs.forEach(t => t.classList.remove('active'));
    forms.forEach(f => {
        f.classList.remove('active');
        f.style.display = 'none';
    });

    // Activate target
    if (tab === 'login') {
        if (tabs[0]) tabs[0].classList.add('active');
        const loginSection = document.getElementById('loginContent');
        if (loginSection) {
            loginSection.classList.add('active');
            loginSection.style.display = 'block';
        }
    } else {
        if (tabs[1]) tabs[1].classList.add('active');
        const signupSection = document.getElementById('signupContent');
        if (signupSection) {
            signupSection.classList.add('active');
            signupSection.style.display = 'block';
        }
    }

    hideAlert();
}

// Ensure global scope access if needed, or bind in JS
document.addEventListener('DOMContentLoaded', () => {
    // Re-bind clicks safely
    const tabs = document.querySelectorAll('.tab-btn');
    if (tabs[0]) tabs[0].onclick = () => switchTab('login');
    if (tabs[1]) tabs[1].onclick = () => switchTab('signup');
});

// Validation Logic
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function checkPasswordStrength(password) {
    let strength = 0;
    if (password.length >= 6) strength++;
    if (password.length >= 10) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;
    return strength;
}

function updatePasswordStrength(input) {
    const val = input.value;
    const strength = checkPasswordStrength(val);
    const bar = document.getElementById('strengthBar');
    const text = document.getElementById('strengthText');

    // Reset classes
    bar.className = 'password-strength-bar';

    if (val.length === 0) {
        bar.style.width = '0%';
        text.textContent = '';
        return;
    }

    if (strength < 2) {
        bar.classList.add('weak');
        bar.style.width = '33%';
        text.textContent = 'Weak';
        text.style.color = '#e74c3c';
    } else if (strength < 4) {
        bar.classList.add('medium');
        bar.style.width = '66%';
        text.textContent = 'Medium';
        text.style.color = '#f1c40f';
    } else {
        bar.classList.add('strong');
        bar.style.width = '100%';
        text.textContent = 'Strong';
        text.style.color = '#2ecc71';
    }
}

// Alert Handling
function showAlert(msg, type = 'error') {
    alertBox.textContent = msg;
    alertBox.className = `alert alert-${type} show`;
    setTimeout(() => {
        alertBox.className = 'alert';
    }, 5000);
}

function hideAlert() {
    alertBox.className = 'alert';
}

// API Calls
async function handleLogin(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button');
    const originalText = btn.textContent;
    btn.textContent = 'Processing...';
    btn.disabled = true;

    const username = document.getElementById('loginUsername').value;
    const password = document.getElementById('loginPassword').value;
    const rememberMe = document.getElementById('rememberMe').checked;

    try {
        const res = await fetch(`${API_Base}?action=login`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password, remember_me: rememberMe })
        });

        const text = await res.text(); // Get raw text first
        let data;

        try {
            data = JSON.parse(text);
        } catch (jsonErr) {
            console.error('SERVER ERROR:', text);
            throw new Error(`Server returned invalid JSON. Raw output logged in console.`);
        }

        if (data.success) {
            showAlert('Login Successful! Redirecting...', 'success');
            localStorage.setItem('session_token', data.session_token);
            localStorage.setItem('player', JSON.stringify({
                id: data.player_id,
                username: data.username
            }));
            setTimeout(() => window.location.href = 'index.html', 1000);
        } else {
            showAlert(data.message || 'Login failed');
        }
    } catch (err) {
        console.error('LOGIN ERROR:', err);
        showAlert(err.message || 'Connection Failed');
    } finally {
        btn.textContent = originalText;
        btn.disabled = false;
    }
}

async function handleSignup(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button');
    const originalText = btn.textContent;
    btn.textContent = 'Creating Account...';
    btn.disabled = true;

    const username = document.getElementById('signupUsername').value;
    const email = document.getElementById('signupEmail').value;
    const password = document.getElementById('signupPassword').value;
    const confirm = document.getElementById('signupConfirm').value;

    if (password !== confirm) {
        showAlert('Passwords do not match');
        btn.textContent = originalText;
        btn.disabled = false;
        return;
    }

    try {
        const res = await fetch(`${API_Base}?action=signup`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, email, password })
        });
        const data = await res.json();

        if (data.success) {
            showAlert('Account Created! Redirecting...', 'success');

            // Auto-login logic
            localStorage.setItem('session_token', data.session_token);
            localStorage.setItem('player', JSON.stringify({
                id: data.player_id,
                username: data.username
            }));

            setTimeout(() => window.location.href = 'index.html', 1000);
        } else {
            showAlert(data.message);
        }
    } catch (err) {
        showAlert('Connection Failed: ' + err.message);
    } finally {
        btn.textContent = originalText;
        btn.disabled = false;
    }
}

async function verifySession(token) {
    try {
        const res = await fetch(`${API_Base}?action=verify_session`, {
            method: 'POST',
            body: JSON.stringify({ session_token: token })
        });
        const data = await res.json();
        if (data.success) {
            window.location.href = 'index.html';
        }
    } catch (err) {
        console.error(err);
    }
}
