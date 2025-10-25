const API = '/drrms/api/index.php';
document.getElementById('welcomeName').textContent = localStorage.getItem('userName') || 'Admin';

async function loadDashboard() {
  try {
    const allocRes = await fetch(`${API}/admin/allocations`);
    const allocData = await allocRes.json();

    document.getElementById('allocCount').textContent = allocData.length;

    const tbody = document.querySelector('#allocTable tbody');
    tbody.innerHTML = '';
    allocData.forEach(a => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${a.event_name || 'N/A'}</td>
        <td>${a.center_name || '-'}</td>
        <td>${a.resource_name || '-'}</td>
        <td>${a.quantity_allocated}</td>
        <td>${a.allocation_date}</td>
      `;
      tbody.appendChild(tr);
    });

    const uniqueEvents = new Set(allocData.map(a => a.event_id)).size;
    document.getElementById('eventCount').textContent = uniqueEvents;

    const totalResources = allocData.reduce((sum, a) => sum + (a.quantity_allocated || 0), 0);
    document.getElementById('resCount').textContent = totalResources;

    const notes = await (await fetch(`${API}/admin/notifications`)).json();
    document.getElementById('noteCount').textContent = notes.length;

  } catch (err) {
    console.error('Load failed', err);
    alert('Failed to load dashboard data from ' + API);
  }
}

loadDashboard();
