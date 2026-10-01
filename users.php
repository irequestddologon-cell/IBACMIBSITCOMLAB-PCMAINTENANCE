<?php
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/includes/functions.php';
$pdo = getDB();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $fullName = trim($_POST['full_name']);
    $role = $_POST['role'];
    $password = $_POST['password'];

    if ($username === '' || $fullName === '' || strlen($password) < 6) {
        $error = 'Please fill all fields. Password must be at least 6 characters.';
    } else {
        try {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare("INSERT INTO users (username, password_hash, full_name, role) VALUES (?,?,?,?)")
                ->execute([$username, $hash, $fullName, $role]);
        } catch (PDOException $e) {
            $error = str_contains($e->getMessage(), 'Duplicate') ? 'Username already exists.' : 'Could not create user.';
        }
    }
}

if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    if ($delId !== (int)currentUser()['id']) {
        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$delId]);
    }
    header('Location: users.php');
    exit;
}

$users = $pdo->query("SELECT * FROM users ORDER BY id")->fetchAll();
$pageTitle = 'User Accounts';
require_once __DIR__ . '/includes/header.php';
?>
<div class="topbar">
    <div><h2>User Accounts</h2><div class="subtitle">Manage administrator and technician logins</div></div>
</div>
<div class="content">
    <div class="grid" style="grid-template-columns: 1fr 1.4fr;align-items:start;">
        <div class="card">
            <div class="card-header"><h3>Add User</h3></div>
            <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
            <form method="POST">
                <div class="form-row"><label>Username</label><input type="text" name="username" required></div>
                <div class="form-row"><label>Full Name</label><input type="text" name="full_name" required></div>
                <div class="form-row">
                    <label>Role</label>
                    <select name="role"><option value="technician">Technician</option><option value="admin">Admin</option></select>
                </div>
                <div class="form-row"><label>Password</label><input type="password" name="password" minlength="6" required></div>
                <button type="submit" name="add_user" value="1" class="btn btn-primary">Create Account</button>
            </form>
        </div>
        <div class="card">
            <div class="card-header"><h3>Existing Accounts</h3></div>
            <table>
                <thead><tr><th>Username</th><th>Full Name</th><th>Role</th><th>Created</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= e($u['username']) ?></td>
                    <td><?= e($u['full_name']) ?></td>
                    <td><span class="badge <?= $u['role']==='admin'?'badge-repair':'badge-healthy' ?>"><?= e($u['role']) ?></span></td>
                    <td><?= formatDate($u['created_at']) ?></td>
                    <td>
                        <?php if ($u['id'] != currentUser()['id']): ?>
                        <a href="users.php?delete=<?= $u['id'] ?>" class="btn btn-sm btn-danger" data-confirm="Remove user <?= e($u['username']) ?>?">Remove</a>
                        <?php else: ?>
                        <span class="text-muted" style="font-size:12px;">(you)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
