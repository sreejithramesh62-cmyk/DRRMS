document.getElementById('regForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const msg = document.getElementById('msg');
  const data = {
    name: document.getElementById('name').value,
    email: document.getElementById('email').value,
    phone: document.getElementById('phone').value,
    password: document.getElementById('password').value
  };

  try {
    const res = await fetch('/drrms2/api/index.php/auth/register', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    const d = await res.json();
    if (d.success) {
      msg.className = 'message show success'; msg.textContent = 'Success! Redirecting...';
      setTimeout(() => location.href = 'login.html', 800);
    } else {
      showError(d.error);
    }
  } catch (e) {
    showError('Network error');
  }
});

function showError(text) {
  const msg = document.getElementById('msg');
  msg.className = 'message show error'; msg.textContent = text;
}
