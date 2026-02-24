<?php include ('../config/db.php'); ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Application</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="index.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>

<body>
    <?php include ('../header.php'); ?>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-xl-8 col-lg-10 col-md-11 col-sm-12">
                <div class="glass-card p-4 text-dark">
                    <div class="text-center mb-4">
                        <h3 class="fw-bold">
                            <i class="bi bi-car-front-fill"></i> Vehicle Application Form
                        </h3>
                        <p class="small">Please fill all required details carefully</p>
                    </div>
                    <form method="POST" action="submit_application.php" enctype="multipart/form-data">
                        <!-- Applicant -->
                        <h5 class="section-title"><i class="bi bi-person-fill"></i> Applicant Details</h5>

                        <div class="row g-3">

                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text" name="applicant_name" class="form-control"
                                        placeholder="Full Name" required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-truck"></i></span>
                                    <select name="vehicle_required" class="form-select">
                                        <option value="Car">🚗 Car</option>
                                        <option value="Bus">🚌 Bus</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">From Date & Time</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-calendar-event"></i>
                                    </span>
                                    <input type="text"
                                        id="datetime_from"
                                        name="datetime_from"
                                        class="form-control"
                                        placeholder="dd/mm/yyyy 24h format"
                                        required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">To Date & Time</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-calendar-check"></i>
                                    </span>
                                    <input type="text"
                                        id="datetime_to"
                                        name="datetime_to"
                                        class="form-control"
                                        placeholder="dd/mm/yyyy 24h format"
                                        required>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                                    <input type="text" name="place_of_visit" class="form-control"
                                        placeholder="Place of Visit">
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-chat-left-text"></i></span>
                                    <textarea name="purpose" class="form-control" rows="3"
                                        placeholder="Purpose of Visit"></textarea>
                                </div>
                            </div>

                        </div>

                        <!-- Co-Travelers -->
                        <h5 class="section-title"><i class="bi bi-people-fill"></i> Co-Travelers</h5>

                        <div class="row g-3">
                            <?php for ($i = 1; $i <= 4; $i++) { ?>
                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person-plus"></i></span>
                                    <input type="text" name="cotraveler<?php echo $i; ?>" class="form-control"
                                        placeholder="Co-Traveler <?php echo $i; ?>">
                                </div>
                            </div>
                            <?php } ?>
                        </div>

                        <!-- Contact -->
                        <h5 class="section-title"><i class="bi bi-telephone-fill"></i> Contact Details</h5>

                        <div class="row g-3">

                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-phone"></i></span>
                                    <input type="text" name="mobile" class="form-control" placeholder="Mobile Number"
                                        required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" name="email" class="form-control" placeholder="Email Address">
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person-check"></i></span>
                                    <select name="forwarded_to" class="form-select">
                                        <?php
                                            $result = $conn->query("SELECT * FROM users WHERE role='forwarded_admin'");
                                            while ($row = $result->fetch_assoc()) {
                                                echo "<option value='" . $row['id'] . "'>" . $row['name'] . ' (' . $row['designation'] . ')</option>';
                                            }
                                            ?>
                                    </select>
                                </div>
                            </div>

                        </div>

                        <!-- Tour Approval -->
                        <h5 class="section-title"><i class="bi bi-file-earmark-check"></i> Tour Approval</h5>

                        <div class="toggle-card mb-3">

                            <div class="form-check form-switch mb-3">
    <!-- Hidden field ensures 0 is sent if unchecked -->

    <input class="form-check-input"
       type="checkbox"
       id="tourApproved"
       name="tour_approved"
       value="1">

    <label class="form-check-label fw-semibold">
        Tour Approved?
    </label>
</div>

                            <div id="tourUploadSection" style="display:none;">

                                <label class="upload-box w-100" for="tourOrderFile">
                                    <i class="bi bi-cloud-upload"></i>
                                    <p class="mt-2">Click or Drag & Drop PDF here</p>
                                    <input type="file" name="tour_order_pdf" id="tourOrderFile" hidden
                                        accept="application/pdf">
                                </label>

                                <div class="text-center mt-3">
                                    <button type="button" id="previewBtn" class="btn btn-sm btn-light"
                                        style="display:none;">
                                        <i class="bi bi-eye"></i> Preview PDF
                                    </button>
                                </div>

                            </div>
                        </div>

                        <div class="d-grid mt-4">
                            <button class="btn btn-custom text-white">
                                <i class="bi bi-send-fill"></i> Submit Application
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- PDF Preview Modal -->
    <div class="modal fade" id="pdfModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tour Order Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <iframe id="pdfPreview" width="100%" height="600px"></iframe>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    const toggle = document.getElementById("tourApproved");
    const uploadSection = document.getElementById("tourUploadSection");
    const fileInput = document.getElementById("tourOrderFile");
    const previewBtn = document.getElementById("previewBtn");
    const pdfPreview = document.getElementById("pdfPreview");

    toggle.addEventListener("change", function() {
        if (this.checked) {
            uploadSection.style.display = "block";
            fileInput.required = true;
        } else {
            uploadSection.style.display = "none";
            fileInput.required = false;
            fileInput.value = "";
            previewBtn.style.display = "none";
        }
    });

    fileInput.addEventListener("change", function() {
        if (this.files.length > 0) {
            previewBtn.style.display = "inline-block";
        }
    });

    previewBtn.addEventListener("click", function() {
        const file = fileInput.files[0];
        if (file) {
            const fileURL = URL.createObjectURL(file);
            pdfPreview.src = fileURL;
            new bootstrap.Modal(document.getElementById('pdfModal')).show();
        }
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
flatpickr("#datetime_from", {
    enableTime: true,
    altInput: true,
    altFormat: "d/m/Y H:i",   // what user sees
    dateFormat: "Y-m-d H:i:S", // what DB receives
    time_24hr: true,
    minDate: "today"
});

flatpickr("#datetime_to", {
    enableTime: true,
    altInput: true,
    altFormat: "d/m/Y H:i",
    dateFormat: "Y-m-d H:i:S",
    time_24hr: true,
    minDate: "today"
});
</script>

</body>

</html>