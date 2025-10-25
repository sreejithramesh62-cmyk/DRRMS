const API = '/drrms/api/index.php';
document.getElementById('uname').textContent = localStorage.getItem('userName') || 'User';

async function load() {
  const ev = await fetch(`${API}/user/events`).then(r => r.json());
  const rs = await fetch(`${API}/user/resources`).then(r => r.json());
  const vs = await fetch(`${API}/user/volunteers`).then(r => r.json());
  const nt = await fetch(`${API}/admin/notifications`).then(r => r.json());

  document.getElementById('eCount').textContent = ev.length;
  document.getElementById('rCount').textContent = (rs || []).reduce((a,b)=> a + (+b.total || 0), 0);
  document.getElementById('vCount').textContent = vs.length;

  const sel = document.getElementById('eventSelect');
  ev.forEach(e => {
    const opt = document.createElement('option');
    opt.value = e.event_id; opt.textContent = e.event_name;
    sel.appendChild(opt);
  });

  const ul = document.getElementById('notes');
  ul.innerHTML = '';
  nt.slice(0,5).forEach(n => {
    const li = document.createElement('li');
    li.innerHTML = `<b>${n.title}</b> <small>${new Date(n.created_at).toLocaleString()}</small><p>${n.message}</p>`;
    ul.appendChild(li);
  });
}

async function sendRequest() {
  const eid = document.getElementById('eventSelect').value;
  const msg = document.getElementById('msg').value;
  await fetch(`${API}/user/request_volunteer`, {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ event_id: eid, message: msg })
  });
  alert('Request sent!');
  location.reload();
}

load();
