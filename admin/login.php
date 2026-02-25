<?php 
session_start();
include("../config/db.php");

$error = "";

if(isset($_POST['login'])){
    $email = $_POST['email'];
    $pass = md5($_POST['password']);

    $res = $conn->query("SELECT * FROM users WHERE email='$email' AND password='$pass'");

    if($res->num_rows > 0){
        $row = $res->fetch_assoc();
        $_SESSION['user'] = $row;

        if($row['role'] == "forwarded_admin"){
            header("location: dashboard_forwarded.php");
        }
        elseif($row['role'] == "security_admin"){
            header("location: security_dashboard.php");
        }
        else{
            header("location: dashboard_super.php");
        }

        exit();
    } else {
        $error = "Invalid Email or Password!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

<style>
body {
    background: linear-gradient(135deg, #667eea, #764ba2);
    min-height: 100vh;
}

.login-card {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(15px);
    border-radius: 20px;
    padding: 40px;
    width: 100%;
    max-width: 420px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.2);
    color: white;
}

.form-control {
    border-radius: 10px;
}

.btn-login {
    border-radius: 10px;
    font-weight: 600;
    padding: 10px;
}

.login-icon {
    font-size: 50px;
}

@media(max-width: 576px){
    .login-card{
        padding: 25px;
    }
}
</style>
</head>

<body>

<?php include('../header.php'); ?>

<div class="d-flex align-items-center justify-content-center" style="height:90vh;">

<div class="login-card text-center">

<div class="mb-4">
<i class="bi bi-shield-lock-fill login-icon"></i>
<h3 class="mt-2 fw-bold">User Login</h3>
<p class="small">Access your dashboard</p>
</div>

<?php if($error != ""){ ?>
<div class="alert alert-danger"><?php echo $error; ?></div>
<?php } ?>

<form method="POST">

<div class="mb-3 text-start">
<label class="form-label">Email</label>
<div class="input-group">
<span class="input-group-text"><i class="bi bi-envelope-fill"></i></span>
<input type="email" name="email" class="form-control" placeholder="Enter your email" required>
</div>
</div>

<div class="mb-4 text-start">
<label class="form-label">Password</label>
<div class="input-group">
<span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
<input type="password" name="password" class="form-control" placeholder="Enter your password" required>
</div>
</div>

<div class="d-grid">
<button type="submit" name="login" class="btn btn-light btn-login">
<i class="bi bi-box-arrow-in-right"></i> Login
</button>
</div>

</form>

</div>
</div>

</body>
</html>