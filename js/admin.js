// js/admin.js: Enhanced Admin JS with Full CRUD
let currentEditId = null;

document.addEventListener('DOMContentLoaded', function() {
    loadUsers();
});

function loadUsers() {
    fetch('php/api/users.php')
        .then(response => response.json())
        .then(users => {
            if (users.error) {
                alert(users.error);
                return;
            }
            const userList = document.getElementById('userList');
            userList.innerHTML = '';
            users.forEach(user => {
                const row = `<tr>
                    <td>${user.username}</td>
                    <td>${user.name}</td>
                    <td>${user.role === 'admin' ? '<span class="badge bg-primary">관리자</span>' : '<span class="badge bg-secondary">사용자</span>'}</td>
                    <td class="text-nowrap">
                        <button class="btn btn-sm btn-outline-info me-1" onclick="testApi(${user.id})"><i class="bi bi-cloud-check"></i> API 테스트</button>
                        <button class="btn btn-sm btn-outline-success me-2" onclick="testWs(${user.id})"><i class="bi bi-broadcast-pin"></i> WS 테스트</button>
                        <button class="btn btn-sm btn-warning me-1" onclick="editUser(${user.id})"><i class="bi bi-pencil"></i> 편집</button>
                        <button class="btn btn-sm btn-danger" onclick="deleteUser(${user.id})"><i class="bi bi-trash"></i> 삭제</button>
                    </td>
                </tr>`;
                userList.innerHTML += row;
            });
        })
        .catch(error => console.error('Error:', error));
}

function addUser() {
    const username = document.getElementById('newUsername').value;
    const password = document.getElementById('newPassword').value;
    const name = document.getElementById('newName').value;
    const role = document.getElementById('newRole').value;

    if (!username || !password || !name) {
        alert('모든 필드를 입력하세요.');
        return;
    }

    fetch('php/api/add_user.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username, password, name, role })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadUsers();
            bootstrap.Modal.getInstance(document.getElementById('addUserModal')).hide();
        } else {
            alert('추가 실패: ' + data.message);
        }
    });
}

function editUser(id) {
    fetch(`php/api/get_user.php?id=${id}`)
        .then(response => response.json())
        .then(user => {
            if (user.error) {
                alert(user.error);
                return;
            }
            document.getElementById('editUserId').value = user.id;
            document.getElementById('editUsername').value = user.username;
            document.getElementById('editName').value = user.name;
            document.getElementById('editRole').value = user.role;
            document.getElementById('editPassword').value = '';
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        });
}

function updateUser() {
    const id = document.getElementById('editUserId').value;
    const username = document.getElementById('editUsername').value;
    const password = document.getElementById('editPassword').value;
    const name = document.getElementById('editName').value;
    const role = document.getElementById('editRole').value;

    if (!username || !name) {
        alert('아이디와 이름을 입력하세요.');
        return;
    }

    const body = { id, username, name, role };
    if (password) body.password = password;

    fetch('php/api/update_user.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadUsers();
            bootstrap.Modal.getInstance(document.getElementById('editUserModal')).hide();
        } else {
            alert('업데이트 실패: ' + data.message);
        }
    });
}

function deleteUser(id) {
    if (!confirm('정말 삭제하시겠습니까?')) return;
    fetch('php/api/delete_user.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadUsers();
        } else {
            alert('삭제 실패: ' + data.message);
        }
    });
}

async function postAdminTest(userId, kind) {
    try {
        const res = await fetch('/api/admin/test', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ userId, kind })
        });
        if (!res.ok) {
            const msg = await res.text().catch(() => '');
            throw new Error(`HTTP ${res.status} ${res.statusText}: ${msg}`);
        }
        const data = await res.json();
        const lines = (data.details || []).join('\n');
        alert(`${kind.toUpperCase()} 테스트 ${data.ok ? '성공' : '실패'}\n` + lines);
    } catch (e) {
        console.error(e);
        alert(`${kind.toUpperCase()} 테스트 중 오류: ${e.message || e}`);
    }
}

function testApi(userId) { postAdminTest(userId, 'api'); }
function testWs(userId)  { postAdminTest(userId, 'ws'); }
