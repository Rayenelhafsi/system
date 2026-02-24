function toggleDropdown() {
    const dropdown = document.getElementById('dropdown');
    dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
}

// Rafraîchissement toutes les 10s
setInterval(fetchNotifications, 10000);

function fetchNotifications() {
    fetch('fetch_notifications.php')
        .then(response => response.json())
        .then(data => {
            document.getElementById('notifCount').textContent = data.count;
            document.getElementById('nbMatchs').textContent = data.count;

            const list = document.getElementById('notifList');
            list.innerHTML = '';
            data.matches.forEach(match => {
                const li = document.createElement('li');
                li.textContent = `${match.client_nom} → ${match.bien_titre} (${match.score}%)`;
                list.appendChild(li);
            });
        });
}
