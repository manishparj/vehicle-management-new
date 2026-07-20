<?php include ('../config/db.php'); 

$message = "";

if(isset($_POST['create_user'])){

    $name = $_POST['name'];
    $email = $_POST['email'];
    $mob = $_POST['mob'];
    $designation = $_POST['designation'];
    $role = $_POST['role'];
    $password = $_POST['password'];

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (name,email,mob,designation,password,role) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param("ssssss",$name,$email,$mob,$designation,$hashed_password,$role);

    if($stmt->execute()){
        $message = "User created successfully!";
    }else{
        $message = "Error creating user!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Create User</title>
</head>
<body>

<h2>Create User</h2>

<?php echo $message; ?>

<form method="POST">

Name:<br>
<input type="text" name="name" required><br><br>

Email:<br>
<input type="email" name="email" required><br><br>

Mobile:<br>
<input type="text" name="mob"><br><br>

Designation:<br>
<input type="text" name="designation"><br><br>

Password:<br>
<input type="password" name="password" required><br><br>

Role:<br>
<select name="role" required>
<option value="super_admin">Super Admin</option>
<option value="forwarded_admin">Forwarded Admin</option>
<option value="security_admin">Security Admin</option>
</select>

<br><br>

<button type="submit" name="create_user">Create User</button>

</form>

</body>
</html>