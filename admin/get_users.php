<?php
include("../config/db.php");

if (isset($_POST['staff_type'])) {

    $staff_type = $_POST['staff_type'];

    if ($staff_type == "Permanent") {
        $role = "super_admin";
    } else {
        $role = "forwarded_admin";
    }

    $stmt = $conn->prepare("SELECT id, name, designation FROM users WHERE role=?");
    $stmt->bind_param("s", $role);
    $stmt->execute();
    $result = $stmt->get_result();

    echo '<option value="">Select Officer</option>';

    while ($row = $result->fetch_assoc()) {
        echo "<option value='".$row['id']."'>".
             $row['name']." (".$row['designation'].")</option>";
    }
}
?>