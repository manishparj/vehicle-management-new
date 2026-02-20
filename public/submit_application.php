<?php
include("../config/db.php");

$sql="INSERT INTO vehicle_applications
(applicant_name,vehicle_required,datetime_from,datetime_to,place_of_visit,purpose,
cotraveler1,cotraveler2,cotraveler3,cotraveler4,mobile,email,forwarded_to)
VALUES
('$_POST[applicant_name]','$_POST[vehicle_required]','$_POST[datetime_from]','$_POST[datetime_to]',
'$_POST[place_of_visit]','$_POST[purpose]','$_POST[cotraveler1]','$_POST[cotraveler2]',
'$_POST[cotraveler3]','$_POST[cotraveler4]','$_POST[mobile]','$_POST[email]','$_POST[forwarded_to]')";

$conn->query($sql);
echo "<script>alert('Application Submitted');window.location='index.php';</script>";
?>