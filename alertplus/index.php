<?php
session_start();
require_once 'config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $db = getDB();

    $stmt = $db->prepare("SELECT id, name, email, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();

    $stmt->bind_result($id, $name, $dbEmail, $hashedPassword);

    if ($stmt->fetch()) {

        if (password_verify($password, $hashedPassword)) {

            $_SESSION['user_id'] = $id;
            $_SESSION['user_name'] = $name;

            header("Location: dashboard.php");
            exit;
        }
    }

    $error = "Invalid email or password.";

    $stmt->close();
    $db->close();
}

if (!empty($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ALERT+ | Login</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  body{
    font-family:'Nunito',sans-serif;
    min-height:100vh;
    display:flex;align-items:center;justify-content:center;
    background:#FFF3CC;
  }
  .card{
    background:#FFCBA4;
    border-radius:20px;
    padding:44px 40px;
    width:100%;max-width:420px;
    box-shadow:0 8px 32px rgba(0,0,0,.12);
  }
  .logo{text-align:center;margin-bottom:32px;}
  .logo h1{
    font-size:38px;font-weight:900;
    color:#E8192C;letter-spacing:-1px;
    font-style:italic;
  }
  .logo p{color:#c0392b;font-size:14px;font-weight:600;margin-top:2px;}
  .err{
    background:#ffe0e0;color:#c0392b;
    border:1px solid #f5a5a5;
    border-radius:10px;padding:10px 14px;
    font-size:13px;margin-bottom:16px;
  }
  label{display:block;font-size:13px;font-weight:700;color:#c0392b;margin-bottom:6px;}
  input[type=email],input[type=password]{
    width:100%;padding:11px 14px;
    border:2px solid #f5a5a5;border-radius:10px;
    font-size:14px;font-family:'Nunito',sans-serif;
    background:#fff;color:#333;
    transition:border-color .2s;
    margin-bottom:16px;
  }
  input:focus{outline:none;border-color:#E8192C;}
  .btn{
    width:100%;padding:13px;
    background:#E8192C;color:#fff;
    border:none;border-radius:12px;
    font-size:16px;font-weight:800;font-family:'Nunito',sans-serif;
    cursor:pointer;transition:background .15s;
  }
  .btn:hover{background:#c0392b;}
  .hint{text-align:center;font-size:12px;color:#c0392b;margin-top:14px;opacity:.8;}
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <h1>ALERT +</h1>
    <p>Family Safety Portal</p>
  </div>
  <?php if($error): ?><div class="err"><?=htmlspecialchars($error)?></div><?php endif ?>
  <form method="POST">
    <label>Email Address</label>
    <input type="email" name="email" placeholder="admin@alertplus.com"
           value="<?=htmlspecialchars($_POST['email']??'')?>" required>
    <label>Password</label>
    <input type="password" name="password" placeholder="••••••••" required>
    <button type="submit" class="btn">Sign In</button>
  </form>
  <p class="hint">Default: admin@alertplus.com</p>
</div>
</body>
</html>